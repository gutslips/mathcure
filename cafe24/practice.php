<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/lib/generators.php';

$type = $_GET['type'] ?? 'divide5';
$difficulty = (int)($_GET['difficulty'] ?? 2);
$count = (int)($_GET['count'] ?? 10);

$problems = generate_problems_by_type($type, $count, $difficulty);
?>

<div class="max-w-2xl mx-auto space-y-6">
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
    <div id="practice-card" class="bg-white border border-slate-200 rounded-2xl p-8 sm:p-12 text-center space-y-6 shadow-sm">
        <div id="p-question" class="text-4xl sm:text-5xl font-mono font-bold text-slate-900 tracking-tight py-4">
            -- ÷ -- =
        </div>

        <div class="max-w-xs mx-auto">
            <input type="text" inputmode="numeric" id="practice-input" placeholder="답 입력 후 확인" autocomplete="off"
                class="w-full text-center text-3xl font-mono font-bold py-3 px-4 rounded-xl border-2 border-slate-300 focus:border-slate-900 focus:outline-none transition">

            <div class="mt-4 flex gap-2">
                <button id="submit-btn" onclick="checkPracticeAnswer()" class="flex-1 py-3 px-4 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-sm transition">
                    정답 확인 (Enter)
                </button>
                <button id="next-btn" onclick="nextPracticeProblem()" class="hidden flex-1 py-3 px-4 bg-slate-800 hover:bg-slate-700 text-white font-semibold rounded-xl text-sm transition">
                    다음 문제 ➔
                </button>
            </div>
        </div>

        <!-- 피드백 메시지 박스 -->
        <div id="feedback-box" class="hidden p-4 rounded-xl text-sm font-semibold"></div>
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

function loadPractice(index) {
    currentIndex = index;
    isAnswerChecked = false;
    const p = problems[index];

    document.getElementById('p-counter').innerText = `${index + 1} / ${problems.length}`;
    document.getElementById('p-question').innerText = p.question;

    const input = document.getElementById('practice-input');
    input.value = '';
    input.disabled = false;
    input.focus();

    document.getElementById('submit-btn').classList.remove('hidden');
    document.getElementById('next-btn').classList.add('hidden');

    const feedback = document.getElementById('feedback-box');
    feedback.className = 'hidden p-4 rounded-xl text-sm font-semibold';
    feedback.innerHTML = '';

    problemStartTime = Date.now();
}

function checkPracticeAnswer() {
    if (isAnswerChecked) return;
    const input = document.getElementById('practice-input');
    const userVal = input.value.trim();
    if (userVal === '') return;

    isAnswerChecked = true;
    const elapsed = Date.now() - problemStartTime;
    totalElapsedMs += elapsed;

    const p = problems[currentIndex];
    const userNum = parseInt(userVal, 10);
    const isCorrect = userNum === p.answer;

    input.disabled = true;
    document.getElementById('submit-btn').classList.add('hidden');
    document.getElementById('next-btn').classList.remove('hidden');

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

    document.getElementById('next-btn').focus();
}

function nextPracticeProblem() {
    if (currentIndex + 1 < problems.length) {
        loadPractice(currentIndex + 1);
    } else {
        finishPractice();
    }
}

document.getElementById('practice-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        if (!isAnswerChecked) {
            checkPracticeAnswer();
        } else {
            nextPracticeProblem();
        }
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
