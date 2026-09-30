export type ProblemType =
  | "basic"
  | "divide2"
  | "divide4"
  | "divide5"
  | "divide8"
  | "divide10"
  | "largeNumber"
  | "mixed";

export interface Problem {
  id: string;
  question: string;
  dividend: number;
  divisor: number;
  answer: number;
  type: ProblemType;
  difficulty: number;
  hint?: string;
}

export interface ProblemGeneratorOptions {
  difficulty?: number;
  count: number;
  seed?: string;
}

export interface IProblemGenerator {
  generate(difficulty: number, count: number, seed?: string): Problem[];
}

export const PROBLEM_TYPE_LABELS: Record<ProblemType, string> = {
  basic: "기본 나눗셈",
  divide2: "÷2 자동화",
  divide4: "÷4 자동화",
  divide5: "÷5 자동화",
  divide8: "÷8 자동화",
  divide10: "÷10 자동화",
  largeNumber: "큰 수 처리",
  mixed: "초5 혼합 계산",
};
