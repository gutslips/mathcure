<?php
/**
 * MathCure - Math Problem Generators in PHP
 */

class SeededRNG {
    private $state;

    public function __construct($seedStr = null) {
        if ($seedStr === null || $seedStr === '') {
            $this->state = mt_rand(0, 0x7fffffff);
            return;
        }
        $h = 0;
        $len = strlen($seedStr);
        for ($i = 0; $i < $len; $i++) {
            $h = (31 * $h + ord($seedStr[$i])) & 0xffffffff;
        }
        $this->state = $h;
    }

    private function imul($a, $b) {
        $a = (int)$a & 0xffffffff;
        $b = (int)$b & 0xffffffff;
        $ah = ($a >> 16) & 0xffff;
        $al = $a & 0xffff;
        $bh = ($b >> 16) & 0xffff;
        $bl = $b & 0xffff;
        return ((($al * $bl) & 0xffffffff) + ((($ah * $bl + $al * $bh) << 16) & 0xffffffff)) & 0xffffffff;
    }

    public function nextFloat() {
        $this->state = ($this->state + 0x6D2B79F5) & 0xffffffff;
        $t = ($this->state ^ ($this->state >> 15)) & 0xffffffff;
        $t = $this->imul($t, 1 | $this->state);
        $t2 = $this->imul(($t ^ ($t >> 7)) & 0xffffffff, 61);
        $t = ($t + $t2) & 0xffffffff;
        $val = ($t ^ ($t >> 14)) & 0xffffffff;
        return abs($val) / 4294967296.0;
    }

    public function randInt($min, $max) {
        $r = $this->nextFloat();
        return (int)floor($r * ($max - min($min, $max) + 1)) + $min;
    }

    public function pickRandom($array) {
        if (empty($array)) return null;
        $idx = (int)floor($this->nextFloat() * count($array));
        return $array[$idx];
    }

    public function shuffle($array) {
        $arr = array_values($array);
        $count = count($arr);
        for ($i = $count - 1; $i > 0; $i--) {
            $j = (int)floor($this->nextFloat() * ($i + 1));
            $temp = $arr[$i];
            $arr[$i] = $arr[$j];
            $arr[$j] = $temp;
        }
        return $arr;
    }
}

function make_problem($dividend, $divisor, $type, $difficulty, $strategyTip = '') {
    $answer = (int)($dividend / $divisor);
    return [
        'id' => uniqid('p_'),
        'question' => number_format($dividend) . ' ÷ ' . number_format($divisor) . ' = ',
        'dividend' => $dividend,
        'divisor' => $divisor,
        'answer' => $answer,
        'type' => $type,
        'difficulty' => $difficulty,
        'strategyTip' => $strategyTip
    ];
}

// 1. 기본 구구단 역연산
function generate_basic($count, $difficulty = 2, $seed = null) {
    $rng = new SeededRNG($seed);
    $problems = [];
    $history = [];

    $limit = $count * 30;
    while (count($problems) < $count && $limit-- > 0) {
        if ($difficulty === 1) {
            $divisor = $rng->randInt(2, 5);
            $quotient = $rng->randInt(2, 9);
        } elseif ($difficulty === 2) {
            $divisor = $rng->randInt(2, 9);
            $quotient = $rng->randInt(2, 9);
        } elseif ($difficulty === 3) {
            $divisor = $rng->randInt(6, 9);
            $quotient = $rng->randInt(6, 9);
        } else {
            $divisor = $rng->randInt(6, 12);
            $quotient = $rng->randInt(7, 15);
        }

        $dividend = $divisor * $quotient;
        $key = "{$dividend}_{$divisor}";
        if (isset($history[$key])) continue;
        $history[$key] = true;

        $problems[] = make_problem($dividend, $divisor, 'basic', $difficulty, '구구단 역연산 곱셈구구를 떠올려 보세요.');
    }
    return $problems;
}

// 2. ÷2 전략 (절반 구하기)
function generate_divide2($count, $difficulty = 2, $seed = null) {
    $rng = new SeededRNG($seed);
    $problems = [];
    $history = [];

    $limit = $count * 30;
    while (count($problems) < $count && $limit-- > 0) {
        if ($difficulty === 1) {
            $quotient = $rng->randInt(11, 49);
        } elseif ($difficulty === 2) {
            $quotient = $rng->randInt(51, 99);
        } elseif ($difficulty === 3) {
            $quotient = $rng->randInt(101, 499);
        } else {
            $quotient = $rng->randInt(501, 1999);
        }

        $dividend = 2 * $quotient;
        $key = "{$dividend}_2";
        if (isset($history[$key])) continue;
        $history[$key] = true;

        $problems[] = make_problem($dividend, 2, 'divide2', $difficulty, '÷2는 짝수의 각 자릿수를 절반으로 나누는 전략입니다.');
    }
    return $problems;
}

// 3. ÷4 전략 (절반의 절반)
function generate_divide4($count, $difficulty = 2, $seed = null) {
    $rng = new SeededRNG($seed);
    $problems = [];
    $history = [];

    $limit = $count * 30;
    while (count($problems) < $count && $limit-- > 0) {
        if ($difficulty === 1) {
            $quotient = $rng->randInt(11, 30);
        } elseif ($difficulty === 2) {
            $quotient = $rng->randInt(31, 80);
        } elseif ($difficulty === 3) {
            $quotient = $rng->randInt(81, 250);
        } else {
            $quotient = $rng->randInt(251, 800);
        }

        $dividend = 4 * $quotient;
        $key = "{$dividend}_4";
        if (isset($history[$key])) continue;
        $history[$key] = true;

        $problems[] = make_problem($dividend, 4, 'divide4', $difficulty, '÷4는 먼저 2로 나눈 뒤, 그 결과를 다시 2로 나누면 빠릅니다.');
    }
    return $problems;
}

// 4. ÷5 전략 (×2 후 ÷10)
function generate_divide5($count, $difficulty = 2, $seed = null) {
    $rng = new SeededRNG($seed);
    $problems = [];
    $history = [];

    $limit = $count * 30;
    while (count($problems) < $count && $limit-- > 0) {
        if ($difficulty === 1) {
            $quotient = $rng->randInt(11, 50);
        } elseif ($difficulty === 2) {
            $quotient = $rng->randInt(51, 150);
        } elseif ($difficulty === 3) {
            $quotient = $rng->randInt(151, 400);
        } else {
            $quotient = $rng->randInt(401, 1500);
        }

        $dividend = 5 * $quotient;
        $key = "{$dividend}_5";
        if (isset($history[$key])) continue;
        $history[$key] = true;

        $problems[] = make_problem($dividend, 5, 'divide5', $difficulty, '÷5는 먼저 2를 곱한 후 끝자리 0을 하나 떼는(÷10) 전략입니다.');
    }
    return $problems;
}

// 5. ÷8 전략 (절반 세 번)
function generate_divide8($count, $difficulty = 2, $seed = null) {
    $rng = new SeededRNG($seed);
    $problems = [];
    $history = [];

    $limit = $count * 30;
    while (count($problems) < $count && $limit-- > 0) {
        if ($difficulty === 1) {
            $quotient = $rng->randInt(11, 30);
        } elseif ($difficulty === 2) {
            $quotient = $rng->randInt(31, 70);
        } elseif ($difficulty === 3) {
            $quotient = $rng->randInt(71, 150);
        } else {
            $quotient = $rng->randInt(151, 500);
        }

        $dividend = 8 * $quotient;
        $key = "{$dividend}_8";
        if (isset($history[$key])) continue;
        $history[$key] = true;

        $problems[] = make_problem($dividend, 8, 'divide8', $difficulty, '÷8은 2로 3번 연속(÷2 → ÷2 → ÷2) 나누는 전략입니다.');
    }
    return $problems;
}

// 6. ÷10 및 자리값 전략
function generate_divide10($count, $difficulty = 2, $seed = null) {
    $rng = new SeededRNG($seed);
    $problems = [];
    $history = [];

    $limit = $count * 30;
    while (count($problems) < $count && $limit-- > 0) {
        if ($difficulty === 1) {
            $divisor = 10;
        } elseif ($difficulty === 2) {
            $divisor = 100;
        } elseif ($difficulty === 3) {
            $divisor = 1000;
        } else {
            $divisor = $rng->pickRandom([100, 1000, 10000]);
        }
        $quotient = $rng->randInt(12, 199);
        $dividend = $divisor * $quotient;

        $key = "{$dividend}_{$divisor}";
        if (isset($history[$key])) continue;
        $history[$key] = true;

        $problems[] = make_problem($dividend, $divisor, 'divide10', $difficulty, '끝자리 0의 개수만큼 자릿수를 줄여 나눕니다.');
    }
    return $problems;
}

// 7. 큰 수 처리 (1,000 이상)
function generate_large_number($count, $difficulty = 2, $seed = null) {
    $rng = new SeededRNG($seed);
    $problems = [];
    $history = [];

    $limit = $count * 30;
    while (count($problems) < $count && $limit-- > 0) {
        $divisors = [2, 3, 4, 5, 6, 8, 10, 20, 50];
        $divisor = $rng->pickRandom($divisors);

        if ($difficulty === 1) {
            $quotient = $rng->randInt(100, 300);
        } elseif ($difficulty === 2) {
            $quotient = $rng->randInt(300, 800);
        } elseif ($difficulty === 3) {
            $quotient = $rng->randInt(800, 2000);
        } else {
            $quotient = $rng->randInt(2000, 6000);
        }

        $dividend = $divisor * $quotient;
        $key = "{$dividend}_{$divisor}";
        if (isset($history[$key])) continue;
        $history[$key] = true;

        $problems[] = make_problem($dividend, $divisor, 'largeNumber', $difficulty, '앞자리 나눗셈 후 끝자리 0을 그대로 붙여줍니다.');
    }
    return $problems;
}

// 8. 초5 혼합 연산
function generate_mixed($count, $difficulty = 2, $seed = null) {
    $rng = new SeededRNG($seed);
    $problems = [];
    $history = [];

    $limit = $count * 30;
    while (count($problems) < $count && $limit-- > 0) {
        if ($difficulty === 1) {
            $divisor = $rng->randInt(11, 20);
            $quotient = $rng->randInt(11, 20);
        } elseif ($difficulty === 2) {
            $divisor = $rng->randInt(11, 29);
            $quotient = $rng->randInt(21, 50);
        } elseif ($difficulty === 3) {
            $divisor = $rng->randInt(15, 49);
            $quotient = $rng->randInt(51, 99);
        } else {
            $divisor = $rng->randInt(25, 99);
            $quotient = $rng->randInt(60, 199);
        }

        $dividend = $divisor * $quotient;
        $key = "{$dividend}_{$divisor}";
        if (isset($history[$key])) continue;
        $history[$key] = true;

        $problems[] = make_problem($dividend, $divisor, 'mixed', $difficulty, '어림수(10단위)로 몫을 먼저 추측하여 계산합니다.');
    }
    return $problems;
}

function generate_problems_by_type($type, $count, $difficulty = 2, $seed = null) {
    switch ($type) {
        case 'basic': return generate_basic($count, $difficulty, $seed);
        case 'divide2': return generate_divide2($count, $difficulty, $seed);
        case 'divide4': return generate_divide4($count, $difficulty, $seed);
        case 'divide5': return generate_divide5($count, $difficulty, $seed);
        case 'divide8': return generate_divide8($count, $difficulty, $seed);
        case 'divide10': return generate_divide10($count, $difficulty, $seed);
        case 'largeNumber': return generate_large_number($count, $difficulty, $seed);
        case 'mixed': return generate_mixed($count, $difficulty, $seed);
        default: return generate_mixed($count, $difficulty, $seed);
    }
}

/**
 * 20문제 진단 세트 생성 (기초 5 + 전략 5 + 큰수 5 + 혼합 5)
 */
function generate_diagnosis_problems($seed = null) {
    $rng = new SeededRNG($seed);
    $actualSeed = $seed ?: 'diag_' . time();

    $basic = generate_basic(5, 1, "{$actualSeed}_b");
    $d2 = generate_divide2(1, 2, "{$actualSeed}_d2");
    $d4 = generate_divide4(1, 2, "{$actualSeed}_d4");
    $d5 = generate_divide5(1, 2, "{$actualSeed}_d5");
    $d8 = generate_divide8(1, 2, "{$actualSeed}_d8");
    $d10 = generate_divide10(1, 2, "{$actualSeed}_d10");
    $strategies = array_merge($d2, $d4, $d5, $d8, $d10);

    $large = generate_large_number(5, 2, "{$actualSeed}_l");
    $mixed = generate_mixed(5, 2, "{$actualSeed}_m");

    $combined = array_merge($basic, $strategies, $large, $mixed);
    $shuffled = $rng->shuffle($combined);

    foreach ($shuffled as $i => &$p) {
        $p['id'] = 'diag_p' . ($i + 1);
    }
    return $shuffled;
}

/**
 * 맞춤 훈련지 생성 (취약점 60% + 기초 20% + 복습 20%)
 */
function generate_worksheet_problems($weakestType, $count = 20, $difficulty = 2, $seed = null) {
    $actualSeed = $seed ?: 'ws_' . time();
    $weakCount = (int)round($count * 0.6);
    $basicCount = (int)round($count * 0.2);
    $reviewCount = $count - $weakCount - $basicCount;

    $weakProblems = generate_problems_by_type($weakestType, $weakCount, $difficulty, "{$actualSeed}_w");
    $basicProblems = generate_basic($basicCount, max(1, $difficulty - 1), "{$actualSeed}_b");
    $reviewProblems = generate_mixed($reviewCount, $difficulty, "{$actualSeed}_r");

    $combined = array_merge($weakProblems, $basicProblems, $reviewProblems);
    $rng = new SeededRNG($actualSeed);
    return $rng->shuffle($combined);
}

const PROBLEM_TYPE_LABELS = [
    'basic' => '기본 나눗셈',
    'divide2' => '÷2 자동화',
    'divide4' => '÷4 자동화',
    'divide5' => '÷5 자동화',
    'divide8' => '÷8 자동화',
    'divide10' => '÷10 자리값',
    'largeNumber' => '큰 수 처리',
    'mixed' => '초5 혼합 나눗셈'
];
