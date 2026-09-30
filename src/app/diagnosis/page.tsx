"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { 
  Calculator, 
  Clock, 
  ArrowRight,
  ShieldCheck
} from "lucide-react";

export default function DiagnosisSetupPage() {
  const router = useRouter();

  const grade = "초5";
  const domain = "나눗셈";
  const [problemCount, setProblemCount] = useState(20);
  const [timeLimit, setTimeLimit] = useState(5); // 분
  const [difficulty, setDifficulty] = useState("표준");

  const [recordTime, setRecordTime] = useState(true);
  const [analyzeErrors, setAnalyzeErrors] = useState(true);
  const [balancedQuota, setBalancedQuota] = useState(true);

  const [loading, setLoading] = useState(false);

  const handleStart = () => {
    setLoading(true);
    // 진단 시작 페이지로 이동 (설정 파라미터 전달)
    router.push(
      `/diagnosis/start?count=${problemCount}&timeLimit=${timeLimit}&seed=diag_${Date.now()}`
    );
  };

  return (
    <div className="max-w-3xl mx-auto space-y-6">
      <div className="bg-white border border-slate-200 rounded-xl p-6 sm:p-8">
        <div className="flex items-center gap-3 mb-4">
          <div className="w-10 h-10 rounded-lg bg-slate-900 text-white flex items-center justify-center font-bold">
            <Calculator className="w-5 h-5" />
          </div>
          <div>
            <h1 className="text-xl font-bold text-slate-900">
              초5 나눗셈 연산 진단 설정
            </h1>
            <p className="text-sm text-slate-600">
              단순한 채점이 아닌 문제별 반응 속도와 오답 유형을 정밀 진단합니다.
            </p>
          </div>
        </div>

        <div className="border-t border-slate-100 my-6"></div>

        {/* 기본 설정 영역 (명세 5) */}
        <div className="space-y-5">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1.5">
                학년
              </label>
              <input
                type="text"
                disabled
                value={grade}
                className="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-slate-700 text-sm font-medium cursor-not-allowed"
              />
              <span className="text-[11px] text-slate-400 mt-1 block">현재 초5 나눗셈 집중 진단 모드</span>
            </div>

            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1.5">
                영역
              </label>
              <input
                type="text"
                disabled
                value={domain}
                className="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-slate-700 text-sm font-medium cursor-not-allowed"
              />
              <span className="text-[11px] text-slate-400 mt-1 block">자연수의 나눗셈 (기본 사실 ~ 큰 수 자동화)</span>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1.5">
                문제 수
              </label>
              <select
                value={problemCount}
                onChange={(e) => setProblemCount(Number(e.target.value))}
                className="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-slate-900"
              >
                <option value={20}>20문제 (권장 표준)</option>
                <option value={10}>10문제 (약식 진단)</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1.5">
                제한 시간
              </label>
              <select
                value={timeLimit}
                onChange={(e) => setTimeLimit(Number(e.target.value))}
                className="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-slate-900"
              >
                <option value={5}>5분 (문제당 15초 표준)</option>
                <option value={7}>7분 (여유 모드)</option>
                <option value={3}>3분 (빠른 암산 모드)</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-semibold text-slate-700 mb-1.5">
                난이도
              </label>
              <select
                value={difficulty}
                onChange={(e) => setDifficulty(e.target.value)}
                className="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-slate-900"
              >
                <option value="표준">표준 (초5 교과 기준)</option>
                <option value="도전">도전 (큰 수 비중 증가)</option>
              </select>
            </div>
          </div>

          {/* 세부 진단 옵션 (명세 5) */}
          <div className="pt-4 border-t border-slate-100">
            <span className="block text-sm font-semibold text-slate-700 mb-3">
              진단 엔진 분석 옵션
            </span>
            <div className="space-y-3">
              <label className="flex items-center gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  checked={recordTime}
                  onChange={(e) => setRecordTime(e.target.checked)}
                  className="w-4 h-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                />
                <span className="text-sm text-slate-700">
                  <strong className="font-semibold text-slate-900">문제별 풀이시간 정밀 측정</strong>
                  {" — "}각 문제의 시작 시간과 제출 시간의 밀리초(ms)를 기록합니다.
                </span>
              </label>

              <label className="flex items-center gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  checked={analyzeErrors}
                  onChange={(e) => setAnalyzeErrors(e.target.checked)}
                  className="w-4 h-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                />
                <span className="text-sm text-slate-700">
                  <strong className="font-semibold text-slate-900">오답 원인 분석</strong>
                  {" — "}구구단 미숙, 자리값 오차, 특정 나눗셈(÷5, ÷8) 막힘을 분류합니다.
                </span>
              </label>

              <label className="flex items-center gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  checked={balancedQuota}
                  onChange={(e) => setBalancedQuota(e.target.checked)}
                  className="w-4 h-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                />
                <span className="text-sm text-slate-700">
                  <strong className="font-semibold text-slate-900">문제 유형별 균형 유지 (Quota 배분)</strong>
                  {" — "}기본 5개, 전략 5개, 큰 수 5개, 초5 혼합 5개 균형 출제
                </span>
              </label>
            </div>
          </div>

          {/* 안내 배너 */}
          <div className="p-4 bg-slate-50 border border-slate-200 rounded-lg flex items-start gap-3">
            <ShieldCheck className="w-5 h-5 text-slate-700 shrink-0 mt-0.5" />
            <div className="text-xs text-slate-600 leading-relaxed">
              <strong className="text-slate-800">진단 진행 안내:</strong> 시험이 시작되면 상단 타이머가 작동하며 20문제가 차례대로 제공됩니다.
              답을 입력하고 <kbd className="px-1.5 py-0.5 bg-white border border-slate-300 rounded text-slate-800 font-mono text-[10px]">Enter</kbd>를 누르면
              다음 문제로 자동 이동합니다. 풀지 못하는 문제는 넘어가도 되며 언제든 이전 문제로 돌아올 수 있습니다.
            </div>
          </div>
        </div>

        {/* 시작 버튼 */}
        <div className="mt-8 pt-5 border-t border-slate-100 flex items-center justify-between">
          <div className="text-xs text-slate-500 flex items-center gap-1.5">
            <Clock className="w-4 h-4 text-slate-400" />
            총 20문제 · 권장 시간 5분
          </div>

          <button
            onClick={handleStart}
            disabled={loading}
            className="inline-flex items-center gap-2 px-6 py-3 bg-slate-900 text-white font-semibold text-sm rounded-lg hover:bg-slate-800 transition disabled:opacity-50"
          >
            {loading ? "문제 준비 중..." : "진단 시작"}
            <ArrowRight className="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  );
}
