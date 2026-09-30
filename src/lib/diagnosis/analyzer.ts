import { ProblemType, PROBLEM_TYPE_LABELS } from "@/types/problem";
import {
  DiagnosisAnswerInput,
  DiagnosisResultSummary,
  TypeAnalysis,
} from "@/types/diagnosis";
import {
  calculateCompositeScore,
  calculateSpeedScore,
  determineStudentLevel,
  getMasteryStatus,
} from "./scoring";
import { generateRecommendation } from "./recommendations";

export function analyzeDiagnosis(answers: DiagnosisAnswerInput[]): DiagnosisResultSummary {
  const total = answers.length;
  if (total === 0) {
    throw new Error("답안 목록이 비어 있습니다.");
  }

  let totalCorrect = 0;
  let totalElapsedMs = 0;

  // 유형별 그룹핑
  const typeMap: Partial<Record<ProblemType, DiagnosisAnswerInput[]>> = {};

  for (const ans of answers) {
    if (ans.correct) totalCorrect++;
    totalElapsedMs += ans.elapsedMs;

    if (!typeMap[ans.type]) {
      typeMap[ans.type] = [];
    }
    typeMap[ans.type]!.push(ans);
  }

  const overallAccuracy = Math.round((totalCorrect / total) * 100);
  const averageMs = Math.round(totalElapsedMs / total);
  const averageSeconds = Number((averageMs / 1000).toFixed(1));
  const overallSpeedScore = calculateSpeedScore(averageMs);
  const finalScore = calculateCompositeScore(totalCorrect / total, overallSpeedScore);
  const studentLevel = determineStudentLevel(finalScore);

  // 유형별 세부 분석
  const typeAnalyses: Partial<Record<ProblemType, TypeAnalysis>> = {};
  const allTypes: ProblemType[] = [
    "basic",
    "divide2",
    "divide4",
    "divide5",
    "divide8",
    "divide10",
    "largeNumber",
    "mixed",
  ];

  let slowestType: ProblemType = "divide5";
  let maxAvgTime = -1;

  let mostErrorsType: ProblemType = "divide5";
  let maxErrors = -1;

  let weakestType: ProblemType = "divide5";
  let lowestCompositeScore = 999;

  for (const t of allTypes) {
    const list = typeMap[t] || [];
    if (list.length === 0) {
      // 해당 진단에 출제되지 않은 경우 기본 양호치 부여
      typeAnalyses[t] = {
        type: t,
        label: PROBLEM_TYPE_LABELS[t],
        total: 0,
        correct: 0,
        accuracy: 100,
        averageMs: 0,
        errorRate: 0,
        speedScore: 1.0,
        compositeScore: 100,
        status: "very_good",
        statusLabel: "미출제 / 양호",
      };
      continue;
    }

    const tCorrect = list.filter((a) => a.correct).length;
    const tErrors = list.length - tCorrect;
    const tTotalMs = list.reduce((sum, a) => sum + a.elapsedMs, 0);
    const tAvgMs = Math.round(tTotalMs / list.length);
    const tAccuracyRatio = tCorrect / list.length;
    const tAccuracy = Math.round(tAccuracyRatio * 100);
    const tErrorRate = Math.round((tErrors / list.length) * 100);
    const tSpeedScore = calculateSpeedScore(tAvgMs);
    const tCompositeScore = calculateCompositeScore(tAccuracyRatio, tSpeedScore);
    const { status, label } = getMasteryStatus(tCompositeScore, tAccuracyRatio, tAvgMs / 1000);

    typeAnalyses[t] = {
      type: t,
      label: PROBLEM_TYPE_LABELS[t],
      total: list.length,
      correct: tCorrect,
      accuracy: tAccuracy,
      averageMs: tAvgMs,
      errorRate: tErrorRate,
      speedScore: tSpeedScore,
      compositeScore: tCompositeScore,
      status,
      statusLabel: label,
    };

    // 최장 소요 유형 탐색
    if (tAvgMs > maxAvgTime) {
      maxAvgTime = tAvgMs;
      slowestType = t;
    }

    // 최다 오답 유형 탐색
    if (tErrors > maxErrors) {
      maxErrors = tErrors;
      mostErrorsType = t;
    }

    // 가장 취약한(종합 점수 최저) 유형 탐색
    if (tCompositeScore < lowestCompositeScore) {
      lowestCompositeScore = tCompositeScore;
      weakestType = t;
    }
  }

  // 만약 모든 문제가 맞았을 경우 가장 느린 유형이 곧 취약 유형
  if (maxErrors <= 0) {
    mostErrorsType = slowestType;
    weakestType = slowestType;
  }

  const recommendedTraining = generateRecommendation(
    weakestType,
    slowestType,
    mostErrorsType,
    typeAnalyses as Record<ProblemType, TypeAnalysis>,
    overallAccuracy
  );

  return {
    total,
    correct: totalCorrect,
    accuracy: overallAccuracy,
    averageMs,
    averageSeconds,
    finalScore,
    studentLevel,
    weakestType,
    slowestType,
    mostErrorsType,
    typeAnalyses: typeAnalyses as Record<ProblemType, TypeAnalysis>,
    recommendedTraining,
    detailedAnswers: answers,
  };
}
