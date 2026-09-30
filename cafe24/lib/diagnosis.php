<?php
/**
 * MathCure - Diagnostic Analysis & Scoring in PHP
 */

require_once __DIR__ . '/generators.php';

function calculate_speed_score($elapsedMs) {
    $seconds = $elapsedMs / 1000;
    if ($seconds <= 5) return 1.0;
    if ($seconds <= 10) return 0.8;
    if ($seconds <= 20) return 0.6;
    if ($seconds <= 30) return 0.4;
    return 0.2;
}

function calculate_composite_score($accuracyRatio, $speedScore) {
    $score = $accuracyRatio * 0.7 + $speedScore * 0.3;
    return (int)round($score * 100);
}

function get_mastery_status($compositeScore, $accuracyRatio, $avgSeconds) {
    if ($accuracyRatio >= 0.9 && $avgSeconds <= 6) {
        return ['status' => 'very_good', 'label' => '매우 좋음'];
    }
    if ($compositeScore >= 75) {
        return ['status' => 'good', 'label' => '양호'];
    }
    if ($compositeScore >= 55) {
        return ['status' => 'needs_improvement', 'label' => '개선 필요'];
    }
    return ['status' => 'critical', 'label' => '집중 훈련 필요'];
}

function determine_student_level($finalScore) {
    if ($finalScore >= 90) return 'Level 4 (심화 자동화)';
    if ($finalScore >= 75) return 'Level 3 (응용 숙달)';
    if ($finalScore >= 60) return 'Level 2 (기본 숙달)';
    return 'Level 1 (원리 집중)';
}

function generate_recommendations($weakestType, $slowestType, $mostErrorsType, $typeAnalyses, $overallAccuracy) {
    $weakLabel = PROBLEM_TYPE_LABELS[$weakestType] ?? $weakestType;
    $slowLabel = PROBLEM_TYPE_LABELS[$slowestType] ?? $slowestType;
    $errorLabel = PROBLEM_TYPE_LABELS[$mostErrorsType] ?? $mostErrorsType;

    $dailyPlan = [
        '1일차: 핵심 원리 및 곱셈 역연산 관계 이해 (10문제)',
        '2일차: 절반 및 자리값 전략 집중 적용 훈련 (15문제)',
        '3일차: 제한 시간(문제당 7초) 타이머 스피드 훈련 (20문제)',
        '4일차: 실전 혼합 문제와 복습 맞춤 훈련지 풀이 (20문제)',
        '5일차: 성취도 재진단 및 오답 완벽 정리 (20문제)'
    ];

    $strategies = [
        'divide2' => '짝수의 절반 구하기 전략을 반복하여 직관적인 나눗셈 감각을 키웁니다.',
        'divide4' => '한 번에 4로 나누지 말고, 2로 두 번 연속 나누는 전략을 연습합니다.',
        'divide5' => '5로 나누기는 "2를 곱하고 10으로 나누기" 자리값 전환을 훈련합니다.',
        'divide8' => '8로 나누기는 2로 세 번 나누기 전략을 통해 암산 부담을 덜어줍니다.',
        'divide10' => '끝자리 0을 지우는 자릿수 이동 원리를 집중적으로 복습합니다.',
        'largeNumber' => '천 단위 이상의 큰 수는 0을 잠시 떼어두고 앞자리 계산 후 0을 붙입니다.',
        'mixed' => '두 자리 수 나눗셈은 몫을 어림(어림수 10단위)하여 계산하는 습관을 들입니다.',
        'basic' => '구구단 역연산이 즉각적으로 튀어나오도록 3초 이내 응답 훈련을 진행합니다.'
    ];

    return [
        'focusType' => $weakestType,
        'title' => "{$weakLabel} 집중 강화 처방",
        'description' => "최근 진단 분석 결과, [{$weakLabel}] 영역의 성취율 및 계산 속도 보완이 가장 시급합니다.",
        'recommendedAction' => $strategies[$weakestType] ?? '맞춤 훈련지를 인쇄하여 매일 20문제씩 꾸준히 훈련하세요.',
        'dailyPlan' => $dailyPlan
    ];
}

function analyze_diagnosis(array $answers) {
    $total = count($answers);
    if ($total === 0) {
        throw new InvalidArgumentException("답안 목록이 비어 있습니다.");
    }

    $totalCorrect = 0;
    $totalElapsedMs = 0;
    $typeMap = [];

    foreach ($answers as $ans) {
        if (!empty($ans['correct'])) $totalCorrect++;
        $totalElapsedMs += (int)($ans['elapsedMs'] ?? 0);
        $t = $ans['type'] ?? 'mixed';
        if (!isset($typeMap[$t])) $typeMap[$t] = [];
        $typeMap[$t][] = $ans;
    }

    $overallAccuracy = (int)round(($totalCorrect / $total) * 100);
    $averageMs = (int)round($totalElapsedMs / $total);
    $averageSeconds = number_format($averageMs / 1000, 1);
    $overallSpeedScore = calculate_speed_score($averageMs);
    $finalScore = calculate_composite_score($totalCorrect / $total, $overallSpeedScore);
    $studentLevel = determine_student_level($finalScore);

    $allTypes = ['basic', 'divide2', 'divide4', 'divide5', 'divide8', 'divide10', 'largeNumber', 'mixed'];
    $typeAnalyses = [];

    $slowestType = 'divide5';
    $maxAvgTime = -1;

    $mostErrorsType = 'divide5';
    $maxErrors = -1;

    $weakestType = 'divide5';
    $lowestCompositeScore = 999;

    foreach ($allTypes as $t) {
        $list = $typeMap[$t] ?? [];
        if (empty($list)) {
            $typeAnalyses[$t] = [
                'type' => $t,
                'label' => PROBLEM_TYPE_LABELS[$t] ?? $t,
                'total' => 0,
                'correct' => 0,
                'accuracy' => 100,
                'averageMs' => 0,
                'errorRate' => 0,
                'compositeScore' => 100,
                'status' => 'very_good',
                'statusLabel' => '미출제 / 양호'
            ];
            continue;
        }

        $tCorrect = count(array_filter($list, fn($a) => !empty($a['correct'])));
        $tErrors = count($list) - $tCorrect;
        $tTotalMs = array_sum(array_column($list, 'elapsedMs'));
        $tAvgMs = (int)round($tTotalMs / count($list));
        $tAccuracyRatio = $tCorrect / count($list);
        $tAccuracy = (int)round($tAccuracyRatio * 100);
        $tErrorRate = (int)round(($tErrors / count($list)) * 100);
        $tSpeedScore = calculate_speed_score($tAvgMs);
        $tCompositeScore = calculate_composite_score($tAccuracyRatio, $tSpeedScore);
        $mastery = get_mastery_status($tCompositeScore, $tAccuracyRatio, $tAvgMs / 1000);

        $typeAnalyses[$t] = [
            'type' => $t,
            'label' => PROBLEM_TYPE_LABELS[$t] ?? $t,
            'total' => count($list),
            'correct' => $tCorrect,
            'accuracy' => $tAccuracy,
            'averageMs' => $tAvgMs,
            'errorRate' => $tErrorRate,
            'compositeScore' => $tCompositeScore,
            'status' => $mastery['status'],
            'statusLabel' => $mastery['label']
        ];

        if ($tAvgMs > $maxAvgTime) {
            $maxAvgTime = $tAvgMs;
            $slowestType = $t;
        }
        if ($tErrors > $maxErrors) {
            $maxErrors = $tErrors;
            $mostErrorsType = $t;
        }
        if ($tCompositeScore < $lowestCompositeScore) {
            $lowestCompositeScore = $tCompositeScore;
            $weakestType = $t;
        }
    }

    if ($maxErrors <= 0) {
        $mostErrorsType = $slowestType;
        $weakestType = $slowestType;
    }

    $recommendation = generate_recommendations($weakestType, $slowestType, $mostErrorsType, $typeAnalyses, $overallAccuracy);

    return [
        'total' => $total,
        'correct' => $totalCorrect,
        'accuracy' => $overallAccuracy,
        'averageMs' => $averageMs,
        'averageSeconds' => $averageSeconds,
        'finalScore' => $finalScore,
        'studentLevel' => $studentLevel,
        'weakestType' => $weakestType,
        'slowestType' => $slowestType,
        'mostErrorsType' => $mostErrorsType,
        'typeAnalyses' => $typeAnalyses,
        'recommendedTraining' => $recommendation,
        'detailedAnswers' => $answers
    ];
}
