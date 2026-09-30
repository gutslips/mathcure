import { Problem } from "@/types/problem";
import { createRNG, getRandomInt, shuffle, validateProblem, formatNumber } from "./utils";

export function generateDivide4(count: number, difficulty: number = 1, seed?: string): Problem[] {
  const rng = createRNG(seed);
  const problems: Problem[] = [];
  const usedKeys = new Set<string>();

  // diff 1: 40 ~ 200 (몫 10~50) 예: 40÷4, 80÷4, 120÷4, 160÷4
  // diff 2: 200 ~ 1,000 (몫 60~250) 예: 240÷4, 320÷4, 480÷4, 840÷4
  // diff 3: 1,000 ~ 10,000 (몫 300~2400) 예: 1200÷4, 2400÷4, 3600÷4, 9600÷4
  let attempts = 0;
  while (problems.length < count && attempts < 2000) {
    attempts++;
    let quotient = 0;
    if (difficulty === 1) {
      quotient = getRandomInt(1, attempts > 100 ? 50 : 20, rng) * (attempts > 150 ? 1 : 5);
    } else if (difficulty === 2) {
      quotient = getRandomInt(6, attempts > 100 ? 100 : 25, rng) * (attempts > 150 ? 5 : 10);
    } else {
      quotient = getRandomInt(25, 500, rng) * 10;
    }

    const divisor = 4;
    const dividend = divisor * quotient;
    const key = `${dividend}/${divisor}`;

    if (usedKeys.has(key) && attempts < 1500) continue;
    usedKeys.add(key);

    const problem: Problem = {
      id: `div4_${dividend}_${divisor}_${problems.length}`,
      question: `${formatNumber(dividend)} ÷ ${divisor}`,
      dividend,
      divisor,
      answer: quotient,
      type: "divide4",
      difficulty,
      hint: "÷4는 2로 나누고(절반), 한 번 더 2로 나누면(절반의 절반) 쉽게 계산할 수 있어요!",
    };

    if (validateProblem(problem)) {
      problems.push(problem);
    }
  }

  return shuffle(problems, rng);
}
