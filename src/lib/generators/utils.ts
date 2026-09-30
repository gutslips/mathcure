import { Problem } from "@/types/problem";

/**
 * 시드 기반 의사 난수 생성기 (Mulberry32)
 * 시드가 주어지면 항상 동일한 수열을 생성하여 문제지 재현성을 보장합니다.
 */
export function createRNG(seedStr?: string): () => number {
  if (!seedStr) {
    return Math.random;
  }
  let h = 0;
  for (let i = 0; i < seedStr.length; i++) {
    h = Math.imul(31, h) + seedStr.charCodeAt(i) | 0;
  }
  let state = h;

  return function mulberry32(): number {
    state |= 0;
    state = state + 0x6D2B79F5 | 0;
    let t = Math.imul(state ^ state >>> 15, 1 | state);
    t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t;
    return ((t ^ t >>> 14) >>> 0) / 4294967296;
  };
}

/**
 * 지정된 범위의 정수 난수 반환 [min, max]
 */
export function getRandomInt(min: number, max: number, rng: () => number = Math.random): number {
  const r = rng();
  return Math.floor(r * (max - min + 1)) + min;
}

/**
 * 배열에서 무작위 항목 선택
 */
export function pickRandom<T>(items: T[], rng: () => number = Math.random): T {
  const index = Math.floor(rng() * items.length);
  return items[index];
}

/**
 * 배열 셔플 (Fisher-Yates)
 */
export function shuffle<T>(items: T[], rng: () => number = Math.random): T[] {
  const array = [...items];
  for (let i = array.length - 1; i > 0; i--) {
    const j = Math.floor(rng() * (i + 1));
    [array[i], array[j]] = [array[j], array[i]];
  }
  return array;
}

/**
 * 숫자 세 자리 콤마 포맷
 */
export function formatNumber(num: number): string {
  return num.toLocaleString("ko-KR");
}

/**
 * 문제 유효성 검사 (안전장치)
 * 1. 나누어 떨어지는가?
 * 2. 답이 양의 정수인가?
 * 3. 피제수/제수가 0이 아닌가?
 */
export function validateProblem(problem: Problem): boolean {
  if (!problem.dividend || !problem.divisor) return false;
  if (problem.dividend <= 0 || problem.divisor <= 0) return false;
  if (problem.dividend % problem.divisor !== 0) return false;
  const calculated = Math.floor(problem.dividend / problem.divisor);
  if (calculated !== problem.answer) return false;
  if (!Number.isInteger(problem.answer) || problem.answer <= 0) return false;
  return true;
}
