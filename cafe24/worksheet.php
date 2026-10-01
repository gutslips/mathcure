<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/student.php';
require_once __DIR__ . '/lib/generators.php';

$pdo = get_db();
$logged_user = require_login($pdo);
$active_student = get_active_student($pdo, $logged_user['id']);

$t_ws = table('worksheets');
$t_exams = table('exams');

// worksheets 테이블에 code 컬럼 추가 (마이그레이션)
try {
    $pdo->exec("ALTER TABLE `{$t_ws}` ADD COLUMN `code` VARCHAR(30) NULL AFTER `id`, ADD INDEX (`code`)");
} catch (Exception $e) {}

// 오늘 날짜 기준 순번 채번 함수 (#YYYYMMDD-01, #YYYYMMDD-02 ...)
function get_next_worksheet_code($pdo, $t_worksheets) {
    $today = date('Ymd');
    try {
        $stmt = $pdo->prepare("SELECT `code` FROM `{$t_worksheets}` WHERE `code` LIKE ? ORDER BY `code` DESC LIMIT 1");
        $stmt->execute(["{$today}-%"]);
        $last_code = $stmt->fetchColumn();
        if ($last_code && preg_match('/^(\d{8})-(\d+)$/', $last_code, $matches)) {
            $next_num = (int)$matches[2] + 1;
        } else {
            $next_num = 1;
        }
        return sprintf("%s-%02d", $today, $next_num);
    } catch (Exception $e) {
        return sprintf("%s-%02d", $today, 1);
    }
}

// 빠른 채점 URL 및 QR코드 생성 헬퍼
function get_answer_qr_info($code) {
    $clean_code = ltrim($code, '#');
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $current_dir = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
    $answer_url = "{$protocol}{$host}{$current_dir}/answer_key.php?code={$clean_code}";
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=" . urlencode($answer_url);
    return ['answer_url' => $answer_url, 'qr_url' => $qr_url];
}

// 1. AJAX 요청 처리 (비동기 새 문제 생성, 문제집 보관, 시험 생성, 삭제, 답안 조회)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // [A] 비동기 새 문제 생성 (미리보기 생성 - DB 저장 및 순번 채번 안 함)
    if ($action === 'generate_ajax') {
        header('Content-Type: application/json; charset=utf-8');
        $target_type = $_POST['type'] ?? 'divide5';
        $diff = (int)($_POST['difficulty'] ?? 2);
        $p_count = (int)($_POST['count'] ?? 20);
        $seed_val = 'ws_' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
        $new_problems = generate_worksheet_problems($target_type, $p_count, $diff, $seed_val);
        $type_label = PROBLEM_TYPE_LABELS[$target_type] ?? $target_type;
        $title = ($active_student['name'] ?? '학생') . "의 " . $type_label . " 맞춤 훈련지";

        // 미리보기 상태이므로 DB 저장 및 고유번호/QR 발급은 하지 않음 ([보관하기] 시 발급)
        echo json_encode([
            'success' => true,
            'id' => null,
            'code' => null,
            'is_saved' => false,
            'type' => $target_type,
            'type_label' => $type_label,
            'difficulty' => $diff,
            'count' => $p_count,
            'seed' => $seed_val,
            'title' => $title,
            'subtitle' => "난이도: Level {$diff} · 문제 수: {$p_count}문항 (미보관 상태)",
            'qr_url' => '',
            'answer_url' => '',
            'problems' => $new_problems
        ]);
        exit;
    }

    // [B] 문제집 보관 (저장)
    if ($action === 'save_worksheet') {
        header('Content-Type: application/json; charset=utf-8');
        $title = trim($_POST['title'] ?? '');
        $target_type = $_POST['problem_type'] ?? 'divide5';
        $diff = (int)($_POST['difficulty'] ?? 2);
        $p_count = (int)($_POST['count'] ?? 20);
        $seed_val = $_POST['seed'] ?? '';
        $problems_json = $_POST['problems'] ?? '[]';
        $save_code = trim($_POST['code'] ?? '');
        if (empty($save_code)) {
            $save_code = get_next_worksheet_code($pdo, $t_ws);
        }

        if (empty($title)) {
            $title = ($active_student['name'] ?? '학생') . "의 " . (PROBLEM_TYPE_LABELS[$target_type] ?? $target_type) . " 맞춤 훈련지 #" . $save_code;
        }

        try {
            $ws_id = 'ws_' . bin2hex(random_bytes(8));
            $stmt = $pdo->prepare("
                INSERT INTO `{$t_ws}` 
                (`id`, `code`, `student_id`, `title`, `subject`, `grade`, `difficulty`, `problem_type`, `count`, `seed`, `problems`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $ws_id,
                $save_code,
                $active_student['id'] ?? null,
                $title,
                '나눗셈',
                $active_student['grade'] ?? 5,
                $diff,
                $target_type,
                $p_count,
                $seed_val,
                $problems_json
            ]);
            $qr_info = get_answer_qr_info($save_code);
            echo json_encode([
                'success' => true,
                'id' => $ws_id,
                'code' => $save_code,
                'title' => $title,
                'qr_url' => $qr_info['qr_url'],
                'answer_url' => $qr_info['answer_url']
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // [C] 온라인 시험 생성 (제한시간 직접입력 + 유효기간 만료시간 지원)
    if ($action === 'create_exam') {
        header('Content-Type: application/json; charset=utf-8');
        $title = trim($_POST['title'] ?? '초5 나눗셈 온라인 시험');
        $worksheet_id = $_POST['worksheet_id'] ?? null;
        $student_name = trim($_POST['student_name'] ?? '');
        $time_limit_sec = (int)($_POST['time_limit_sec'] ?? 600);
        $expire_hours = (int)($_POST['expire_hours'] ?? 3);
        $show_result = isset($_POST['show_result']) && $_POST['show_result'] == '0' ? 0 : 1;
        $problems_json = $_POST['problems'] ?? '[]';

        $expires_at = $expire_hours > 0 ? date('Y-m-d H:i:s', time() + $expire_hours * 3600) : null;
        $code = (string)mt_rand(100000, 999999);
        $exam_id = 'ex_' . bin2hex(random_bytes(8));

        try {
            $stmt = $pdo->prepare("
                INSERT INTO `{$t_exams}`
                (`id`, `code`, `title`, `worksheet_id`, `student_name`, `time_limit_sec`, `status`, `problems`, `show_result`, `total_count`, `expires_at`)
                VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?)
            ");
            $decoded = json_decode($problems_json, true) ?: [];
            $stmt->execute([
                $exam_id,
                $code,
                $title,
                $worksheet_id ?: null,
                $student_name ?: null,
                $time_limit_sec,
                $problems_json,
                $show_result,
                count($decoded),
                $expires_at
            ]);

            // 현재 프로토콜 및 호스트 기반 URL 생성
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $current_dir = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
            $hub_url = "{$protocol}{$host}{$current_dir}/exam.php";
            $exam_url = "{$protocol}{$host}{$current_dir}/exam.php?code={$code}";
            $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=" . urlencode($exam_url);

            echo json_encode([
                'success' => true,
                'exam_id' => $exam_id,
                'code' => $code,
                'hub_url' => $hub_url,
                'exam_url' => $exam_url,
                'qr_url' => $qr_url,
                'title' => $title,
                'time_limit_sec' => $time_limit_sec,
                'expire_hours' => $expire_hours,
                'total_count' => count($decoded)
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // [D] 보관된 문제집 단건 삭제
    if ($action === 'delete_worksheet') {
        header('Content-Type: application/json; charset=utf-8');
        $del_id = $_POST['id'] ?? '';
        try {
            $stmt = $pdo->prepare("DELETE FROM `{$t_ws}` WHERE `id` = ?");
            $stmt->execute([$del_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // [D-1] 보관함 정리 (전체 문제집 비우기)
    if ($action === 'clear_all_worksheets') {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $pdo->exec("DELETE FROM `{$t_ws}`");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // [D-2] 선택한 문제집 일괄 삭제
    if ($action === 'delete_selected_worksheets') {
        header('Content-Type: application/json; charset=utf-8');
        $ids = json_decode($_POST['ids'] ?? '[]', true);
        if (!empty($ids) && is_array($ids)) {
            $inClause = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM `{$t_ws}` WHERE `id` IN ($inClause)");
            $stmt->execute($ids);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // [E] 빠른 답안 조회 (훈련번호 #YYYYMMDD-XX 또는 순번 검색)
    if ($action === 'lookup_answers') {
        header('Content-Type: application/json; charset=utf-8');
        $search_code = trim($_POST['code'] ?? '');
        $clean_code = ltrim($search_code, '#');

        $candidates = [$clean_code, "#{$clean_code}"];
        if (preg_match('/^\d{1,2}$/', $clean_code)) {
            $today = date('Ymd');
            $pad = str_pad($clean_code, 2, '0', STR_PAD_LEFT);
            $candidates[] = "{$today}-{$pad}";
            $candidates[] = "#{$today}-{$pad}";
        }

        try {
            $in_p = implode(',', array_fill(0, count($candidates), '?'));
            $stmt = $pdo->prepare("SELECT * FROM `{$t_ws}` WHERE `code` IN ({$in_p}) OR `id` = ? LIMIT 1");
            $params = array_merge($candidates, [$clean_code]);
            $stmt->execute($params);
            $found = $stmt->fetch();

            if ($found) {
                $ans_problems = json_decode($found['problems'] ?? '[]', true) ?: [];
                $qr_info = get_answer_qr_info($found['code'] ?? $clean_code);
                echo json_encode([
                    'success' => true,
                    'worksheet' => [
                        'id' => $found['id'],
                        'code' => $found['code'] ?? $clean_code,
                        'title' => $found['title'],
                        'count' => $found['count'],
                        'difficulty' => $found['difficulty'],
                        'created_at' => $found['created_at'],
                        'answer_url' => $qr_info['answer_url'],
                        'problems' => $ans_problems
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => "훈련번호(#{$clean_code})에 해당하는 문제지를 찾을 수 없습니다."]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// 2. 화면 로드 처리 (HTML 렌더링 시에만 header.php 호출)
require_once __DIR__ . '/includes/header.php';

$id = $_GET['id'] ?? null;
$type = $_GET['type'] ?? 'divide5';
$difficulty = (int)($_GET['difficulty'] ?? 2);
$count = (int)($_GET['count'] ?? 20);
$seed = $_GET['seed'] ?? ('ws_' . substr(md5(uniqid(mt_rand(), true)), 0, 8));
$tab = $_GET['tab'] ?? 'worksheet';
$open_archive = (isset($_GET['archive']) && $_GET['archive'] == '1');

$is_saved_view = false;
$saved_worksheet = null;
$current_code = null;
$current_ws_id = null;

if ($id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM `{$t_ws}` WHERE `id` = ?");
        $stmt->execute([$id]);
        $saved_worksheet = $stmt->fetch();
        if ($saved_worksheet) {
            $type = $saved_worksheet['problem_type'];
            $difficulty = (int)$saved_worksheet['difficulty'];
            $count = (int)$saved_worksheet['count'];
            $seed = $saved_worksheet['seed'];
            $is_saved_view = true;
            $current_ws_id = $saved_worksheet['id'];
            $current_code = $saved_worksheet['code'] ?? null;

            if (empty($current_code)) {
                $current_code = get_next_worksheet_code($pdo, $t_ws);
                try {
                    $up = $pdo->prepare("UPDATE `{$t_ws}` SET `code` = ? WHERE `id` = ?");
                    $up->execute([$current_code, $current_ws_id]);
                } catch (Exception $e) {}
            }

            if (!empty($saved_worksheet['problems'])) {
                $decoded = json_decode($saved_worksheet['problems'], true);
                if (is_array($decoded) && count($decoded) > 0) {
                    $problems = $decoded;
                }
            }

            if (empty($problems)) {
                $problems = generate_worksheet_problems($type, $count, $difficulty, $seed);
                try {
                    $up = $pdo->prepare("UPDATE `{$t_ws}` SET `problems` = ? WHERE `id` = ?");
                    $up->execute([json_encode($problems, JSON_UNESCAPED_UNICODE), $id]);
                } catch (Exception $e) {}
            }
        }
    } catch (Exception $e) {}
}

if (!isset($problems) || empty($problems)) {
    $problems = generate_worksheet_problems($type, $count, $difficulty, $seed);
    $current_code = null;
    $current_ws_id = null;
    $is_saved_view = false;
    $type_label = PROBLEM_TYPE_LABELS[$type] ?? $type;
    $initial_title = ($active_student['name'] ?? '학생') . "의 " . $type_label . " 맞춤 훈련지";
}

$qr_info = !empty($current_code) ? get_answer_qr_info($current_code) : ['qr_url' => '', 'answer_url' => ''];
$qr_url = $qr_info['qr_url'];
$answer_url = $qr_info['answer_url'];

// 보관함 목록 조회
$saved_list = [];
try {
    $stmt = $pdo->query("SELECT `id`, `code`, `title`, `problem_type`, `difficulty`, `count`, `created_at` FROM `{$t_ws}` ORDER BY `created_at` DESC");
    $saved_list = $stmt->fetchAll();
} catch (Exception $e) {}
?>

<!-- 상단 헤더 및 액션 바 (인쇄 시 숨김) -->
<div class="no-print bg-white border border-slate-200 rounded-2xl p-6 mb-8 shadow-xs space-y-4">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-slate-900"></span>
                <h1 class="text-xl font-bold text-slate-900">맞춤 훈련지 생성 및 보관함</h1>
                <span id="top-badge-code" class="px-2 py-0.5 <?php echo $current_code ? 'bg-emerald-100 text-emerald-800 text-xs font-mono font-bold' : 'bg-slate-100 text-slate-500 text-xs font-medium'; ?> rounded-lg">
                    <?php echo $current_code ? ('#' . htmlspecialchars($current_code)) : '미보관 (임시)'; ?>
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">
                문제 생성 후 [★ 보관하기]를 누르면 공식 훈련번호와 스마트폰 빠른 채점 QR코드가 발급됩니다.
            </p>
        </div>

        <!-- 핵심 인쇄 & 액션 버튼군 -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- [A] 보관된 문제집 전용 인쇄 버튼군 (미보관 생성 상태에서는 숨김) -->
            <div id="saved-print-actions" class="<?php echo $current_code ? 'flex' : 'hidden'; ?> items-center gap-2 flex-wrap">
                <!-- [1] 문제지만 1장 인쇄 (메인 다크 버튼) -->
                <button type="button" onclick="printWorksheetOnly()" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 transition shadow-sm active:scale-95">
                    <svg class="w-4 h-4 fill-white" viewBox="0 0 24 24"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                    📄 문제지만 인쇄 (A4 1장)
                </button>

                <!-- [2] 정답지만 1장 인쇄 -->
                <button type="button" onclick="printAnswersOnly()" class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold transition shadow-2xs active:scale-95">
                    ✅ 정답지만 인쇄 (1장)
                </button>

                <!-- [3] 전체 2장 인쇄 -->
                <button type="button" onclick="printFullWorksheet()" class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition active:scale-95">
                    🖨️ 전체 인쇄 (2장)
                </button>
            </div>

            <!-- [B] 이 문제집 보관하기 (미보관 시 초록색 강조, 보관 후 회색 뱃지) -->
            <button type="button" id="btn-save-action" onclick="saveCurrentWorksheet()" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl <?php echo $current_code ? 'bg-slate-100 text-slate-700 border border-slate-300' : 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm'; ?> text-xs font-bold transition active:scale-95">
                <span id="btn-save-label"><?php echo $current_code ? ('✓ 보관됨 (#' . htmlspecialchars($current_code) . ')') : '★ 이 문제집 보관하기 (번호&QR발급)'; ?></span>
            </button>

            <!-- [C] 온라인 시험 모달 -->
            <button type="button" onclick="openExamModal()" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition shadow-sm active:scale-95">
                📱 온라인 시험 링크
            </button>
        </div>
    </div>

    <!-- 보조 옵션 바: QR코드 On/Off 토글 + 빠른 답안 조회 검색창 -->
    <div class="pt-3 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 cursor-pointer font-medium text-slate-700 select-none">
                <input type="checkbox" id="toggle-print-qr" checked onchange="handleQrToggle(this.checked)" class="w-4 h-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                <span>채점용 미니 QR코드 인쇄 포함 <span class="text-slate-400 font-normal">(학생 커닝 방지 보안 잠금 적용)</span></span>
            </label>
        </div>

        <div class="flex items-center gap-1.5">
            <span class="text-slate-500 font-semibold whitespace-nowrap">🔍 빠른 답안 조회:</span>
            <div class="flex items-center gap-1">
                <input type="text" id="quick-search-code" placeholder="예: #<?php echo htmlspecialchars($current_code); ?> 또는 순번" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs font-mono w-44 focus:outline-none focus:ring-1 focus:ring-slate-900">
                <button type="button" onclick="quickLookupAnswer()" class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-xs font-bold transition">
                    조회
                </button>
            </div>
        </div>
    </div>

    <!-- 보관된 문제집 선택 알림 배너 -->
    <div id="saved-worksheet-banner" class="<?php echo ($is_saved_view && $saved_worksheet) ? '' : 'hidden'; ?> mt-4 p-3.5 bg-slate-900 text-white rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-2">
            <span class="px-2 py-0.5 bg-emerald-500 text-slate-950 font-bold rounded">보관된 문제집 선택됨</span>
            <strong id="banner-saved-title" class="text-sm font-semibold"><?php echo htmlspecialchars($saved_worksheet['title'] ?? ''); ?></strong>
            <span id="banner-saved-count" class="text-slate-400">(<?php echo count($problems); ?>문제 그대로 인쇄/풀이 가능)</span>
        </div>
        <div class="flex items-center gap-2">
            <a id="banner-practice-link" href="practice.php?worksheet_id=<?php echo urlencode($saved_worksheet['id'] ?? ''); ?>" class="px-2.5 py-1 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold rounded-lg transition">
                이 문제로 온라인 풀기
            </a>
            <button type="button" onclick="resetToGenerator()" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition">
                새 생성기로 전환
            </button>
        </div>
    </div>

    <!-- 옵션 폼 (페이지 리로드 없이 비동기 갱신) -->
    <div class="mt-6 pt-6 border-t border-slate-100 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">훈련 유형</label>
                <select id="gen-opt-type" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-xs font-medium bg-white">
                    <?php foreach (PROBLEM_TYPE_LABELS as $k => $label): ?>
                        <option value="<?php echo $k; ?>" <?php echo $type === $k ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-2 sm:contents">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">난이도</label>
                    <select id="gen-opt-diff" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-xs font-medium bg-white">
                        <option value="1" <?php echo $difficulty === 1 ? 'selected' : ''; ?>>Level 1 (기초/원리)</option>
                        <option value="2" <?php echo $difficulty === 2 ? 'selected' : ''; ?>>Level 2 (표준 숙달)</option>
                        <option value="3" <?php echo $difficulty === 3 ? 'selected' : ''; ?>>Level 3 (큰 수 확장)</option>
                        <option value="4" <?php echo $difficulty === 4 ? 'selected' : ''; ?>>Level 4 (응용 혼합)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">문항 수</label>
                    <select id="gen-opt-count" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-xs font-medium bg-white">
                        <option value="20" <?php echo $count === 20 ? 'selected' : ''; ?>>20문제 (A4 1장 권장)</option>
                        <option value="10" <?php echo $count === 10 ? 'selected' : ''; ?>>10문제 (간이 시험)</option>
                        <option value="30" <?php echo $count === 30 ? 'selected' : ''; ?>>30문제 (집중 훈련)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 하단 액션 버튼: 모바일에서 길고 시원한 와이드 버튼으로 배치 -->
        <div class="pt-3 border-t border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
            <button type="button" id="btn-generate-ajax" onclick="generateNewProblemsAsync()" class="flex-1 py-3.5 px-4 bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm rounded-xl transition active:scale-98 flex items-center justify-center gap-2 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>새 문제 세트 생성하기 (↺ 새로고침)</span>
            </button>
            <button type="button" id="btn-archive-toggle" onclick="toggleArchiveView()" class="py-3.5 px-4 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 text-emerald-800 font-bold text-xs sm:text-sm rounded-xl whitespace-nowrap transition flex items-center justify-center gap-1.5 shadow-2xs">
                📁 보관함 목록 (<?php echo count($saved_list); ?>)
            </button>
        </div>
    </div>
</div>

<!-- 저장된 보관함 뷰 (기본 숨김 또는 토글) -->
<div id="archive-section" class="no-print <?php echo $open_archive ? '' : 'hidden'; ?> bg-white border border-slate-200 rounded-2xl p-6 mb-8 shadow-xs space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-100 gap-3">
        <div>
            <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                <span>보관된 맞춤 문제집 목록</span>
                <span id="archive-count-badge" class="px-2.5 py-0.5 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-full">
                    총 <?php echo count($saved_list); ?>개
                </span>
            </h2>
            <p class="text-xs text-slate-500">저장된 문제집을 다시 인쇄하거나 바로 학생 시험 링크를 생성합니다.</p>
        </div>

        <div class="flex items-center gap-2">
            <?php if (!empty($saved_list)): ?>
                <button type="button" onclick="deleteSelectedWorksheets()" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
                    선택 삭제
                </button>
                <button type="button" onclick="clearAllWorksheets()" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-700 text-xs font-bold rounded-xl transition flex items-center gap-1">
                    🧹 보관함 전체 정리 (모두 비우기)
                </button>
            <?php endif; ?>
            <button onclick="toggleArchiveView()" class="text-xs text-slate-500 hover:text-slate-800 font-semibold px-2 py-1">
                닫기 ✕
            </button>
        </div>
    </div>

    <?php if (empty($saved_list)): ?>
        <div class="py-12 text-center text-slate-400 space-y-2">
            <p class="text-sm">아직 보관된 문제집이 없습니다.</p>
            <p class="text-xs text-slate-400">상단의 [★ 이 문제집 보관하기]를 눌러 원하는 문제를 저장해 두세요.</p>
        </div>
    <?php else: ?>
        <div class="flex items-center gap-2 py-1 text-xs text-slate-500">
            <label class="flex items-center gap-1.5 cursor-pointer">
                <input type="checkbox" id="archive-select-all" onchange="toggleSelectAllArchive(this.checked)" class="w-4 h-4 rounded border-slate-300">
                <span>전체 선택</span>
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($saved_list as $ws): ?>
                <div class="p-5 rounded-2xl border border-slate-200 hover:border-slate-300 bg-white transition flex flex-col justify-between">
                    <div class="space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" class="archive-item-cb w-4 h-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900" value="<?php echo $ws['id']; ?>">
                                <span class="font-mono text-xs font-bold px-2 py-0.5 bg-slate-900 text-white rounded">
                                    #<?php echo htmlspecialchars($ws['code'] ?: '미지정'); ?>
                                </span>
                                <h3 class="font-bold text-sm sm:text-base text-slate-900"><?php echo htmlspecialchars($ws['title']); ?></h3>
                            </div>
                            <button onclick="deleteSavedWorksheet('<?php echo $ws['id']; ?>', '<?php echo htmlspecialchars($ws['title']); ?>')" class="text-slate-400 hover:text-rose-500 p-1 text-xs whitespace-nowrap">
                                삭제
                            </button>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs pl-6">
                            <span class="px-2 py-0.5 bg-slate-100 text-slate-700 font-semibold rounded-md">
                                <?php echo PROBLEM_TYPE_LABELS[$ws['problem_type']] ?? $ws['problem_type']; ?>
                            </span>
                            <span class="px-2 py-0.5 bg-slate-100 text-slate-600 font-mono rounded-md">
                                Level <?php echo $ws['difficulty']; ?>
                            </span>
                            <span class="px-2 py-0.5 bg-slate-100 text-slate-600 font-mono rounded-md">
                                <?php echo $ws['count']; ?>문제
                            </span>
                            <span class="text-slate-400 text-[11px] ml-auto">
                                <?php echo date('Y.m.d', strtotime($ws['created_at'])); ?>
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap gap-1.5 pl-6">
                        <a href="worksheet.php?id=<?php echo urlencode($ws['id']); ?>" class="px-2.5 py-1.5 bg-slate-900 text-white rounded-lg text-xs font-semibold hover:bg-slate-800 transition">
                            문제지 열기/인쇄
                        </a>
                        <a href="practice.php?worksheet_id=<?php echo urlencode($ws['id']); ?>" class="px-2.5 py-1.5 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-lg text-xs font-semibold hover:bg-emerald-100 transition">
                            온라인 풀기
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ======================================================== -->
<!-- A4 인쇄 규격 영역 (1페이지: 문제지 / 2페이지: 정답지) -->
<!-- ======================================================== -->

<!-- [1페이지] 문제지 -->
<div id="worksheet-page-1" class="a4-sheet bg-white p-4 sm:p-12 mb-8 border border-slate-200 sm:rounded-2xl shadow-sm text-slate-900 transition-opacity duration-300">
    <div class="sheet-header border-b-2 border-slate-900 pb-3 mb-6 flex flex-col sm:flex-row justify-between sm:items-end gap-3">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-widest block">
                    MATH CURE · 초5 나눗셈 자동화 프로젝트
                </span>
                <span id="display-worksheet-code-badge" class="px-2 py-0.5 rounded <?php echo $current_code ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-500'; ?> font-mono text-[11px] font-bold tracking-wider">
                    <?php echo $current_code ? ('#' . htmlspecialchars($current_code)) : '미보관 (임시)'; ?>
                </span>
            </div>
            <h2 id="display-worksheet-title" class="text-xl sm:text-2xl font-black text-slate-900 mt-0.5">
                <?php echo htmlspecialchars($is_saved_view && $saved_worksheet ? $saved_worksheet['title'] : ((PROBLEM_TYPE_LABELS[$type] ?? $type) . " 맞춤 훈련지")); ?>
            </h2>
            <span id="display-worksheet-subtitle" class="text-xs text-slate-600 font-medium">
                난이도: Level <?php echo $difficulty; ?> · 문제 수: <?php echo count($problems); ?>문항
            </span>
        </div>

        <div class="sheet-student-info flex flex-wrap sm:flex-col sm:text-right gap-x-4 gap-y-1 text-xs">
            <div>
                <span class="text-slate-500">훈련 번호:</span>
                <strong id="display-worksheet-code-text" class="font-mono text-slate-900 font-bold ml-1">
                    <?php echo $current_code ? ('#' . htmlspecialchars($current_code)) : '미보관 (보관 시 번호 발급)'; ?>
                </strong>
            </div>
            <div>
                <span class="text-slate-500">학생 이름:</span>
                <span class="font-bold underline underline-offset-4 inline-block min-w-[70px] text-center">
                    <?php echo htmlspecialchars($active_student['name'] ?? '홍길동'); ?>
                </span>
            </div>
            <div>
                <span class="text-slate-500">날짜:</span>
                <span class="font-bold underline underline-offset-4 inline-block min-w-[70px] text-center">
                    <?php echo date('Y. m. d'); ?>
                </span>
            </div>
            <div>
                <span class="text-slate-500">걸린 시간:</span>
                <span class="font-bold underline underline-offset-4 inline-block min-w-[70px] text-center">
                    &nbsp;&nbsp;&nbsp;&nbsp;분 &nbsp;&nbsp;&nbsp;&nbsp;초
                </span>
            </div>
        </div>
    </div>

    <!-- 문제 리스트 (2열 그리드) -->
    <div id="problems-grid-container" class="grid grid-cols-1 sm:grid-cols-2 gap-x-12 gap-y-7 my-6 text-base">
        <?php foreach ($problems as $idx => $p): ?>
            <div class="print-problem-row flex items-baseline justify-between border-b border-slate-200 pb-2">
                <div class="flex items-baseline gap-2 font-mono">
                    <span class="prob-num font-bold text-slate-400 w-7 text-right text-sm">
                        <?php echo $idx + 1; ?>.
                    </span>
                    <span class="prob-eq text-lg font-bold text-slate-900 tracking-tight">
                        <?php echo htmlspecialchars(rtrim(trim($p['question']), '=')); ?> <span class="font-normal text-slate-400">=</span>
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <span class="inline-block w-24 border-b-2 border-slate-400"></span>
                    <span class="text-[10px] text-slate-400 font-sans whitespace-nowrap">
                        (___초)
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="sheet-footer mt-12 pt-4 border-t border-slate-300 flex justify-between items-center text-[11px] text-slate-400">
        <div class="flex items-center gap-2">
            <span>초5 나눗셈 트레이너 · MathCure</span>
            <strong class="font-mono text-slate-700" id="display-footer-code"><?php echo $current_code ? ('#' . htmlspecialchars($current_code)) : ''; ?></strong>
        </div>

        <!-- 선생님 빠른 채점 미니 QR (보관 시에만 활성화) -->
        <div id="print-qr-container" class="<?php echo $current_code ? 'flex' : 'hidden'; ?> items-center gap-1.5 bg-slate-50 px-2 py-0.5 rounded-lg border border-slate-200">
            <img id="print-qr-img" src="<?php echo htmlspecialchars($qr_url); ?>" alt="채점 QR" class="w-8 h-8 border border-slate-300 rounded bg-white p-0.5">
            <div class="text-left text-[8.5px] leading-tight text-slate-600 font-sans">
                <span class="font-bold text-slate-900 block flex items-center gap-0.5">
                    <svg class="w-2.5 h-2.5 inline text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                    선생님 빠른 채점
                </span>
                <span class="text-slate-400">스마트폰 스캔 (보안 잠금)</span>
            </div>
        </div>

        <span id="display-page-num-1">Page 1 / 1</span>
    </div>
</div>

<!-- 인쇄용 페이지 구분자 (화면에서는 구분선, 인쇄 시 페이지 나눔) -->
<div class="page-break"></div>

<!-- [2페이지] 정답지 -->
<div id="worksheet-page-2" class="a4-sheet bg-white p-4 sm:p-12 mb-8 border border-slate-200 sm:rounded-2xl shadow-sm text-slate-900 transition-opacity duration-300">
    <div class="sheet-header border-b-2 border-slate-900 pb-3 mb-6 flex flex-col sm:flex-row justify-between sm:items-end gap-3">
        <div>
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-widest block">
                ANSWER KEY · 정답 및 빠른 채점표
            </span>
            <h2 id="display-answers-title" class="text-xl sm:text-2xl font-black text-slate-900 mt-0.5">
                <?php echo htmlspecialchars($is_saved_view && $saved_worksheet ? $saved_worksheet['title'] : ((PROBLEM_TYPE_LABELS[$type] ?? $type) . " 맞춤 훈련지")); ?>
                <span id="display-answers-title-code-wrap" class="<?php echo $current_code ? '' : 'hidden'; ?>"> - [ 정답지 #<span id="display-answers-title-code"><?php echo htmlspecialchars($current_code ?? ''); ?></span> ]</span>
            </h2>
            <span id="display-answers-subtitle" class="text-xs text-slate-600 font-medium">
                초5 나눗셈 자동화 빠른 채점 기준표
            </span>
        </div>

        <div class="text-left sm:text-right text-xs">
            <span class="px-2 py-1 bg-slate-100 border border-slate-300 rounded font-bold">
                교사용 / 채점용
            </span>
        </div>
    </div>

    <div id="answers-grid-container" class="grid grid-cols-2 sm:grid-cols-4 gap-4 my-6">
        <?php foreach ($problems as $idx => $p): ?>
            <div class="print-answer-item p-3 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between">
                <span class="font-bold text-slate-500 text-sm font-mono">
                    <?php echo $idx + 1; ?>번
                </span>
                <span class="font-bold text-slate-900 text-lg font-mono">
                    <?php echo $p['answer']; ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="sheet-tip-box mt-12 p-4 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-600 leading-relaxed">
        <strong class="text-slate-800 block mb-1">지도 팁:</strong>
        채점 시 정답 여부뿐만 아니라 15초 이상 지체된 문제에 대해서는 나눗셈 원리(예: ÷5는 2배 후 10 나누기)를 다시 점검해 주세요.
    </div>

    <div class="sheet-footer mt-8 pt-4 border-t border-slate-300 flex justify-between items-center text-[11px] text-slate-400">
        <div class="flex items-center gap-2">
            <span>초5 나눗셈 트레이너 · MathCure 정답지</span>
            <strong class="font-mono text-slate-700" id="display-answers-footer-code">#<?php echo htmlspecialchars($current_code); ?></strong>
        </div>
        <span>Page 2 / 2</span>
    </div>
</div>

<!-- 빠른 답안 조회 팝업 모달 (PC/태블릿에서 상단 검색 시 즉시 표시) -->
<div id="quick-ans-modal" class="no-print hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 relative space-y-4 my-8">
        <button onclick="closeQuickAnsModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
        <div class="flex items-center gap-2">
            <span class="w-7 h-7 rounded-lg bg-emerald-500 text-slate-950 font-black flex items-center justify-center text-xs">✓</span>
            <div>
                <h3 id="modal-ans-title" class="text-base font-bold text-slate-900">빠른 정답 조회</h3>
                <span id="modal-ans-code" class="text-xs font-mono font-bold text-emerald-600"></span>
            </div>
        </div>

        <div id="modal-ans-grid" class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 max-h-96 overflow-y-auto p-1">
            <!-- 답안 카드 동적 삽입 -->
        </div>

        <div class="pt-3 border-t border-slate-100 flex gap-2">
            <a id="modal-ans-mobile-link" href="#" target="_blank" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 text-center font-bold text-xs rounded-xl transition flex items-center justify-center gap-1">
                📱 모바일 전용 채점기 열기 ➔
            </a>
            <button type="button" onclick="closeQuickAnsModal()" class="flex-1 py-2.5 bg-slate-900 text-white font-bold text-xs rounded-xl">
                닫기
            </button>
        </div>
    </div>
</div>

<!-- 온라인 시험 링크 발급 모달 -->
<div id="exam-modal" class="no-print hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 relative space-y-5 my-8">
        <button onclick="closeExamModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
        
        <!-- 시험 생성 폼 -->
        <div id="exam-form-view" class="space-y-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-slate-900 text-white flex items-center justify-center font-bold text-sm">📱</div>
                <h3 class="text-lg font-bold text-slate-900">온라인 시험 링크 & QR 생성</h3>
            </div>
            <p class="text-xs text-slate-500">선생님 관리 화면 노출 없이 태블릿/모바일로 독립 응시합니다.</p>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">시험 제목</label>
                    <input type="text" id="m-exam-title" value="<?php echo htmlspecialchars($active_student['name'] ?? '홍길동'); ?>의 나눗셈 맞춤 시험" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm font-semibold">
                </div>

                <!-- 풀이 시간 설정 (직접 입력 지원) -->
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                    <label class="block font-bold text-slate-800">⏱️ 시험 풀이 시간 제한</label>
                    <select id="m-exam-time" onchange="toggleCustomTime(this.value)" class="w-full px-3 py-2 border border-slate-300 rounded-xl bg-white text-xs font-semibold">
                        <option value="300">5분</option>
                        <option value="600" selected>10분 (표준 권장)</option>
                        <option value="900">15분</option>
                        <option value="1200">20분</option>
                        <option value="custom">직접 입력 (분)</option>
                        <option value="0">무제한</option>
                    </select>
                    <div id="m-custom-time-box" class="hidden flex items-center gap-2 pt-1">
                        <input type="number" id="m-custom-time-input" min="1" max="180" value="10" placeholder="분" class="w-24 px-3 py-1.5 border border-slate-300 rounded-xl bg-white text-xs font-bold text-center">
                        <span class="text-slate-600 font-semibold">분 동안 시험 응시</span>
                    </div>
                </div>

                <!-- 링크 유효기간 설정 (자동 만료) -->
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                    <label class="block font-bold text-slate-800">⏰ 시험 링크 유효기간 (자동 만료)</label>
                    <select id="m-exam-expire" onchange="toggleCustomExpire(this.value)" class="w-full px-3 py-2 border border-slate-300 rounded-xl bg-white text-xs font-semibold">
                        <option value="1">1시간 후 만료 (즉시 응시용)</option>
                        <option value="3" selected>3시간 후 만료 (수업 권장)</option>
                        <option value="5">5시간 후 만료</option>
                        <option value="8">8시간 후 만료</option>
                        <option value="24">24시간 (1일) 후 만료</option>
                        <option value="custom">직접 지정 (시간)</option>
                        <option value="0">무제한 (만료 없음)</option>
                    </select>
                    <div id="m-custom-expire-box" class="hidden flex items-center gap-2 pt-1">
                        <input type="number" id="m-custom-expire-input" min="1" max="720" value="3" placeholder="시간" class="w-24 px-3 py-1.5 border border-slate-300 rounded-xl bg-white text-xs font-bold text-center">
                        <span class="text-slate-600 font-semibold">시간 후 링크 자동 비활성화</span>
                    </div>
                    <div class="text-[11px] text-slate-400 leading-tight">
                        * 만료 후 링크 접속 시 시험이 차단되며, 원본 데이터는 보존됩니다.
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">응시 학생 이름</label>
                    <input type="text" id="m-exam-student" value="<?php echo htmlspecialchars($active_student['name'] ?? ''); ?>" placeholder="시작 시 직접 입력" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-semibold">
                </div>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="button" onclick="closeExamModal()" class="flex-1 py-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50">취소</button>
                <button type="button" onclick="submitCreateExam()" class="flex-1 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold shadow-sm">시험 생성하기</button>
            </div>
        </div>

        <!-- 시험 생성 완료 뷰 (QR & 코드 & 허브 주소) -->
        <div id="exam-result-view" class="hidden space-y-4 text-center">
            <span class="inline-block px-2.5 py-1 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-full">✓ 시험 발급 완료</span>
            <h3 id="res-exam-title" class="text-base font-bold text-slate-900"></h3>
            
            <div class="bg-slate-900 text-white p-4 rounded-2xl space-y-3">
                <div>
                    <div class="text-[11px] text-slate-400 uppercase tracking-wider mb-1">태블릿 / PC 간편 입장 코드</div>
                    <div id="res-exam-code" class="text-3xl font-mono font-black tracking-widest text-emerald-400">------</div>
                </div>
                
                <div class="pt-2 border-t border-slate-800 flex items-center justify-between text-xs text-slate-300">
                    <span class="text-[11px]">
                        코드 입력 주소: <strong id="res-hub-url-text" class="font-mono text-white underline underline-offset-2"></strong>
                    </span>
                    <button type="button" onclick="copyHubUrl()" class="text-[11px] text-emerald-400 hover:text-emerald-300 font-semibold">
                        주소 복사
                    </button>
                </div>
            </div>

            <div class="py-1">
                <img id="res-qr-img" src="" alt="QR코드" class="w-40 h-40 mx-auto border-2 border-slate-200 rounded-2xl p-2 bg-white shadow-sm">
                <p class="text-[11px] text-slate-500 mt-1.5 font-medium">태블릿 카메라로 비추면 바로 시험 시작</p>
            </div>

            <div class="space-y-2">
                <input type="text" id="res-exam-url" readonly class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono text-center select-all">
                <div class="flex gap-2">
                    <button type="button" onclick="copyExamLink()" class="flex-1 py-2 bg-slate-900 text-white font-bold text-xs rounded-xl hover:bg-slate-800 transition">
                        전용 링크 복사
                    </button>
                    <button type="button" onclick="copyShareText()" class="flex-1 py-2 bg-emerald-600 text-white font-bold text-xs rounded-xl hover:bg-emerald-500 transition">
                        카톡 안내문 전체 복사
                    </button>
                </div>
            </div>

            <div class="pt-2 flex gap-2">
                <a id="res-hub-link" href="#" target="_blank" class="flex-1 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold rounded-xl flex items-center justify-center gap-1">
                    코드 입력창 열기 ➔
                </a>
                <button type="button" onclick="closeExamModal()" class="flex-1 py-2 bg-slate-900 text-white text-xs font-bold rounded-xl">
                    닫기
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentProblems = <?php echo json_encode($problems); ?>;
let currentSeed = <?php echo json_encode($seed); ?>;
let currentType = <?php echo json_encode($type); ?>;
let currentDifficulty = <?php echo (int)$difficulty; ?>;
let currentCount = <?php echo (int)count($problems); ?>;
let currentCode = <?php echo json_encode($current_code); ?>;
let currentWsId = <?php echo json_encode($current_ws_id); ?>;
let currentQrUrl = <?php echo json_encode($qr_url); ?>;
let createdExamData = null;

// [1] 문제지만 1장 인쇄 (보관된 문제지에서 호출)
function printWorksheetOnly() {
    doPrintWorksheetOnly();
}

function doPrintWorksheetOnly() {
    document.getElementById('display-page-num-1').innerText = "Page 1 / 1";
    document.body.classList.remove('print-only-answers');
    document.body.classList.add('print-only-problems');
    const qrChecked = document.getElementById('toggle-print-qr')?.checked ?? true;
    if (!qrChecked || !currentCode) document.body.classList.add('hide-print-qr');
    else document.body.classList.remove('hide-print-qr');

    window.print();

    setTimeout(() => {
        document.body.classList.remove('print-only-problems', 'hide-print-qr');
    }, 1000);
}

// [2] 정답지만 1장 인쇄 (보관된 문제지에서 호출)
function printAnswersOnly() {
    doPrintAnswersOnly();
}

function doPrintAnswersOnly() {
    document.body.classList.remove('print-only-problems', 'hide-print-qr');
    document.body.classList.add('print-only-answers');

    window.print();

    setTimeout(() => {
        document.body.classList.remove('print-only-answers');
    }, 1000);
}

// [3] 전체 2장 인쇄 (보관된 문제지에서 호출)
function printFullWorksheet() {
    doPrintFullWorksheet();
}

function doPrintFullWorksheet() {
    document.getElementById('display-page-num-1').innerText = "Page 1 / 2";
    document.body.classList.remove('print-only-problems', 'print-only-answers');
    const qrChecked = document.getElementById('toggle-print-qr')?.checked ?? true;
    if (!qrChecked || !currentCode) document.body.classList.add('hide-print-qr');
    else document.body.classList.remove('hide-print-qr');

    window.print();

    setTimeout(() => {
        document.body.classList.remove('hide-print-qr');
    }, 1000);
}

// QR코드 인쇄 포함 토글
function handleQrToggle(checked) {
    const qrEl = document.getElementById('print-qr-container');
    if (qrEl) {
        if (checked) qrEl.classList.remove('hidden');
        else qrEl.classList.add('hidden');
    }
}

// 초스피드 훈련번호 답안 조회 (PC 모달)
function quickLookupAnswer() {
    const inputEl = document.getElementById('quick-search-code');
    const codeVal = inputEl ? inputEl.value.trim() : '';
    if (!codeVal) {
        alert("조회할 훈련번호(예: #" + currentCode + " 또는 뒷자리 번호)를 입력해 주세요.");
        return;
    }

    const formData = new FormData();
    formData.append('action', 'lookup_answers');
    formData.append('code', codeVal);

    fetch('worksheet.php', { method: 'POST', body: formData })
        .then(async (r) => {
            const text = await r.text();
            try {
                return JSON.parse(text);
            } catch(e) {
                throw new Error("서버 응답 오류");
            }
        })
        .then(data => {
            if (data.success && data.worksheet) {
                const ws = data.worksheet;
                document.getElementById('modal-ans-title').innerText = ws.title;
                document.getElementById('modal-ans-code').innerText = "#" + ws.code;
                document.getElementById('modal-ans-mobile-link').href = ws.answer_url;

                const grid = document.getElementById('modal-ans-grid');
                grid.innerHTML = '';
                ws.problems.forEach((p, idx) => {
                    const card = document.createElement('div');
                    card.className = "p-2.5 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between";
                    card.innerHTML = `
                        <span class="font-bold text-slate-500 text-xs font-mono">${idx + 1}번</span>
                        <span class="font-bold text-slate-900 text-base font-mono">${p.answer}</span>
                    `;
                    grid.appendChild(card);
                });

                document.getElementById('quick-ans-modal').classList.remove('hidden');
            } else {
                alert(data.error || "해당 훈련번호를 찾을 수 없습니다.");
            }
        })
        .catch(e => alert("답안 조회 중 오류가 발생했습니다: " + e.message));
}

function closeQuickAnsModal() {
    document.getElementById('quick-ans-modal').classList.add('hidden');
}

// [비동기 새 문제 생성 - 화면 깜빡임/새로고침 없음]
function generateNewProblemsAsync() {
    const type = document.getElementById('gen-opt-type').value;
    const difficulty = document.getElementById('gen-opt-diff').value;
    const count = document.getElementById('gen-opt-count').value;
    const btn = document.getElementById('btn-generate-ajax');
    
    btn.innerText = "생성 중...";
    btn.disabled = true;

    // 문제지 영역 부드럽게 반투명 처리
    const p1 = document.getElementById('worksheet-page-1');
    const p2 = document.getElementById('worksheet-page-2');
    p1.style.opacity = '0.3';
    p2.style.opacity = '0.3';

    const formData = new FormData();
    formData.append('action', 'generate_ajax');
    formData.append('type', type);
    formData.append('difficulty', difficulty);
    formData.append('count', count);

    fetch('worksheet.php', { method: 'POST', body: formData })
        .then(async (r) => {
            const text = await r.text();
            try {
                return JSON.parse(text);
            } catch (err) {
                console.error("Server raw response:", text);
                throw new Error("서버 응답 오류");
            }
        })
        .then(data => {
            if (data.success) {
                currentProblems = data.problems;
                currentSeed = data.seed;
                currentType = data.type;
                currentDifficulty = data.difficulty;
                currentCount = data.count;
                currentCode = data.code;
                currentWsId = data.id;
                currentQrUrl = data.qr_url;

                // 타이틀, 서브타이틀 및 일련번호 안전 갱신 (미보관 상태로 표시)
                const safeSetText = (id, val) => {
                    const el = document.getElementById(id);
                    if (el) el.innerText = val;
                };
                safeSetText('top-badge-code', "미보관 (임시)");
                const topBadge = document.getElementById('top-badge-code');
                if (topBadge) topBadge.className = "px-2 py-0.5 bg-slate-100 text-slate-500 text-xs font-medium rounded-lg";

                safeSetText('display-worksheet-code-badge', "미보관 (임시)");
                const sheetBadge = document.getElementById('display-worksheet-code-badge');
                if (sheetBadge) sheetBadge.className = "px-2 py-0.5 rounded bg-slate-100 text-slate-500 font-mono text-[11px] font-medium tracking-wider";

                safeSetText('display-worksheet-code-text', "미보관 (보관 시 번호 발급)");
                safeSetText('display-footer-code', "");
                safeSetText('display-answers-footer-code', "");
                
                // QR코드 및 정답표 번호 숨김
                const qrContainer = document.getElementById('print-qr-container');
                if (qrContainer) {
                    qrContainer.classList.add('hidden');
                    qrContainer.classList.remove('flex');
                }
                const qrImg = document.getElementById('print-qr-img');
                if (qrImg) qrImg.src = "";

                const ansCodeWrap = document.getElementById('display-answers-title-code-wrap');
                if (ansCodeWrap) ansCodeWrap.classList.add('hidden');

                // 보관하기 버튼 활성화 모드로 변경 및 인쇄 버튼군 숨김
                const printActions = document.getElementById('saved-print-actions');
                if (printActions) {
                    printActions.classList.add('hidden');
                    printActions.classList.remove('flex');
                }

                const saveBtn = document.getElementById('btn-save-action');
                if (saveBtn) saveBtn.className = "inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition shadow-sm active:scale-95";
                const saveLabel = document.getElementById('btn-save-label');
                if (saveLabel) saveLabel.innerText = "★ 이 문제집 보관하기 (번호&QR발급)";

                const searchInput = document.getElementById('quick-search-code');
                if (searchInput) searchInput.placeholder = "예: #20261001-01 또는 순번";

                safeSetText('display-worksheet-title', data.title);
                safeSetText('display-worksheet-subtitle', data.subtitle);

                const ansTitleEl = document.getElementById('display-answers-title');
                if (ansTitleEl) {
                    ansTitleEl.innerHTML = `${data.title} <span id="display-answers-title-code-wrap" class="hidden"> - [ 정답지 #<span id="display-answers-title-code"></span> ]</span>`;
                }

                safeSetText('display-seed-text-1', "Seed: " + data.seed);

                // 문제 리스트 (1페이지) DOM 갱신
                const probContainer = document.getElementById('problems-grid-container');
                if (probContainer && Array.isArray(data.problems)) {
                    probContainer.innerHTML = '';
                    data.problems.forEach((p, idx) => {
                        const cleanQ = p.question.trim().replace(/\s*=\s*$/, '');
                        const div = document.createElement('div');
                        div.className = "print-problem-row flex items-baseline justify-between border-b border-slate-200 pb-2";
                        div.innerHTML = `
                            <div class="flex items-baseline gap-2 font-mono">
                                <span class="prob-num font-bold text-slate-400 w-7 text-right text-sm">${idx + 1}.</span>
                                <span class="prob-eq text-lg font-bold text-slate-900 tracking-tight">${cleanQ} <span class="font-normal text-slate-400">=</span></span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="inline-block w-24 border-b-2 border-slate-400"></span>
                                <span class="text-[10px] text-slate-400 font-sans whitespace-nowrap">(___초)</span>
                            </div>
                        `;
                        probContainer.appendChild(div);
                    });
                }

                // 정답표 (2페이지) DOM 갱신
                const ansContainer = document.getElementById('answers-grid-container');
                if (ansContainer && Array.isArray(data.problems)) {
                    ansContainer.innerHTML = '';
                    data.problems.forEach((p, idx) => {
                        const div = document.createElement('div');
                        div.className = "print-answer-item p-3 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between";
                        div.innerHTML = `
                            <span class="font-bold text-slate-500 text-sm font-mono">${idx + 1}번</span>
                            <span class="font-bold text-slate-900 text-lg font-mono">${p.answer}</span>
                        `;
                        ansContainer.appendChild(div);
                    });
                }

                // 보관된 문제집 선택 배너 숨김
                const savedBanner = document.getElementById('saved-worksheet-banner');
                if (savedBanner) savedBanner.classList.add('hidden');
            } else {
                alert("문제 생성 실패: " + (data.error || '알 수 없는 오류'));
            }
        })
        .catch(e => {
            console.error("문제 생성 통신/처리 오류:", e);
            alert("문제 생성 중 오류가 발생했습니다: " + (e.message || "통신 오류"));
        })
        .finally(() => {
            btn.innerText = "새 문제 세트 생성하기 (↺ 새로고침)";
            btn.disabled = false;
            p1.style.opacity = '1';
            p2.style.opacity = '1';
        });
}

function resetToGenerator() {
    document.getElementById('saved-worksheet-banner').classList.add('hidden');
    generateNewProblemsAsync();
}

function toggleArchiveView() {
    const el = document.getElementById('archive-section');
    el.classList.toggle('hidden');
}

function toggleCustomTime(val) {
    const box = document.getElementById('m-custom-time-box');
    if (val === 'custom') {
        box.classList.remove('hidden');
    } else {
        box.classList.add('hidden');
    }
}

function toggleCustomExpire(val) {
    const box = document.getElementById('m-custom-expire-box');
    if (val === 'custom') {
        box.classList.remove('hidden');
    } else {
        box.classList.add('hidden');
    }
}

function saveCurrentWorksheet() {
    if (currentCode) {
        alert("이미 보관함에 저장된 문제집입니다 (훈련번호: #" + currentCode + ")");
        return;
    }

    const defaultTitle = "<?php echo htmlspecialchars($active_student['name'] ?? '학생'); ?>의 " + (document.getElementById('gen-opt-type').selectedOptions[0]?.text || '맞춤 훈련지') + " " + currentCount + "제";
    const title = prompt("보관할 문제집 이름을 입력해 주세요:", defaultTitle);
    if (!title || !title.trim()) return;

    const formData = new FormData();
    formData.append('action', 'save_worksheet');
    formData.append('title', title.trim());
    formData.append('problem_type', currentType);
    formData.append('difficulty', currentDifficulty);
    formData.append('count', currentCount);
    formData.append('seed', currentSeed);
    formData.append('problems', JSON.stringify(currentProblems));

    fetch('worksheet.php', { method: 'POST', body: formData })
        .then(async (r) => {
            const text = await r.text();
            try {
                return JSON.parse(text);
            } catch (err) {
                console.error("Server response:", text);
                throw new Error("서버 응답 오류 (JSON 파싱 실패)");
            }
        })
        .then(data => {
            if (data.success) {
                currentCode = data.code;
                currentWsId = data.id;

                const safeSetText = (id, val) => {
                    const el = document.getElementById(id);
                    if (el) el.innerText = val;
                };
                safeSetText('top-badge-code', "#" + data.code);
                const topBadge = document.getElementById('top-badge-code');
                if (topBadge) topBadge.className = "px-2 py-0.5 bg-emerald-100 text-emerald-800 text-xs font-mono font-bold rounded-lg";

                safeSetText('display-worksheet-code-badge', "#" + data.code);
                const sheetBadge = document.getElementById('display-worksheet-code-badge');
                if (sheetBadge) sheetBadge.className = "px-2 py-0.5 rounded bg-slate-900 text-white font-mono text-[11px] font-bold tracking-wider";

                safeSetText('display-worksheet-code-text', "#" + data.code);
                safeSetText('display-footer-code', "#" + data.code);
                safeSetText('display-answers-footer-code', "#" + data.code);
                safeSetText('display-answers-title-code', data.code);

                const ansCodeWrap = document.getElementById('display-answers-title-code-wrap');
                if (ansCodeWrap) ansCodeWrap.classList.remove('hidden');

                const qrContainer = document.getElementById('print-qr-container');
                if (qrContainer) {
                    qrContainer.classList.remove('hidden');
                    qrContainer.classList.add('flex');
                }
                const qrImg = document.getElementById('print-qr-img');
                if (qrImg) qrImg.src = data.qr_url;

                const saveBtn = document.getElementById('btn-save-action');
                if (saveBtn) saveBtn.className = "inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-slate-100 text-slate-700 border border-slate-300 text-xs font-bold transition active:scale-95";
                const saveLabel = document.getElementById('btn-save-label');
                if (saveLabel) saveLabel.innerText = "✓ 보관됨 (#" + data.code + ")";

                // 보관함 버튼 카운터 갱신
                const arcBtn = document.getElementById('btn-archive-toggle');
                if (arcBtn) {
                    const match = arcBtn.innerText.match(/\d+/);
                    const newCount = match ? parseInt(match[0]) + 1 : 1;
                    arcBtn.innerHTML = `📁 보관함 목록 (${newCount})`;
                }

                alert(`'${data.title}' 문제집이 보관되었습니다!\n\n공식 훈련번호 #${data.code} 와 빠른 채점 QR코드가 발급되었습니다.\n인쇄 버튼이 활성화된 화면으로 이동합니다.`);
                location.href = `worksheet.php?id=${encodeURIComponent(data.id)}`;
            } else {
                alert("저장 실패: " + (data.error || '알 수 없는 오류'));
            }
        })
        .catch(e => {
            console.error(e);
            alert("통신 오류 발생: " + e.message);
        });
}

function deleteSavedWorksheet(id, title) {
    if (!confirm("'" + title + "' 문제집을 보관함에서 삭제하시겠습니까?")) return;
    const formData = new FormData();
    formData.append('action', 'delete_worksheet');
    formData.append('id', id);

    fetch('worksheet.php', { method: 'POST', body: formData })
        .then(async (r) => {
            const text = await r.text();
            try {
                return JSON.parse(text);
            } catch (err) {
                throw new Error("서버 응답 오류");
            }
        })
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert("삭제 실패: " + (data.error || '알 수 없는 오류'));
            }
        })
        .catch(e => alert("삭제 중 오류 발생: " + e.message));
}

function clearAllWorksheets() {
    if (!confirm("보관함에 저장된 모든 문제집을 삭제하시겠습니까?\n\n(지금까지 테스트로 임시 누적된 번호와 문제집이 모두 깨끗하게 비워집니다)")) {
        return;
    }
    const formData = new FormData();
    formData.append('action', 'clear_all_worksheets');

    fetch('worksheet.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert("보관함이 깨끗하게 정리되었습니다.");
                location.href = 'worksheet.php';
            } else {
                alert("정리 실패: " + (data.error || '알 수 없는 오류'));
            }
        })
        .catch(e => alert("통신 오류 발생: " + e.message));
}

function deleteSelectedWorksheets() {
    const checked = Array.from(document.querySelectorAll('.archive-item-cb:checked')).map(cb => cb.value);
    if (checked.length === 0) {
        alert("삭제할 문제집을 1개 이상 체크해 주세요.");
        return;
    }
    if (!confirm(`선택한 ${checked.length}개의 문제집을 보관함에서 삭제하시겠습니까?`)) {
        return;
    }
    const formData = new FormData();
    formData.append('action', 'delete_selected_worksheets');
    formData.append('ids', JSON.stringify(checked));

    fetch('worksheet.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert("선택한 문제집이 삭제되었습니다.");
                location.reload();
            } else {
                alert("삭제 실패: " + (data.error || '알 수 없는 오류'));
            }
        })
        .catch(e => alert("통신 오류 발생: " + e.message));
}

function toggleSelectAllArchive(checked) {
    document.querySelectorAll('.archive-item-cb').forEach(cb => {
        cb.checked = checked;
    });
}

function openExamModal() {
    document.getElementById('exam-modal').classList.remove('hidden');
    document.getElementById('exam-form-view').classList.remove('hidden');
    document.getElementById('exam-result-view').classList.add('hidden');
}

function closeExamModal() {
    document.getElementById('exam-modal').classList.add('hidden');
}

function submitCreateExam() {
    const title = document.getElementById('m-exam-title').value;
    const timeSelect = document.getElementById('m-exam-time').value;
    let timeLimitSec = 600;
    if (timeSelect === 'custom') {
        const mins = Number(document.getElementById('m-custom-time-input').value) || 10;
        timeLimitSec = Math.max(1, mins) * 60;
    } else {
        timeLimitSec = Number(timeSelect);
    }

    const expireSelect = document.getElementById('m-exam-expire').value;
    let expireHours = 3;
    if (expireSelect === 'custom') {
        expireHours = Number(document.getElementById('m-custom-expire-input').value) || 3;
    } else {
        expireHours = Number(expireSelect);
    }

    const student = document.getElementById('m-exam-student').value;

    const formData = new FormData();
    formData.append('action', 'create_exam');
    formData.append('title', title);
    formData.append('time_limit_sec', timeLimitSec);
    formData.append('expire_hours', expireHours);
    formData.append('student_name', student);
    formData.append('problems', JSON.stringify(currentProblems));

    fetch('worksheet.php', { method: 'POST', body: formData })
        .then(async (r) => {
            const text = await r.text();
            try {
                return JSON.parse(text);
            } catch (err) {
                console.error("Server response:", text);
                throw new Error("서버 응답 오류");
            }
        })
        .then(data => {
            if (data.success) {
                createdExamData = data;
                document.getElementById('res-exam-title').innerText = data.title;
                document.getElementById('res-exam-code').innerText = data.code;
                document.getElementById('res-hub-url-text').innerText = data.hub_url;
                document.getElementById('res-qr-img').src = data.qr_url;
                document.getElementById('res-exam-url').value = data.exam_url;
                document.getElementById('res-hub-link').href = data.hub_url;

                document.getElementById('exam-form-view').classList.add('hidden');
                document.getElementById('exam-result-view').classList.remove('hidden');
            } else {
                alert("시험 생성 실패: " + (data.error || '알 수 없는 오류'));
            }
        })
        .catch(e => alert("시험 생성 중 오류 발생: " + e.message));
}

function copyExamLink() {
    const input = document.getElementById('res-exam-url');
    input.select();
    navigator.clipboard.writeText(input.value);
    alert("전용 시험 링크가 복사되었습니다!");
}

function copyHubUrl() {
    if (!createdExamData) return;
    navigator.clipboard.writeText(createdExamData.hub_url);
    alert("코드 입력 페이지 주소 (" + createdExamData.hub_url + ") 가 복사되었습니다!");
}

function copyShareText() {
    if (!createdExamData) return;
    const timeText = createdExamData.time_limit_sec > 0 ? (createdExamData.time_limit_sec / 60) + "분" : "무제한";
    const expireText = createdExamData.expire_hours > 0 ? createdExamData.expire_hours + "시간 후 만료" : "무제한";

    const msg = "[초5 연산 트레이너] 온라인 시험 안내\n\n" +
        "📌 시험명: " + createdExamData.title + "\n" +
        "⏱️ 제한 시간: " + timeText + " (" + createdExamData.total_count + "문항)\n" +
        "⏰ 링크 유효기간: " + expireText + "\n\n" +
        "👉 [방법 1] 전용 링크로 바로 입장:\n" + createdExamData.exam_url + "\n\n" +
        "👉 [방법 2] 태블릿/PC 브라우저 간편 입장:\n" +
        "1. 브라우저 주소창에 " + createdExamData.hub_url + " 접속\n" +
        "2. 6자리 입장 코드 [ " + createdExamData.code + " ] 입력 후 시작\n\n" +
        "👉 [방법 3] 학원 태블릿 카메라로 QR 코드 스캔\n\n" +
        "편한 방법으로 접속하여 시험을 치러주세요!";

    navigator.clipboard.writeText(msg);
    alert("카카오톡 / 문자 전체 안내 문구가 복사되었습니다!");
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
