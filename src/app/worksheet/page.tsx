"use client";

import { useState, useMemo, Suspense } from "react";
import { useSearchParams } from "next/navigation";
import { ProblemType } from "@/types/problem";
import { generateWorksheet } from "@/lib/worksheet/generator";
import { 
  Printer, 
  RefreshCw, 
  CheckSquare
} from "lucide-react";

function WorksheetContent() {
  const searchParams = useSearchParams();
  const initialType = (searchParams.get("type") as ProblemType) || "divide5";
  const initialDifficulty = Number(searchParams.get("difficulty")) || 2;

  const [studentName, setStudentName] = useState("홍길동");
  const [targetType, setTargetType] = useState<ProblemType>(initialType);
  const [problemCount, setProblemCount] = useState(20);
  const [difficulty, setDifficulty] = useState(initialDifficulty);
  const [includeTimerRecord, setIncludeTimerRecord] = useState(true);
  const [activeTab, setActiveTab] = useState<"worksheet" | "answers">("worksheet");
  const [refreshKey, setRefreshKey] = useState(0);

  // useMemo로 렌더링 중 계산 (setState in effect 방지)
  const worksheet = useMemo(() => {
    return generateWorksheet({
      studentName,
      grade: 5,
      subject: "나눗셈",
      targetType,
      count: problemCount,
      difficulty,
      includeTimerRecord,
      seed: refreshKey > 0 ? `ws_${refreshKey}` : undefined,
    });
  }, [studentName, targetType, problemCount, difficulty, includeTimerRecord, refreshKey]);

  const handlePrint = (tab: "worksheet" | "answers") => {
    setActiveTab(tab);
    setTimeout(() => {
      window.print();
    }, 100);
  };

  return (
    <div className="space-y-8">
      {/* 훈련지 설정 패널 (화면용, 인쇄시 숨김) */}
      <div className="no-print bg-white border border-slate-200 rounded-xl p-6">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-slate-100">
          <div>
            <div className="flex items-center gap-2">
              <span className="w-2 h-2 rounded-full bg-slate-900"></span>
              <h1 className="text-xl font-bold text-slate-900">
                맞춤형 연산 훈련지 생성
              </h1>
            </div>
            <p className="text-xs text-slate-500 mt-1">
              진단 약점(60%)과 기본 사실(20%), 복습(20%)이 조화된 A4 전용 프린트 문제지
            </p>
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={() => handlePrint("worksheet")}
              className="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800 transition shadow-sm"
            >
              <Printer className="w-4 h-4" />
              [ 문제지 인쇄 ]
            </button>
            <button
              onClick={() => handlePrint("answers")}
              className="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-semibold rounded-lg hover:bg-slate-50 transition"
            >
              <CheckSquare className="w-4 h-4 text-slate-600" />
              [ 정답지 인쇄 ]
            </button>
          </div>
        </div>

        {/* 설정 필드 그리드 (명세 19) */}
        <div className="grid grid-cols-2 sm:grid-cols-5 gap-4 text-sm">
          <div>
            <label className="block text-xs font-semibold text-slate-600 mb-1">
              학생 이름
            </label>
            <input
              type="text"
              value={studentName}
              onChange={(e) => setStudentName(e.target.value)}
              className="w-full px-3 py-2 border border-slate-300 rounded-lg text-slate-800 font-medium"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-600 mb-1">
              집중 훈련 유형
            </label>
            <select
              value={targetType}
              onChange={(e) => setTargetType(e.target.value as ProblemType)}
              className="w-full px-3 py-2 border border-slate-300 rounded-lg text-slate-800 font-medium bg-white"
            >
              <option value="divide5">÷5 큰 수 자동화 (추천)</option>
              <option value="divide8">÷8 자동화</option>
              <option value="divide4">÷4 자동화</option>
              <option value="divide2">÷2 자동화</option>
              <option value="divide10">÷10 자동화</option>
              <option value="largeNumber">큰 수 자리값 처리</option>
              <option value="mixed">초5 혼합 계산</option>
              <option value="basic">기본 나눗셈</option>
            </select>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-600 mb-1">
              난이도
            </label>
            <select
              value={difficulty}
              onChange={(e) => setDifficulty(Number(e.target.value))}
              className="w-full px-3 py-2 border border-slate-300 rounded-lg text-slate-800 font-medium bg-white"
            >
              <option value={1}>Level 1 (원리/기초)</option>
              <option value={2}>Level 2 (표준 숙달)</option>
              <option value={3}>Level 3 (큰 수 확장)</option>
              <option value={4}>Level 4 (고난도 혼합)</option>
            </select>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-600 mb-1">
              문제 수
            </label>
            <select
              value={problemCount}
              onChange={(e) => setProblemCount(Number(e.target.value))}
              className="w-full px-3 py-2 border border-slate-300 rounded-lg text-slate-800 font-medium bg-white"
            >
              <option value={20}>20문제 (A4 1장 최적)</option>
              <option value={10}>10문제 (간이 시험)</option>
              <option value={30}>30문제 (집중 훈련)</option>
            </select>
          </div>

          <div className="flex flex-col justify-end">
            <button
              onClick={() => setRefreshKey((prev) => prev + 1)}
              className="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold rounded-lg text-xs transition"
            >
              <RefreshCw className="w-3.5 h-3.5" />
              새 문제 세트 생성
            </button>
          </div>
        </div>

        {/* 옵션 토글 */}
        <div className="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600">
          <label className="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              checked={includeTimerRecord}
              onChange={(e) => setIncludeTimerRecord(e.target.checked)}
              className="w-4 h-4 rounded border-slate-300 text-slate-900"
            />
            <span>문제별 풀이시간 기록칸 포함 (예: [ 풀이시간: ___초 ])</span>
          </label>

          <span className="font-mono text-slate-400">
            Seed: {worksheet.seed}
          </span>
        </div>
      </div>

      {/* 미리보기 탭 전환 (화면용) */}
      <div className="no-print flex items-center justify-center gap-2">
        <button
          onClick={() => setActiveTab("worksheet")}
          className={`px-5 py-2 rounded-lg text-sm font-semibold transition ${
            activeTab === "worksheet"
              ? "bg-slate-900 text-white"
              : "bg-white border border-slate-200 text-slate-600 hover:bg-slate-50"
          }`}
        >
          📄 문제지 미리보기
        </button>
        <button
          onClick={() => setActiveTab("answers")}
          className={`px-5 py-2 rounded-lg text-sm font-semibold transition ${
            activeTab === "answers"
              ? "bg-slate-900 text-white"
              : "bg-white border border-slate-200 text-slate-600 hover:bg-slate-50"
          }`}
        >
          ✅ 정답지 미리보기
        </button>
      </div>

      {/* ======================================================== */}
      {/* A4 인쇄 규격 영역 (명세 20, 21, 22) */}
      {/* ======================================================== */}

      {activeTab === "worksheet" ? (
        /* [1] 문제지 영역 */
        <div className="a4-preview-box worksheet-sheet text-slate-900">
          {/* 헤더 */}
          <div className="border-b-2 border-slate-900 pb-3 mb-6 flex justify-between items-end">
            <div>
              <span className="text-[12px] font-bold text-slate-500 uppercase tracking-widest block">
                MATH CURE · 초5 연산 트레이닝
              </span>
              <h2 className="text-2xl font-black text-slate-900 mt-0.5">
                {worksheet.title}
              </h2>
              <span className="text-xs text-slate-600 font-medium">
                {worksheet.subtitle}
              </span>
            </div>

            <div className="text-right text-xs space-y-1">
              <div>
                <span className="text-slate-500">이름:</span>{" "}
                <span className="font-bold underline underline-offset-4 inline-block min-w-[70px] text-center">
                  {worksheet.studentName}
                </span>
              </div>
              <div>
                <span className="text-slate-500">날짜:</span>{" "}
                <span className="font-bold underline underline-offset-4 inline-block min-w-[70px] text-center">
                  {worksheet.date}
                </span>
              </div>
              <div>
                <span className="text-slate-500">걸린 시간:</span>{" "}
                <span className="font-bold underline underline-offset-4 inline-block min-w-[70px] text-center">
                  &nbsp;&nbsp;&nbsp;&nbsp;분 &nbsp;&nbsp;&nbsp;&nbsp;초
                </span>
              </div>
            </div>
          </div>

          {/* 문제 그리드 */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-12 gap-y-7 my-4 text-base">
            {worksheet.problems.map((prob, idx) => (
              <div
                key={prob.id}
                className="flex items-baseline justify-between border-b border-slate-200 pb-2 page-break-inside-avoid"
              >
                <div className="flex items-baseline gap-2 font-mono">
                  <span className="font-bold text-slate-400 w-7 text-right text-sm">
                    {idx + 1}.
                  </span>
                  <span className="text-lg font-bold text-slate-900 tracking-tight">
                    {prob.question} <span className="font-normal text-slate-400">=</span>
                  </span>
                </div>

                <div className="flex items-center gap-3">
                  <span className="inline-block w-24 border-b-2 border-slate-400"></span>
                  {worksheet.includeTimerRecord && (
                    <span className="text-[10px] text-slate-400 font-sans tracking-tighter whitespace-nowrap">
                      (___초)
                    </span>
                  )}
                </div>
              </div>
            ))}
          </div>

          {/* 하단 푸터 (명세 22) */}
          <div className="mt-12 pt-4 border-t border-slate-300 flex justify-between items-center text-[11px] text-slate-400">
            <span>초5 나눗셈 자동화 프로젝트 · MathCure</span>
            <span>Seed: {worksheet.seed}</span>
            <span>Page 1 / 1</span>
          </div>
        </div>
      ) : (
        /* [2] 정답지 영역 (명세 23: 문제지와 완전히 분리) */
        <div className="a4-preview-box worksheet-sheet text-slate-900">
          <div className="border-b-2 border-slate-900 pb-3 mb-6 flex justify-between items-end">
            <div>
              <span className="text-[12px] font-bold text-slate-500 uppercase tracking-widest block">
                ANSWER KEY · 정답 및 빠른 채점표
              </span>
              <h2 className="text-2xl font-black text-slate-900 mt-0.5">
                {worksheet.title} - [ 정답지 ]
              </h2>
              <span className="text-xs text-slate-600 font-medium">
                {worksheet.subtitle} · Seed: {worksheet.seed}
              </span>
            </div>

            <div className="text-right text-xs">
              <span className="px-2 py-1 bg-slate-100 border border-slate-300 rounded font-bold">
                교사용 / 채점용
              </span>
            </div>
          </div>

          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 my-6">
            {worksheet.problems.map((prob, idx) => (
              <div
                key={prob.id}
                className="p-3 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between"
              >
                <span className="font-bold text-slate-500 text-sm font-mono">
                  {idx + 1}번
                </span>
                <span className="font-bold text-slate-900 text-lg font-mono">
                  {prob.answer}
                </span>
              </div>
            ))}
          </div>

          <div className="mt-12 p-4 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-600 leading-relaxed">
            <strong className="text-slate-800 block mb-1">지도 조언:</strong>
            아이의 채점 시 단순히 맞고 틀림만 확인하지 마시고, 문제 옆에 기록된 풀이 시간을 확인하여
            15초 이상 지체된 문제의 경우 나눗셈 전략(예: ÷5는 2배 후 10 나누기)을 다시 짚어주세요.
          </div>

          <div className="mt-8 pt-4 border-t border-slate-300 flex justify-between items-center text-[11px] text-slate-400">
            <span>초5 나눗셈 자동화 프로젝트 · MathCure 정답지</span>
            <span>Page 1 / 1</span>
          </div>
        </div>
      )}
    </div>
  );
}

export default function WorksheetPage() {
  return (
    <Suspense
      fallback={
        <div className="py-20 text-center text-slate-500">
          훈련지 생성기를 로드하고 있습니다...
        </div>
      }
    >
      <WorksheetContent />
    </Suspense>
  );
}
