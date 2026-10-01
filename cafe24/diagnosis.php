<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/lib/generators.php';

// 진단용 20문제 생성 (서버 사이드에서 고유 시드로 생성)
$diag_problems = generate_diagnosis_problems('diag_' . time() . '_' . $active_student['id']);
?>

<div class="max-w-2xl mx-auto space-y-6">
    <!-- 시작 전 안내 화면 -->
    <div id="intro-screen" class="bg-white border border-slate-200 rounded-2xl p-8 text-center space-y-6">
        <div class="w-16 h-16 rounded-2xl bg-slate-900 text-white inline-flex items-center justify-center mx-auto shadow-sm">
            <svg class="w-8 h-8 fill-white" viewBox="0 0 24 24"><path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"/></svg>
        </div>

        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                <?php echo htmlspecialchars($active_student['name']); ?> 학생의 5분 연산 진단
            </h1>
            <p class="text-slate-600 text-sm mt-2 max-w-md mx-auto leading-relaxed">
                초등학교 5학년 나눗셈 핵심 20문제가 출제됩니다. 문제별 풀이 시간(밀리초)과 오답 유형을 정밀 측정하여 맞춤형 훈련지를 설계합니다.
            </p>
        </div>

        <div class="grid grid-cols-3 gap-3 max-w-md mx-auto text-left">
            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-center">
                <div class="text-[11px] font-semibold text-slate-500 uppercase">문항수</div>
                <div class="text-xl font-extrabold text-slate-900 mt-0.5">20문제</div>
            </div>
            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-center">
                <div class="text-[11px] font-semibold text-slate-500 uppercase">제한시간</div>
                <div class="text-xl font-extrabold text-slate-900 mt-0.5">5분</div>
            </div>
            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-center">
                <div class="text-[11px] font-semibold text-slate-500 uppercase">측정항목</div>
                <div class="text-xl font-extrabold text-slate-900 mt-0.5">속도 &amp; 정확도</div>
            </div>
        </div>

        <div class="pt-4">
            <button onclick="startDiagnosis()" class="w-full py-3.5 px-6 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-base transition shadow-sm">
                진단 시작하기 ➔
            </button>
            <p class="text-xs text-slate-400 mt-3">답을 입력하고 <kbd class="px-1.5 py-0.5 rounded bg-slate-100 border text-slate-700">Enter</kbd>를 누르면 다음 문제로 자동 이동합니다.</p>
        </div>
    </div>

    <!-- 진단 풀이 화면 (초기 숨김) -->
    <div id="test-screen" class="hidden space-y-6">
        <!-- 타이머 및 진행 바 -->
        <div class="bg-white border border-slate-200 rounded-xl p-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">진행도</span>
                <span id="progress-text" class="text-sm font-bold text-slate-900">1 / 20</span>
            </div>

            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span id="timer-display" class="font-mono text-lg font-bold text-slate-900">05:00</span>
            </div>
        </div>

        <!-- 진행 게이지 -->
        <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
            <div id="progress-bar" class="h-full bg-slate-900 transition-all duration-300" style="width: 5%"></div>
        </div>

        <!-- 문제 카드 -->
        <div class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-10 text-center space-y-6 shadow-sm">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-xs font-medium" id="problem-badge">
                문제 1
            </div>

            <div class="py-2 sm:py-4">
                <div id="question-text" class="text-3xl sm:text-5xl font-mono font-bold text-slate-900 tracking-tight">
                    12 ÷ 3 =
                </div>
            </div>

            <div class="max-w-xs mx-auto">
                <input type="text" inputmode="numeric" pattern="[0-9]*" id="answer-input" placeholder="답 입력 후 엔터" autocomplete="off"
                    class="w-full text-center text-2xl sm:text-3xl font-mono font-bold py-3 px-4 rounded-xl border-2 border-slate-300 focus:border-slate-900 focus:outline-none transition">
                <div class="mt-3">
                    <button onclick="submitAnswer()" class="w-full py-3 px-4 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-sm transition active:scale-98">
                        확인 (Enter) ➔
                    </button>
                </div>
            </div>
        </div>

        <!-- 숫자 키패드 (모바일 및 태블릿 터치 편의용) -->
        <div class="bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 max-w-xs mx-auto grid grid-cols-3 gap-2 select-none shadow-xs" style="touch-action: manipulation;">
            <?php for ($i = 1; $i <= 9; $i++): ?>
                <button type="button" onclick="appendDigit('<?php echo $i; ?>')" class="py-3.5 bg-slate-50 hover:bg-slate-100 active:bg-slate-200 text-slate-900 font-bold rounded-xl text-xl transition active:scale-95">
                    <?php echo $i; ?>
                </button>
            <?php endfor; ?>
            <button type="button" onclick="clearInput()" class="py-3.5 bg-slate-50 hover:bg-slate-100 active:bg-slate-200 text-slate-500 font-semibold rounded-xl text-sm transition active:scale-95">
                지우기
            </button>
            <button type="button" onclick="appendDigit('0')" class="py-3.5 bg-slate-50 hover:bg-slate-100 active:bg-slate-200 text-slate-900 font-bold rounded-xl text-xl transition active:scale-95">
                0
            </button>
            <button type="button" onclick="submitAnswer()" class="py-3.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-sm transition active:scale-95 shadow-xs">
                입력 ↵
            </button>
        </div>
    </div>

    <!-- 제출 로딩 화면 -->
    <div id="loading-screen" class="hidden bg-white border border-slate-200 rounded-2xl p-12 text-center space-y-4">
        <div class="inline-block animate-spin rounded-full h-10 w-10 border-4 border-slate-900 border-t-transparent"></div>
        <h2 class="text-xl font-bold text-slate-900">진단 결과를 정밀 분석 중입니다...</h2>
        <p class="text-xs text-slate-500">정확도, 소요 시간, 취약 유형 및 맞춤 처방을 계산하고 있습니다.</p>
    </div>
</div>

<script>
const problems = <?php echo json_encode($diag_problems); ?>;
let currentIndex = 0;
let answers = [];
let testStartTime = null;
let currentProblemStartTime = null;
let timerInterval = null;
const TOTAL_SECONDS = 300; // 5분
let remainingSeconds = TOTAL_SECONDS;

function startDiagnosis() {
    document.getElementById('intro-screen').classList.add('hidden');
    document.getElementById('test-screen').classList.remove('hidden');

    testStartTime = new Date();
    currentProblemStartTime = Date.now();
    loadProblem(0);

    timerInterval = setInterval(() => {
        remainingSeconds--;
        updateTimerDisplay();
        if (remainingSeconds <= 0) {
            clearInterval(timerInterval);
            finishDiagnosis();
        }
    }, 1000);
}

function updateTimerDisplay() {
    const mins = String(Math.floor(remainingSeconds / 60)).padStart(2, '0');
    const secs = String(remainingSeconds % 60).padStart(2, '0');
    document.getElementById('timer-display').innerText = `${mins}:${secs}`;
}

function loadProblem(index) {
    currentIndex = index;
    const p = problems[index];

    document.getElementById('problem-badge').innerText = `문제 ${index + 1} / ${problems.length}`;
    document.getElementById('progress-text').innerText = `${index + 1} / ${problems.length}`;
    document.getElementById('progress-bar').style.width = `${((index + 1) / problems.length) * 100}%`;
    document.getElementById('question-text').innerText = p.question;

    const input = document.getElementById('answer-input');
    input.value = '';
    if (!('ontouchstart' in window)) {
        input.focus();
    }
    currentProblemStartTime = Date.now();
}

function appendDigit(digit) {
    const input = document.getElementById('answer-input');
    input.value += digit;
}

function clearInput() {
    const input = document.getElementById('answer-input');
    input.value = '';
}

function submitAnswer() {
    const input = document.getElementById('answer-input');
    const userVal = input.value.trim();
    if (userVal === '') return;

    const p = problems[currentIndex];
    const elapsedMs = Date.now() - currentProblemStartTime;
    const userNum = parseInt(userVal, 10);
    const isCorrect = userNum === p.answer;

    answers.push({
        question: p.question,
        answer: p.answer,
        userAnswer: userNum,
        correct: isCorrect,
        elapsedMs: elapsedMs,
        type: p.type,
        difficulty: p.difficulty
    });

    if (currentIndex + 1 < problems.length) {
        loadProblem(currentIndex + 1);
    } else {
        clearInterval(timerInterval);
        finishDiagnosis();
    }
}

document.addEventListener('keydown', function(e) {
    if (document.getElementById('test-screen').classList.contains('hidden')) return;
    if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA')) {
        if (e.key === 'Enter') {
            e.preventDefault();
            submitAnswer();
        }
        return;
    }

    if (e.key >= '0' && e.key <= '9') {
        e.preventDefault();
        appendDigit(e.key);
    } else if (e.key === 'Backspace') {
        e.preventDefault();
        const input = document.getElementById('answer-input');
        input.value = input.value.slice(0, -1);
    } else if (e.key === 'Delete') {
        e.preventDefault();
        clearInput();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        submitAnswer();
    }
});

function finishDiagnosis() {
    document.getElementById('test-screen').classList.add('hidden');
    document.getElementById('loading-screen').classList.remove('hidden');

    const finishedTime = new Date();

    // 혹시 시간 초과로 다 못 푼 문제가 있다면 빈 답안으로 처리
    while (answers.length < problems.length) {
        const p = problems[answers.length];
        answers.push({
            question: p.question,
            answer: p.answer,
            userAnswer: null,
            correct: false,
            elapsedMs: 0,
            type: p.type,
            difficulty: p.difficulty
        });
    }

    const payload = {
        answers: answers,
        startedAt: testStartTime.toISOString(),
        finishedAt: finishedTime.toISOString()
    };

    fetch('api/submit_diagnosis.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.diagnosisId) {
            window.location.href = 'diagnosis_result.php?id=' + data.diagnosisId;
        } else {
            alert('결과 저장 중 오류가 발생했습니다: ' + (data.error || '알 수 없는 오류'));
            window.location.href = 'index.php';
        }
    })
    .catch(err => {
        console.error(err);
        alert('서버 통신 중 오류가 발생했습니다.');
        window.location.href = 'index.php';
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
