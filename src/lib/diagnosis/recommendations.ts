import { ProblemType, PROBLEM_TYPE_LABELS } from "@/types/problem";
import { RecommendedTraining, TypeAnalysis } from "@/types/diagnosis";

export function generateRecommendation(
  weakestType: ProblemType,
  slowestType: ProblemType,
  mostErrorsType: ProblemType,
  typeAnalyses: Record<ProblemType, TypeAnalysis>,
  overallAccuracy: number
): RecommendedTraining {
  // 우선순위 결정: 오답률이 높으면 오답 우선, 정확도는 높은데 시간이 길면 자동화 우선
  const targetType = typeAnalyses[mostErrorsType]?.errorRate > 0 ? mostErrorsType : slowestType;
  const targetAnalysis = typeAnalyses[targetType] || typeAnalyses[weakestType];

  let title = `${PROBLEM_TYPE_LABELS[targetType]} 맞춤 훈련`;
  let reason = "";
  let strategyHint = "";
  let sampleProblems: string[] = [];
  let difficulty = 2;

  // 유형별 특화 전략 가이드
  switch (targetType) {
    case "divide5":
      title = "÷5 큰 수 자동화 훈련";
      reason =
        targetAnalysis.accuracy < 80
          ? "÷5 연산에서 오답이 발생하여 나눗셈 원리와 자리값 점검이 필요합니다."
          : `평균 ${(targetAnalysis.averageMs / 1000).toFixed(1)}초로 정답은 맞히지만 자동화 속도가 느립니다.`;
      strategyHint =
        "💡 계산 꿀팁: 어떤 수를 5로 나눌 때는 먼저 2배(×2)를 하고, 10으로 나누면(끝의 0 제거) 훨씬 빨라집니다! (예: 8,000 × 2 = 16,000 → 1,600)";
      sampleProblems = [
        "800 ÷ 5 = 160",
        "1,200 ÷ 5 = 240",
        "2,500 ÷ 5 = 500",
        "4,500 ÷ 5 = 900",
        "8,000 ÷ 5 = 1,600",
      ];
      difficulty = targetAnalysis.accuracy < 70 ? 1 : 2;
      break;

    case "divide8":
      title = "÷8 반의 반의 반 전략 훈련";
      reason = `÷8 연산에서 계산 시간이 다소 지체되거나 오답이 발생했습니다 (평균 ${(targetAnalysis.averageMs / 1000).toFixed(1)}초).`;
      strategyHint =
        "💡 계산 꿀팁: 8로 나눌 때는 절반을 3번 연속으로 구하면 암산이 쉬워집니다. (÷2 → ÷2 → ÷2) 예: 2,400 → 1,200 → 600 → 300";
      sampleProblems = [
        "800 ÷ 8 = 100",
        "1,600 ÷ 8 = 200",
        "3,200 ÷ 8 = 400",
        "4,800 ÷ 8 = 600",
        "6,400 ÷ 8 = 800",
      ];
      difficulty = 2;
      break;

    case "divide4":
      title = "÷4 절반의 절반 훈련";
      reason = "÷4 연산의 즉각적인 암산 자동화 훈련이 필요합니다.";
      strategyHint = "💡 계산 꿀팁: 4로 나눌 때는 2로 나누고 다시 한 번 2로 나누세요. (예: 480 → 240 → 120)";
      sampleProblems = [
        "240 ÷ 4 = 60",
        "480 ÷ 4 = 120",
        "1,200 ÷ 4 = 300",
        "2,400 ÷ 4 = 600",
        "3,600 ÷ 4 = 900",
      ];
      difficulty = 2;
      break;

    case "largeNumber":
      title = "큰 수 자리값 처리 훈련";
      reason = "천 단위 이상의 큰 수 나눗셈에서 0의 개수와 자리값 처리에 집중 훈련이 필요합니다.";
      strategyHint = "💡 계산 꿀팁: 앞의 유효 숫자끼리 먼저 구구단으로 나눈 후, 남은 0의 개수를 자릿수에 맞게 붙이세요.";
      sampleProblems = [
        "1,200 ÷ 4 = 300",
        "2,500 ÷ 5 = 500",
        "3,200 ÷ 8 = 400",
        "4,800 ÷ 6 = 800",
        "8,000 ÷ 5 = 1,600",
      ];
      difficulty = 2;
      break;

    default:
      title = `${PROBLEM_TYPE_LABELS[targetType]} 집중 보강 훈련`;
      reason = "전반적인 계산 정확도와 풀이 속도 자동화를 위해 맞춤형 반복 훈련이 권장됩니다.";
      strategyHint = "💡 계산 꿀팁: 구구단 곱셈 사실을 거꾸로 떠올리며 자리값을 신속히 계산해보세요.";
      sampleProblems = [
        "240 ÷ 4 = 60",
        "350 ÷ 5 = 70",
        "480 ÷ 8 = 60",
        "720 ÷ 9 = 80",
        "800 ÷ 5 = 160",
      ];
      difficulty = overallAccuracy > 80 ? 2 : 1;
      break;
  }

  return {
    type: targetType,
    typeLabel: PROBLEM_TYPE_LABELS[targetType],
    difficulty,
    title,
    reason,
    strategyHint,
    sampleProblems,
    compositionRatio: {
      targetType,
      targetRatio: 60, // 약점 60%
      basicRatio: 20,  // 기본 20%
      otherRatio: 20,  // 기타 복습 20%
    },
  };
}
