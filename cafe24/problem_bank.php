<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/lib/generators.php';

$types_info = [
    'basic' => [
        'name' => '기본 구구단 역연산',
        'desc' => '구구단 범위 내에서의 나눗셈으로 모든 나눗셈의 가장 기본이 되는 사실적 자동화 영역입니다.',
        'tip' => '곱셈구구를 거꾸로 생각하여 3초 이내에 직관적으로 답이 튀어나오도록 훈련합니다.',
        'examples' => ['54 ÷ 6 = 9', '42 ÷ 7 = 6', '72 ÷ 8 = 9', '63 ÷ 9 = 7']
    ],
    'divide2' => [
        'name' => '÷2 전략 (절반 구하기)',
        'desc' => '짝수를 절반으로 나누는 연산으로, 상위 나눗셈 전략(÷4, ÷8)의 기초가 됩니다.',
        'tip' => '각 자릿수를 십의 자리, 일의 자리 단위로 분해하여 절반을 구하는 감각을 익힙니다.',
        'examples' => ['64 ÷ 2 = 32', '86 ÷ 2 = 43', '140 ÷ 2 = 70', '52 ÷ 2 = 26']
    ],
    'divide4' => [
        'name' => '÷4 전략 (절반의 절반)',
        'desc' => '4로 나누는 번거로운 계산을 2로 두 번 연속 나누는 전략으로 단순화합니다.',
        'tip' => '한 번에 4로 나누려 하지 말고, 절반을 구한 뒤 그 결과를 다시 절반으로 나눕니다.',
        'examples' => ['120 ÷ 4 = 30 (120→60→30)', '84 ÷ 4 = 21 (84→42→21)', '180 ÷ 4 = 45']
    ],
    'divide5' => [
        'name' => '÷5 전략 (×2 후 ÷10)',
        'desc' => '초등 5학년 연산에서 가장 강력한 자리값 변환 전략입니다.',
        'tip' => '피제수에 2를 먼저 곱하고, 끝자리 0을 하나 지우는(÷10) 방식으로 세로셈 없이 암산합니다.',
        'examples' => ['350 ÷ 5 = 70 (350×2=700→70)', '1,200 ÷ 5 = 240', '4,500 ÷ 5 = 900']
    ],
    'divide8' => [
        'name' => '÷8 전략 (절반 3번 나누기)',
        'desc' => '8로 나눌 때 2로 3번 연속(÷2 → ÷2 → ÷2) 나누어 암산 부하를 획기적으로 낮춥니다.',
        'tip' => '세로셈 계산 실수를 줄이고 2의 거듭제곱 분해 감각을 기릅니다.',
        'examples' => ['120 ÷ 8 = 15 (120→60→30→15)', '240 ÷ 8 = 30', '480 ÷ 8 = 60']
    ],
    'divide10' => [
        'name' => '÷10 및 자리값 처리',
        'desc' => '10, 100, 1000으로 나눌 때 자릿수 이동의 원리를 이해합니다.',
        'tip' => '0의 개수만큼 자릿수를 이동하여 소수점 및 끝자리 0을 처리합니다.',
        'examples' => ['750 ÷ 10 = 75', '4,200 ÷ 100 = 42', '80,000 ÷ 1,000 = 80']
    ],
    'largeNumber' => [
        'name' => '큰 수 처리 전략 (1,000 이상)',
        'desc' => '자릿수가 큰 수에서 0을 분리하여 앞자리 연산 후 결합하는 전략입니다.',
        'tip' => '끝자리 0들을 떼어두고 앞의 수만 나눈 뒤 0의 개수를 알맞게 붙여줍니다.',
        'examples' => ['2,400 ÷ 6 = 400', '15,000 ÷ 3 = 5,000', '36,000 ÷ 4 = 9,000']
    ],
    'mixed' => [
        'name' => '초5 두 자리 수 나눗셈',
        'desc' => '초5 교과 과정에 등장하는 두 자리 수로 나누는 실전 연산입니다.',
        'tip' => '제수(나누는 수)를 몇십으로 어림하여 몫을 예상하고 오차를 조정합니다.',
        'examples' => ['345 ÷ 15 = 23', '672 ÷ 21 = 32', '528 ÷ 24 = 22']
    ]
];
?>

<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">나눗셈 문제 유형 및 전략 가이드</h1>
        <p class="text-xs text-slate-500 mt-1">
            단순 세로셈 반복이 아닌, 뇌의 인지 부하를 줄여주는 8가지 나눗셈 자동화 전략을 소개합니다.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach ($types_info as $typeKey => $info): ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-6 flex flex-col justify-between shadow-xs">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="px-2.5 py-1 rounded bg-slate-100 text-slate-700 text-xs font-bold">
                            <?php echo $info['name']; ?>
                        </span>
                        <a href="practice.php?type=<?php echo $typeKey; ?>" class="text-xs font-bold text-slate-900 hover:underline">
                            연습 풀기 ➔
                        </a>
                    </div>

                    <p class="text-xs text-slate-600 leading-relaxed">
                        <?php echo $info['desc']; ?>
                    </p>

                    <div class="mt-4 p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                            💡 연산 전략 팁
                        </div>
                        <p class="text-xs font-medium text-slate-800 leading-relaxed">
                            <?php echo $info['tip']; ?>
                        </p>
                    </div>

                    <div class="mt-4">
                        <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                            대표 문제 예시
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($info['examples'] as $ex): ?>
                                <span class="px-2.5 py-1 bg-white border border-slate-200 rounded-md font-mono text-xs font-bold text-slate-800">
                                    <?php echo $ex; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100 flex gap-2">
                    <a href="worksheet.php?type=<?php echo $typeKey; ?>" class="flex-1 text-center py-2 px-3 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-lg text-xs transition">
                        맞춤 훈련지 생성
                    </a>
                    <a href="practice.php?type=<?php echo $typeKey; ?>" class="flex-1 text-center py-2 px-3 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold rounded-lg text-xs transition">
                        온라인 풀기
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
