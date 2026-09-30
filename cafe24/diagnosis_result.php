<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/lib/diagnosis.php';

$diagId = $_GET['id'] ?? '';
if (!$diagId) {
    header('Location: index.php');
    exit;
}

$t_diag = table('diagnoses');
$t_ans = table('diagnosis_answers');

$stmt = $pdo->prepare("SELECT * FROM `{$t_diag}` WHERE `id` = ?");
$stmt->execute([$diagId]);
$diag = $stmt->fetch();

if (!$diag) {
    echo "<div class='p-8 text-center text-slate-500'>진단 기록을 찾을 수 없습니다. <a href='index.php' class='underline'>대시보드로 이동</a></div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$ansStmt = $pdo->prepare("SELECT * FROM `{$t_ans}` WHERE `diagnosis_id` = ?");
$ansStmt->execute([$diagId]);
$rawAnswers = $ansStmt->fetchAll();

// PHP analysis 형식으로 매핑
$answersForAnalysis = [];
foreach ($rawAnswers as $a) {
    $answersForAnalysis[] = [
        'question' => $a['question'],
        'answer' => (int)$a['answer'],
        'userAnswer' => $a['user_answer'] !== null ? (int)$a['user_answer'] : null,
        'correct' => (bool)$a['correct'],
        'elapsedMs' => (int)$a['elapsed_ms'],
        'type' => $a['type'],
        'difficulty' => (int)$a['difficulty']
    ];
}

$report = analyze_diagnosis($answersForAnalysis);
?>

<div class="space-y-8">
    <!-- 상단 결과 헤더 -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6 shadow-xs">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-semibold mb-2">
                연산 역량 정밀 진단 결과표
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">
                <?php echo htmlspecialchars($active_student['name']); ?> 학생의 진단 리포트
            </h1>
            <p class="text-slate-500 text-xs mt-1">
                진단 완료 일시: <?php echo date('Y년 m월 d일 H:i', strtotime($diag['finished_at'])); ?> · 총 20문제 채점 완료
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="worksheet.php?type=<?php echo $report['weakestType']; ?>&difficulty=2" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-bold hover:bg-slate-800 transition shadow-sm">
                맞춤 훈련지 인쇄/생성 ➔
            </a>
            <a href="index.php" class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition">
                대시보드
            </a>
        </div>
    </div>

    <!-- 3대 핵심 성취 지표 -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">정확도 (점수)</div>
            <div class="flex items-baseline gap-2">
                <span class="text-4xl font-extrabold text-slate-900"><?php echo $report['accuracy']; ?>%</span>
                <span class="text-xs font-bold <?php echo $report['accuracy'] >= 80 ? 'text-emerald-600' : 'text-amber-600'; ?>">
                    (<?php echo $report['correct']; ?> / <?php echo $report['total']; ?> 정답)
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-2">
                <?php echo $report['accuracy'] >= 80 ? '안정적인 계산 정확도를 유지하고 있습니다.' : '오답률이 높아 개념 및 기초 전략 복습이 권장됩니다.'; ?>
            </p>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">평균 풀이 속도</div>
            <div class="flex items-baseline gap-2">
                <span class="text-4xl font-extrabold text-slate-900"><?php echo $report['averageSeconds']; ?>초</span>
                <span class="text-xs text-slate-500 font-medium">/ 문제당</span>
            </div>
            <p class="text-xs text-slate-500 mt-2">
                <?php echo $report['averageSeconds'] <= 6 ? '자동화 수준이 우수하여 빠른 계산이 가능합니다.' : '풀이 지연이 발생하여 어림 및 절반 전략 훈련이 필요합니다.'; ?>
            </p>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">종합 평가 레벨</div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900"><?php echo $report['studentLevel']; ?></span>
            </div>
            <p class="text-xs text-slate-500 mt-2">
                정확도 70% + 속도 지수 30%를 결합한 종합 연산 역량 등급
            </p>
        </div>
    </div>

    <!-- 취약점 처방 및 추천 훈련 카드 -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 space-y-6">
        <div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md bg-amber-50 text-amber-800 text-xs font-bold border border-amber-200">
                🚨 집중 보완 필요 유형: <?php echo PROBLEM_TYPE_LABELS[$report['weakestType']] ?? $report['weakestType']; ?>
            </span>
            <h2 class="text-xl font-bold text-slate-900 mt-3">
                <?php echo $report['recommendedTraining']['title']; ?>
            </h2>
            <p class="text-sm text-slate-600 mt-1 leading-relaxed">
                <?php echo $report['recommendedTraining']['description']; ?>
            </p>
        </div>

        <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 text-sm text-slate-800 leading-relaxed">
            <strong>핵심 처방:</strong> <?php echo $report['recommendedTraining']['recommendedAction']; ?>
        </div>

        <!-- 5일 맞춤 훈련 로드맵 -->
        <div>
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">5일 완성 맞춤 훈련 플랜</h3>
            <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                <?php foreach ($report['recommendedTraining']['dailyPlan'] as $idx => $plan): ?>
                    <div class="p-3 bg-white border border-slate-200 rounded-lg text-xs space-y-1">
                        <div class="font-bold text-slate-900"><?php echo ($idx + 1); ?>일차</div>
                        <p class="text-slate-600 text-[11px] leading-relaxed"><?php echo preg_replace('/^\d일차:\s*/', '', $plan); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pt-2">
            <a href="worksheet.php?type=<?php echo $report['weakestType']; ?>&difficulty=2" class="inline-flex items-center gap-2 px-6 py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-sm transition">
                이 처방으로 맞춤 훈련지 바로 만들기 ➔
            </a>
        </div>
    </div>

    <!-- 세부 문제 풀이 기록표 -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 space-y-4">
        <h2 class="text-lg font-bold text-slate-900">문제별 정밀 채점 내역</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase">
                        <th class="py-3 px-3">번호</th>
                        <th class="py-3 px-3">문제</th>
                        <th class="py-3 px-3">정답</th>
                        <th class="py-3 px-3">학생 답안</th>
                        <th class="py-3 px-3">풀이 시간</th>
                        <th class="py-3 px-3">유형</th>
                        <th class="py-3 px-3 text-center">결과</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($answersForAnalysis as $idx => $item): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3 px-3 font-mono text-xs text-slate-400"><?php echo ($idx + 1); ?></td>
                            <td class="py-3 px-3 font-mono font-semibold text-slate-900"><?php echo $item['question']; ?></td>
                            <td class="py-3 px-3 font-mono text-slate-600"><?php echo $item['answer']; ?></td>
                            <td class="py-3 px-3 font-mono font-bold <?php echo $item['correct'] ? 'text-slate-900' : 'text-rose-600'; ?>">
                                <?php echo $item['userAnswer'] !== null ? $item['userAnswer'] : '<span class="text-slate-400 font-normal">미입력</span>'; ?>
                            </td>
                            <td class="py-3 px-3 font-mono text-xs text-slate-500">
                                <?php echo number_format($item['elapsedMs'] / 1000, 1); ?>초
                            </td>
                            <td class="py-3 px-3 text-xs text-slate-600">
                                <?php echo PROBLEM_TYPE_LABELS[$item['type']] ?? $item['type']; ?>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <?php if ($item['correct']): ?>
                                    <span class="inline-block px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-800">정답</span>
                                <?php else: ?>
                                    <span class="inline-block px-2 py-0.5 rounded text-xs font-bold bg-rose-100 text-rose-800">오답</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
