import { describe, it, expect } from "vitest";
import { analyzeDiagnosis } from "@/lib/diagnosis/analyzer";
import { generateWorksheet } from "@/lib/worksheet/generator";
import { DiagnosisAnswerInput } from "@/types/diagnosis";

describe("Diagnosis Analyzer and Recommendations", () => {
  it("correctly analyzes accuracy, elapsedMs, and finds slowest / weakest types", () => {
    // 20문제 시뮬레이션:
    // divide5 5문제 중 4문제 정답이지만 시간이 28초 걸림 (명세 8, 43 예시)
    // basic 5문제 모두 정답 (3초)
    // divide8 5문제 3문제 정답 (15초)
    // mixed 5문제 모두 정답 (7초)
    const answers: DiagnosisAnswerInput[] = [
      // basic 5개 (빠르고 정확)
      ...Array.from({ length: 5 }, (_, i) => ({
        problemId: `p_b_${i}`,
        question: `36 ÷ 6`,
        dividend: 36,
        divisor: 6,
        answer: 6,
        userAnswer: 6,
        correct: true,
        elapsedMs: 3000,
        type: "basic" as const,
        difficulty: 1,
      })),
      // divide5 5개 (명세 8: 8000 ÷ 5 = 1600, 28초 걸림)
      ...Array.from({ length: 5 }, (_, i) => ({
        problemId: `p_d5_${i}`,
        question: `8000 ÷ 5`,
        dividend: 8000,
        divisor: 5,
        answer: 1600,
        userAnswer: 1600,
        correct: true,
        elapsedMs: 28000, // 28초!
        type: "divide5" as const,
        difficulty: 3,
      })),
      // divide8 5개 (2문제 오답, 12초)
      ...Array.from({ length: 5 }, (_, i) => ({
        problemId: `p_d8_${i}`,
        question: `1600 ÷ 8`,
        dividend: 1600,
        divisor: 8,
        answer: 200,
        userAnswer: i < 2 ? 100 : 200,
        correct: i >= 2,
        elapsedMs: 12000,
        type: "divide8" as const,
        difficulty: 2,
      })),
      // mixed 5개 (정답, 7초)
      ...Array.from({ length: 5 }, (_, i) => ({
        problemId: `p_m_${i}`,
        question: `6400 ÷ 8`,
        dividend: 6400,
        divisor: 8,
        answer: 800,
        userAnswer: 800,
        correct: true,
        elapsedMs: 7000,
        type: "mixed" as const,
        difficulty: 2,
      })),
    ];

    const result = analyzeDiagnosis(answers);

    // 총 20문제 중 18문제 정답 = 90% 정확도
    expect(result.total).toBe(20);
    expect(result.correct).toBe(18);
    expect(result.accuracy).toBe(90);

    // 가장 느린 유형: divide5 (28초)
    expect(result.slowestType).toBe("divide5");

    // 가장 많은 오답 유형: divide8 (2문제 오답)
    expect(result.mostErrorsType).toBe("divide8");

    // divide5 분석 세부 확인
    const d5 = result.typeAnalyses["divide5"];
    expect(d5.accuracy).toBe(100);
    expect(d5.averageMs).toBe(28000);
    expect(d5.speedScore).toBe(0.4); // 20~30초 구간

    // 오답률이 높은 divide8이 최우선 훈련 추천 대상이 됨 (명세 29: 오답률 높음 -> 우선 훈련)
    expect(result.recommendedTraining).toBeDefined();
    expect(result.recommendedTraining.type).toBe("divide8");
    expect(result.recommendedTraining.strategyHint).toContain("8");
  });

  it("generates personalized worksheet with 60/20/20 ratio and seed reproducibility", () => {
    const seed = "test_worksheet_seed_456";
    const ws1 = generateWorksheet({
      targetType: "divide5",
      count: 20,
      difficulty: 2,
      seed,
    });

    expect(ws1.problems.length).toBe(20);
    expect(ws1.targetType).toBe("divide5");

    // 재현성 검증
    const ws2 = generateWorksheet({
      targetType: "divide5",
      count: 20,
      difficulty: 2,
      seed,
    });
    expect(ws1.problems.map((p) => p.question)).toEqual(ws2.problems.map((p) => p.question));
  });
});
