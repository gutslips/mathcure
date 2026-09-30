import { Problem } from "@/types/problem";
import { createRNG, getRandomInt, shuffle, validateProblem, formatNumber } from "./utils";

export function generateMixed(count: number, difficulty: number = 2, seed?: string): Problem[] {
  const rng = createRNG(seed);
  const problems: Problem[] = [];
  const usedKeys = new Set<string>();

  // 실생활 및 초5 표준 문제 패턴
  const seedPairs = [
    { dividend: 6400, divisor: 8 },
    { dividend: 7200, divisor: 9 },
    { dividend: 4500, divisor: 5 },
    { dividend: 9600, divisor: 4 },
    { dividend: 8400, divisor: 7 },
    { dividend: 5600, divisor: 8 },
    { dividend: 4800, divisor: 6 },
    { dividend: 6300, divisor: 7 },
    { dividend: 3600, divisor: 4 },
    { dividend: 5400, divisor: 6 },
    { dividend: 3500, divisor: 5 },
    { dividend: 4200, divisor: 6 },
    { dividend: 8100, divisor: 9 },
    { dividend: 4900, divisor: 7 },
  ];

  for (const pair of shuffle(seedPairs, rng)) {
    if (problems.length >= count) break;
    const quotient = pair.dividend / pair.divisor;
    const key = `${pair.dividend}/${pair.divisor}`;
    if (usedKeys.has(key)) continue;
    usedKeys.add(key);

    const problem: Problem = {
      id: `mixed_${pair.dividend}_${pair.divisor}_${problems.length}`,
      question: `${formatNumber(pair.dividend)} ÷ ${pair.divisor}`,
      dividend: pair.dividend,
      divisor: pair.divisor,
      answer: quotient,
      type: "mixed",
      difficulty,
      hint: "구구단과 자릿수(0의 개수)의 관계를 이용해 보세요.",
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
    const basicDividend = divisor * getRandomInt(3, 80, rng);
    const multiplier = 100;
    const dividend = basicDividend * multiplier;
    const key = `${dividend}/${divisor}`;

    if (usedKeys.has(key) && attempts < 1500) continue;
    usedKeys.add(key);

    const problem: Problem = {
      id: `mixed_${dividend}_${divisor}_${problems.length}`,
      question: `${formatNumber(dividend)} ÷ ${divisor}`,
      dividend,
      divisor,
      answer: dividend / divisor,
      type: "mixed",
      difficulty,
      hint: "구구단과 자릿수(0의 개수)의 관계를 이용해 보세요.",
    };

    if (validateProblem(problem)) {
      problems.push(problem);
    }
  }

  return shuffle(problems, rng);
}
