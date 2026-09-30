import { Problem } from "@/types/problem";
import { createRNG, getRandomInt, shuffle, validateProblem, formatNumber } from "./utils";

export function generateDivide10(count: number, difficulty: number = 1, seed?: string): Problem[] {
  const rng = createRNG(seed);
  const problems: Problem[] = [];
  const usedKeys = new Set<string>();

  // diff 1: 100 ~ 900 (예: 240÷10, 350÷10, 720÷10)
  // diff 2: 1,000 ~ 9,900 (예: 1200÷10, 4500÷10, 8400÷10)
  // diff 3: 10,000 ~ 99,000
  let attempts = 0;
  while (problems.length < count && attempts < 500) {
    attempts++;
    let quotient = 0;
    if (difficulty === 1) {
      quotient = getRandomInt(12, 99, rng);
    } else if (difficulty === 2) {
      quotient = getRandomInt(100, 990, rng);
    } else {
      quotient = getRandomInt(1000, 9900, rng);
    }

    const divisor = 10;
    const dividend = divisor * quotient;
    const key = `${dividend}/${divisor}`;

    if (usedKeys.has(key)) continue;
    usedKeys.add(key);

    const problem: Problem = {
      id: `div10_${dividend}_${divisor}_${problems.length}`,
      question: `${formatNumber(dividend)} ÷ ${divisor}`,
      dividend,
      divisor,
      answer: quotient,
      type: "divide10",
      difficulty,
      hint: "÷10은 끝의 0을 하나 지워주면 답이 바로 나옵니다.",
    };

    if (validateProblem(problem)) {
      problems.push(problem);
    }
  }

  return shuffle(problems, rng);
}
