<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/lib/diagnosis.php';

$t_diag = table('diagnoses');
$t_ans = table('diagnosis_answers');

// 활성 학생의 최근 진단 조회
$stmt = $pdo->prepare("SELECT * FROM `{$t_diag}` WHERE `student_id` = ? ORDER BY `finished_at` DESC LIMIT 1");
$stmt->execute([$active_student['id']]);
$latest_diag = $stmt->fetch();

$answers = [];
if ($latest_diag) {
    $ansStmt = $pdo->prepare("SELECT * FROM `{$t_ans}` WHERE `diagnosis_id` = ?");
    $ansStmt->execute([$latest_diag['id']]);
    $answers = $ansStmt->fetchAll();
}

// 누적 진단 횟수
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM `{$t_diag}` WHERE `student_id` = ?");
$countStmt->execute([$active_student['id']]);
$total_diagnoses_count = (int)$countStmt->fetchColumn();

$has_diagnosis = !empty($latest_diag) && !empty($answers);

$accuracy = 0;
$avg_seconds = 0;
$level = '진단 대기';
$weakest_type = 'divide5';
$type_stats = [];

$all_types = ['basic', 'divide2', 'divide4', 'divide5', 'divide8', 'largeNumber'];

if ($has_diagnosis) {
    $accuracy = (int)round($latest_diag['accuracy']);
    $avg_seconds = number_format($latest_diag['average_ms'] / 1000, 1);
    $level = determine_student_level((int)round($latest_diag['accuracy'] * 0.7 + calculate_speed_score($latest_diag['average_ms']) * 30));

    $custom_stats = [];
    foreach ($answers as $ans) {
        $t = $ans['type'];
        if (!isset($custom_stats[$t])) {
            $custom_stats[$t] = ['total' => 0, 'correct' => 0, 'totalMs' => 0];
        }
        $custom_stats[$t]['total']++;
        if ($ans['correct']) $custom_stats[$t]['correct']++;
        $custom_stats[$t]['totalMs'] += (int)$ans['elapsed_ms'];
    }

    $min_rate = 999;
    foreach ($all_types as $t) {
        $v = $custom_stats[$t] ?? null;
        if ($v && $v['total'] > 0) {
            $rate = (int)round(($v['correct'] / $v['total']) * 100);
            $avg_sec = number_format($v['totalMs'] / $v['total'] / 1000, 1);
            $type_stats[$t] = ['total' => $v['total'], 'correct' => $v['correct'], 'avgSec' => $avg_sec, 'rate' => $rate];
            if ($rate < $min_rate) {
                $min_rate = $rate;
                $weakest_type = $t;
            }
        } else {
            $type_stats[$t] = ['total' => 0, 'correct' => 0, 'avgSec' => 0, 'rate' => 0];
        }
    }
}
?>

<div class="space-y-8">
    <!-- 상단 프로필 헤더 -->
    <div class="bg-white border border-slate-200 rounded-xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded bg-slate-100 text-slate-700 text-xs font-medium mb-2">
                초등학교 <?php echo $active_student['grade']; ?>학년 연산 역량 대시보드
            </div>
            <h1 class="text-2xl font-bold text-slate-900">
                <?php echo htmlspecialchars($active_student['name']); ?> 학생의 연산 트레이너
            </h1>
            <p class="text-slate-600 text-sm mt-1">
                현재 학생: <span class="font-semibold text-slate-900"><?php echo htmlspecialchars($active_student['name']); ?></span> (초<?php echo $active_student['grade']; ?>) · 누적 진단 <?php echo $total_diagnoses_count; ?>회
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="diagnosis.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800 transition shadow-sm">
                <svg class="w-4 h-4 fill-white" viewBox="0 0 24 24"><path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"/></svg>
                5분 진단 시작
            </a>
            <a href="worksheet.php?type=<?php echo $weakest_type; ?>" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-white border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition">
                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                맞춤 훈련지 만들기
            </a>
        </div>
    </div>

    <!-- 신규 학생 안내 배너 (진단 기록이 없을 때) -->
    <?php if (!$has_diagnosis): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 flex items-start gap-4">
            <div class="text-amber-700 mt-0.5">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-amber-900 text-sm">
                    '<?php echo htmlspecialchars($active_student['name']); ?>' 학생의 첫 연산 진단을 시작해 보세요!
                </h3>
                <p class="text-xs text-amber-800 mt-1 leading-relaxed">
                    아직 완료된 진단 기록이 없습니다. 5분 진단을 완료하면 학생의 정확한 계산 속도(초)와 나눗셈 오답 유형, 취약점 분석 차트가 자동으로 생성됩니다.
                </p>
                <div class="mt-3">
                    <a href="diagnosis.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-700 hover:bg-amber-800 text-white rounded-md text-xs font-semibold transition">
                        첫 진단 시작하기 ➔
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- 주요 지표 3개 카드 -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-sm font-medium">최근 정확도</span>
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/><circle cx="12" cy="12" r="5" stroke-width="2"/><circle cx="12" cy="12" r="1" stroke-width="2"/></svg>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-extrabold text-slate-900">
                    <?php echo $has_diagnosis ? "{$accuracy}%" : "진단 전"; ?>
                </span>
                <?php if ($has_diagnosis): ?>
                    <span class="text-xs font-medium <?php echo $accuracy >= 80 ? 'text-emerald-600' : 'text-amber-600'; ?>">
                        <?php echo $accuracy >= 80 ? '양호' : '훈련 필요'; ?>
                    </span>
                <?php endif; ?>
            </div>
            <p class="text-xs text-slate-500 mt-2">
                <?php echo $has_diagnosis ? "최근 20문제 진단 채점 기준" : "5분 진단 후 자동 계산됩니다"; ?>
            </p>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-sm font-medium">평균 풀이시간</span>
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-extrabold text-slate-900">
                    <?php echo $has_diagnosis ? "{$avg_seconds}초" : "진단 전"; ?>
                </span>
                <?php if ($has_diagnosis): ?>
                    <span class="text-xs text-slate-500 font-medium">/ 문제당</span>
                <?php endif; ?>
            </div>
            <p class="text-xs text-slate-500 mt-2">
                <?php echo $has_diagnosis ? ($avg_seconds <= 6 ? "우수한 자동화 속도" : "전략 훈련 권장") : "문제별 정밀 소요시간 측정"; ?>
            </p>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl p-6">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-sm font-medium">현재 진단 레벨</span>
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900">
                    <?php echo $level; ?>
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-2">
                <?php echo $has_diagnosis ? "정확도 70% + 속도 30% 종합 지수" : "진단 완료 시 레벨이 결정됩니다"; ?>
            </p>
        </div>
    </div>

    <!-- 하단 2단 레이아웃: 능력 영역별 성취도 & 추천 훈련 -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- 능력 영역별 자동화 수준 -->
        <div class="lg:col-span-2 bg-white border border-slate-200 rounded-xl p-6">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">능력 영역별 자동화 수준</h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        <?php echo htmlspecialchars($active_student['name']); ?> 학생의 나눗셈 유형별 성취율
                    </p>
                </div>
                <a href="problem_bank.php" class="text-xs text-slate-600 hover:text-slate-900 font-medium flex items-center gap-1">
                    유형 상세 ➔
                </a>
            </div>

            <div class="space-y-4">
                <?php
                $type_configs = [
                    ['key' => 'basic', 'label' => '기본 나눗셈', 'desc' => '구구단 역연산 사실 자동화'],
                    ['key' => 'divide2', 'label' => '÷2 자동화', 'desc' => '절반 구하기 전략'],
                    ['key' => 'divide4', 'label' => '÷4 자동화', 'desc' => '절반의 절반 전략'],
                    ['key' => 'divide5', 'label' => '÷5 자동화', 'desc' => '×2 후 ÷10 자리값 전략'],
                    ['key' => 'divide8', 'label' => '÷8 자동화', 'desc' => '절반 3번 나누기 전략'],
                    ['key' => 'largeNumber', 'label' => '큰 수 처리', 'desc' => '천 단위 및 끝자리 0 처리'],
                ];

                foreach ($type_configs as $item):
                    $stat = $type_stats[$item['key']] ?? null;
                    $rate = $stat ? $stat['rate'] : 0;
                    $has_data = !empty($stat) && $stat['total'] > 0;
                ?>
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-slate-800"><?php echo $item['label']; ?></span>
                                <span class="text-xs text-slate-400 hidden sm:inline">(<?php echo $item['desc']; ?>)</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <?php if ($has_data): ?>
                                    <span class="text-[11px] font-medium text-slate-500">
                                        평균 <?php echo $stat['avgSec']; ?>초
                                    </span>
                                    <span class="font-bold text-slate-900 w-12 text-right">
                                        <?php echo $rate; ?>%
                                    </span>
                                <?php else: ?>
                                    <span class="text-[11px] text-slate-400 font-medium">진단 전</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
                            <div class="h-full transition-all duration-500 <?php echo !$has_data ? 'bg-slate-200' : ($rate >= 80 ? 'bg-slate-800' : ($rate >= 60 ? 'bg-slate-600' : 'bg-amber-600')); ?>"
                                style="width: <?php echo $has_data ? $rate : 0; ?>%">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 현재 추천 훈련 카드 -->
        <div class="bg-white border border-slate-200 rounded-xl p-6 flex flex-col justify-between">
            <div>
                <div class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-md border border-amber-200 mb-3">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <?php echo $has_diagnosis ? "현재 가장 필요한 훈련" : "추천 시작 훈련"; ?>
                </div>

                <h3 class="text-lg font-bold text-slate-900">
                    <?php echo PROBLEM_TYPE_LABELS[$weakest_type] ?? '÷5 자동화'; ?> 집중 훈련
                </h3>

                <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                    <?php if ($has_diagnosis): ?>
                        '<?php echo htmlspecialchars($active_student['name']); ?>' 학생의 최근 진단 분석 결과, 해당 유형의 연산 속도 개선 및 자동화가 가장 시급합니다.
                    <?php else: ?>
                        초5 과정에서 가장 중요한 ÷5 연산과 큰 수 자리값 자동화 맞춤 훈련지입니다.
                    <?php endif; ?>
                </p>

                <div class="mt-5 p-3.5 bg-slate-50 rounded-lg border border-slate-200">
                    <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-2">
                        추천 문제 예시
                    </div>
                    <ul class="text-sm font-mono text-slate-800 space-y-1.5 font-medium">
                        <li>800 ÷ 5 = _____</li>
                        <li>1,200 ÷ 5 = _____</li>
                        <li>2,500 ÷ 5 = _____</li>
                        <li>4,500 ÷ 5 = _____</li>
                        <li>8,000 ÷ 5 = _____</li>
                    </ul>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-100 flex flex-col gap-2">
                <a href="worksheet.php?type=<?php echo $weakest_type; ?>&difficulty=2" class="w-full text-center py-2.5 px-4 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800 transition">
                    맞춤 훈련지 만들기
                </a>
                <a href="practice.php?type=<?php echo $weakest_type; ?>" class="w-full text-center py-2 px-4 bg-white border border-slate-200 text-slate-700 text-xs font-medium rounded-lg hover:bg-slate-50 transition">
                    온라인으로 바로 풀기
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
