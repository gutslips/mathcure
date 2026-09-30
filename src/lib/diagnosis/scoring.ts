import { MasteryStatus } from "@/types/diagnosis";

/**
 * 명세 11: 소요 시간(ms)에 따른 속도 점수 계산 (0.2 ~ 1.0)
 * 0~5초: 1.0
 * 5~10초: 0.8
 * 10~20초: 0.6
 * 20~30초: 0.4
 * 30초+: 0.2
 */
export function calculateSpeedScore(elapsedMs: number): number {
  const seconds = elapsedMs / 1000;
  if (seconds <= 5) return 1.0;
  if (seconds <= 10) return 0.8;
  if (seconds <= 20) return 0.6;
  if (seconds <= 30) return 0.4;
  return 0.2;
}

/**
 * 명세 11: 종합 점수 계산 (0 ~ 100)
 * finalScore = accuracy * 0.7 + speedScore * 0.3
 */
export function calculateCompositeScore(accuracyRatio: number, speedScore: number): number {
  const score = accuracyRatio * 0.7 + speedScore * 0.3;
  return Math.round(score * 100);
}

/**
 * 성취 상태 및 한글 레이블 반환
 */
export function getMasteryStatus(compositeScore: number, accuracyRatio: number, avgSeconds: number): {
  status: MasteryStatus;
  label: string;
} {
  if (accuracyRatio >= 0.9 && avgSeconds <= 6) {
    return { status: "very_good", label: "매우 좋음" };
  }
  if (compositeScore >= 75) {
    return { status: "good", label: "양호" };
  }
  if (compositeScore >= 55) {
    return { status: "needs_improvement", label: "개선 필요" };
  }
  return { status: "critical", label: "집중 훈련 필요" };
}

/**
 * 종합 점수에 따른 현재 학생 레벨 결정
 */
export function determineStudentLevel(finalScore: number): string {
  if (finalScore >= 90) return "Level 4 (심화 자동화)";
  if (finalScore >= 75) return "Level 3 (응용 숙달)";
  if (finalScore >= 60) return "Level 2 (기본 숙달)";
  return "Level 1 (원리 집중)";
}
