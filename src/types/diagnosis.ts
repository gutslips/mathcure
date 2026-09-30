import { ProblemType } from "./problem";

export interface DiagnosisAnswerInput {
  problemId: string;
  question: string;
  dividend: number;
  divisor: number;
  answer: number;
  userAnswer: number | null;
  correct: boolean;
  elapsedMs: number;
  type: ProblemType;
  difficulty: number;
  usedHint?: boolean;
}

export type MasteryStatus = "very_good" | "good" | "needs_improvement" | "critical";

export interface TypeAnalysis {
  type: ProblemType;
  label: string;
  total: number;
  correct: number;
  accuracy: number; // 0 ~ 100 (%)
  averageMs: number;
  errorRate: number; // 0 ~ 100 (%)
  speedScore: number; // 0 ~ 1
  compositeScore: number; // 0 ~ 100
  status: MasteryStatus;
  statusLabel: string;
}

export interface RecommendedTraining {
  type: ProblemType;
  typeLabel: string;
  difficulty: number;
  title: string;
  reason: string;
  strategyHint: string;
  sampleProblems: string[];
  compositionRatio: {
    targetType: ProblemType;
    targetRatio: number;
    basicRatio: number;
    otherRatio: number;
  };
}

export interface DiagnosisResultSummary {
  total: number;
  correct: number;
  accuracy: number; // %
  averageMs: number; // ms
  averageSeconds: number; // sec
  finalScore: number; // 0 ~ 100
  studentLevel: string; // e.g. "Level 2"
  weakestType: ProblemType;
  slowestType: ProblemType;
  mostErrorsType: ProblemType;
  typeAnalyses: Record<ProblemType, TypeAnalysis>;
  recommendedTraining: RecommendedTraining;
  detailedAnswers: DiagnosisAnswerInput[];
}
