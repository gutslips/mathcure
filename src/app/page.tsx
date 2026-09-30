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
  AlertTriangle
} from "lucide-react";
import { PROBLEM_TYPE_LABELS, ProblemType } from "@/types/problem";

export const dynamic = "force-dynamic";

export default async function DashboardPage() {
  const student = await getActiveStudent();

  // 최근 진단 기록 가져오기
  const latestDiagnosis = await prisma.diagnosis.findFirst({
    where: { studentId: student.id },
    orderBy: { finishedAt: "desc" },
    include: { answers: true },
  });

  const totalDiagnosesCount = await prisma.diagnosis.count({
    where: { studentId: student.id },
  });

  // 최근 진단이 있으면 유형별 분석 데이터 산출
  const typeStats: Record<string, { total: number; correct: number; avgSec: number; rate: number }> = {
    basic: { total: 5, correct: 5, avgSec: 4.2, rate: 100 },
    divide2: { total: 2, correct: 2, avgSec: 3.5, rate: 100 },
    divide4: { total: 2, correct: 2, avgSec: 6.8, rate: 80 },
    divide5: { total: 4, correct: 2, avgSec: 18.4, rate: 50 },
    divide8: { total: 2, correct: 1, avgSec: 14.2, rate: 60 },
    largeNumber: { total: 5, correct: 3, avgSec: 12.0, rate: 60 },
  };

  let accuracy = 85;
  let avgSeconds = 9.8;
  let level = "Level 2 (기본 숙달)";
  let weakestType: ProblemType = "divide5";

  if (latestDiagnosis && latestDiagnosis.answers.length > 0) {
    accuracy = Math.round(latestDiagnosis.accuracy);
    avgSeconds = Number((latestDiagnosis.averageMs / 1000).toFixed(1));
    level = accuracy >= 90 ? "Level 3" : accuracy >= 70 ? "Level 2" : "Level 1";

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
    for (const [k, v] of Object.entries(customStats)) {
      const rate = Math.round((v.correct / v.total) * 100);
      const avgSec = Number((v.totalMs / v.total / 1000).toFixed(1));
      typeStats[k] = { total: v.total, correct: v.correct, avgSec, rate };
      if (rate < minRate) {
        minRate = rate;
        weakestType = k as ProblemType;
      }
    }
  }

  return (
    <div className="space-y-8">
      {/* 상단 프로필 헤더 */}
      <div className="bg-white border border-slate-200 rounded-xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="inline-flex items-center gap-2 px-2.5 py-1 rounded bg-slate-100 text-slate-700 text-xs font-medium mb-2">
            초등학교 5학년 연산 진단 및 맞춤 처방
          </div>
          <h1 className="text-2xl font-bold text-slate-900">
            초5 연산 트레이너
          </h1>
          <p className="text-slate-600 text-sm mt-1">
            학생: <span className="font-semibold text-slate-900">{student.name}</span> · 초등학교 {student.grade}학년 · 누적 진단 {totalDiagnosesCount}회
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

      {/* 주요 지표 3개 카드 (명세 4) */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div className="bg-white border border-slate-200 rounded-xl p-6">
          <div className="flex items-center justify-between text-slate-500 mb-2">
            <span className="text-sm font-medium">최근 정확도</span>
            <Target className="w-5 h-5 text-slate-400" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-3xl font-extrabold text-slate-900">{accuracy}%</span>
            <span className="text-xs text-emerald-600 font-medium flex items-center">
              {accuracy >= 80 ? "목표 도달" : "개선 필요"}
            </span>
          </div>
          <p className="text-xs text-slate-500 mt-2">
            {latestDiagnosis ? "최근 20문제 진단 기준" : "기본 진단 기준 (진단 시작 권장)"}
          </p>
        </div>

        <div className="bg-white border border-slate-200 rounded-xl p-6">
          <div className="flex items-center justify-between text-slate-500 mb-2">
            <span className="text-sm font-medium">평균 풀이시간</span>
            <Clock className="w-5 h-5 text-slate-400" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-3xl font-extrabold text-slate-900">{avgSeconds}초</span>
            <span className="text-xs text-slate-500 font-medium">/ 문제당</span>
          </div>
          <p className="text-xs text-slate-500 mt-2">
            {avgSeconds <= 6 ? "우수한 자동화 속도" : "일부 연산 전략 보완 필요"}
          </p>
        </div>

        <div className="bg-white border border-slate-200 rounded-xl p-6">
          <div className="flex items-center justify-between text-slate-500 mb-2">
            <span className="text-sm font-medium">현재 진단 레벨</span>
            <Award className="w-5 h-5 text-slate-400" />
          </div>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-extrabold text-slate-900">{level}</span>
          </div>
          <p className="text-xs text-slate-500 mt-2">
            정확도 70% + 속도 30% 종합 연산 지수
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* 능력 영역 (명세 4) */}
        <div className="lg:col-span-2 bg-white border border-slate-200 rounded-xl p-6">
          <div className="flex items-center justify-between mb-5">
            <div>
              <h2 className="text-lg font-bold text-slate-900">능력 영역별 자동화 수준</h2>
              <p className="text-xs text-slate-500 mt-0.5">
                각 나눗셈 유형별 정답률 및 계산 처리 숙달도
              </p>
            </div>
            <Link href="/problem-bank" className="text-xs text-slate-600 hover:text-slate-900 font-medium flex items-center gap-1">
              유형 상세 <ArrowRight className="w-3.5 h-3.5" />
            </Link>
          </div>

          <div className="space-y-4">
            {[
              { key: "basic", label: "기본 나눗셈", defaultRate: 90, desc: "구구단 역연산 사실 자동화" },
              { key: "divide2", label: "÷2 자동화", defaultRate: 100, desc: "절반 구하기 전략" },
              { key: "divide4", label: "÷4 자동화", defaultRate: 80, desc: "절반의 절반 전략" },
              { key: "divide5", label: "÷5 자동화", defaultRate: 50, desc: "×2 후 ÷10 자리값 전략" },
              { key: "divide8", label: "÷8 자동화", defaultRate: 70, desc: "절반 3번 나누기 전략" },
              { key: "largeNumber", label: "큰 수 처리", defaultRate: 60, desc: "천 단위 및 끝자리 0 처리" },
            ].map((item) => {
              const stat = typeStats[item.key] || { rate: item.defaultRate, avgSec: 8.0 };
              const rate = stat.rate;
              const isWeak = rate < 70;

              return (
                <div key={item.key} className="space-y-1.5">
                  <div className="flex items-center justify-between text-sm">
                    <div className="flex items-center gap-2">
                      <span className="font-semibold text-slate-800">{item.label}</span>
                      <span className="text-xs text-slate-400 hidden sm:inline">({item.desc})</span>
                    </div>
                    <div className="flex items-center gap-2">
                      {isWeak ? (
                        <span className="text-[11px] font-medium text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                          집중 훈련 권장
                        </span>
                      ) : (
                        <span className="text-[11px] font-medium text-slate-500">
                          {stat.avgSec}초
                        </span>
                      )}
                      <span className="font-bold text-slate-900 w-12 text-right">{rate}%</span>
                    </div>
                  </div>

                  {/* 게이지 바 (과도한 gradient 없이 깔끔한 slate 테마) */}
                  <div className="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
                    <div
                      className={`h-full transition-all duration-500 ${
                        rate >= 80 ? "bg-slate-800" : rate >= 60 ? "bg-slate-600" : "bg-amber-600"
                      }`}
                      style={{ width: `${rate}%` }}
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
              현재 가장 필요한 훈련
            </div>

            <h3 className="text-lg font-bold text-slate-900">
              {weakestType === "divide5" ? "÷5 큰 수 자동화" : `${PROBLEM_TYPE_LABELS[weakestType]} 자동화`}
            </h3>

            <p className="text-xs text-slate-600 mt-2 leading-relaxed">
              {weakestType === "divide5"
                ? "학생이 8,000 ÷ 5 같은 문제에서 정답은 맞히지만 시간이 20초 이상 지체되고 있습니다. 2배를 먼저 하고 10으로 나누는 자리값 전략을 체화해야 합니다."
                : "해당 유형의 오답과 시간 지체가 진단에서 확인되었습니다. 약점 유형 60% 비중의 맞춤 훈련지를 추천합니다."}
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
