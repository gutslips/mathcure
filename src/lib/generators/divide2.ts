import { Problem } from "@/types/problem";
import { createRNG, getRandomInt, shuffle, validateProblem, formatNumber } from "./utils";

export function generateDivide2(count: number, difficulty: number = 1, seed?: string): Problem[] {
  const rng = createRNG(seed);
  const problems: Problem[] = [];
  const usedKeys = new Set<string>();

  // 난이도별 몫 범위
  // diff 1: 몫 10~50 (dividend 20~100)
  // diff 2: 몫 50~450 (dividend 100~900, 10단위)
  // diff 3: 몫 500~4500 (dividend 1,000~9,000, 100단위)
  let attempts = 0;
  while (problems.length < count && attempts < 2000) {
    attempts++;
    let quotient = 0;
    if (difficulty === 1) {
      quotient = getRandomInt(10, attempts > 100 ? 99 : 49, rng);
    } else if (difficulty === 2) {
      quotient = getRandomInt(10, attempts > 100 ? 500 : 90, rng) * (attempts > 200 ? 1 : 5);
    } else {
      quotient = getRandomInt(10, 500, rng) * 10;
    }

    const divisor = 2;
    const dividend = divisor * quotient;
    const key = `${dividend}/${divisor}`;

    if (usedKeys.has(key) && attempts < 1500) continue;
    usedKeys.add(key);

    const problem: Problem = {
      id: `div2_${dividend}_${divisor}_${problems.length}`,
      question: `${formatNumber(dividend)} ÷ ${divisor}`,
      dividend,
      divisor,
      answer: quotient,
      type: "divide2",
      difficulty,
      hint: "÷2는 어떤 수의 절반(반토막)을 구하는 것과 같아요.",
    };

    if (validateProblem(problem)) {
      problems.push(problem);
    }
  }

  return shuffle(problems, rng);
}
