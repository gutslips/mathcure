<?php
/**
 * MathCure - 학생 전용 온라인 시험 응시 화면 (Cafe24 PHP)
 * 선생님 관리 화면이나 상단 메뉴가 일체 노출되지 않는 독립형 시험장입니다.
 */

require_once __DIR__ . '/db.php';
$pdo = get_db();
$t_exams = table('exams');

$code = trim($_GET['code'] ?? '');

// [1] 답안 제출 처리 (POST AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit') {
    header('Content-Type: application/json; charset=utf-8');
    $exam_code = trim($_POST['code'] ?? '');
    $student_name = trim($_POST['student_name'] ?? '');
    $raw_answers = json_decode($_POST['answers'] ?? '[]', true) ?: [];

    if (empty($exam_code)) {
        echo json_encode(['success' => false, 'error' => '시험 코드가 누락되었습니다.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM `{$t_exams}` WHERE `code` = ?");
        $stmt->execute([$exam_code]);
        $exam = $stmt->fetch();

        if (!$exam) {
            echo json_encode(['success' => false, 'error' => '유효하지 않은 시험입니다.']);
            exit;
        }

        $original_problems = json_decode($exam['problems'], true) ?: [];
        $correct_count = 0;
        $evaluated = [];

        foreach ($original_problems as $idx => $prob) {
            $user_ans_val = null;
            foreach ($raw_answers as $ans) {
                if (($ans['problemId'] ?? null) === ($prob['id'] ?? null) || ($ans['index'] ?? null) === $idx) {
                    if (isset($ans['userAnswer']) && $ans['userAnswer'] !== '' && $ans['userAnswer'] !== null) {
                        $user_ans_val = (int)$ans['userAnswer'];
                    }
                    break;
                }
            }

            $is_correct = ($user_ans_val !== null && $user_ans_val === (int)$prob['answer']);
            if ($is_correct) $correct_count++;

            $evaluated[] = [
                'id' => $prob['id'] ?? ('p_' . $idx),
                'index' => $idx + 1,
                'question' => $prob['question'],
                'correctAnswer' => (int)$prob['answer'],
                'userAnswer' => $user_ans_val,
                'isCorrect' => $is_correct,
            ];
        }

        $total_count = count($original_problems);
        $score = $total_count > 0 ? (int)round(($correct_count / $total_count) * 100) : 0;
        $final_name = $student_name ?: ($exam['student_name'] ?: '학생');

        $update = $pdo->prepare("
            UPDATE `{$t_exams}`
            SET `student_name` = ?, `status` = 'completed', `score` = ?, `results` = ?, `submitted_at` = NOW()
            WHERE `code` = ?
        ");
        $update->execute([$final_name, $score, json_encode($evaluated), $exam_code]);

        echo json_encode([
            'success' => true,
            'score' => $score,
            'totalCount' => $total_count,
            'correctCount' => $correct_count,
            'showResult' => (bool)$exam['show_result'],
            'evaluatedAnswers' => (bool)$exam['show_result'] ? $evaluated : []
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// [2] 시험 코드 미입력 시: 6자리 코드 입력 허브
if (empty($code)) {
?>
<!DOCTYPE html>
<html lang="ko" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>초5 연산 온라인 시험 입장</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-full flex items-center justify-center p-4 text-white">
    <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl p-8 shadow-2xl text-center space-y-6">
        <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center mx-auto text-2xl">
            🔑
        </div>
        <div>
            <h1 class="text-2xl font-black text-white">초5 연산 온라인 시험 입장</h1>
            <p class="text-xs text-slate-400 mt-1.5">선생님께 전달받은 6자리 시험 코드를 입력하세요.</p>
        </div>
        <form method="GET" action="exam.php" class="space-y-4">
            <input type="text" name="code" inputmode="numeric" maxlength="8" required autofocus placeholder="예: 839102"
                class="w-full text-center text-3xl font-mono font-black tracking-widest py-4 px-6 rounded-2xl bg-slate-950 border-2 border-slate-700 text-emerald-400 focus:outline-none focus:border-emerald-500 transition">
            <button type="submit" class="w-full py-4 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-base rounded-2xl shadow-lg transition">
                시험장 입장하기 ➔
            </button>
        </form>
        <div class="text-[11px] text-slate-500 pt-2 border-t border-slate-800">
            태블릿 · 스마트폰 · PC 전 기기 무로그인 자율 응시
        </div>
    </div>
</body>
</html>
<?php
    exit;
}

// [3] 시험 정보 조회
$exam = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM `{$t_exams}` WHERE `code` = ?");
    $stmt->execute([$code]);
    $exam = $stmt->fetch();
} catch (Exception $e) {}

if (!$exam) {
    die("<!DOCTYPE html><html lang='ko' class='bg-slate-950 text-white'><head><meta charset='UTF-8'><title>오류</title><script src='https://cdn.tailwindcss.com'></script></head><body class='p-12 text-center'><h2 class='text-xl font-bold'>시험을 찾을 수 없습니다.</h2><p class='text-slate-400 text-xs mt-2'>코드를 다시 확인해 주세요.</p><a href='exam.php' class='mt-4 inline-block px-4 py-2 bg-slate-800 rounded-xl text-xs font-bold'>코드 입력으로 돌아가기</a></body></html>");
}

// 시험 링크 유효기간 만료 체크
$is_expired = (!empty($exam['expires_at']) && strtotime($exam['expires_at']) < time() && $exam['status'] !== 'completed');
if ($is_expired) {
    die("<!DOCTYPE html><html lang='ko' class='bg-slate-950 text-white'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>시험 만료 안내</title><script src='https://cdn.tailwindcss.com'></script></head><body class='min-h-screen flex items-center justify-center p-4 text-center'><div class='max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl p-8 space-y-4 shadow-2xl'><div class='w-14 h-14 rounded-2xl bg-amber-500/10 text-amber-400 flex items-center justify-center text-3xl mx-auto'>⏰</div><h2 class='text-xl font-bold'>시험 링크 유효기간 만료</h2><p class='text-slate-400 text-xs leading-relaxed'>시험 응시 유효기간이 지났습니다.<br>선생님께 새로운 시험 링크 또는 유효기간 연장을 요청해 주세요.</p><a href='exam.php' class='inline-block mt-4 px-6 py-2.5 bg-slate-800 hover:bg-slate-700 rounded-xl text-xs font-bold transition'>코드 입력으로 돌아가기</a></div></body></html>");
}

$problems = json_decode($exam['problems'], true) ?: [];
$time_limit_sec = (int)$exam['time_limit_sec'];
$total_count = count($problems);
?>
<!DOCTYPE html>
<html lang="ko" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo htmlspecialchars($exam['title']); ?> - 온라인 시험</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .no-select { -webkit-touch-callout: none; -webkit-user-select: none; user-select: none; }
    </style>
</head>
<body class="min-h-full bg-slate-950 text-white flex flex-col justify-between no-select">

    <!-- [STEP 1: 시험 대기실] -->
    <div id="step-intro" class="min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl p-8 shadow-2xl space-y-6 text-center">
            <span class="inline-block px-3 py-1 bg-emerald-500/10 text-emerald-400 font-mono text-xs font-bold rounded-full">
                시험 코드: <?php echo htmlspecialchars($exam['code']); ?>
            </span>
            <h1 class="text-2xl sm:text-3xl font-black text-white">
                <?php echo htmlspecialchars($exam['title']); ?>
            </h1>

            <div class="grid grid-cols-2 gap-3 p-4 bg-slate-800/80 rounded-2xl border border-slate-700/60 text-center">
                <div>
                    <div class="text-[11px] text-slate-400 mb-0.5">총 문항 수</div>
                    <div class="text-xl font-mono font-bold text-white"><?php echo $total_count; ?>문항</div>
                </div>
                <div>
                    <div class="text-[11px] text-slate-400 mb-0.5">제한 시간</div>
                    <div class="text-xl font-mono font-bold text-emerald-400">
                        <?php echo $time_limit_sec > 0 ? ($time_limit_sec / 60) . '분' : '무제한'; ?>
                    </div>
                </div>
            </div>

            <div class="text-left space-y-2">
                <label class="block text-xs font-semibold text-slate-300">응시 학생 이름</label>
                <input type="text" id="student-name-input" value="<?php echo htmlspecialchars($exam['student_name'] ?? ''); ?>" placeholder="이름을 입력하세요 (예: 김민수)"
                    class="w-full px-4 py-3 bg-slate-800 border-2 border-slate-700 rounded-2xl text-white font-medium focus:outline-none focus:border-emerald-500 text-sm">
            </div>

            <div class="p-4 bg-amber-500/10 border border-amber-500/20 rounded-2xl text-xs text-amber-300 text-left space-y-1">
                <div class="font-bold">⚠️ 응시 전 주의사항</div>
                <div>• [시험 시작하기]를 누르면 타이머가 즉시 시작됩니다.</div>
                <div>• 시간 초과 시 지금까지 푼 답안이 자동 제출됩니다.</div>
            </div>

            <button type="button" onclick="startExam()" class="w-full py-4 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-base rounded-2xl shadow-lg transition">
                시험 시작하기 ➔
            </button>
        </div>
    </div>

    <!-- [STEP 2: 시험 진행 화면] -->
    <div id="step-testing" class="hidden min-h-screen flex flex-col justify-between pb-10">
        <!-- 상단 고정 헤더 -->
        <header class="sticky top-0 z-40 bg-slate-900/95 backdrop-blur border-b border-slate-800 px-4 py-3">
            <div class="max-w-2xl mx-auto flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span id="display-student-name" class="font-bold text-sm text-slate-200">학생</span>
                </div>

                <div id="timer-box" class="px-3 py-1.5 rounded-xl font-mono font-black text-sm tracking-wider bg-slate-800 text-emerald-400 border border-slate-700">
                    --:--
                </div>

                <button onclick="confirmSubmitModal(true)" class="px-3.5 py-1.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs rounded-xl shadow-sm transition">
                    제출하기
                </button>
            </div>
            <div class="max-w-2xl mx-auto mt-2.5">
                <div class="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                    <div id="progress-bar" class="bg-emerald-500 h-full transition-all duration-300" style="width: 5%;"></div>
                </div>
            </div>
        </header>

        <!-- 중앙 문제 카드 & 키패드 -->
        <main class="flex-1 max-w-lg w-full mx-auto px-4 py-6 flex flex-col justify-between">
            <div class="space-y-6">
                <div class="flex items-center justify-between text-xs text-slate-400">
                    <span id="problem-num-indicator" class="font-bold font-mono text-emerald-400 text-sm">Q1 / <?php echo $total_count; ?></span>
                    <span id="answered-indicator">응답: 0 / <?php echo $total_count; ?></span>
                </div>

                <!-- 문제 카드 -->
                <div class="bg-slate-900 border-2 border-slate-800 rounded-3xl p-8 shadow-xl text-center space-y-5">
                    <div id="q-text" class="text-3xl sm:text-5xl font-mono font-black text-white tracking-tight">
                        -- ÷ -- =
                    </div>

                    <div class="max-w-xs mx-auto">
                        <div id="q-answer-display" class="w-full h-16 rounded-2xl bg-slate-950 border-2 border-emerald-500/40 shadow-inner flex items-center justify-center text-3xl font-mono font-black text-emerald-400 tracking-wider">
                            <span class="text-slate-600 text-lg font-normal">답 입력</span>
                            <span class="inline-block w-0.5 h-6 bg-slate-600 ml-1 animate-pulse"></span>
                        </div>
                    </div>

                    <!-- PC 키보드 & 터치 입력 안내 -->
                    <div class="flex items-center justify-center">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-800/80 rounded-full border border-slate-700/80 text-[11px] text-slate-300">
                            ⌨️ <strong class="text-emerald-400 font-semibold">PC 키보드 지원:</strong> 숫자(0~9) · Enter(다음) · Backspace(지우기)
                        </span>
                    </div>
                </div>

                <!-- 가상 키패드 토글 바 (PC / 태블릿 선택) -->
                <div class="flex items-center justify-between max-w-xs mx-auto text-xs px-1">
                    <span class="text-slate-400 font-medium">화면 터치 키패드</span>
                    <button type="button" onclick="toggleKeypad()" id="btn-toggle-keypad" class="text-emerald-400 hover:text-emerald-300 font-semibold flex items-center gap-1 transition">
                        키패드 접기 ▲
                    </button>
                </div>

                <!-- 가상 숫자 키패드 -->
                <div id="virtual-keypad" class="bg-slate-900/80 border border-slate-800 rounded-3xl p-3 max-w-xs mx-auto grid grid-cols-3 gap-2 transition-all duration-200" style="touch-action: manipulation;">
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                        <button type="button" onclick="pressDigit('<?php echo $i; ?>')" class="py-3.5 bg-slate-800 hover:bg-slate-700 text-white font-mono font-bold text-xl rounded-2xl transition active:scale-95 shadow-sm">
                            <?php echo $i; ?>
                        </button>
                    <?php endfor; ?>
                    <button type="button" onclick="backspaceAnswer()" class="py-3.5 bg-slate-800/60 hover:bg-slate-800 text-slate-400 font-semibold text-xs rounded-2xl transition active:scale-95 flex items-center justify-center gap-1">
                        ⌫ 지우기
                    </button>
                    <button type="button" onclick="pressDigit('0')" class="py-3.5 bg-slate-800 hover:bg-slate-700 text-white font-mono font-bold text-xl rounded-2xl transition active:scale-95 shadow-sm">
                        0
                    </button>
                    <button type="button" onclick="nextOrSubmit()" class="py-3.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-sm rounded-2xl transition active:scale-95 shadow-sm">
                        다음 ➔
                    </button>
                </div>

                <!-- 이전/다음 네비게이션 -->
                <div class="flex items-center justify-between max-w-xs mx-auto pt-1">
                    <button type="button" onclick="prevProblem()" id="btn-prev" class="px-4 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs font-semibold text-slate-300 disabled:opacity-30">
                        ◀ 이전 문제
                    </button>
                    <button type="button" onclick="nextProblem()" id="btn-next" class="px-4 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs font-semibold text-slate-300 disabled:opacity-30">
                        다음 문제 ▶
                    </button>
                </div>
            </div>

            <!-- 하단 문항 번호 점 -->
            <div class="mt-8 pt-4 border-t border-slate-800/80">
                <div class="flex flex-wrap justify-center gap-1.5 max-w-sm mx-auto" id="dots-container"></div>
            </div>
        </main>
    </div>

    <!-- [STEP 3: 제출 완료 화면] -->
    <div id="step-submitted" class="hidden min-h-screen flex items-center justify-center p-4 py-12">
        <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl p-8 shadow-2xl text-center space-y-6">
            <div class="w-16 h-16 rounded-3xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center mx-auto text-3xl">
                🏆
            </div>
            <div>
                <span class="px-3 py-1 bg-emerald-500/20 text-emerald-400 text-xs font-bold rounded-full">시험 완료</span>
                <h2 class="text-2xl font-black text-white mt-2">수고하셨습니다!</h2>
                <p class="text-xs text-slate-400 mt-1">시험 결과가 선생님 대시보드로 전송되었습니다.</p>
            </div>

            <div id="result-score-box" class="p-6 bg-slate-800/90 rounded-2xl border border-slate-700/60 text-center">
                <div class="text-xs text-slate-400 mb-1">시험 점수</div>
                <div id="final-score" class="text-5xl font-mono font-black text-emerald-400">--점</div>
                <div id="final-detail" class="text-xs text-slate-400 mt-2"></div>
            </div>

            <div id="evaluated-list" class="text-left space-y-2 max-h-60 overflow-y-auto pr-1"></div>

            <div class="pt-4 border-t border-slate-800 text-[11px] text-slate-500">
                시험창을 닫으셔도 좋습니다.
            </div>
        </div>
    </div>

    <!-- 미응답 확인 모달 -->
    <div id="confirm-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-xs">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-sm w-full p-6 text-center space-y-4 shadow-2xl">
            <h3 class="text-lg font-bold text-white">시험지를 제출하시겠습니까?</h3>
            <div id="confirm-stats" class="p-3 bg-slate-800/80 rounded-xl text-xs text-slate-300"></div>
            <div class="flex gap-2 pt-2">
                <button onclick="confirmSubmitModal(false)" class="flex-1 py-3 bg-slate-800 text-slate-300 font-bold text-xs rounded-xl">더 풀기</button>
                <button onclick="submitExamFinal()" class="flex-1 py-3 bg-emerald-500 text-slate-950 font-black text-xs rounded-xl">제출하기</button>
            </div>
        </div>
    </div>

<script>
const problems = <?php echo json_encode($problems); ?>;
const timeLimitSec = <?php echo $time_limit_sec; ?>;
const examCode = "<?php echo htmlspecialchars($exam['code']); ?>";

let currentIndex = 0;
let answers = {};
let remainingSec = timeLimitSec > 0 ? timeLimitSec : null;
let timerInterval = null;
let studentName = "";

function startExam() {
    studentName = document.getElementById('student-name-input').value.trim();
    if (!studentName) {
        alert("이름을 입력해 주세요.");
        return;
    }
    document.getElementById('display-student-name').innerText = studentName + " 학생";
    document.getElementById('step-intro').classList.add('hidden');
    document.getElementById('step-testing').classList.remove('hidden');

    renderProblem(0);
    renderDots();

    if (remainingSec !== null) {
        updateTimerDisplay();
        timerInterval = setInterval(() => {
            remainingSec--;
            updateTimerDisplay();
            if (remainingSec <= 0) {
                clearInterval(timerInterval);
                alert("시험 시간이 종료되어 자동 제출됩니다!");
                submitExamFinal();
            }
        }, 1000);
    } else {
        document.getElementById('timer-box').innerText = "자율 풀이";
    }
}

function updateTimerDisplay() {
    if (remainingSec === null) return;
    const m = Math.floor(remainingSec / 60);
    const s = remainingSec % 60;
    const box = document.getElementById('timer-box');
    box.innerText = String(m).padStart(2, '0') + ":" + String(s).padStart(2, '0');
    if (remainingSec <= 60) {
        box.classList.add('text-rose-400', 'bg-rose-500/20');
        box.classList.remove('text-emerald-400', 'bg-slate-800');
    }
}

let keypadVisible = true;
function toggleKeypad() {
    keypadVisible = !keypadVisible;
    const keypad = document.getElementById('virtual-keypad');
    const btn = document.getElementById('btn-toggle-keypad');
    if (keypadVisible) {
        keypad.classList.remove('hidden');
        btn.innerText = "키패드 접기 ▲";
    } else {
        keypad.classList.add('hidden');
        btn.innerText = "키패드 펼치기 ▼";
    }
}

function renderProblem(index) {
    currentIndex = index;
    const p = problems[index];
    const cleanQ = p.question.trim().replace(/\s*=\s*$/, '');
    document.getElementById('q-text').innerText = cleanQ + " =";
    const disp = document.getElementById('q-answer-display');
    if (answers[index]) {
        disp.innerHTML = "<span class='font-mono'>" + answers[index] + "</span><span class='inline-block w-0.5 h-7 bg-emerald-400 ml-1 animate-pulse'></span>";
    } else {
        disp.innerHTML = "<span class='text-slate-600 text-lg font-normal'>답 입력</span><span class='inline-block w-0.5 h-6 bg-slate-600 ml-1 animate-pulse'></span>";
    }

    document.getElementById('problem-num-indicator').innerText = "Q" + (index + 1) + " / " + problems.length;
    document.getElementById('progress-bar').style.width = (((index + 1) / problems.length) * 100) + "%";

    document.getElementById('btn-prev').disabled = (index === 0);
    document.getElementById('btn-next').disabled = (index === problems.length - 1);

    updateAnsweredCount();
    renderDots();
}

function pressDigit(num) {
    let cur = answers[currentIndex] || "";
    if (cur.length >= 6) return;
    answers[currentIndex] = cur + num;
    renderProblem(currentIndex);
}

function backspaceAnswer() {
    let cur = answers[currentIndex] || "";
    if (cur.length > 0) {
        const nextVal = cur.slice(0, -1);
        if (nextVal === "") {
            delete answers[currentIndex];
        } else {
            answers[currentIndex] = nextVal;
        }
        renderProblem(currentIndex);
    }
}

function clearAnswer() {
    delete answers[currentIndex];
    renderProblem(currentIndex);
}

function nextProblem() {
    if (currentIndex < problems.length - 1) {
        renderProblem(currentIndex + 1);
    }
}

function prevProblem() {
    if (currentIndex > 0) {
        renderProblem(currentIndex - 1);
    }
}

function nextOrSubmit() {
    if (currentIndex < problems.length - 1) {
        nextProblem();
    } else {
        confirmSubmitModal(true);
    }
}

// PC 물리 키보드 완벽 연동
document.addEventListener('keydown', function(e) {
    // 시험 진행 화면이 아닐 경우(대기실 or 제출완료) 무시
    const testStep = document.getElementById('step-testing');
    if (!testStep || testStep.classList.contains('hidden')) return;

    // 제출 확인 모달이 열려있는 경우
    const modal = document.getElementById('confirm-modal');
    if (modal && !modal.classList.contains('hidden')) {
        if (e.key === 'Escape') {
            confirmSubmitModal(false);
            e.preventDefault();
        } else if (e.key === 'Enter') {
            submitExamFinal();
            e.preventDefault();
        }
        return;
    }

    // 숫자 키 (상단 숫자열 및 우측 텐키패드)
    if (e.key >= '0' && e.key <= '9') {
        e.preventDefault();
        pressDigit(e.key);
    } else if (e.key === 'Backspace') {
        e.preventDefault();
        backspaceAnswer();
    } else if (e.key === 'Delete') {
        e.preventDefault();
        clearAnswer();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        nextOrSubmit();
    } else if (e.key === 'ArrowLeft') {
        e.preventDefault();
        prevProblem();
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        nextProblem();
    }
});

function updateAnsweredCount() {
    const answered = Object.keys(answers).length;
    document.getElementById('answered-indicator').innerText = "응답: " + answered + " / " + problems.length;
}

function renderDots() {
    const container = document.getElementById('dots-container');
    container.innerHTML = "";
    problems.forEach((_, idx) => {
        const btn = document.createElement('button');
        btn.innerText = idx + 1;
        btn.className = "w-7 h-7 rounded-lg font-mono text-xs font-bold transition ";
        if (idx === currentIndex) {
            btn.className += "bg-white text-slate-950 ring-2 ring-emerald-400";
        } else if (answers[idx]) {
            btn.className += "bg-emerald-500/20 text-emerald-400 border border-emerald-500/40";
        } else {
            btn.className += "bg-slate-800 text-slate-400";
        }
        btn.onclick = () => renderProblem(idx);
        container.appendChild(btn);
    });
}

function confirmSubmitModal(show) {
    const modal = document.getElementById('confirm-modal');
    if (show) {
        const answered = Object.keys(answers).length;
        document.getElementById('confirm-stats').innerHTML = 
            "총 <strong>" + problems.length + "문제</strong> 중 <strong class='text-emerald-400'>" + answered + "문제</strong> 작성 완료" +
            (answered < problems.length ? "<br><span class='text-rose-400 font-bold'>⚠️ 안 푼 문제가 " + (problems.length - answered) + "개 있습니다!</span>" : "");
        modal.classList.remove('hidden');
    } else {
        modal.classList.add('hidden');
    }
}

function submitExamFinal() {
    confirmSubmitModal(false);
    if (timerInterval) clearInterval(timerInterval);

    const formatted = problems.map((p, idx) => ({
        problemId: p.id,
        index: idx,
        userAnswer: answers[idx] !== undefined ? answers[idx] : null
    }));

    const formData = new FormData();
    formData.append('action', 'submit');
    formData.append('code', examCode);
    formData.append('student_name', studentName);
    formData.append('answers', JSON.stringify(formatted));

    fetch('exam.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                document.getElementById('step-testing').classList.add('hidden');
                document.getElementById('step-submitted').classList.remove('hidden');
                if (res.showResult) {
                    document.getElementById('final-score').innerText = res.score + "점";
                    document.getElementById('final-detail').innerText = "총 " + res.totalCount + "문항 중 " + res.correctCount + "문항 정답";

                    const list = document.getElementById('evaluated-list');
                    list.innerHTML = "";
                    res.evaluatedAnswers.forEach(item => {
                        const div = document.createElement('div');
                        div.className = "p-3 rounded-xl border flex items-center justify-between text-xs " + 
                            (item.isCorrect ? "bg-emerald-500/10 border-emerald-500/30 text-emerald-300" : "bg-rose-500/10 border-rose-500/30 text-rose-300");
                        div.innerHTML = "<div class='font-mono'><strong>" + item.index + "번.</strong> " + item.question + " = <strong class='text-white'>" + (item.userAnswer !== null ? item.userAnswer : "미입력") + "</strong></div>" +
                            "<div class='font-bold'>" + (item.isCorrect ? "✓ 정답" : "✕ 정답: " + item.correctAnswer) + "</div>";
                        list.appendChild(div);
                    });
                } else {
                    document.getElementById('result-score-box').classList.add('hidden');
                }
            } else {
                alert("제출 실패: " + (res.error || '알 수 없는 오류'));
            }
        });
}
</script>
</body>
</html>
