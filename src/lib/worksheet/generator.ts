import { ProblemType, PROBLEM_TYPE_LABELS } from "@/types/problem";
import { WorksheetConfig, WorksheetData } from "@/types/worksheet";
import { generateProblemsByType, generateBasic } from "../generators";
import { createRNG, shuffle } from "../generators/utils";

/**
 * 명세 13 & 17 & 18:
 * 진단 결과 또는 사용자 선택에 따른 맞춤 훈련지 생성
 * - 대상 약점 유형 60%
 * - 기본 유형 20%
 * - 기타 복습 유형 20%
 * - Seed 기반 재현 지원
 */
export function generateWorksheet(config: WorksheetConfig): WorksheetData {
  const seed = config.seed || `ws_${Date.now()}_${Math.floor(Math.random() * 10000)}`;
  const count = config.count || 20;
  const difficulty = config.difficulty || 2;
  const targetType = config.targetType || "divide5";
  const rng = createRNG(seed);

  // 문제 수 배분 (명세 13: 약점 60%, 기본 20%, 기타 20%)
  const targetCount = Math.round(count * 0.6);
  const basicCount = Math.round(count * 0.2);
  const otherCount = Math.max(0, count - targetCount - basicCount);

  // 1. 약점 문제 생성
  const targetProblems = generateProblemsByType(targetType, targetCount, difficulty, `${seed}_target`);

  // 2. 기본 문제 생성
  const basicProblems = generateBasic(basicCount, 1, `${seed}_basic`);

  // 3. 기타 연관 문제 생성 (targetType과 다른 전략)
  const candidateOthers: ProblemType[] = ["divide2", "divide4", "divide8", "divide10", "largeNumber"].filter(
    (t) => t !== targetType
  ) as ProblemType[];
  const otherType = candidateOthers[Math.floor(rng() * candidateOthers.length)];
  const otherProblems = generateProblemsByType(otherType, otherCount, difficulty, `${seed}_other`);

  // 합치고 순서 섞기
  const combined = shuffle([...targetProblems, ...basicProblems, ...otherProblems], rng).map(
    (p, index) => ({
      ...p,
      id: `ws_p${index + 1}`,
    })
  );

  const todayStr = new Date().toLocaleDateString("ko-KR", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });

  return {
    id: `ws_${Date.now()}`,
    seed,
    title: `초5 나눗셈 맞춤 연산 트레이닝`,
    subtitle: `${PROBLEM_TYPE_LABELS[targetType]} 집중 훈련 · Level ${difficulty}`,
    studentName: config.studentName || "홍길동",
    date: todayStr,
    grade: config.grade || 5,
    targetType,
    difficulty,
    problems: combined,
    totalCount: combined.length,
    includeTimerRecord: config.includeTimerRecord ?? true,
  };
}
