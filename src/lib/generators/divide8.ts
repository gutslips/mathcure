import { Problem } from "@/types/problem";
import { createRNG, getRandomInt, shuffle, validateProblem, formatNumber } from "./utils";

export function generateDivide8(count: number, difficulty: number = 1, seed?: string): Problem[] {
  const rng = createRNG(seed);
  const problems: Problem[] = [];
  const usedKeys = new Set<string>();

  // diff 1: 80 ~ 400 (몫 10~50)
  // diff 2: 400 ~ 2,000 (몫 50~250) 예: 480÷8, 800÷8, 1,200÷8, 1,600÷8
  // diff 3: 2,000 ~ 10,000 (몫 250~1200) 예: 2,400÷8, 3,200÷8, 4,800÷8, 6,400÷8
  const seedPool: Record<number, number[]> = {
    1: [80, 120, 160, 200, 240, 280, 320, 360, 400],
    2: [480, 560, 640, 720, 800, 960, 1200, 1440, 1600, 1920],
    3: [2400, 3200, 4000, 4800, 5600, 6400, 7200, 8000, 9600],
  };

  const pool = seedPool[difficulty] || seedPool[2];
  for (const dividend of shuffle([...pool], rng)) {
    if (problems.length >= count) break;
    const divisor = 8;
    const quotient = dividend / divisor;
    const key = `${dividend}/${divisor}`;
    if (usedKeys.has(key)) continue;
    usedKeys.add(key);

    const problem: Problem = {
      id: `div8_${dividend}_${divisor}_${problems.length}`,
      question: `${formatNumber(dividend)} ÷ ${divisor}`,
      dividend,
      divisor,
      answer: quotient,
      type: "divide8",
      difficulty,
      hint: "÷8은 절반을 세 번(÷2 → ÷2 → ÷2) 하면 쉽게 암산할 수 있어요.",
    };

    if (validateProblem(problem)) {
      problems.push(problem);
    }
  }

  let attempts = 0;
  while (problems.length < count && attempts < 2000) {
    attempts++;
    let quotient = 0;
    if (difficulty === 1) {
      quotient = getRandomInt(10, attempts > 100 ? 100 : 50, rng);
    } else if (difficulty === 2) {
      quotient = getRandomInt(50, attempts > 100 ? 500 : 250, rng);
    } else {
      quotient = getRandomInt(250, 2000, rng);
    }

    const divisor = 8;
    const dividend = divisor * quotient;
    const key = `${dividend}/${divisor}`;

    if (usedKeys.has(key) && attempts < 1500) continue;
    usedKeys.add(key);

    const problem: Problem = {
      id: `div8_${dividend}_${divisor}_${problems.length}`,
      question: `${formatNumber(dividend)} ÷ ${divisor}`,
      dividend,
      divisor,
      answer: quotient,
      type: "divide8",
      difficulty,
      hint: "÷8은 절반을 세 번(÷2 → ÷2 → ÷2) 하면 쉽게 암산할 수 있어요.",
    };

    if (validateProblem(problem)) {
      problems.push(problem);
    }
  }

  return shuffle(problems, rng);
}
