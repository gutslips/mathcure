"use client";

import { useState, useEffect, useRef, Suspense } from "react";
import { useSearchParams } from "next/navigation";
import { Problem, ProblemType, PROBLEM_TYPE_LABELS } from "@/types/problem";
import { generateProblemsByType } from "@/lib/generators";
import { 
  RotateCcw, 
  CheckCircle2, 
  XCircle, 
  HelpCircle,
  Award
} from "lucide-react";

function PracticeContent() {
  const searchParams = useSearchParams();
  const initialType = (searchParams.get("type") as ProblemType) || "divide5";

  const [type, setType] = useState<ProblemType>(initialType);
  const [difficulty, setDifficulty] = useState(2);
  const [problems, setProblems] = useState<Problem[]>(() =>
    generateProblemsByType(initialType, 10, 2)
  );
  const [currentIndex, setCurrentIndex] = useState(0);
  const [inputValue, setInputValue] = useState("");
  const [feedback, setFeedback] = useState<{ isCorrect: boolean; message: string; elapsedSec: number } | null>(null);
  const [results, setResults] = useState<{ correct: boolean; elapsedMs: number }[]>([]);
  const [showHint, setShowHint] = useState(false);

  const problemStartTime = useRef<number>(0);
  const inputRef = useRef<HTMLInputElement>(null);

  // 마운트 시 문제 풀이 시작 시점 기록
  useEffect(() => {
    problemStartTime.current = Date.now();
  }, []);

  const loadSet = (t: ProblemType, d: number) => {
    const list = generateProblemsByType(t, 10, d);
    setProblems(list);
    setCurrentIndex(0);
    setInputValue("");
    setFeedback(null);
    setResults([]);
    setShowHint(false);
    problemStartTime.current = Date.now();
  };

  const handleTypeChange = (newType: ProblemType) => {
    setType(newType);
    loadSet(newType, difficulty);
  };

  const handleDifficultyChange = (newDiff: number) => {
    setDifficulty(newDiff);
    loadSet(type, newDiff);
  };

  useEffect(() => {
    if (inputRef.current) {
      inputRef.current.focus();
    }
  }, [currentIndex, feedback]);

  const handleSubmitAnswer = (e?: React.FormEvent) => {
    if (e) e.preventDefault();
    if (!problems[currentIndex] || feedback) return;

    const prob = problems[currentIndex];
    const now = Date.now();
    const start = problemStartTime.current || now - 1000;
    const elapsedMs = Math.max(100, now - start);
    const elapsedSec = Number((elapsedMs / 1000).toFixed(1));
    const userNum = Number(inputValue.trim());
    const isCorrect = userNum === prob.answer;

    setResults((prev) => [...prev, { correct: isCorrect, elapsedMs }]);

    setFeedback({
      isCorrect,
      elapsedSec,
      message: isCorrect
        ? elapsedSec <= 5
          ? `완벽한 자동화! (${elapsedSec}초)`
          : `정답입니다! (${elapsedSec}초)`
        : `오답입니다. (정답: ${prob.answer})`,
    });
  };

  const handleNextProblem = () => {
    setFeedback(null);
    setInputValue("");
    setShowHint(false);
    if (currentIndex < problems.length - 1) {
      setCurrentIndex((prev) => prev + 1);
      problemStartTime.current = Date.now();
    }
  };

  const isCompleted = results.length === problems.length && problems.length > 0;
  const correctCount = results.filter((r) => r.correct).length;
  const totalElapsedMs = results.reduce((sum, r) => sum + r.elapsedMs, 0);
  const avgSec = results.length > 0 ? (totalElapsedMs / results.length / 1000).toFixed(1) : "0";

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      {/* 훈련 설정 바 */}
      <div className="bg-white border border-slate-200 rounded-xl p-4 flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">
            훈련 유형
          </span>
          <select
            value={type}
            onChange={(e) => handleTypeChange(e.target.value as ProblemType)}
            className="px-3 py-1.5 border border-slate-300 rounded-lg text-sm font-semibold bg-white text-slate-800"
          >
            <option value="divide5">÷5 자동화</option>
            <option value="divide8">÷8 자동화</option>
            <option value="divide4">÷4 자동화</option>
            <option value="divide2">÷2 자동화</option>
            <option value="divide10">÷10 자동화</option>
            <option value="largeNumber">큰 수 자리값</option>
            <option value="mixed">초5 혼합</option>
            <option value="basic">기본 나눗셈</option>
          </select>

          <select
            value={difficulty}
            onChange={(e) => handleDifficultyChange(Number(e.target.value))}
            className="px-3 py-1.5 border border-slate-300 rounded-lg text-sm font-semibold bg-white text-slate-800"
          >
            <option value={1}>Level 1</option>
            <option value={2}>Level 2</option>
            <option value={3}>Level 3</option>
          </select>
        </div>

        <button
          onClick={() => loadSet(type, difficulty)}
          className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 rounded-lg text-xs font-semibold text-slate-700 transition"
        >
          <RotateCcw className="w-3.5 h-3.5" />
          새로 시작
        </button>
      </div>

      {!isCompleted ? (
        problems[currentIndex] && (
          <div className="bg-white border border-slate-200 rounded-2xl p-8 sm:p-12 text-center space-y-6 shadow-sm">
            <div className="flex items-center justify-between text-xs font-semibold text-slate-400">
              <span>문제 {currentIndex + 1} / {problems.length}</span>
              <span>{PROBLEM_TYPE_LABELS[type]}</span>
            </div>

            <div className="text-4xl sm:text-6xl font-extrabold text-slate-900 font-mono tracking-tight my-4">
              {problems[currentIndex].question} <span className="text-slate-400 font-light">=</span>
            </div>

            <form onSubmit={handleSubmitAnswer} className="max-w-xs mx-auto space-y-4">
              <input
                ref={inputRef}
                type="number"
                disabled={Boolean(feedback)}
                value={inputValue}
                onChange={(e) => setInputValue(e.target.value)}
                placeholder="답 입력 후 Enter"
                className="w-full text-center text-3xl font-bold font-mono py-3 border-2 border-slate-300 rounded-xl focus:outline-none focus:border-slate-900"
                autoFocus
              />

              {!feedback ? (
                <button
                  type="submit"
                  className="w-full py-2.5 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 transition"
                >
                  확인
                </button>
              ) : (
                <button
                  type="button"
                  onClick={handleNextProblem}
                  className="w-full py-2.5 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 transition"
                  autoFocus
                >
                  다음 문제 ➔
                </button>
              )}
            </form>

            {/* 피드백 알림 */}
            {feedback && (
              <div
                className={`p-3.5 rounded-xl text-sm font-semibold flex items-center justify-center gap-2 ${
                  feedback.isCorrect
                    ? "bg-emerald-50 text-emerald-800 border border-emerald-200"
                    : "bg-red-50 text-red-800 border border-red-200"
                }`}
              >
                {feedback.isCorrect ? (
                  <CheckCircle2 className="w-5 h-5 text-emerald-600" />
                ) : (
                  <XCircle className="w-5 h-5 text-red-600" />
                )}
                <span>{feedback.message}</span>
              </div>
            )}

            {/* 힌트 토글 */}
            <div className="pt-2">
              {!showHint ? (
                <button
                  onClick={() => setShowHint(true)}
                  className="text-xs text-slate-500 hover:text-slate-800 font-medium underline inline-flex items-center gap-1"
                >
                  <HelpCircle className="w-3.5 h-3.5" />
                  계산 전략 힌트 보기
                </button>
              ) : (
                <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700 text-left">
                  {problems[currentIndex].hint || "전략 힌트가 없습니다."}
                </div>
              )}
            </div>
          </div>
        )
      ) : (
        /* 완료 카드 */
        <div className="bg-white border border-slate-200 rounded-2xl p-8 text-center space-y-6">
          <div className="w-12 h-12 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center mx-auto font-bold">
            <Award className="w-6 h-6" />
          </div>
          <div>
            <h2 className="text-xl font-bold text-slate-900">
              10문제 훈련 완료!
            </h2>
            <p className="text-xs text-slate-500 mt-1">
              반복 훈련을 통해 계산 자동화 속도가 지속적으로 향상됩니다.
            </p>
          </div>

          <div className="grid grid-cols-2 gap-4 max-w-sm mx-auto">
            <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl">
              <span className="text-xs text-slate-500 block mb-1">정확도</span>
              <span className="text-2xl font-black text-slate-900">
                {Math.round((correctCount / problems.length) * 100)}%
              </span>
              <span className="text-[11px] text-slate-400 block mt-0.5">
                {correctCount}/{problems.length} 정답
              </span>
            </div>

            <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl">
              <span className="text-xs text-slate-500 block mb-1">평균 시간</span>
              <span className="text-2xl font-black text-slate-900">
                {avgSec}초
              </span>
              <span className="text-[11px] text-slate-400 block mt-0.5">
                문제당 풀이 속도
              </span>
            </div>
          </div>

          <button
            onClick={() => loadSet(type, difficulty)}
            className="px-6 py-2.5 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 transition"
          >
            한 번 더 풀기
          </button>
        </div>
      )}
    </div>
  );
}

export default function PracticePage() {
  return (
    <Suspense fallback={<div className="py-20 text-center text-slate-500">훈련을 준비하고 있습니다...</div>}>
      <PracticeContent />
    </Suspense>
  );
}
