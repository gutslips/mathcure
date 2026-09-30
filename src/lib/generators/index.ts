import { Problem, ProblemType } from "@/types/problem";
import { generateBasic } from "./basic";
import { generateDivide2 } from "./divide2";
import { generateDivide4 } from "./divide4";
import { generateDivide5 } from "./divide5";
import { generateDivide8 } from "./divide8";
import { generateDivide10 } from "./divide10";
import { generateLargeNumber } from "./largeNumber";
import { generateMixed } from "./mixed";
import { createRNG, shuffle } from "./utils";

export {
  generateBasic,
  generateDivide2,
  generateDivide4,
  generateDivide5,
  generateDivide8,
  generateDivide10,
  generateLargeNumber,
  generateMixed,
};

/**
 * 특정 유형의 문제를 지정된 개수만큼 생성
 */
export function generateProblemsByType(
  type: ProblemType,
  count: number,
  difficulty: number = 2,
  seed?: string
): Problem[] {
  switch (type) {
    case "basic":
      return generateBasic(count, difficulty, seed);
    case "divide2":
      return generateDivide2(count, difficulty, seed);
    case "divide4":
      return generateDivide4(count, difficulty, seed);
    case "divide5":
      return generateDivide5(count, difficulty, seed);
    case "divide8":
      return generateDivide8(count, difficulty, seed);
    case "divide10":
      return generateDivide10(count, difficulty, seed);
    case "largeNumber":
      return generateLargeNumber(count, difficulty, seed);
    case "mixed":
      return generateMixed(count, difficulty, seed);
    default:
      return generateMixed(count, difficulty, seed);
  }
}

/**
 * 명세 6: 기본 20문제 진단 세트 생성
 * - 기초 나눗셈 (basic): 5문제
 * - 전략 자동화 (divide2, divide4, divide5, divide8, divide10 중): 5문제
 * - 큰 수 (largeNumber): 5문제
 * - 초5 혼합 (mixed): 5문제
 */
export function generateDiagnosisProblems(seed?: string): Problem[] {
  const rng = createRNG(seed);
  const actualSeed = seed || `diag_${Date.now()}`;

  const basic = generateBasic(5, 1, `${actualSeed}_basic`);
  
  // 전략 자동화 5문제 (÷2, ÷4, ÷5, ÷8, ÷10 각 1문제씩 골고루 배정)
  const d2 = generateDivide2(1, 2, `${actualSeed}_d2`);
  const d4 = generateDivide4(1, 2, `${actualSeed}_d4`);
  const d5 = generateDivide5(1, 2, `${actualSeed}_d5`);
  const d8 = generateDivide8(1, 2, `${actualSeed}_d8`);
  const d10 = generateDivide10(1, 2, `${actualSeed}_d10`);
  const strategies = [...d2, ...d4, ...d5, ...d8, ...d10];

  const large = generateLargeNumber(5, 2, `${actualSeed}_large`);
  const mixed = generateMixed(5, 2, `${actualSeed}_mixed`);

  const combined = [...basic, ...strategies, ...large, ...mixed];

  // 순서 셔플하여 특정 유형이 한 번에 몰리지 않도록 함
  return shuffle(combined, rng).map((p, idx) => ({
    ...p,
    id: `diag_p${idx + 1}`,
  }));
}
