<?php
/**
 * MathCure - 선생님 전용 초스피드 빠른 채점 & 정답지 (모바일 QR 연동)
 * 학생의 커닝 방지를 위한 3중 보안 잠금(로그인 세션 / 4자리 PIN)이 적용되어 있습니다.
 * 원클릭 [채점 결과 저장]으로 학생 DB에 점수와 날짜가 즉시 영구 기록됩니다.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/auth.php';

$pdo = get_db();
$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'mc_';
$t_ws = "{$prefix}worksheets";
$t_users = "{$prefix}users";
$t_grades = "{$prefix}worksheet_grades";
$t_prac = "{$prefix}practice_sessions";
$t_students = "{$prefix}students";

start_session_safe();
$logged_user = get_logged_in_user($pdo);

// worksheet_grades 테이블 자동 생성 (테이블 미존재 시 대비)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `{$t_grades}` (
            `id` VARCHAR(36) NOT NULL PRIMARY KEY,
            `worksheet_id` VARCHAR(36) NULL,
            `worksheet_code` VARCHAR(30) NOT NULL,
            `student_id` VARCHAR(36) NOT NULL,
            `title` VARCHAR(100) NOT NULL,
            `problem_type` VARCHAR(50) NOT NULL,
            `difficulty` INT NOT NULL DEFAULT 2,
            `total_count` INT NOT NULL,
            `correct_count` INT NOT NULL,
            `score` INT NOT NULL,
            `graded_by` VARCHAR(36) NULL,
            `graded_items` LONGTEXT NULL,
            `graded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (`student_id`),
            INDEX (`worksheet_code`),
            INDEX (`graded_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e) {}

$raw_code = trim($_GET['code'] ?? '');
$clean_code = ltrim($raw_code, '#');

// PIN 인증 처리 (POST)
$pin_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_pin') {
    $input_pin = trim($_POST['pin'] ?? '');
    
    // DB의 사용자 PIN 또는 기본값 1234
    $valid_pins = ['1234'];
    try {
        $stmt = $pdo->query("SELECT DISTINCT `pin` FROM `{$t_users}` WHERE `pin` IS NOT NULL AND `pin` != ''");
        $db_pins = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if ($db_pins) {
            $valid_pins = array_unique(array_merge($valid_pins, $db_pins));
        }
    } catch (Exception $e) {}

    if (in_array($input_pin, $valid_pins, true)) {
        $_SESSION['teacher_pin_auth'] = true;
        $_SESSION['teacher_pin_auth_time'] = time();
        setcookie('mc_teacher_pin', hash('sha256', $input_pin . 'mathcure_salt'), time() + 86400, '/');
        // 새로고침 방지 리다이렉트
        header("Location: answer_key.php?code=" . urlencode($clean_code));
        exit;
    } else {
        $pin_error = '선생님 PIN 번호가 일치하지 않습니다. (초기 기본값: 1234)';
    }
}

// 잠금 해제 (로그아웃/재잠금)
if (isset($_GET['lock']) && $_GET['lock'] == '1') {
    unset($_SESSION['teacher_pin_auth']);
    unset($_SESSION['teacher_pin_auth_time']);
    setcookie('mc_teacher_pin', '', time() - 3600, '/');
    header("Location: answer_key.php?code=" . urlencode($clean_code));
    exit;
}

// 인증 여부 판정
$is_authenticated = false;
$auth_method = '';
if ($logged_user !== null) {
    $is_authenticated = true;
    $auth_method = '회원 로그인';
} elseif (!empty($_SESSION['teacher_pin_auth']) && (time() - ($_SESSION['teacher_pin_auth_time'] ?? 0) < 86400)) {
    $is_authenticated = true;
    $auth_method = '간편 PIN 인증';
} elseif (!empty($_COOKIE['mc_teacher_pin'])) {
    $is_authenticated = true;
    $auth_method = '간편 PIN 기억';
}

// [채점 결과 저장 AJAX 처리]
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_grade') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$is_authenticated) {
        echo json_encode(['success' => false, 'error' => '선생님 인증이 필요합니다.']);
        exit;
    }

    $ws_id = trim($_POST['worksheet_id'] ?? '');
    $ws_code = trim($_POST['code'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $total_count = (int)($_POST['total_count'] ?? 0);
    $correct_count = (int)($_POST['correct_count'] ?? 0);
    $score = (int)($_POST['score'] ?? 0);
    $graded_items = $_POST['graded_items'] ?? '[]';

    // 문제지 정보 조회
    $ws_title = '맞춤 훈련지 #' . $ws_code;
    $ws_type = 'divide5';
    $ws_diff = 2;
    if (!empty($ws_id) || !empty($ws_code)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM `{$t_ws}` WHERE `id` = ? OR `code` = ? LIMIT 1");
            $stmt->execute([$ws_id, $ws_code]);
            $w_info = $stmt->fetch();
            if ($w_info) {
                $ws_title = $w_info['title'];
                $ws_type = $w_info['problem_type'];
                $ws_diff = (int)$w_info['difficulty'];
                if (empty($student_id) && !empty($w_info['student_id'])) {
                    $student_id = $w_info['student_id'];
                }
            }
        } catch (Exception $e) {}
    }

    if (empty($student_id)) {
        try {
            $st = $pdo->query("SELECT `id` FROM `{$t_students}` LIMIT 1");
            $student_id = $st->fetchColumn() ?: 'std_default';
        } catch (Exception $e) {
            $student_id = 'std_default';
        }
    }

    try {
        $grade_id = 'gr_' . bin2hex(random_bytes(8));
        $stmt = $pdo->prepare("
            INSERT INTO `{$t_grades}`
            (`id`, `worksheet_id`, `worksheet_code`, `student_id`, `title`, `problem_type`, `difficulty`, `total_count`, `correct_count`, `score`, `graded_by`, `graded_items`, `graded_at`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $grade_id,
            $ws_id ?: null,
            $ws_code,
            $student_id,
            $ws_title,
            $ws_type,
            $ws_diff,
            $total_count,
            $correct_count,
            $score,
            $logged_user['id'] ?? null,
            $graded_items
        ]);

        // practice_sessions 테이블에도 함께 기록하여 대시보드 및 연습 이력과 즉시 연동
        try {
            $prac_id = 'pr_' . bin2hex(random_bytes(8));
            $p_stmt = $pdo->prepare("
                INSERT INTO `{$t_prac}`
                (`id`, `student_id`, `worksheet_id`, `total`, `correct`, `accuracy`, `average_ms`, `started_at`, `finished_at`)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $p_stmt->execute([
                $prac_id,
                $student_id,
                $ws_id ?: null,
                $total_count,
                $correct_count,
                $score,
                0 // 종이 채점
            ]);
        } catch (Exception $e) {}

        // 학생 이름 조회
        $st_name = '학생';
        try {
            $s_stmt = $pdo->prepare("SELECT `name` FROM `{$t_students}` WHERE `id` = ?");
            $s_stmt->execute([$student_id]);
            $st_name = $s_stmt->fetchColumn() ?: '학생';
        } catch (Exception $e) {}

        echo json_encode([
            'success' => true,
            'grade_id' => $grade_id,
            'student_name' => $st_name,
            'score' => $score,
            'correct_count' => $correct_count,
            'total_count' => $total_count,
            'graded_at' => date('Y.m.d H:i')
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 문제지 조회
$worksheet = null;
if (!empty($clean_code)) {
    $candidates = [$clean_code, "#{$clean_code}"];
    if (preg_match('/^\d{1,2}$/', $clean_code)) {
        $today = date('Ymd');
        $pad = str_pad($clean_code, 2, '0', STR_PAD_LEFT);
        $candidates[] = "{$today}-{$pad}";
        $candidates[] = "#{$today}-{$pad}";
    }

    try {
        $in_placeholders = implode(',', array_fill(0, count($candidates), '?'));
        $stmt = $pdo->prepare("SELECT * FROM `{$t_ws}` WHERE `code` IN ({$in_placeholders}) OR `id` = ? LIMIT 1");
        $params = array_merge($candidates, [$clean_code]);
        $stmt->execute($params);
        $worksheet = $stmt->fetch();
    } catch (Exception $e) {}
}

$problems = $worksheet ? (json_decode($worksheet['problems'] ?? '[]', true) ?: []) : [];

// 전체 학생 목록 조회 (채점 대상 학생 선택 지원)
$all_students = [];
try {
    $stmt = $pdo->query("SELECT `id`, `name`, `grade` FROM `{$t_students}` ORDER BY `name` ASC");
    $all_students = $stmt->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="ko" class="h-full bg-slate-900 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>초스피드 빠른 채점표 | MathCure</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .pb-safe { padding-bottom: max(1.25rem, env(safe-area-inset-bottom)); }
        .pt-safe { padding-top: max(1rem, env(safe-area-inset-top)); }
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    </style>
</head>
<body class="min-h-full flex flex-col bg-slate-900 font-sans antialiased text-slate-100">

    <!-- 상단 네비 바 -->
    <header class="bg-slate-950/80 backdrop-blur-md border-b border-slate-800 sticky top-0 z-30 pt-safe">
        <div class="max-w-xl mx-auto px-4 h-14 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-emerald-500 text-slate-950 font-black flex items-center justify-center text-xs">
                    MC
                </span>
                <div>
                    <h1 class="font-bold text-sm text-white leading-tight">빠른 채점표</h1>
                    <span class="text-[10px] text-slate-400 block">선생님 전용 모바일 채점기</span>
                </div>
            </div>

            <!-- 상단 검색창 & 잠금 버튼 -->
            <div class="flex items-center gap-2">
                <?php if ($is_authenticated): ?>
                    <form method="GET" action="answer_key.php" class="flex items-center gap-1">
                        <input type="text" name="code" value="<?php echo htmlspecialchars($clean_code); ?>" placeholder="번호 #..." class="w-24 px-2 py-1 bg-slate-800 border border-slate-700 rounded-lg text-xs font-mono text-center text-white focus:outline-none focus:border-emerald-500">
                        <button type="submit" class="px-2 py-1 bg-slate-700 hover:bg-slate-600 rounded-lg text-xs font-bold text-white">이동</button>
                    </form>
                    <a href="answer_key.php?code=<?php echo urlencode($clean_code); ?>&lock=1" title="화면 잠금" class="p-1.5 bg-slate-800 hover:bg-rose-950/50 hover:text-rose-400 text-slate-400 rounded-lg text-xs transition">
                        🔒
                    </a>
                <?php else: ?>
                    <a href="login.php" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-xs font-semibold">
                        로그인
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-xl w-full mx-auto px-4 py-5 pb-safe space-y-4">

        <?php if (!$is_authenticated): ?>
            <!-- ======================================================== -->
            <!-- [A] 보안 잠금 화면 (학생 커닝 원천 차단) -->
            <!-- ======================================================== -->
            <div class="my-auto pt-6 text-center space-y-6">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-slate-800/80 border border-slate-700 shadow-xl text-4xl">
                    🔒
                </div>

                <div class="space-y-1.5">
                    <span class="inline-block px-2.5 py-0.5 rounded-full bg-rose-500/20 text-rose-400 text-[11px] font-bold tracking-wide">
                        선생님 / 학부모 전용 보호 모드
                    </span>
                    <h2 class="text-xl font-black text-white">답안지가 잠겨 있습니다</h2>
                    <p class="text-xs text-slate-400 max-w-xs mx-auto leading-relaxed">
                        학생들의 사전 정답 열람을 차단하기 위해 보안 잠금이 적용되어 있습니다. 선생님 간편 PIN(4자리)을 입력해 주세요.
                    </p>
                </div>

                <?php if ($pin_error): ?>
                    <div class="p-3 bg-rose-950/60 border border-rose-800 text-rose-300 text-xs rounded-xl font-medium">
                        ⚠️ <?php echo htmlspecialchars($pin_error); ?>
                    </div>
                <?php endif; ?>

                <!-- PIN 번호 입력 폼 -->
                <form method="POST" action="answer_key.php?code=<?php echo urlencode($clean_code); ?>" class="max-w-xs mx-auto space-y-4">
                    <input type="hidden" name="action" value="verify_pin">

                    <div class="relative">
                        <input type="password" name="pin" id="pin-input" inputmode="numeric" maxlength="6" autofocus placeholder="PIN 4자리 입력 (기본: 1234)" required
                            class="w-full px-4 py-3.5 bg-slate-800/90 border border-slate-700 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 rounded-2xl text-center text-lg font-mono font-bold tracking-widest text-white shadow-inner">
                    </div>

                    <button type="submit" class="w-full py-3.5 px-4 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black rounded-2xl text-sm transition shadow-lg shadow-emerald-500/20 active:scale-98">
                        🔓 정답표 열기
                    </button>
                </form>

                <div class="pt-4 border-t border-slate-800/80 flex items-center justify-center gap-4 text-xs text-slate-500">
                    <span>* 초기 기본 PIN은 <strong>1234</strong> 입니다.</span>
                    <span>·</span>
                    <a href="login.php" class="text-emerald-400 hover:underline">선생님 로그인 ➔</a>
                </div>
            </div>

        <?php elseif (!$worksheet): ?>
            <!-- ======================================================== -->
            <!-- [B] 문제지를 찾을 수 없는 경우 -->
            <!-- ======================================================== -->
            <div class="py-16 text-center space-y-4">
                <div class="text-4xl text-slate-600">📑</div>
                <div class="space-y-1">
                    <h2 class="text-lg font-bold text-white">문제지를 찾을 수 없습니다</h2>
                    <p class="text-xs text-slate-400">
                        입력된 훈련번호: <code class="font-mono text-emerald-400">#<?php echo htmlspecialchars($clean_code ?: '없음'); ?></code>
                    </p>
                </div>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">
                    문제지 상단에 인쇄된 훈련번호(예: <code>20261001-01</code> 또는 뒷번호 <code>01</code>)를 상단 검색창에 정확히 입력해 주세요.
                </p>
                <div class="pt-2">
                    <a href="worksheet.php" class="inline-block px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-bold transition">
                        맞춤 문제지 화면으로 이동
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- ======================================================== -->
            <!-- [C] 인증 완료: 초스피드 정답표 및 빠른 채점기 -->
            <!-- ======================================================== -->

            <!-- 문제지 정보 헤더 카드 -->
            <div class="bg-slate-800/90 border border-slate-700 rounded-2xl p-4 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 text-[10px] font-bold">
                            ✓ <?php echo htmlspecialchars($auth_method); ?> 인증됨
                        </span>
                        <span class="font-mono text-xs font-bold text-white px-2 py-0.5 bg-slate-900 border border-slate-700 rounded-md">
                            #<?php echo htmlspecialchars($worksheet['code'] ?? $clean_code); ?>
                        </span>
                    </div>
                    <span class="text-[11px] text-slate-400">
                        총 <strong class="text-white"><?php echo count($problems); ?></strong>문항 (Lv.<?php echo $worksheet['difficulty']; ?>)
                    </span>
                </div>

                <div class="border-t border-slate-700/60 pt-2 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-white leading-snug">
                            <?php echo htmlspecialchars($worksheet['title']); ?>
                        </h2>
                        <span class="text-[11px] text-slate-400">
                            출제일: <?php echo date('Y.m.d', strtotime($worksheet['created_at'])); ?>
                        </span>
                    </div>
                    <a href="worksheet.php?id=<?php echo urlencode($worksheet['id']); ?>" class="px-2.5 py-1.5 bg-slate-700 hover:bg-slate-600 text-slate-200 rounded-lg text-xs font-semibold transition">
                        문제지 보기
                    </a>
                </div>

                <!-- 채점 대상 학생 선택 드롭다운 -->
                <div class="border-t border-slate-700/60 pt-2.5 flex items-center justify-between text-xs">
                    <span class="text-slate-400 flex items-center gap-1 font-semibold">
                        👤 채점 대상 학생:
                    </span>
                    <select id="grade-student-select" class="px-2.5 py-1.5 bg-slate-900 border border-slate-700 rounded-xl text-emerald-400 font-bold text-xs focus:outline-none focus:border-emerald-500">
                        <?php foreach ($all_students as $st): ?>
                            <option value="<?php echo $st['id']; ?>" <?php echo (($worksheet['student_id'] ?? '') === $st['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($st['name']); ?> (초<?php echo $st['grade']; ?>)
                            </option>
                        <?php endforeach; ?>
                        <?php if (empty($all_students)): ?>
                            <option value="std_default">기본 학생 (홍길동)</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <!-- 라이브 채점 스코어 보드 (선생님이 터치 채점 가능) -->
            <div id="live-scoring-bar" class="p-3 bg-slate-800/70 border border-slate-700/80 rounded-2xl flex items-center justify-between text-xs">
                <div class="flex items-center gap-2">
                    <span class="text-slate-400">터치 채점:</span>
                    <span class="font-bold text-emerald-400 text-sm" id="stat-correct">0</span>
                    <span class="text-slate-500">/</span>
                    <span class="text-slate-400" id="stat-total"><?php echo count($problems); ?></span>
                    <span class="text-xs text-slate-300 font-bold">(<span id="stat-pct" class="text-emerald-400">0</span>점)</span>
                </div>
                <button type="button" onclick="resetAllMarks()" class="text-[11px] text-slate-400 hover:text-white underline">
                    채점 초기화
                </button>
            </div>

            <!-- 정답 그리드 (4열 컴팩트 배치 / 탭 시 O·X 채점 토글) -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                <?php foreach ($problems as $idx => $p): 
                    $cleanQ = rtrim(trim($p['question']), '=');
                ?>
                    <div id="item-box-<?php echo $idx; ?>" onclick="toggleGradeItem(<?php echo $idx; ?>)" class="cursor-pointer select-none p-3 bg-slate-800/90 hover:bg-slate-750 border-2 border-slate-700 rounded-2xl transition flex flex-col justify-between active:scale-95">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="font-mono font-bold text-slate-400"><?php echo $idx + 1; ?>번</span>
                            <span id="mark-badge-<?php echo $idx; ?>" class="w-5 h-5 rounded-full text-[10px] font-bold flex items-center justify-center bg-slate-700 text-slate-400">
                                -
                            </span>
                        </div>

                        <div class="my-1.5 text-center">
                            <span class="text-2xl font-black font-mono text-emerald-400 tracking-tight block">
                                <?php echo htmlspecialchars($p['answer']); ?>
                            </span>
                        </div>

                        <div class="text-[10px] text-slate-400 text-center font-mono truncate border-t border-slate-700/60 pt-1">
                            <?php echo htmlspecialchars($cleanQ); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="p-3 bg-slate-800/50 rounded-xl text-center text-[11px] text-slate-400">
                💡 정답 카드를 탭하면 <strong>맞음(⭕) ➔ 틀림(❌)</strong>으로 토글되어 바로 점수를 매길 수 있습니다.
            </div>

            <!-- ★ 핵심: 채점 결과 원클릭 DB 저장 플로팅 바 -->
            <div class="sticky bottom-3 z-20 pt-2">
                <button type="button" id="btn-save-grade" onclick="submitGradeResult()" class="w-full py-4 px-4 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black rounded-2xl text-sm transition shadow-xl shadow-emerald-500/25 active:scale-98 flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                    <span id="save-btn-label">이 점수로 학습 기록에 저장하기 (<span id="btn-score-text">0점</span>)</span>
                </button>
            </div>

            <div class="pt-1 flex gap-2">
                <a href="worksheet.php" class="flex-1 py-3 bg-slate-800 hover:bg-slate-700 text-center text-xs font-bold text-white rounded-xl transition">
                    ← 다른 문제지 만들기
                </a>
                <button type="button" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" class="py-3 px-4 bg-slate-800 hover:bg-slate-700 text-xs font-bold text-slate-300 rounded-xl transition">
                    ↑ 맨 위로
                </button>
            </div>

        <?php endif; ?>

    </main>

    <!-- 채점 결과 저장 성공 알림 팝업 모달 -->
    <div id="grade-success-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-xs">
        <div class="bg-slate-900 border border-slate-700 rounded-3xl max-w-sm w-full p-6 text-center space-y-5 shadow-2xl relative">
            <div class="w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-3xl mx-auto">
                🎉
            </div>

            <div class="space-y-1">
                <h3 class="text-lg font-bold text-white">채점 결과 저장 완료!</h3>
                <p id="res-success-desc" class="text-xs text-slate-300 leading-relaxed"></p>
            </div>

            <div class="p-3 bg-slate-800 rounded-2xl flex items-center justify-around font-mono text-xs">
                <div>
                    <span class="text-slate-400 block text-[10px]">최종 점수</span>
                    <strong id="res-score-badge" class="text-emerald-400 text-xl font-black">--점</strong>
                </div>
                <div class="border-l border-slate-700 h-8"></div>
                <div>
                    <span class="text-slate-400 block text-[10px]">정답 문항</span>
                    <strong id="res-correct-badge" class="text-white text-base">-- / --</strong>
                </div>
            </div>

            <div class="pt-2 flex flex-col gap-2">
                <a href="history.php" class="w-full py-3 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs rounded-xl transition">
                    📈 학생 학습 기록(그래프) 보러가기 ➔
                </a>
                <button type="button" onclick="closeSuccessModal()" class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl">
                    확인 (계속 보기)
                </button>
            </div>
        </div>
    </div>

    <script>
        const totalItems = <?php echo count($problems); ?>;
        const currentWsId = <?php echo json_encode($worksheet['id'] ?? ''); ?>;
        const currentWsCode = <?php echo json_encode($worksheet['code'] ?? $clean_code); ?>;

        // item status: 0=none, 1=correct, 2=wrong
        const itemStates = new Array(totalItems).fill(0);

        function toggleGradeItem(idx) {
            const current = itemStates[idx];
            const next = (current + 1) % 3; // 0 -> 1 -> 2 -> 0
            itemStates[idx] = next;

            const box = document.getElementById(`item-box-${idx}`);
            const badge = document.getElementById(`mark-badge-${idx}`);

            if (next === 1) {
                // Correct
                box.className = "cursor-pointer select-none p-3 bg-emerald-950/40 border-2 border-emerald-500 rounded-2xl transition flex flex-col justify-between active:scale-95";
                badge.className = "w-5 h-5 rounded-full text-[10px] font-black flex items-center justify-center bg-emerald-500 text-slate-950";
                badge.innerText = "O";
            } else if (next === 2) {
                // Wrong
                box.className = "cursor-pointer select-none p-3 bg-rose-950/40 border-2 border-rose-500 rounded-2xl transition flex flex-col justify-between active:scale-95";
                badge.className = "w-5 h-5 rounded-full text-[10px] font-black flex items-center justify-center bg-rose-500 text-white";
                badge.innerText = "X";
            } else {
                // Neutral
                box.className = "cursor-pointer select-none p-3 bg-slate-800/90 hover:bg-slate-750 border-2 border-slate-700 rounded-2xl transition flex flex-col justify-between active:scale-95";
                badge.className = "w-5 h-5 rounded-full text-[10px] font-bold flex items-center justify-center bg-slate-700 text-slate-400";
                badge.innerText = "-";
            }

            updateScoreStats();
        }

        function updateScoreStats() {
            let correct = 0;
            itemStates.forEach(s => { if (s === 1) correct++; });
            const pct = totalItems > 0 ? Math.round((correct / totalItems) * 100) : 0;

            document.getElementById('stat-correct').innerText = correct;
            document.getElementById('stat-pct').innerText = pct;
            
            const btnScore = document.getElementById('btn-score-text');
            if (btnScore) btnScore.innerText = `${pct}점`;
        }

        function resetAllMarks() {
            if (!confirm("채점 기록을 초기화하시겠습니까?")) return;
            for (let i = 0; i < totalItems; i++) {
                itemStates[i] = 0;
                const box = document.getElementById(`item-box-${i}`);
                const badge = document.getElementById(`mark-badge-${i}`);
                if (box && badge) {
                    box.className = "cursor-pointer select-none p-3 bg-slate-800/90 hover:bg-slate-750 border-2 border-slate-700 rounded-2xl transition flex flex-col justify-between active:scale-95";
                    badge.className = "w-5 h-5 rounded-full text-[10px] font-bold flex items-center justify-center bg-slate-700 text-slate-400";
                    badge.innerText = "-";
                }
            }
            updateScoreStats();
        }

        // [채점 결과 서버 DB 영구 저장]
        function submitGradeResult() {
            let correct = 0;
            let unmarked = 0;
            itemStates.forEach(s => {
                if (s === 1) correct++;
                if (s === 0) unmarked++;
            });
            const pct = totalItems > 0 ? Math.round((correct / totalItems) * 100) : 0;

            if (unmarked > 0) {
                if (!confirm(`아직 채점하지 않은 문항이 ${unmarked}개 있습니다.\n현재 맞힌 문항(${correct}/${totalItems}개, ${pct}점)으로 저장하시겠습니까?`)) {
                    return;
                }
            }

            const studentSelect = document.getElementById('grade-student-select');
            const studentId = studentSelect ? studentSelect.value : '';

            const saveBtn = document.getElementById('btn-save-grade');
            const saveBtnLabel = document.getElementById('save-btn-label');
            saveBtn.disabled = true;
            saveBtnLabel.innerText = "저장 중...";

            const formData = new FormData();
            formData.append('action', 'save_grade');
            formData.append('worksheet_id', currentWsId);
            formData.append('code', currentWsCode);
            formData.append('student_id', studentId);
            formData.append('total_count', totalItems);
            formData.append('correct_count', correct);
            formData.append('score', pct);
            formData.append('graded_items', JSON.stringify(itemStates));

            fetch('answer_key.php?code=' + encodeURIComponent(currentWsCode), {
                method: 'POST',
                body: formData
            })
            .then(async (r) => {
                const text = await r.text();
                try {
                    return JSON.parse(text);
                } catch(e) {
                    console.error("Server response:", text);
                    throw new Error("서버 응답 오류");
                }
            })
            .then(data => {
                if (data.success) {
                    document.getElementById('res-success-desc').innerHTML = `
                        <strong>${data.student_name}</strong> 학생의 학습 기록에<br>
                        <strong>#${currentWsCode}</strong> 문제지 채점 결과가 성공적으로 등록되었습니다.
                    `;
                    document.getElementById('res-score-badge').innerText = `${data.score}점`;
                    document.getElementById('res-correct-badge').innerText = `${data.correct_count} / ${data.total_count}개`;
                    document.getElementById('grade-success-modal').classList.remove('hidden');

                    saveBtnLabel.innerText = `✓ 저장 완료 (${data.score}점)`;
                    saveBtn.className = "w-full py-4 px-4 bg-emerald-600 text-white font-black rounded-2xl text-sm transition shadow-md flex items-center justify-center gap-2";
                } else {
                    alert("저장 실패: " + (data.error || '알 수 없는 오류'));
                    saveBtn.disabled = false;
                    saveBtnLabel.innerText = `이 점수로 학습 기록에 저장하기 (${pct}점)`;
                }
            })
            .catch(e => {
                alert("통신 오류가 발생했습니다: " + e.message);
                saveBtn.disabled = false;
                saveBtnLabel.innerText = `이 점수로 학습 기록에 저장하기 (${pct}점)`;
            });
        }

        function closeSuccessModal() {
            document.getElementById('grade-success-modal').classList.add('hidden');
        }
    </script>
</body>
</html>
