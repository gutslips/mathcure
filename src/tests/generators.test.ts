import { describe, it, expect } from "vitest";
import {
  generateBasic,
  generateDivide2,
  generateDivide4,
  generateDivide5,
  generateDivide8,
  generateDivide10,
  generateLargeNumber,
  generateMixed,
  generateDiagnosisProblems,
} from "@/lib/generators";
import { validateProblem } from "@/lib/generators/utils";

describe("Division Problem Generators", () => {
  it("generates valid basic division problems", () => {
    const problems = generateBasic(20, 1);
    expect(problems.length).toBe(20);
    problems.forEach((p) => {
      expect(validateProblem(p)).toBe(true);
      expect(Number.isInteger(p.answer)).toBe(true);
      expect(p.answer).toBeGreaterThan(0);
      expect(p.dividend / p.divisor).toBe(p.answer);
    });
  });

  it("satisfies specification for divide5 problems", () => {
    const problems = generateDivide5(20, 2, "test_seed_5");
    expect(problems.length).toBe(20);
    problems.forEach((p) => {
      expect(p.divisor).toBe(5);
      expect(validateProblem(p)).toBe(true);
      expect(p.dividend % 5).toBe(0);
      expect(p.dividend / 5).toBe(p.answer);
    });

    // 샘플 계산 검증
    expect(8000 / 5).toBe(1600);
    expect(2500 / 5).toBe(500);
    expect(6500 / 5).toBe(1300);
  });

  it("satisfies specification for divide8 problems", () => {
    const problems = generateDivide8(15, 2, "test_seed_8");
    expect(problems.length).toBe(15);
    problems.forEach((p) => {
      expect(p.divisor).toBe(8);
      expect(validateProblem(p)).toBe(true);
      expect(p.dividend % 8).toBe(0);
      expect(p.dividend / 8).toBe(p.answer);
    });

    expect(800 / 8).toBe(100);
    expect(1600 / 8).toBe(200);
    expect(6400 / 8).toBe(800);
  });

  it("generates bulk 1,000 problems with 100% validity and integer answers", () => {
    // 1000개 문제 생성 검증 (대규모 스트레스 테스트)
    const allGenerators = [
      generateBasic,
      generateDivide2,
      generateDivide4,
      generateDivide5,
      generateDivide8,
      generateDivide10,
      generateLargeNumber,
      generateMixed,
    ];

    let totalTested = 0;
    for (const gen of allGenerators) {
      const set = gen(125, 2, `bulk_test_${totalTested}`);
      totalTested += set.length;
      set.forEach((p) => {
        expect(validateProblem(p)).toBe(true);
        expect(p.dividend % p.divisor).toBe(0);
        expect(p.answer).toBe(p.dividend / p.divisor);
      });
    }

    expect(totalTested).toBe(1000);
  });

  it("generates diagnosis set with exact 20 problems and balanced quotas", () => {
    const diag = generateDiagnosisProblems("diag_fixed_seed_123");
    expect(diag.length).toBe(20);

    const typeCounts: Record<string, number> = {};
    diag.forEach((p) => {
      typeCounts[p.type] = (typeCounts[p.type] || 0) + 1;
      expect(validateProblem(p)).toBe(true);
    });

    // 기본 5문제, 큰 수 5문제, 초5혼합 5문제, 전략 5문제
    expect(typeCounts["basic"]).toBe(5);
    expect(typeCounts["largeNumber"]).toBe(5);
    expect(typeCounts["mixed"]).toBe(5);
    const strategySum =
      (typeCounts["divide2"] || 0) +
      (typeCounts["divide4"] || 0) +
      (typeCounts["divide5"] || 0) +
      (typeCounts["divide8"] || 0) +
      (typeCounts["divide10"] || 0);
    expect(strategySum).toBe(5);
  });

  it("reproduces identical problems with same seed", () => {
    const seed = "math_cure_seed_2026";
    const run1 = generateDiagnosisProblems(seed);
    const run2 = generateDiagnosisProblems(seed);

    expect(run1.map((p) => p.question)).toEqual(run2.map((p) => p.question));
    expect(run1.map((p) => p.answer)).toEqual(run2.map((p) => p.answer));
  });
});
