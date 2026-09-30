import { Problem, ProblemType } from "./problem";

export interface WorksheetConfig {
  studentName?: string;
  grade?: number;
  subject?: string;
  targetType: ProblemType;
  count: number;
  difficulty: number;
  includeAnswerSheet?: boolean;
  includeTimerRecord?: boolean;
  seed?: string;
}

export interface WorksheetData {
  id: string;
  seed: string;
  title: string;
  subtitle: string;
  studentName: string;
  date: string;
  grade: number;
  targetType: ProblemType;
  difficulty: number;
  problems: Problem[];
  totalCount: number;
  includeTimerRecord: boolean;
}
