import { Problem } from "@/types/problem";
import { createRNG, getRandomInt, shuffle, validateProblem, formatNumber } from "./utils";

export function generateDivide5(count: number, difficulty: number = 1, seed?: string): Problem[] {
  const rng = createRNG(seed);
  const problems: Problem[] = [];
  const usedKeys = new Set<string>();

  // 명세 14번 기준:
  // Level 1: 40 ÷ 5, 80 ÷ 5, 100 ÷ 5, 200 ÷ 5 (십 단위 ~ 200)
  // Level 2: 400 ÷ 5, 650 ÷ 5, 800 ÷ 5, 1,000 ÷ 5, 1,200 ÷ 5, 1,500 ÷ 5
  // Level 3: 2,500 ÷ 5, 3,500 ÷ 5, 4,500 ÷ 5, 6,500 ÷ 5, 8,000 ÷ 5
  // Level 4: 8,400~18,000 범위 큰 수
  const poolByLevel: Record<number, number[]> = {
    1: [35, 40, 45, 60, 75, 80, 90, 100, 120, 150, 160, 180, 200, 250],
    2: [350, 400, 450, 550, 600, 650, 750, 800, 900, 1000, 1200, 1400, 1500, 1800, 2000],
    3: [2500, 3000, 3500, 4000, 4500, 5500, 6000, 6500, 7000, 7500, 8000, 8500, 9000],
    4: [8000, 9500, 10500, 12000, 13500, 14000, 15000, 16000, 18000, 20000, 25000],
  };

  const currentPool = poolByLevel[difficulty] || poolByLevel[2];
  const shuffledPool = shuffle([...currentPool], rng);

  // 풀에서 우선 추출
  for (const dividend of shuffledPool) {
    if (problems.length >= count) break;
    const divisor = 5;
    const quotient = dividend / divisor;
    const key = `${dividend}/${divisor}`;
    if (usedKeys.has(key)) continue;
    usedKeys.add(key);

    const problem: Problem = {
      id: `div5_${dividend}_${divisor}_${problems.length}`,
      question: `${formatNumber(dividend)} ÷ ${divisor}`,
      dividend,
      divisor,
      answer: quotient,
      type: "divide5",
      difficulty,
      hint: "÷5는 2배(×2)를 한 뒤 10으로 나누면(÷10) 아주 빠릅니다! (예: 8,000 × 2 = 16,000 → 1,600)",
    };

    if (validateProblem(problem)) {
      problems.push(problem);
    }
  }

  // 부족하면 동적 생성
  let attempts = 0;
  while (problems.length < count && attempts < 500) {
    attempts++;
    let quotient = 0;
    if (difficulty === 1) {
      quotient = getRandomInt(6, 45, rng);
    } else if (difficulty === 2) {
      quotient = getRandomInt(60, 380, rng);
    } else if (difficulty === 3) {
      quotient = getRandomInt(450, 1800, rng);
    } else {
      quotient = getRandomInt(1500, 5000, rng);
    }

    const divisor = 5;
    const dividend = divisor * quotient;
    const key = `${dividend}/${divisor}`;

    if (usedKeys.has(key)) continue;
    usedKeys.add(key);

    const problem: Problem = {
      id: `div5_${dividend}_${divisor}_${problems.length}`,
      question: `${formatNumber(dividend)} ÷ ${divisor}`,
      dividend,
      divisor,
      answer: quotient,
      type: "divide5",
      difficulty,
      hint: "÷5는 2배(×2)를 한 뒤 10으로 나누면(÷10) 아주 빠릅니다! (예: 8,000 × 2 = 16,000 → 1,600)",
    };

    if (validateProblem(problem)) {
      problems.push(problem);
    }
  }

  return shuffle(problems, rng);
}
