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

// 1. AJAX 요청 처리 (비동기 새 문제 생성, 문제집 보관, 시험 생성, 삭제)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // [A] 비동기 새 문제 생성 (페이지 새로고침 없는 부드러운 갱신)
    if ($action === 'generate_ajax') {
        header('Content-Type: application/json; charset=utf-8');
        $target_type = $_POST['type'] ?? 'divide5';
        $diff = (int)($_POST['difficulty'] ?? 2);
        $p_count = (int)($_POST['count'] ?? 20);
        $seed_val = 'ws_' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
        $new_problems = generate_worksheet_problems($target_type, $p_count, $diff, $seed_val);

        echo json_encode([
            'success' => true,
            'type' => $target_type,
            'type_label' => PROBLEM_TYPE_LABELS[$target_type] ?? $target_type,
            'difficulty' => $diff,
            'count' => $p_count,
            'seed' => $seed_val,
            'title' => ($active_student['name'] ?? '홍길동') . "의 " . (PROBLEM_TYPE_LABELS[$target_type] ?? $target_type) . " 맞춤 훈련지",
            'subtitle' => "난이도: Level {$diff} · 문제 수: {$p_count}문항",
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

        if (empty($title)) {
            $title = ($active_student['name'] ?? '학생') . "의 " . (PROBLEM_TYPE_LABELS[$target_type] ?? $target_type) . " 맞춤 훈련지";
        }

        try {
            $ws_id = 'ws_' . bin2hex(random_bytes(8));
            $stmt = $pdo->prepare("
                INSERT INTO `{$t_ws}` 
                (`id`, `student_id`, `title`, `subject`, `grade`, `difficulty`, `problem_type`, `count`, `seed`, `problems`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $ws_id,
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
            echo json_encode(['success' => true, 'id' => $ws_id, 'title' => $title]);
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

    // [D] 보관된 문제집 삭제
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
}

// 2. 화면 로드 처리 (HTML 렌더링 시에만 header.php 호출)
require_once __DIR__ . '/includes/header.php';

$id = $_GET['id'] ?? null;
$type = $_GET['type'] ?? 'divide5';
$difficulty = (int)($_GET['difficulty'] ?? 2);
$count = (int)($_GET['count'] ?? 20);
$seed = $_GET['seed'] ?? ('ws_' . substr(md5(uniqid(mt_rand(), true)), 0, 8));
$tab = $_GET['tab'] ?? 'worksheet';

$is_saved_view = false;
$saved_worksheet = null;

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

            if (!empty($saved_worksheet['problems'])) {
                $decoded = json_decode($saved_worksheet['problems'], true);
                if (is_array($decoded) && count($decoded) > 0) {
                    $problems = $decoded;
                }
            }

            // 이전에 problems 컬럼 없이 저장되었던 레코드인 경우, seed로 문제를 생성하여 DB에 영구 동결 저장
            if (empty($problems)) {
                $problems = generate_worksheet_problems($type, $count, $difficulty, $seed);
                try {
                    $up = $pdo->prepare("UPDATE `{$t_ws}` SET `problems` = ? WHERE `id` = ?");
                    $up->execute([json_encode($problems), $id]);
                } catch (Exception $e) {}
            }
        }
    } catch (Exception $e) {}
}

if (!isset($problems) || empty($problems)) {
    $problems = generate_worksheet_problems($type, $count, $difficulty, $seed);
}

// 보관함 목록 조회
$saved_list = [];
try {
    $stmt = $pdo->query("SELECT `id`, `title`, `problem_type`, `difficulty`, `count`, `created_at` FROM `{$t_ws}` ORDER BY `created_at` DESC");
    $saved_list = $stmt->fetchAll();
} catch (Exception $e) {}
?>

<!-- 상단 헤더 및 액션 바 (인쇄 시 숨김) -->
<div class="no-print bg-white border border-slate-200 rounded-2xl p-6 mb-8 shadow-xs">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-slate-900"></span>
                <h1 class="text-xl font-bold text-slate-900">맞춤 훈련지 생성 및 보관함</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1">
                원하는 문제 세트를 보관하여 언제든 다시 인쇄하거나 학생 전용 시험 링크(QR)로 전송할 수 있습니다.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 transition shadow-sm active:scale-95">
                <svg class="w-4 h-4 fill-white" viewBox="0 0 24 24"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                A4 인쇄하기 (Ctrl+P)
            </button>
            <button type="button" onclick="saveCurrentWorksheet()" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition shadow-sm active:scale-95">
                ★ 이 문제집 보관하기
            </button>
            <button type="button" onclick="openExamModal()" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition shadow-sm active:scale-95">
                📱 온라인 시험 링크 & QR
            </button>
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
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-6 pt-6 border-t border-slate-100">
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">훈련 유형</label>
            <select id="gen-opt-type" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs font-medium bg-white">
                <?php foreach (PROBLEM_TYPE_LABELS as $k => $label): ?>
                    <option value="<?php echo $k; ?>" <?php echo $type === $k ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">난이도</label>
            <select id="gen-opt-diff" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs font-medium bg-white">
                <option value="1" <?php echo $difficulty === 1 ? 'selected' : ''; ?>>Level 1 (기초)</option>
                <option value="2" <?php echo $difficulty === 2 ? 'selected' : ''; ?>>Level 2 (표준)</option>
                <option value="3" <?php echo $difficulty === 3 ? 'selected' : ''; ?>>Level 3 (심화)</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">문항 수</label>
            <select id="gen-opt-count" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs font-medium bg-white">
                <option value="20" <?php echo $count === 20 ? 'selected' : ''; ?>>20문제 (A4 1장 권장)</option>
                <option value="10" <?php echo $count === 10 ? 'selected' : ''; ?>>10문제 (간이 시험)</option>
                <option value="30" <?php echo $count === 30 ? 'selected' : ''; ?>>30문제 (집중 훈련)</option>
            </select>
        </div>

        <div class="flex items-end gap-2">
            <button type="button" id="btn-generate-ajax" onclick="generateNewProblemsAsync()" class="w-full py-2 px-3 bg-slate-900 text-white font-semibold text-xs rounded-xl transition hover:bg-slate-800 active:scale-98">
                새 문제 생성
            </button>
            <button type="button" onclick="toggleArchiveView()" class="py-2 px-3 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 font-bold text-xs rounded-xl whitespace-nowrap transition">
                보관함 (<?php echo count($saved_list); ?>)
            </button>
        </div>
    </div>
</div>

<!-- 저장된 보관함 뷰 (기본 숨김 또는 토글) -->
<div id="archive-section" class="no-print hidden bg-white border border-slate-200 rounded-2xl p-6 mb-8 shadow-xs space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <div>
            <h2 class="text-lg font-bold text-slate-900">보관된 맞춤 문제집 목록</h2>
            <p class="text-xs text-slate-500">저장된 문제집을 다시 인쇄하거나 바로 학생 시험 링크를 생성합니다.</p>
        </div>
        <button onclick="toggleArchiveView()" class="text-xs text-slate-500 hover:text-slate-800 font-semibold">
            닫기 ✕
        </button>
    </div>

    <?php if (empty($saved_list)): ?>
        <div class="py-12 text-center text-slate-400 space-y-2">
            <p class="text-sm">아직 보관된 문제집이 없습니다.</p>
            <p class="text-xs text-slate-400">상단의 [★ 이 문제집 보관하기]를 눌러 원하는 문제를 저장해 두세요.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($saved_list as $ws): ?>
                <div class="p-5 rounded-2xl border border-slate-200 hover:border-slate-300 bg-white transition flex flex-col justify-between">
                    <div class="space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-bold text-base text-slate-900"><?php echo htmlspecialchars($ws['title']); ?></h3>
                            <button onclick="deleteSavedWorksheet('<?php echo $ws['id']; ?>', '<?php echo htmlspecialchars($ws['title']); ?>')" class="text-slate-400 hover:text-rose-500 p-1 text-xs">
                                삭제
                            </button>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs">
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

                    <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap gap-1.5">
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
<div id="worksheet-page-1" class="a4-sheet bg-white p-8 sm:p-12 mb-8 border border-slate-200 sm:rounded-2xl shadow-sm text-slate-900 transition-opacity duration-300">
    <div class="border-b-2 border-slate-900 pb-3 mb-6 flex justify-between items-end">
        <div>
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-widest block">
                MATH CURE · 초5 나눗셈 자동화 프로젝트
            </span>
            <h2 id="display-worksheet-title" class="text-2xl font-black text-slate-900 mt-0.5">
                <?php echo htmlspecialchars($is_saved_view && $saved_worksheet ? $saved_worksheet['title'] : ((PROBLEM_TYPE_LABELS[$type] ?? $type) . " 맞춤 훈련지")); ?>
            </h2>
            <span id="display-worksheet-subtitle" class="text-xs text-slate-600 font-medium">
                난이도: Level <?php echo $difficulty; ?> · 문제 수: <?php echo count($problems); ?>문항
            </span>
        </div>

        <div class="text-right text-xs space-y-1">
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
            <div class="flex items-baseline justify-between border-b border-slate-200 pb-2">
                <div class="flex items-baseline gap-2 font-mono">
                    <span class="font-bold text-slate-400 w-7 text-right text-sm">
                        <?php echo $idx + 1; ?>.
                    </span>
                    <span class="text-lg font-bold text-slate-900 tracking-tight">
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

    <div class="mt-12 pt-4 border-t border-slate-300 flex justify-between items-center text-[11px] text-slate-400">
        <span>초5 나눗셈 트레이너 · MathCure</span>
        <span id="display-seed-text-1">Seed: <?php echo htmlspecialchars($seed); ?></span>
        <span>Page 1 / 2</span>
    </div>
</div>

<!-- 인쇄용 페이지 구분자 (화면에서는 구분선, 인쇄 시 페이지 나눔) -->
<div class="page-break"></div>

<!-- [2페이지] 정답지 -->
<div id="worksheet-page-2" class="a4-sheet bg-white p-8 sm:p-12 mb-8 border border-slate-200 sm:rounded-2xl shadow-sm text-slate-900 transition-opacity duration-300">
    <div class="border-b-2 border-slate-900 pb-3 mb-6 flex justify-between items-end">
        <div>
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-widest block">
                ANSWER KEY · 정답 및 빠른 채점표
            </span>
            <h2 id="display-answers-title" class="text-2xl font-black text-slate-900 mt-0.5">
                <?php echo htmlspecialchars($is_saved_view && $saved_worksheet ? $saved_worksheet['title'] : ((PROBLEM_TYPE_LABELS[$type] ?? $type) . " 맞춤 훈련지")); ?> - [ 정답지 ]
            </h2>
            <span id="display-answers-subtitle" class="text-xs text-slate-600 font-medium">
                초5 나눗셈 자동화 빠른 채점 기준표
            </span>
        </div>

        <div class="text-right text-xs">
            <span class="px-2 py-1 bg-slate-100 border border-slate-300 rounded font-bold">
                교사용 / 채점용
            </span>
        </div>
    </div>

    <div id="answers-grid-container" class="grid grid-cols-2 sm:grid-cols-4 gap-4 my-6">
        <?php foreach ($problems as $idx => $p): ?>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between">
                <span class="font-bold text-slate-500 text-sm font-mono">
                    <?php echo $idx + 1; ?>번
                </span>
                <span class="font-bold text-slate-900 text-lg font-mono">
                    <?php echo $p['answer']; ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-12 p-4 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-600 leading-relaxed">
        <strong class="text-slate-800 block mb-1">지도 팁:</strong>
        채점 시 정답 여부뿐만 아니라 15초 이상 지체된 문제에 대해서는 나눗셈 원리(예: ÷5는 2배 후 10 나누기)를 다시 점검해 주세요.
    </div>

    <div class="mt-8 pt-4 border-t border-slate-300 flex justify-between items-center text-[11px] text-slate-400">
        <span>초5 나눗셈 트레이너 · MathCure 정답지</span>
        <span id="display-seed-text-2">Page 2 / 2</span>
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
let createdExamData = null;

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

                // 타이틀 및 서브타이틀 갱신
                document.getElementById('display-worksheet-title').innerText = data.title;
                document.getElementById('display-worksheet-subtitle').innerText = data.subtitle;
                document.getElementById('display-answers-title').innerText = data.title + " - [ 정답지 ]";
                document.getElementById('display-seed-text-1').innerText = "Seed: " + data.seed;

                // 문제 리스트 (1페이지) DOM 갱신
                const probContainer = document.getElementById('problems-grid-container');
                probContainer.innerHTML = '';
                data.problems.forEach((p, idx) => {
                    const cleanQ = p.question.trim().replace(/\s*=\s*$/, '');
                    const div = document.createElement('div');
                    div.className = "flex items-baseline justify-between border-b border-slate-200 pb-2";
                    div.innerHTML = `
                        <div class="flex items-baseline gap-2 font-mono">
                            <span class="font-bold text-slate-400 w-7 text-right text-sm">${idx + 1}.</span>
                            <span class="text-lg font-bold text-slate-900 tracking-tight">${cleanQ} <span class="font-normal text-slate-400">=</span></span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="inline-block w-24 border-b-2 border-slate-400"></span>
                            <span class="text-[10px] text-slate-400 font-sans whitespace-nowrap">(___초)</span>
                        </div>
                    `;
                    probContainer.appendChild(div);
                });

                // 정답표 (2페이지) DOM 갱신
                const ansContainer = document.getElementById('answers-grid-container');
                ansContainer.innerHTML = '';
                data.problems.forEach((p, idx) => {
                    const div = document.createElement('div');
                    div.className = "p-3 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between";
                    div.innerHTML = `
                        <span class="font-bold text-slate-500 text-sm font-mono">${idx + 1}번</span>
                        <span class="font-bold text-slate-900 text-lg font-mono">${p.answer}</span>
                    `;
                    ansContainer.appendChild(div);
                });

                // 보관된 문제집 선택 배너 숨김
                document.getElementById('saved-worksheet-banner').classList.add('hidden');
            } else {
                alert("문제 생성 실패: " + (data.error || '알 수 없는 오류'));
            }
        })
        .catch(e => alert("문제 생성 중 통신 오류가 발생했습니다."))
        .finally(() => {
            btn.innerText = "새 문제 생성";
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
                alert("'" + data.title + "' 문제집이 보관함에 저장되었습니다!");
                location.reload();
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
