"use client";

import { useState, useEffect, useRef, useCallback, Suspense } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Problem, ProblemType, PROBLEM_TYPE_LABELS } from "@/types/problem";
import { generateDiagnosisProblems } from "@/lib/generators";
import { 
  Clock, 
  ChevronLeft, 
  ChevronRight, 
  HelpCircle, 
  Send
} from "lucide-react";

interface AnswerState {
  problemId: string;
  question: string;
  dividend: number;
  divisor: number;
  answer: number;
  userAnswer: number | null;
  elapsedMs: number;
  type: ProblemType;
  difficulty: number;
  usedHint?: boolean;
}

function DiagnosisRunContent() {
  const router = useRouter();
  const searchParams = useSearchParams();

  const countParam = Number(searchParams.get("count")) || 20;
  const timeLimitMin = Number(searchParams.get("timeLimit")) || 5;
  const seedParam = searchParams.get("seed") || "diag_default_seed";

  // 초기 문제 생성 (lazy useState)
  const [problems] = useState<Problem[]>(() => {
    const list = generateDiagnosisProblems(seedParam);
    return list.slice(0, countParam);
  });

  const [currentIndex, setCurrentIndex] = useState(0);
  const [answers, setAnswers] = useState<Record<number, AnswerState>>({});
  const [currentInput, setCurrentInput] = useState("");
  const [showHint, setShowHint] = useState(false);

  // 타이머 관련 (초)
  const totalSecondsLimit = timeLimitMin * 60;
  const [secondsRemaining, setSecondsRemaining] = useState(totalSecondsLimit);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // 문제별 풀이 시간 측정을 위한 타임스탬프 ref
  const problemEnterTimeRef = useRef<number>(0);
  const startedAtRef = useRef<Date | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  // 컴포넌트 마운트 시 타이머 시작점 기록
  useEffect(() => {
    startedAtRef.current = new Date();
    problemEnterTimeRef.current = Date.now();
  }, []);

  // 답변 기록 헬퍼
  const commitCurrentAnswer = useCallback(() => {
    if (!problems[currentIndex]) return;

    const currentProb = problems[currentIndex];
    const now = Date.now();
    const startTime = problemEnterTimeRef.current || now;
    const additionalElapsed = now - startTime;
    const prevElapsed = answers[currentIndex]?.elapsedMs || 0;
    const parsedVal = currentInput.trim() === "" ? null : Number(currentInput);

    setAnswers((prev) => ({
      ...prev,
      [currentIndex]: {
        problemId: currentProb.id,
        question: currentProb.question,
        dividend: currentProb.dividend,
        divisor: currentProb.divisor,
        answer: currentProb.answer,
        userAnswer: parsedVal !== null && !isNaN(parsedVal) ? parsedVal : null,
        elapsedMs: prevElapsed + additionalElapsed,
        type: currentProb.type,
        difficulty: currentProb.difficulty,
        usedHint: showHint || Boolean(prev[currentIndex]?.usedHint),
      },
    }));

    problemEnterTimeRef.current = Date.now();
  }, [currentIndex, currentInput, answers, problems, showHint]);

  // 제출 핸들러
  const handleSubmit = useCallback(async () => {
    commitCurrentAnswer();
    setIsSubmitting(true);

    const finishedAt = new Date();
    const finalAnswerList = problems.map((prob, idx) => {
      const recorded = answers[idx];
      const parsedVal =
        idx === currentIndex && currentInput.trim() !== ""
          ? Number(currentInput)
          : recorded?.userAnswer;
      const additionalMs =
        idx === currentIndex && problemEnterTimeRef.current
          ? Date.now() - problemEnterTimeRef.current
          : 0;
      const totalMs = (recorded?.elapsedMs || 0) + additionalMs;

      return {
        problemId: prob.id,
        question: prob.question,
        dividend: prob.dividend,
        divisor: prob.divisor,
        answer: prob.answer,
        userAnswer:
          parsedVal !== null && parsedVal !== undefined && !isNaN(parsedVal)
            ? parsedVal
            : null,
        elapsedMs: Math.max(200, totalMs),
        type: prob.type,
        difficulty: prob.difficulty,
        usedHint: recorded?.usedHint || (idx === currentIndex && showHint),
      };
    });

    try {
      const res = await fetch("/api/diagnosis/submit", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          answers: finalAnswerList,
          startedAt: startedAtRef.current?.toISOString() || new Date().toISOString(),
          finishedAt: finishedAt.toISOString(),
        }),
      });

      const data = await res.json();
      if (data.success) {
        sessionStorage.setItem("lastDiagnosisAnalysis", JSON.stringify(data.analysis));
        router.push(`/diagnosis/result?id=${data.diagnosisId}`);
      } else {
        alert("진단 결과 저장 중 오류가 발생했습니다: " + (data.error || ""));
        setIsSubmitting(false);
      }
    } catch (err) {
      console.error(err);
      alert("서버 연결에 실패했습니다.");
      setIsSubmitting(false);
    }
  }, [answers, commitCurrentAnswer, currentIndex, currentInput, problems, router, showHint]);

  const handleTimeExpired = useCallback(() => {
    alert("5분 제한 시간이 종료되었습니다! 현재까지 작성된 답안으로 자동 제출됩니다.");
    handleSubmit();
  }, [handleSubmit]);

  // 카운트다운 타이머
  useEffect(() => {
    if (problems.length === 0 || isSubmitting) return;

    const timer = setInterval(() => {
      setSecondsRemaining((prev) => {
        if (prev <= 1) {
          clearInterval(timer);
          handleTimeExpired();
          return 0;
        }
        return prev - 1;
      });
    }, 1000);

    return () => clearInterval(timer);
  }, [problems.length, isSubmitting, handleTimeExpired]);

  // 문제 이동 시 상태 전환
  const moveToProblem = (targetIndex: number) => {
    commitCurrentAnswer();
    setCurrentIndex(targetIndex);
    const targetAnswer = answers[targetIndex];
    setCurrentInput(
      targetAnswer?.userAnswer !== null && targetAnswer?.userAnswer !== undefined
        ? String(targetAnswer.userAnswer)
        : ""
    );
    setShowHint(Boolean(targetAnswer?.usedHint));
    problemEnterTimeRef.current = Date.now();

    if (inputRef.current) {
      inputRef.current.focus();
    }
  };

  const handleNext = () => {
    if (currentIndex < problems.length - 1) {
      moveToProblem(currentIndex + 1);
    }
  };

  const handlePrev = () => {
    if (currentIndex > 0) {
      moveToProblem(currentIndex - 1);
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === "Enter") {
      e.preventDefault();
      if (currentIndex < problems.length - 1) {
        handleNext();
      } else {
        commitCurrentAnswer();
      }
    }
  };

  if (problems.length === 0) {
    return (
      <div className="py-20 text-center">
        <div className="w-8 h-8 border-4 border-slate-900 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
        <p className="text-slate-600 font-medium">초5 나눗셈 진단 문제를 생성하고 있습니다...</p>
      </div>
    );
  }

  const currentProblem = problems[currentIndex];
  const minutes = Math.floor(secondsRemaining / 60);
  const seconds = secondsRemaining % 60;
  const isTimeUrgent = secondsRemaining < 60;
  const answeredCount = Object.values(answers).filter((a) => a.userAnswer !== null).length;

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      {/* 상단 컨트롤 바 */}
      <div className="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 flex flex-wrap items-center justify-between gap-4 sticky top-20 z-20 shadow-sm">
        <div className="flex items-center gap-4">
          <span className="text-sm font-bold text-slate-900">
            문제 {currentIndex + 1} / {problems.length}
          </span>
          <span className="text-xs px-2.5 py-1 bg-slate-100 text-slate-700 rounded-md font-medium">
            {PROBLEM_TYPE_LABELS[currentProblem.type]}
          </span>
        </div>

        {/* 5분 타이머 */}
        <div className={`flex items-center gap-2 px-3 py-1.5 rounded-lg border font-mono font-bold text-sm ${
          isTimeUrgent ? "bg-red-50 text-red-700 border-red-200 animate-pulse" : "bg-slate-50 text-slate-800 border-slate-200"
        }`}>
          <Clock className="w-4 h-4 text-slate-500" />
          <span>남은 시간</span>
          <span className="text-base tracking-wider">
            {String(minutes).padStart(2, "0")}:{String(seconds).padStart(2, "0")}
          </span>
        </div>

        <button
          onClick={handleSubmit}
          disabled={isSubmitting}
          className="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-900 text-white text-xs font-semibold rounded-lg hover:bg-slate-800 transition disabled:opacity-50"
        >
          <Send className="w-3.5 h-3.5" />
          {isSubmitting ? "채점 분석 중..." : `제출하고 결과 보기 (${answeredCount}/${problems.length})`}
        </button>
      </div>

      {/* 문제 카드 (중앙 핵심) */}
      <div className="bg-white border border-slate-200 rounded-2xl p-8 sm:p-14 text-center space-y-8">
        <div className="text-xs font-semibold text-slate-400 tracking-wider uppercase">
          PROBLEM #{currentIndex + 1}
        </div>

        {/* 나눗셈 문제 표시 */}
        <div className="text-4xl sm:text-6xl font-extrabold text-slate-900 tracking-tight font-mono select-none">
          {currentProblem.question} <span className="text-slate-400 font-light">=</span>
        </div>

        {/* 답 입력창 */}
        <div className="max-w-xs mx-auto">
          <input
            ref={inputRef}
            type="number"
            value={currentInput}
            onChange={(e) => setCurrentInput(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder="답을 입력하세요"
            className="w-full text-center text-3xl font-bold font-mono py-3 border-2 border-slate-300 rounded-xl focus:outline-none focus:border-slate-900 focus:ring-4 focus:ring-slate-100 transition"
            autoFocus
          />
          <div className="text-[11px] text-slate-400 mt-2">
            입력 후 <kbd className="px-1.5 py-0.5 bg-slate-100 border border-slate-300 rounded text-slate-700 font-mono">Enter</kbd>를 누르면 다음 문제로 넘어갑니다
          </div>
        </div>

        {/* 힌트 토글 (명세 32) */}
        <div className="pt-2">
          {!showHint ? (
            <button
              onClick={() => setShowHint(true)}
              className="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-800 font-medium underline underline-offset-4"
            >
              <HelpCircle className="w-3.5 h-3.5" />
              계산 전략 힌트 보기 (기록됨)
            </button>
          ) : (
            <div className="inline-block max-w-md p-3.5 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-900 text-left leading-relaxed">
              <span className="font-bold block mb-1">💡 계산 힌트</span>
              {currentProblem.hint || "구구단과 자리값을 이용해보세요."}
            </div>
          )}
        </div>

        {/* 이전 / 다음 이동 버튼 */}
        <div className="flex items-center justify-between pt-6 border-t border-slate-100">
          <button
            onClick={handlePrev}
            disabled={currentIndex === 0}
            className="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 disabled:opacity-30 disabled:cursor-not-allowed"
          >
            <ChevronLeft className="w-4 h-4" />
            이전 문제
          </button>

          {currentIndex < problems.length - 1 ? (
            <button
              onClick={handleNext}
              className="inline-flex items-center gap-1 px-5 py-2 text-sm font-semibold rounded-lg bg-slate-900 text-white hover:bg-slate-800"
            >
              다음 문제
              <ChevronRight className="w-4 h-4" />
            </button>
          ) : (
            <button
              onClick={handleSubmit}
              disabled={isSubmitting}
              className="inline-flex items-center gap-1 px-5 py-2 text-sm font-semibold rounded-lg bg-slate-900 text-white hover:bg-slate-800"
            >
              {isSubmitting ? "제출 중..." : "진단 완료 및 제출"}
            </button>
          )}
        </div>
      </div>

      {/* 1~20 번호 네비게이션 그리드 */}
      <div className="bg-white border border-slate-200 rounded-xl p-5">
        <div className="flex items-center justify-between mb-3 text-xs text-slate-500">
          <span className="font-semibold text-slate-700">문제 목록 바로가기</span>
          <span>
            입력 완료: {answeredCount} / {problems.length}
          </span>
        </div>

        <div className="grid grid-cols-5 sm:grid-cols-10 gap-2">
          {problems.map((p, idx) => {
            const isCurrent = idx === currentIndex;
            const hasAnswer =
              answers[idx]?.userAnswer !== null &&
              answers[idx]?.userAnswer !== undefined &&
              String(answers[idx]?.userAnswer) !== "";

            return (
              <button
                key={p.id}
                onClick={() => moveToProblem(idx)}
                className={`py-2 rounded-lg text-xs font-bold font-mono transition border ${
                  isCurrent
                    ? "bg-slate-900 text-white border-slate-900"
                    : hasAnswer
                    ? "bg-slate-100 text-slate-800 border-slate-300 font-semibold"
                    : "bg-white text-slate-400 border-slate-200 hover:bg-slate-50"
                }`}
              >
                {idx + 1}
              </button>
            );
          })}
        </div>
      </div>
    </div>
  );
}

export default function DiagnosisRunPage() {
  return (
    <Suspense
      fallback={
        <div className="py-20 text-center text-slate-500">
          진단 환경을 준비하고 있습니다...
        </div>
      }
    >
      <DiagnosisRunContent />
    </Suspense>
  );
}
