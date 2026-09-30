import { Problem } from "@/types/problem";
import { createRNG, getRandomInt, shuffle, validateProblem, formatNumber } from "./utils";

export function generateBasic(count: number, difficulty: number = 1, seed?: string): Problem[] {
  const rng = createRNG(seed);
  const problems: Problem[] = [];
  const usedKeys = new Set<string>();

  // 구구단 기반 나눗셈 풀
  // divisor: 2 ~ 9 (difficulty에 따라 분기)
  const divisors = difficulty === 1 
    ? [2, 3, 4, 5, 6] 
    : difficulty === 2 
    ? [4, 5, 6, 7, 8, 9] 
    : [6, 7, 8, 9, 10];

  const minQuotient = difficulty === 1 ? 2 : difficulty === 2 ? 4 : 5;
  const maxQuotient = difficulty === 1 ? 6 : difficulty === 2 ? 9 : 10;

  let attempts = 0;
  while (problems.length < count && attempts < 2000) {
    attempts++;
    // count가 크거나 시도가 많아지면 제수와 몫 범위를 자연스럽게 확장
    const curDivisors = attempts > 200 ? [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12] : divisors;
    const curMinQ = attempts > 200 ? 2 : minQuotient;
    const curMaxQ = attempts > 200 ? 20 : maxQuotient;

    const divisor = curDivisors[getRandomInt(0, curDivisors.length - 1, rng)];
    const quotient = getRandomInt(curMinQ, curMaxQ, rng);
    const dividend = divisor * quotient;
    const key = `${dividend}/${divisor}`;

    if (usedKeys.has(key) && attempts < 1500) continue;
    usedKeys.add(key);

    const problem: Problem = {
      id: `basic_${dividend}_${divisor}_${problems.length}`,
      question: `${formatNumber(dividend)} ÷ ${divisor}`,
      dividend,
      divisor,
      answer: quotient,
      type: "basic",
      difficulty,
      hint: `${divisor}단 구구단을 떠올려 보세요. ${divisor} × ? = ${dividend}`,
    };

    if (validateProblem(problem)) {
      problems.push(problem);
    }
  }

  return shuffle(problems, rng);
}
