"use client";

import { useEffect, useState, Suspense } from "react";
import { useSearchParams } from "next/navigation";
import Link from "next/link";
import { 
  DiagnosisResultSummary,
  DiagnosisAnswerInput
} from "@/types/diagnosis";
import { PROBLEM_TYPE_LABELS } from "@/types/problem";
import { 
  CheckCircle2, 
  XCircle, 
  Printer, 
  ArrowRight, 
  RotateCcw,
  Sparkles,
  AlertTriangle,
  FileText
} from "lucide-react";

function DiagnosisResultContent() {
  const searchParams = useSearchParams();
  const diagnosisId = searchParams.get("id");

  // lazy initializer로 세션스토리지 초기 복원
  const [analysis, setAnalysis] = useState<DiagnosisResultSummary | null>(() => {
    if (typeof window !== "undefined") {
      const cached = sessionStorage.getItem("lastDiagnosisAnalysis");
      if (cached) {
        try {
          return JSON.parse(cached);
        } catch {
          return null;
        }
      }
    }
    return null;
  });

  const [loading, setLoading] = useState(!analysis);
  const [studentInfo, setStudentInfo] = useState<{ name: string; grade: number }>({
    name: "학생",
    grade: 5,
  });

  useEffect(() => {
    fetch("/api/student")
      .then((res) => res.json())
      .then((data) => {
        if (data.activeStudent) {
          setStudentInfo({
            name: data.activeStudent.name,
            grade: data.activeStudent.grade,
          });
        }
      })
      .catch(() => {});
  }, []);

  useEffect(() => {
    if (analysis) return;

    // 세션에 없으면 서버 history API에서 로드
    fetch("/api/diagnosis/history")
      .then((res) => res.json())
      .then((data) => {
        if (data.diagnoses && data.diagnoses.length > 0) {
          const target = diagnosisId
            ? data.diagnoses.find((d: { id: string }) => d.id === diagnosisId)
            : data.diagnoses[0];

          if (target && target.answers) {
            import("@/lib/diagnosis/analyzer").then(({ analyzeDiagnosis }) => {
              const res = analyzeDiagnosis(target.answers as DiagnosisAnswerInput[]);
              setAnalysis(res);
              setLoading(false);
            });
            return;
          }
        }
        setLoading(false);
      })
      .catch(() => setLoading(false));
  }, [diagnosisId, analysis]);

  const handlePrint = () => {
    window.print();
  };

  if (loading) {
    return (
      <div className="py-24 text-center">
        <div className="w-8 h-8 border-4 border-slate-900 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
        <p className="text-slate-600 font-medium">연산 진단 결과를 분석하고 있습니다...</p>
      </div>
    );
  }

  if (!analysis) {
    return (
      <div className="py-20 text-center max-w-md mx-auto space-y-4">
        <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto" />
        <h2 className="text-xl font-bold text-slate-800">진단 결과를 찾을 수 없습니다</h2>
        <p className="text-sm text-slate-600">
          새로운 5분 진단을 시작하여 연산 수준과 약점 분석을 진행해 보세요.
        </p>
        <Link
          href="/diagnosis"
          className="inline-block px-5 py-2.5 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800"
        >
          진단 시작하기
        </Link>
      </div>
    );
  }

  const {
    total,
    correct,
    accuracy,
    averageSeconds,
    slowestType,
    mostErrorsType,
    typeAnalyses,
    recommendedTraining,
    detailedAnswers,
  } = analysis;

  const todayStr = new Date().toLocaleDateString("ko-KR", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });

  return (
    <div className="space-y-8">
      {/* 상단 액션 바 (화면용) */}
      <div className="no-print bg-white border border-slate-200 rounded-xl p-4 sm:p-5 flex flex-wrap items-center justify-between gap-4">
        <div>
          <span className="text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded border border-emerald-200 inline-block mb-1">
            진단 완료
          </span>
          <h1 className="text-xl font-bold text-slate-900">
            초5 나눗셈 진단 결과 리포트
          </h1>
        </div>

        <div className="flex items-center gap-3">
          <button
            onClick={handlePrint}
            className="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-semibold rounded-lg hover:bg-slate-50 transition"
          >
            <Printer className="w-4 h-4 text-slate-600" />
            분석지 인쇄
          </button>
          <Link
            href={`/worksheet?type=${recommendedTraining.type}&difficulty=${recommendedTraining.difficulty}`}
            className="inline-flex items-center gap-1.5 px-5 py-2 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800 transition shadow-sm"
          >
            <FileText className="w-4 h-4" />
            맞춤 훈련지 만들기
          </Link>
        </div>
      </div>

      {/* 인쇄 전용 헤더 (A4 출력 시 표시, 명세 24) */}
      <div className="hidden print:block pb-4 mb-6 border-b border-slate-800">
        <div className="flex justify-between items-baseline">
          <div>
            <h1 className="text-2xl font-extrabold text-slate-900">
              초5 나눗셈 연산 역량 진단 및 맞춤 처방 리포트
            </h1>
            <p className="text-xs text-slate-600 mt-1">
              학생: {studentInfo.name} · 초등학교 {studentInfo.grade}학년 · 진단일: {todayStr}
            </p>
          </div>
          <div className="text-right">
            <span className="text-xs font-bold px-2 py-1 bg-slate-100 border border-slate-300 rounded">
              교사용 / 학부모용 분석지
            </span>
          </div>
        </div>
      </div>

      {/* 종합 성적 요약 카드 4개 (명세 10) */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white border border-slate-200 rounded-xl p-5">
          <span className="text-xs font-semibold text-slate-500 block mb-1">
            정확도
          </span>
          <div className="text-3xl font-extrabold text-slate-900">
            {accuracy}%
          </div>
          <span className="text-xs text-slate-500 mt-1 block">
            {correct} / {total} 문제 정답
          </span>
        </div>

        <div className="bg-white border border-slate-200 rounded-xl p-5">
          <span className="text-xs font-semibold text-slate-500 block mb-1">
            평균 풀이시간
          </span>
          <div className="text-3xl font-extrabold text-slate-900">
            {averageSeconds}초
          </div>
          <span className="text-xs text-slate-500 mt-1 block">
            문제당 소요 시간
          </span>
        </div>

        <div className="bg-white border border-slate-200 rounded-xl p-5">
          <span className="text-xs font-semibold text-slate-500 block mb-1">
            가장 느린 유형
          </span>
          <div className="text-xl font-bold text-amber-700 truncate mt-1">
            {PROBLEM_TYPE_LABELS[slowestType]}
          </div>
          <span className="text-xs text-slate-500 mt-1 block">
            평균 {(typeAnalyses[slowestType]?.averageMs / 1000).toFixed(1)}초 소요
          </span>
        </div>

        <div className="bg-white border border-slate-200 rounded-xl p-5">
          <span className="text-xs font-semibold text-slate-500 block mb-1">
            가장 많은 오답
          </span>
          <div className="text-xl font-bold text-red-700 truncate mt-1">
            {PROBLEM_TYPE_LABELS[mostErrorsType]}
          </div>
          <span className="text-xs text-slate-500 mt-1 block">
            오답률 {typeAnalyses[mostErrorsType]?.errorRate}%
          </span>
        </div>
      </div>

      {/* 맞춤 처방 및 추천 훈련 배너 (명세 4, 13, 24) */}
      <div className="bg-white border-2 border-slate-900 rounded-2xl p-6 sm:p-8">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
          <div>
            <div className="inline-flex items-center gap-1.5 text-xs font-bold text-amber-800 bg-amber-100 px-2.5 py-1 rounded-md mb-2">
              <Sparkles className="w-3.5 h-3.5" />
              진단 엔진 맞춤 처방
            </div>
            <h2 className="text-2xl font-extrabold text-slate-900">
              {recommendedTraining.title}
            </h2>
            <p className="text-sm text-slate-600 mt-1">
              {recommendedTraining.reason}
            </p>
          </div>

          <Link
            href={`/worksheet?type=${recommendedTraining.type}&difficulty=${recommendedTraining.difficulty}`}
            className="no-print inline-flex items-center justify-center gap-2 px-6 py-3 bg-slate-900 text-white font-semibold text-sm rounded-lg hover:bg-slate-800 transition shrink-0"
          >
            [ 맞춤 훈련지 만들기 ]
            <ArrowRight className="w-4 h-4" />
          </Link>
        </div>

        {/* 계산 꿀팁 전략 (명세 32) */}
        <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl mb-6">
          <p className="text-sm text-slate-800 font-medium leading-relaxed">
            {recommendedTraining.strategyHint}
          </p>
        </div>

        {/* 훈련지 추천 문제 샘플 미리보기 */}
        <div>
          <span className="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-2">
            훈련 세트 구성 문제 예시
          </span>
          <div className="grid grid-cols-2 sm:grid-cols-5 gap-2">
            {recommendedTraining.sampleProblems.map((prob, idx) => (
              <div
                key={idx}
                className="p-3 bg-white border border-slate-200 rounded-lg text-center font-mono text-sm font-semibold text-slate-800"
              >
                {prob}
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* 유형별 상세 분석 테이블 (명세 10 & 24) */}
      <div className="bg-white border border-slate-200 rounded-xl p-6">
        <h3 className="text-lg font-bold text-slate-900 mb-4">
          유형별 진단 및 자동화 수준
        </h3>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-slate-50 text-slate-600 border-b border-slate-200 text-xs uppercase">
              <tr>
                <th className="py-3 px-4 font-semibold">문제 유형</th>
                <th className="py-3 px-4 font-semibold text-center">문항 수</th>
                <th className="py-3 px-4 font-semibold text-center">정답률</th>
                <th className="py-3 px-4 font-semibold text-center">평균 풀이시간</th>
                <th className="py-3 px-4 font-semibold text-center">성취 상태</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {Object.values(typeAnalyses).map((t) => {
                if (t.total === 0) return null;
                const isSlow = t.averageMs > 15000;
                const isError = t.accuracy < 80;

                return (
                  <tr key={t.type} className="hover:bg-slate-50/50">
                    <td className="py-3 px-4 font-semibold text-slate-800">
                      {t.label}
                    </td>
                    <td className="py-3 px-4 text-center text-slate-600 font-mono">
                      {t.total}
                    </td>
                    <td className="py-3 px-4 text-center">
                      <span className={`font-bold font-mono ${isError ? "text-red-600" : "text-slate-900"}`}>
                        {t.accuracy}%
                      </span>
                    </td>
                    <td className="py-3 px-4 text-center font-mono">
                      <span className={isSlow ? "text-amber-700 font-bold" : "text-slate-700"}>
                        {(t.averageMs / 1000).toFixed(1)}초
                      </span>
                    </td>
                    <td className="py-3 px-4 text-center">
                      <span
                        className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold ${
                          t.status === "very_good"
                            ? "bg-slate-100 text-slate-800"
                            : t.status === "good"
                            ? "bg-slate-100 text-slate-700"
                            : t.status === "needs_improvement"
                            ? "bg-amber-100 text-amber-800"
                            : "bg-red-100 text-red-800"
                        }`}
                      >
                        {t.statusLabel}
                      </span>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* 20문제 전체 풀이 내역 상세 테이블 (명세 8) */}
      <div className="bg-white border border-slate-200 rounded-xl p-6">
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-lg font-bold text-slate-900">
            문항별 상세 풀이 기록
          </h3>
          <span className="text-xs text-slate-500">
            문제별 시작·제출 시각 및 반응 시간(ms) 기록 완료
          </span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-slate-50 text-slate-600 border-b border-slate-200 text-xs">
              <tr>
                <th className="py-2.5 px-3 font-semibold text-center w-12">#</th>
                <th className="py-2.5 px-3 font-semibold">문제</th>
                <th className="py-2.5 px-3 font-semibold text-center">유형</th>
                <th className="py-2.5 px-3 font-semibold text-center">입력한 답</th>
                <th className="py-2.5 px-3 font-semibold text-center">정답</th>
                <th className="py-2.5 px-3 font-semibold text-center">풀이시간</th>
                <th className="py-2.5 px-3 font-semibold text-center">결과</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 font-mono text-xs">
              {detailedAnswers.map((ans, idx) => {
                const sec = (ans.elapsedMs / 1000).toFixed(1);
                const isSlow = ans.elapsedMs >= 20000;

                return (
                  <tr key={idx} className={ans.correct ? "" : "bg-red-50/30"}>
                    <td className="py-2.5 px-3 text-center text-slate-500">{idx + 1}</td>
                    <td className="py-2.5 px-3 font-bold text-slate-900 font-sans">
                      {ans.question}
                    </td>
                    <td className="py-2.5 px-3 text-center font-sans text-[11px] text-slate-500">
                      {PROBLEM_TYPE_LABELS[ans.type]}
                    </td>
                    <td className="py-2.5 px-3 text-center font-bold">
                      {ans.userAnswer !== null ? ans.userAnswer : <span className="text-slate-300 font-sans">미입력</span>}
                    </td>
                    <td className="py-2.5 px-3 text-center text-slate-700">{ans.answer}</td>
                    <td className="py-2.5 px-3 text-center">
                      <span className={isSlow ? "text-amber-700 font-bold" : "text-slate-600"}>
                        {sec}초
                      </span>
                    </td>
                    <td className="py-2.5 px-3 text-center">
                      {ans.correct ? (
                        <span className="inline-flex items-center gap-1 text-emerald-700 font-sans text-xs font-semibold">
                          <CheckCircle2 className="w-3.5 h-3.5" /> 정답
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1 text-red-600 font-sans text-xs font-semibold">
                          <XCircle className="w-3.5 h-3.5" /> 오답
                        </span>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* 하단 다시 진단하기 링크 */}
      <div className="no-print flex justify-center gap-4 py-4">
        <Link
          href="/diagnosis"
          className="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-700 font-semibold text-sm hover:bg-slate-50"
        >
          <RotateCcw className="w-4 h-4" />
          새 진단 다시 하기
        </Link>
        <Link
          href="/"
          className="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-slate-900 text-white font-semibold text-sm hover:bg-slate-800"
        >
          대시보드로 돌아가기
        </Link>
      </div>
    </div>
  );
}

export default function DiagnosisResultPage() {
  return (
    <Suspense
      fallback={
        <div className="py-20 text-center text-slate-500">
          진단 리포트를 불러오고 있습니다...
        </div>
      }
    >
      <DiagnosisResultContent />
    </Suspense>
  );
}
