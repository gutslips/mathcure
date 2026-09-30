import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { getActiveStudent } from "@/lib/student";
import { 
  Play, 
  FileText, 
  Clock, 
  Target, 
  Award, 
  ArrowRight,
  AlertTriangle,
  Sparkles
} from "lucide-react";
import { PROBLEM_TYPE_LABELS, ProblemType } from "@/types/problem";

export const dynamic = "force-dynamic";

export default async function DashboardPage() {
  const student = await getActiveStudent();

  // 현재 활성 학생의 최근 진단 기록 가져오기
  const latestDiagnosis = await prisma.diagnosis.findFirst({
    where: { studentId: student.id },
    orderBy: { finishedAt: "desc" },
    include: { answers: true },
  });

  const totalDiagnosesCount = await prisma.diagnosis.count({
    where: { studentId: student.id },
  });

  const hasDiagnosis = Boolean(latestDiagnosis && latestDiagnosis.answers.length > 0);

  // 유형별 통계 계산
  const typeStats: Record<string, { total: number; correct: number; avgSec: number; rate: number }> = {};
  const allTypes: ProblemType[] = ["basic", "divide2", "divide4", "divide5", "divide8", "largeNumber"];

  let accuracy = 0;
  let avgSeconds = 0;
  let level = "진단 대기";
  let weakestType: ProblemType = "divide5";

  if (hasDiagnosis && latestDiagnosis) {
    accuracy = Math.round(latestDiagnosis.accuracy);
    avgSeconds = Number((latestDiagnosis.averageMs / 1000).toFixed(1));
    level = accuracy >= 90 ? "Level 4 (심화)" : accuracy >= 75 ? "Level 3 (숙달)" : accuracy >= 60 ? "Level 2 (보통)" : "Level 1 (기초)";

    const customStats: Record<string, { total: number; correct: number; totalMs: number }> = {};
    for (const ans of latestDiagnosis.answers) {
      if (!customStats[ans.type]) {
        customStats[ans.type] = { total: 0, correct: 0, totalMs: 0 };
      }
      customStats[ans.type].total++;
      if (ans.correct) customStats[ans.type].correct++;
      customStats[ans.type].totalMs += ans.elapsedMs;
    }

    let minRate = 999;
    for (const t of allTypes) {
      const v = customStats[t];
      if (v && v.total > 0) {
        const rate = Math.round((v.correct / v.total) * 100);
        const avgSec = Number((v.totalMs / v.total / 1000).toFixed(1));
        typeStats[t] = { total: v.total, correct: v.correct, avgSec, rate };
        if (rate < minRate) {
          minRate = rate;
          weakestType = t;
        }
      } else {
        typeStats[t] = { total: 0, correct: 0, avgSec: 0, rate: 0 };
      }
    }
  }

  return (
    <div className="space-y-8">
      {/* 상단 프로필 헤더 */}
      <div className="bg-white border border-slate-200 rounded-xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="inline-flex items-center gap-2 px-2.5 py-1 rounded bg-slate-100 text-slate-700 text-xs font-medium mb-2">
            초등학교 {student.grade}학년 연산 역량 대시보드
          </div>
          <h1 className="text-2xl font-bold text-slate-900">
            {student.name} 학생의 연산 트레이너
          </h1>
          <p className="text-slate-600 text-sm mt-1">
            현재 학생: <span className="font-semibold text-slate-900">{student.name}</span> (초{student.grade}) · 누적 진단 {totalDiagnosesCount}회
          </p>
        </div>

        <div className="flex items-center gap-3">
          <Link
            href="/diagnosis"
            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800 transition shadow-sm"
          >
            <Play className="w-4 h-4 fill-white" />
            5분 진단 시작
          </Link>
          <Link
            href={`/worksheet?type=${weakestType}`}
            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-white border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition"
          >
            <FileText className="w-4 h-4 text-slate-600" />
            맞춤 훈련지 만들기
          </Link>
        </div>
      </div>

      {/* 신규 학생 안내 배너 (진단 기록이 없을 때) */}
      {!hasDiagnosis && (
        <div className="bg-amber-50 border border-amber-200 rounded-xl p-5 flex items-start gap-4">
          <Sparkles className="w-6 h-6 text-amber-700 shrink-0 mt-0.5" />
          <div className="flex-1">
            <h3 className="font-bold text-amber-900 text-sm">
              &apos;{student.name}&apos; 학생의 첫 연산 진단을 시작해 보세요!
            </h3>
            <p className="text-xs text-amber-800 mt-1 leading-relaxed">
              아직 완료된 진단 기록이 없습니다. 5분 진단을 완료하면 학생의 정확한 계산 속도(초)와
              나눗셈 오답 유형, 취약점 분석 차트가 자동으로 생성됩니다.
            </p>
            <div className="mt-3">
              <Link
                href="/diagnosis"
                className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-700 hover:bg-amber-800 text-white rounded-md text-xs font-semibold transition"
              >
                첫 진단 시작하기 ➔
              </Link>
            </div>
          </div>
        </div>
      )}

      {/* 주요 지표 3개 카드 (명세 4) */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div className="bg-white border border-slate-200 rounded-xl p-6">
          <div className="flex items-center justify-between text-slate-500 mb-2">
            <span className="text-sm font-medium">최근 정확도</span>
            <Target className="w-5 h-5 text-slate-400" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-3xl font-extrabold text-slate-900">
              {hasDiagnosis ? `${accuracy}%` : "진단 전"}
            </span>
            {hasDiagnosis && (
              <span className={`text-xs font-medium flex items-center ${accuracy >= 80 ? "text-emerald-600" : "text-amber-600"}`}>
                {accuracy >= 80 ? "양호" : "훈련 필요"}
              </span>
            )}
          </div>
          <p className="text-xs text-slate-500 mt-2">
            {hasDiagnosis ? "최근 20문제 진단 채점 기준" : "5분 진단 후 자동 계산됩니다"}
          </p>
        </div>

        <div className="bg-white border border-slate-200 rounded-xl p-6">
          <div className="flex items-center justify-between text-slate-500 mb-2">
            <span className="text-sm font-medium">평균 풀이시간</span>
            <Clock className="w-5 h-5 text-slate-400" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-3xl font-extrabold text-slate-900">
              {hasDiagnosis ? `${avgSeconds}초` : "진단 전"}
            </span>
            {hasDiagnosis && (
              <span className="text-xs text-slate-500 font-medium">/ 문제당</span>
            )}
          </div>
          <p className="text-xs text-slate-500 mt-2">
            {hasDiagnosis
              ? avgSeconds <= 6
                ? "우수한 자동화 속도"
                : "전략 훈련 권장"
              : "문제별 정밀 소요시간 측정"}
          </p>
        </div>

        <div className="bg-white border border-slate-200 rounded-xl p-6">
          <div className="flex items-center justify-between text-slate-500 mb-2">
            <span className="text-sm font-medium">현재 진단 레벨</span>
            <Award className="w-5 h-5 text-slate-400" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-extrabold text-slate-900">
              {level}
            </span>
          </div>
          <p className="text-xs text-slate-500 mt-2">
            {hasDiagnosis ? "정확도 70% + 속도 30% 종합 지수" : "진단 완료 시 레벨이 결정됩니다"}
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* 능력 영역 (명세 4) */}
        <div className="lg:col-span-2 bg-white border border-slate-200 rounded-xl p-6">
          <div className="flex items-center justify-between mb-5">
            <div>
              <h2 className="text-lg font-bold text-slate-900">
                능력 영역별 자동화 수준
              </h2>
              <p className="text-xs text-slate-500 mt-0.5">
                {student.name} 학생의 나눗셈 유형별 성취율
              </p>
            </div>
            <Link href="/problem-bank" className="text-xs text-slate-600 hover:text-slate-900 font-medium flex items-center gap-1">
              유형 상세 <ArrowRight className="w-3.5 h-3.5" />
            </Link>
          </div>

          <div className="space-y-4">
            {[
              { key: "basic", label: "기본 나눗셈", desc: "구구단 역연산 사실 자동화" },
              { key: "divide2", label: "÷2 자동화", desc: "절반 구하기 전략" },
              { key: "divide4", label: "÷4 자동화", desc: "절반의 절반 전략" },
              { key: "divide5", label: "÷5 자동화", desc: "×2 후 ÷10 자리값 전략" },
              { key: "divide8", label: "÷8 자동화", desc: "절반 3번 나누기 전략" },
              { key: "largeNumber", label: "큰 수 처리", desc: "천 단위 및 끝자리 0 처리" },
            ].map((item) => {
              const stat = typeStats[item.key];
              const rate = stat ? stat.rate : 0;
              const hasData = Boolean(stat && stat.total > 0);

              return (
                <div key={item.key} className="space-y-1.5">
                  <div className="flex items-center justify-between text-sm">
                    <div className="flex items-center gap-2">
                      <span className="font-semibold text-slate-800">{item.label}</span>
                      <span className="text-xs text-slate-400 hidden sm:inline">({item.desc})</span>
                    </div>
                    <div className="flex items-center gap-2">
                      {hasData ? (
                        <>
                          <span className="text-[11px] font-medium text-slate-500">
                            평균 {stat.avgSec}초
                          </span>
                          <span className="font-bold text-slate-900 w-12 text-right">
                            {rate}%
                          </span>
                        </>
                      ) : (
                        <span className="text-[11px] text-slate-400 font-medium">
                          진단 전
                        </span>
                      )}
                    </div>
                  </div>

                  {/* 게이지 바 */}
                  <div className="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
                    <div
                      className={`h-full transition-all duration-500 ${
                        !hasData ? "bg-slate-200" : rate >= 80 ? "bg-slate-800" : rate >= 60 ? "bg-slate-600" : "bg-amber-600"
                      }`}
                      style={{ width: `${hasData ? rate : 0}%` }}
                    />
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* 현재 추천 훈련 카드 (명세 4) */}
        <div className="bg-white border border-slate-200 rounded-xl p-6 flex flex-col justify-between">
          <div>
            <div className="inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-md border border-amber-200 mb-3">
              <AlertTriangle className="w-3.5 h-3.5" />
              {hasDiagnosis ? "현재 가장 필요한 훈련" : "추천 시작 훈련"}
            </div>

            <h3 className="text-lg font-bold text-slate-900">
              {PROBLEM_TYPE_LABELS[weakestType]} 집중 훈련
            </h3>

            <p className="text-xs text-slate-600 mt-2 leading-relaxed">
              {hasDiagnosis
                ? `'${student.name}' 학생의 최근 진단 분석 결과, 해당 유형의 연산 속도 개선 및 자동화가 가장 시급합니다.`
                : "초5 과정에서 가장 중요한 ÷5 연산과 큰 수 자리값 자동화 맞춤 훈련지입니다."}
            </p>

            <div className="mt-5 p-3.5 bg-slate-50 rounded-lg border border-slate-200">
              <div className="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-2">
                추천 문제 예시
              </div>
              <ul className="text-sm font-mono text-slate-800 space-y-1.5 font-medium">
                <li>800 ÷ 5 = _____</li>
                <li>1,200 ÷ 5 = _____</li>
                <li>2,500 ÷ 5 = _____</li>
                <li>4,500 ÷ 5 = _____</li>
                <li>8,000 ÷ 5 = _____</li>
              </ul>
            </div>
          </div>

          <div className="mt-6 pt-4 border-t border-slate-100 flex flex-col gap-2">
            <Link
              href={`/worksheet?type=${weakestType}&difficulty=2`}
              className="w-full text-center py-2.5 px-4 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800 transition"
            >
              맞춤 훈련지 만들기
            </Link>
            <Link
              href={`/practice?type=${weakestType}`}
              className="w-full text-center py-2 px-4 bg-white border border-slate-200 text-slate-700 text-xs font-medium rounded-lg hover:bg-slate-50 transition"
            >
              온라인으로 바로 풀기
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
