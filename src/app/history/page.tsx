import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { getOrCreateDefaultStudent } from "@/lib/student";
import { 
  Clock, 
  Target, 
  ArrowRight,
  Calculator
} from "lucide-react";

export const dynamic = "force-dynamic";

export default async function HistoryPage() {
  const student = await getOrCreateDefaultStudent();

  const diagnoses = await prisma.diagnosis.findMany({
    where: { studentId: student.id },
    orderBy: { finishedAt: "asc" }, // 시간순 정렬로 그래프 표현
    include: { answers: true },
  });

  const hasData = diagnoses.length > 0;

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">학습 및 진단 기록</h1>
        <p className="text-sm text-slate-600 mt-1">
          {student.name} 학생의 나눗셈 계산 속도 및 정확도 변화 추이를 추적합니다.
        </p>
      </div>

      {!hasData ? (
        <div className="bg-white border border-slate-200 rounded-xl p-12 text-center max-w-md mx-auto space-y-4">
          <Calculator className="w-12 h-12 text-slate-400 mx-auto" />
          <h3 className="text-lg font-bold text-slate-800">아직 진단 기록이 없습니다</h3>
          <p className="text-xs text-slate-500">
            첫 번째 5분 진단을 완료하면 계산 속도 및 정확도 변화 그래프가 생성됩니다.
          </p>
          <Link
            href="/diagnosis"
            className="inline-block px-5 py-2.5 bg-slate-900 text-white font-semibold text-sm rounded-lg hover:bg-slate-800"
          >
            첫 진단 시작하기
          </Link>
        </div>
      ) : (
        <>
          {/* 그래프 2개 (속도와 정확도 분리, 명세 25) */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* [1] 평균 풀이시간 추이 (초) */}
            <div className="bg-white border border-slate-200 rounded-xl p-6">
              <div className="flex items-center justify-between mb-4">
                <div className="flex items-center gap-2">
                  <Clock className="w-4 h-4 text-slate-600" />
                  <h3 className="font-bold text-slate-900 text-base">
                    평균 풀이시간 추이 (초)
                  </h3>
                </div>
                <span className="text-xs text-slate-400">낮을수록 빠름</span>
              </div>

              {/* 막대/추이 시각화 */}
              <div className="h-44 flex items-end justify-between gap-3 pt-6 pb-2 border-b border-slate-200">
                {diagnoses.slice(-8).map((d, idx) => {
                  const sec = Number((d.averageMs / 1000).toFixed(1));
                  // 최대 30초 기준 높이 백분율 계산
                  const heightPercent = Math.min(100, Math.max(15, (sec / 25) * 100));

                  return (
                    <div key={d.id} className="flex-1 flex flex-col items-center gap-2">
                      <span className="text-[11px] font-mono font-bold text-slate-700">
                        {sec}s
                      </span>
                      <div className="w-full bg-slate-100 rounded-t-md h-28 flex items-end">
                        <div
                          className="w-full bg-slate-800 rounded-t-md transition-all duration-500 hover:bg-slate-900"
                          style={{ height: `${heightPercent}%` }}
                        />
                      </div>
                      <span className="text-[10px] text-slate-400">
                        #{idx + 1}회
                      </span>
                    </div>
                  );
                })}
              </div>
              <div className="text-[11px] text-slate-500 mt-3 flex items-center justify-between">
                <span>0~5초: 자동화 완성</span>
                <span>10~20초: 보통</span>
                <span>20초 이상: 전략 필요</span>
              </div>
            </div>

            {/* [2] 정확도 추이 (%) */}
            <div className="bg-white border border-slate-200 rounded-xl p-6">
              <div className="flex items-center justify-between mb-4">
                <div className="flex items-center gap-2">
                  <Target className="w-4 h-4 text-slate-600" />
                  <h3 className="font-bold text-slate-900 text-base">
                    정확도 추이 (%)
                  </h3>
                </div>
                <span className="text-xs text-slate-400">100% 목표</span>
              </div>

              <div className="h-44 flex items-end justify-between gap-3 pt-6 pb-2 border-b border-slate-200">
                {diagnoses.slice(-8).map((d, idx) => {
                  const acc = Math.round(d.accuracy);
                  return (
                    <div key={d.id} className="flex-1 flex flex-col items-center gap-2">
                      <span className="text-[11px] font-mono font-bold text-slate-700">
                        {acc}%
                      </span>
                      <div className="w-full bg-slate-100 rounded-t-md h-28 flex items-end">
                        <div
                          className="w-full bg-slate-700 rounded-t-md transition-all duration-500 hover:bg-slate-900"
                          style={{ height: `${acc}%` }}
                        />
                      </div>
                      <span className="text-[10px] text-slate-400">
                        #{idx + 1}회
                      </span>
                    </div>
                  );
                })}
              </div>
              <div className="text-[11px] text-slate-500 mt-3 flex items-center justify-between">
                <span>목표 정확도: 90% 이상</span>
                <span>권장 재진단 주기: 주 1회</span>
              </div>
            </div>
          </div>

          {/* 누적 진단 목록 테이블 */}
          <div className="bg-white border border-slate-200 rounded-xl p-6">
            <h3 className="text-lg font-bold text-slate-900 mb-4">
              과거 진단 내역
            </h3>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-slate-600 border-b border-slate-200 text-xs">
                  <tr>
                    <th className="py-3 px-4 font-semibold">진단 일시</th>
                    <th className="py-3 px-4 font-semibold text-center">정답수 / 총문항</th>
                    <th className="py-3 px-4 font-semibold text-center">정확도</th>
                    <th className="py-3 px-4 font-semibold text-center">평균 시간</th>
                    <th className="py-3 px-4 font-semibold text-center">결과</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 font-mono text-xs">
                  {diagnoses.slice().reverse().map((d) => {
                    const dateStr = new Date(d.finishedAt).toLocaleDateString("ko-KR", {
                      year: "numeric",
                      month: "2-digit",
                      day: "2-digit",
                      hour: "2-digit",
                      minute: "2-digit",
                    });

                    return (
                      <tr key={d.id} className="hover:bg-slate-50/50">
                        <td className="py-3 px-4 font-sans text-slate-700 font-medium">
                          {dateStr}
                        </td>
                        <td className="py-3 px-4 text-center text-slate-600">
                          {d.correct} / {d.total}
                        </td>
                        <td className="py-3 px-4 text-center font-bold text-slate-900">
                          {Math.round(d.accuracy)}%
                        </td>
                        <td className="py-3 px-4 text-center text-slate-700">
                          {(d.averageMs / 1000).toFixed(1)}초
                        </td>
                        <td className="py-3 px-4 text-center font-sans">
                          <Link
                            href={`/diagnosis/result?id=${d.id}`}
                            className="inline-flex items-center gap-1 text-slate-700 hover:text-slate-900 font-semibold underline underline-offset-2"
                          >
                            리포트 확인 <ArrowRight className="w-3 h-3" />
                          </Link>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
