<?php
require_once __DIR__ . '/includes/header.php';

$t_diag = table('diagnoses');
$t_prac = table('practice_sessions');

$diagStmt = $pdo->prepare("SELECT * FROM `{$t_diag}` WHERE `student_id` = ? ORDER BY `finished_at` DESC");
$diagStmt->execute([$active_student['id']]);
$diagnoses = $diagStmt->fetchAll();

$pracStmt = $pdo->prepare("SELECT * FROM `{$t_prac}` WHERE `student_id` = ? ORDER BY `finished_at` DESC LIMIT 20");
$pracStmt->execute([$active_student['id']]);
$practices = $pracStmt->fetchAll();
?>

<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">
            <?php echo htmlspecialchars($active_student['name']); ?> 학생의 학습 기록
        </h1>
        <p class="text-xs text-slate-500 mt-1">
            연산 진단 및 온라인 풀이 내역을 확인하고 실력 향상 추이를 점검하세요.
        </p>
    </div>

    <!-- 진단 평가 기록 -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 space-y-4 shadow-xs">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-900">5분 연산 진단 이력</h2>
            <span class="text-xs font-semibold text-slate-500">총 <?php echo count($diagnoses); ?>회 완료</span>
        </div>

        <?php if (empty($diagnoses)): ?>
            <div class="p-8 text-center bg-slate-50 rounded-xl text-slate-400 text-sm">
                아직 완료된 진단 기록이 없습니다. <a href="diagnosis.php" class="text-slate-900 font-bold underline ml-1">첫 진단 시작하기</a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase">
                            <th class="py-3 px-3">진단 일시</th>
                            <th class="py-3 px-3">문항수</th>
                            <th class="py-3 px-3">정답수</th>
                            <th class="py-3 px-3">정확도</th>
                            <th class="py-3 px-3">평균 풀이시간</th>
                            <th class="py-3 px-3 text-right">상세 리포트</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($diagnoses as $d): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="py-3 px-3 font-mono text-xs text-slate-600">
                                    <?php echo date('Y.m.d H:i', strtotime($d['finished_at'])); ?>
                                </td>
                                <td class="py-3 px-3 text-slate-900"><?php echo $d['total']; ?>문제</td>
                                <td class="py-3 px-3 text-slate-900 font-semibold"><?php echo $d['correct']; ?>개</td>
                                <td class="py-3 px-3 font-bold <?php echo $d['accuracy'] >= 80 ? 'text-emerald-600' : 'text-amber-600'; ?>">
                                    <?php echo round($d['accuracy']); ?>%
                                </td>
                                <td class="py-3 px-3 font-mono text-xs text-slate-600">
                                    <?php echo number_format($d['average_ms'] / 1000, 1); ?>초
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <a href="diagnosis_result.php?id=<?php echo $d['id']; ?>" class="inline-flex items-center gap-1 text-xs font-bold text-slate-900 hover:underline">
                                        결과 보기 ➔
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- 최근 온라인 풀이 연습 기록 -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 space-y-4 shadow-xs">
        <h2 class="text-lg font-bold text-slate-900">최근 온라인 연습 이력</h2>

        <?php if (empty($practices)): ?>
            <div class="p-8 text-center bg-slate-50 rounded-xl text-slate-400 text-sm">
                아직 온라인 연습 기록이 없습니다. <a href="practice.php" class="text-slate-900 font-bold underline ml-1">온라인 연습 시작하기</a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase">
                            <th class="py-3 px-3">연습 일시</th>
                            <th class="py-3 px-3">푼 문항</th>
                            <th class="py-3 px-3">맞힌 개수</th>
                            <th class="py-3 px-3">정답률</th>
                            <th class="py-3 px-3">평균 소요시간</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($practices as $p): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="py-3 px-3 font-mono text-xs text-slate-600">
                                    <?php echo date('Y.m.d H:i', strtotime($p['finished_at'])); ?>
                                </td>
                                <td class="py-3 px-3 text-slate-900"><?php echo $p['total']; ?>문제</td>
                                <td class="py-3 px-3 text-slate-900 font-semibold"><?php echo $p['correct']; ?>개</td>
                                <td class="py-3 px-3 font-bold text-slate-900"><?php echo round($p['accuracy']); ?>%</td>
                                <td class="py-3 px-3 font-mono text-xs text-slate-600">
                                    <?php echo number_format($p['average_ms'] / 1000, 1); ?>초
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
