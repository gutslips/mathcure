<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/lib/generators.php';

$type = $_GET['type'] ?? 'divide5';
$difficulty = (int)($_GET['difficulty'] ?? 2);
$count = (int)($_GET['count'] ?? 10);
$worksheet_id = $_GET['worksheet_id'] ?? null;
$worksheet_title = null;

$problems = [];
if ($worksheet_id) {
    try {
        $t_ws = table('worksheets');
        $stmt = $pdo->prepare("SELECT * FROM `{$t_ws}` WHERE `id` = ?");
        $stmt->execute([$worksheet_id]);
        $ws_data = $stmt->fetch();
        if ($ws_data) {
            $worksheet_title = $ws_data['title'];
            $type = $ws_data['problem_type'] ?? $type;
            $difficulty = (int)($ws_data['difficulty'] ?? 2);
            $count = (int)($ws_data['count'] ?? 20);
            $seed = $ws_data['seed'] ?? 'ws_default';

            if (!empty($ws_data['problems'])) {
                $decoded = json_decode($ws_data['problems'], true);
                if (is_array($decoded) && count($decoded) > 0) {
                    $problems = $decoded;
                }
            }

            // 이전에 저장되어 problems가 비어있던 레코드인 경우 seed 기반으로 생성 후 영구 보관
            if (empty($problems)) {
                $problems = generate_worksheet_problems($type, $count, $difficulty, $seed);
                try {
                    $up = $pdo->prepare("UPDATE `{$t_ws}` SET `problems` = ? WHERE `id` = ?");
                    $up->execute([json_encode($problems), $worksheet_id]);
                } catch (Exception $e) {}
            }
        }
    } catch (Exception $e) {}
}

if (empty($problems)) {
    $problems = generate_problems_by_type($type, $count, $difficulty);
}
?>

<div class="max-w-2xl mx-auto space-y-6">
    <?php if ($worksheet_title): ?>
        <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex items-center justify-between text-xs text-emerald-950 shadow-xs">
            <div class="flex items-center gap-2.5">
                <span class="font-bold px-2.5 py-1 bg-emerald-600 text-white rounded-lg">보관된 문제집 풀이 중</span>
                <span class="font-bold text-sm"><?php echo htmlspecialchars($worksheet_title); ?></span>
                <span class="text-emerald-700">(<?php echo count($problems); ?>문제 그대로 풀이)</span>
            </div>
            <a href="worksheet.php" class="text-xs text-emerald-800 hover:text-emerald-950 font-semibold underline underline-offset-2">
                보관함 목록 ➔
            </a>
        </div>
    <?php endif; ?>

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900">
                <?php echo PROBLEM_TYPE_LABELS[$type] ?? $type; ?> 온라인 연습
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">
                학생: <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($active_student['name']); ?></span> · 실시간 인터랙티브 풀이
            </p>
        </div>

        <div class="flex items-center gap-2">
            <span id="p-counter" class="text-xs font-bold text-slate-500 bg-white border border-slate-200 px-3 py-1.5 rounded-lg">
                1 / <?php echo count($problems); ?>
            </span>
        </div>
    </div>

    <!-- 연습 문제 카드 -->
    <div id="practice-card" class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-10 text-center space-y-5 shadow-sm">
        <div id="p-question" class="text-3xl sm:text-5xl font-mono font-bold text-slate-900 tracking-tight py-2 sm:py-4">
            -- ÷ -- =
        </div>

        <div class="max-w-xs mx-auto space-y-3">
            <div id="p-answer-display" class="w-full h-16 rounded-2xl bg-slate-50 border-2 border-slate-300 flex items-center justify-center text-3xl font-mono font-black text-slate-900 tracking-wider">
                <span class="text-slate-400 text-lg font-normal">답 입력</span>
                <span class="inline-block w-0.5 h-6 bg-slate-400 ml-1 animate-pulse"></span>
            </div>

            <!-- PC 키보드 & 터치 입력 안내 -->
            <div class="flex items-center justify-center">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 rounded-full border border-slate-200 text-[11px] text-slate-600">
                    ⌨️ <strong class="text-slate-900 font-semibold">PC 키보드:</strong> 숫자(0~9) · Enter(확인/다음) · Backspace(지우기)
                </span>
            </div>

            <div class="pt-1 flex gap-2">
                <button id="submit-btn" onclick="checkPracticeAnswer()" class="flex-1 py-3 px-4 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-sm transition active:scale-98">
                    정답 확인 (Enter)
                </button>
                <button id="next-btn" onclick="nextPracticeProblem()" class="hidden flex-1 py-3 px-4 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-sm transition active:scale-98 shadow-sm">
                    다음 문제 ➔ (Enter)
                </button>
            </div>
        </div>

        <!-- 피드백 메시지 박스 -->
        <div id="feedback-box" class="hidden p-4 rounded-xl text-sm font-semibold"></div>

        <!-- 가상 키패드 토글 바 -->
        <div class="flex items-center justify-between max-w-xs mx-auto text-xs px-1 pt-1">
            <span class="text-slate-500 font-medium">화면 터치 키패드</span>
            <button type="button" onclick="togglePracticeKeypad()" id="btn-toggle-keypad" class="text-slate-700 hover:text-slate-900 font-semibold flex items-center gap-1 transition">
                키패드 접기 ▲
            </button>
        </div>

        <!-- 모바일 가상 키패드 -->
        <div id="practice-keypad" class="bg-slate-50 border border-slate-200 rounded-2xl p-3 max-w-xs mx-auto grid grid-cols-3 gap-2 select-none transition-all duration-200" style="touch-action: manipulation;">
            <?php for ($i = 1; $i <= 9; $i++): ?>
                <button type="button" onclick="appendPracticeDigit('<?php echo $i; ?>')" class="py-3 bg-white hover:bg-slate-100 active:bg-slate-200 text-slate-900 font-bold rounded-xl text-lg transition active:scale-95 shadow-2xs">
                    <?php echo $i; ?>
                </button>
            <?php endfor; ?>
            <button type="button" onclick="backspacePracticeDigit()" class="py-3 bg-white hover:bg-slate-100 active:bg-slate-200 text-slate-500 font-semibold rounded-xl text-xs transition active:scale-95 shadow-2xs">
                ⌫ 지우기
            </button>
            <button type="button" onclick="appendPracticeDigit('0')" class="py-3 bg-white hover:bg-slate-100 active:bg-slate-200 text-slate-900 font-bold rounded-xl text-lg transition active:scale-95 shadow-2xs">
                0
            </button>
            <button type="button" onclick="onKeypadAction()" id="btn-keypad-action" class="py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs transition active:scale-95 shadow-xs">
                확인 ↵
            </button>
        </div>
    </div>

    <!-- 결과 화면 (모든 문제 종료 시 표시) -->
    <div id="result-card" class="hidden bg-white border border-slate-200 rounded-2xl p-8 text-center space-y-6 shadow-sm">
        <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-700 inline-flex items-center justify-center mx-auto text-2xl font-bold">
            ✓
        </div>
        <div>
            <h2 class="text-2xl font-bold text-slate-900">연습 완료!</h2>
            <p class="text-slate-500 text-xs mt-1">풀이 기록이 학생 프로필에 안전하게 저장되었습니다.</p>
        </div>

        <div class="grid grid-cols-3 gap-3 max-w-sm mx-auto">
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <div class="text-[11px] text-slate-500 font-semibold">정답률</div>
                <div id="res-accuracy" class="text-xl font-black text-slate-900 mt-0.5">0%</div>
            </div>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <div class="text-[11px] text-slate-500 font-semibold">맞힌 개수</div>
                <div id="res-score" class="text-xl font-black text-slate-900 mt-0.5">0 / 0</div>
            </div>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <div class="text-[11px] text-slate-500 font-semibold">평균 시간</div>
                <div id="res-time" class="text-xl font-black text-slate-900 mt-0.5">0초</div>
            </div>
        </div>

        <div class="flex justify-center gap-3 pt-2">
            <a href="practice.php?type=<?php echo $type; ?>" class="py-2.5 px-5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-sm transition">
                한 번 더 풀기
            </a>
            <a href="index.php" class="py-2.5 px-5 border border-slate-300 text-slate-700 font-semibold rounded-xl text-sm hover:bg-slate-50 transition">
                대시보드로 가기
            </a>
        </div>
    </div>
</div>

<script>
const problems = <?php echo json_encode($problems); ?>;
let currentIndex = 0;
let correctCount = 0;
let totalElapsedMs = 0;
let problemStartTime = Date.now();
let isAnswerChecked = false;
let sessionStartTime = new Date();
let currentAnswerVal = "";
let keypadVisible = true;

function togglePracticeKeypad() {
    keypadVisible = !keypadVisible;
    const kp = document.getElementById('practice-keypad');
    const btn = document.getElementById('btn-toggle-keypad');
    if (keypadVisible) {
        kp.classList.remove('hidden');
        btn.innerText = "키패드 접기 ▲";
    } else {
        kp.classList.add('hidden');
        btn.innerText = "키패드 펼치기 ▼";
    }
}

function updateAnswerDisplay() {
    const disp = document.getElementById('p-answer-display');
    if (!disp) return;
    if (currentAnswerVal.length > 0) {
        disp.innerHTML = `<span class="font-mono">${currentAnswerVal}</span><span class="inline-block w-0.5 h-6 bg-slate-900 ml-1 animate-pulse"></span>`;
    } else {
        disp.innerHTML = `<span class="text-slate-400 text-lg font-normal">답 입력</span><span class="inline-block w-0.5 h-6 bg-slate-400 ml-1 animate-pulse"></span>`;
    }
}

function loadPractice(index) {
    currentIndex = index;
    isAnswerChecked = false;
    currentAnswerVal = "";
    const p = problems[index];

    document.getElementById('p-counter').innerText = `${index + 1} / ${problems.length}`;
    document.getElementById('p-question').innerText = p.question;

    updateAnswerDisplay();

    document.getElementById('submit-btn').classList.remove('hidden');
    document.getElementById('next-btn').classList.add('hidden');
    const kpAction = document.getElementById('btn-keypad-action');
    if (kpAction) {
        kpAction.innerText = "확인 ↵";
        kpAction.className = "py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs transition active:scale-95 shadow-xs";
    }

    const feedback = document.getElementById('feedback-box');
    feedback.className = 'hidden p-4 rounded-xl text-sm font-semibold';
    feedback.innerHTML = '';

    problemStartTime = Date.now();
}

function appendPracticeDigit(digit) {
    if (isAnswerChecked) return;
    if (currentAnswerVal.length >= 6) return;
    currentAnswerVal += digit;
    updateAnswerDisplay();
}

function backspacePracticeDigit() {
    if (isAnswerChecked) return;
    if (currentAnswerVal.length > 0) {
        currentAnswerVal = currentAnswerVal.slice(0, -1);
        updateAnswerDisplay();
    }
}

function clearPracticeInput() {
    if (isAnswerChecked) return;
    currentAnswerVal = "";
    updateAnswerDisplay();
}

function onKeypadAction() {
    if (!isAnswerChecked) {
        checkPracticeAnswer();
    } else {
        nextPracticeProblem();
    }
}

function checkPracticeAnswer() {
    if (isAnswerChecked) return;
    const userVal = currentAnswerVal.trim();
    if (userVal === '') return;

    isAnswerChecked = true;
    const elapsed = Date.now() - problemStartTime;
    totalElapsedMs += elapsed;

    const p = problems[currentIndex];
    const userNum = parseInt(userVal, 10);
    const isCorrect = userNum === p.answer;

    document.getElementById('submit-btn').classList.add('hidden');
    document.getElementById('next-btn').classList.remove('hidden');
    const kpAction = document.getElementById('btn-keypad-action');
    if (kpAction) {
        kpAction.innerText = "다음 ➔";
        kpAction.className = "py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition active:scale-95 shadow-xs";
    }

    const feedback = document.getElementById('feedback-box');
    feedback.classList.remove('hidden');

    if (isCorrect) {
        correctCount++;
        feedback.className = 'p-4 rounded-xl text-sm font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200';
        feedback.innerHTML = `🎉 정답입니다! (${(elapsed / 1000).toFixed(1)}초)`;
    } else {
        feedback.className = 'p-4 rounded-xl text-sm font-semibold bg-rose-50 text-rose-800 border border-rose-200';
        feedback.innerHTML = `❌ 아쉬워요! 정답은 <strong>${p.answer}</strong> 입니다.<br><small class="text-rose-600 font-normal mt-1 block">${p.strategyTip || ''}</small>`;
    }
}

function nextPracticeProblem() {
    if (currentIndex + 1 < problems.length) {
        loadPractice(currentIndex + 1);
    } else {
        finishPractice();
    }
}

// PC 물리 키보드 완벽 연동
document.addEventListener('keydown', function(e) {
    if (!document.getElementById('result-card').classList.contains('hidden')) return;
    if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA')) return;

    if (e.key >= '0' && e.key <= '9') {
        e.preventDefault();
        appendPracticeDigit(e.key);
    } else if (e.key === 'Backspace') {
        e.preventDefault();
        backspacePracticeDigit();
    } else if (e.key === 'Delete') {
        e.preventDefault();
        clearPracticeInput();
    } else if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        onKeypadAction();
    }
});

function finishPractice() {
    document.getElementById('practice-card').classList.add('hidden');
    document.getElementById('result-card').classList.remove('hidden');

    const accuracy = Math.round((correctCount / problems.length) * 100);
    const avgSec = (totalElapsedMs / problems.length / 1000).toFixed(1);

    document.getElementById('res-accuracy').innerText = `${accuracy}%`;
    document.getElementById('res-score').innerText = `${correctCount} / ${problems.length}`;
    document.getElementById('res-time').innerText = `${avgSec}초`;

    // API 전송
    fetch('api/submit_practice.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            total: problems.length,
            correct: correctCount,
            accuracy: accuracy,
            averageMs: totalElapsedMs / problems.length,
            startedAt: sessionStartTime.toISOString(),
            finishedAt: new Date().toISOString()
        })
    }).catch(err => console.error(err));
}

loadPractice(0);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
