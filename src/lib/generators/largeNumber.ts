import { Problem } from "@/types/problem";
import { createRNG, getRandomInt, shuffle, validateProblem, formatNumber } from "./utils";

export function generateLargeNumber(count: number, difficulty: number = 2, seed?: string): Problem[] {
  const rng = createRNG(seed);
  const problems: Problem[] = [];
  const usedKeys = new Set<string>();

  // 큰 수 패턴: 1,200 ÷ 4, 2,500 ÷ 5, 3,200 ÷ 8, 4,800 ÷ 6, 8,000 ÷ 5 등
  const seedPairs = [
    { dividend: 1200, divisor: 4 },
    { dividend: 2500, divisor: 5 },
    { dividend: 3200, divisor: 8 },
    { dividend: 4800, divisor: 6 },
    { dividend: 8000, divisor: 5 },
    { dividend: 1800, divisor: 3 },
    { dividend: 3600, divisor: 6 },
    { dividend: 4200, divisor: 7 },
    { dividend: 5400, divisor: 9 },
    { dividend: 6300, divisor: 7 },
    { dividend: 7200, divisor: 8 },
    { dividend: 5600, divisor: 8 },
    { dividend: 4900, divisor: 7 },
    { dividend: 8100, divisor: 9 },
    { dividend: 9600, divisor: 4 },
  ];

  for (const pair of shuffle(seedPairs, rng)) {
    if (problems.length >= count) break;
    const quotient = pair.dividend / pair.divisor;
    const key = `${pair.dividend}/${pair.divisor}`;
    if (usedKeys.has(key)) continue;
    usedKeys.add(key);

    const problem: Problem = {
      id: `large_${pair.dividend}_${pair.divisor}_${problems.length}`,
      question: `${formatNumber(pair.dividend)} ÷ ${pair.divisor}`,
      dividend: pair.dividend,
      divisor: pair.divisor,
      answer: quotient,
      type: "largeNumber",
      difficulty,
      hint: "앞의 두 자리 숫자를 먼저 나누고 뒤의 0 개수를 알맞게 붙여보세요.",
    };

    if (validateProblem(problem)) {
      problems.push(problem);
    }
  }

  let attempts = 0;
  while (problems.length < count && attempts < 2000) {
    attempts++;
    const divisors = [2, 3, 4, 5, 6, 7, 8, 9, 10];
    const divisor = divisors[getRandomInt(0, divisors.length - 1, rng)];
    const basicDividend = divisor * getRandomInt(2, 50, rng);
    const multiplier = difficulty === 1 ? 10 : difficulty === 2 ? 100 : 1000;
    const dividend = basicDividend * multiplier;
    const key = `${dividend}/${divisor}`;

    if (usedKeys.has(key) && attempts < 1500) continue;
    usedKeys.add(key);

    const problem: Problem = {
      id: `large_${dividend}_${divisor}_${problems.length}`,
      question: `${formatNumber(dividend)} ÷ ${divisor}`,
      dividend,
      divisor,
      answer: dividend / divisor,
      type: "largeNumber",
      difficulty,
      hint: "앞의 두 자리 숫자를 먼저 나누고 뒤의 0 개수를 알맞게 붙여보세요.",
    };

    if (validateProblem(problem)) {
      problems.push(problem);
    }
  }

  return shuffle(problems, rng);
}
