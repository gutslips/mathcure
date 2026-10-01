"use client";

import { useState, useEffect, useRef, use } from "react";
import { 
  Clock, 
  User, 
  Award, 
  AlertCircle, 
  CheckCircle2, 
  XCircle, 
  ArrowLeft, 
  ArrowRight,
  Send,
  HelpCircle,
  ShieldAlert,
  Keyboard,
  ChevronUp,
  ChevronDown
} from "lucide-react";

interface ExamProblem {
  id: string;
  question: string;
  type: string;
  difficulty: number;
}

interface ExamData {
  id: string;
  code: string;
  title: string;
  studentName: string | null;
  timeLimitSec: number;
  status: string;
  showResult: boolean;
  totalCount: number;
  problems: ExamProblem[];
}

interface SubmittedResult {
  score: number;
  totalCount: number;
  correctCount: number;
  evaluatedAnswers: {
    id: string;
    index: number;
    question: string;
    correctAnswer: number;
    userAnswer: number | null;
    isCorrect: boolean;
    elapsedMs: number;
  }[];
  showResult: boolean;
}

export default function ExamSessionPage({ params }: { params: Promise<{ code: string }> }) {
  const resolvedParams = use(params);
  const examCode = resolvedParams.code;

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [exam, setExam] = useState<ExamData | null>(null);

  // 진행 상태: "intro" | "testing" | "submitted"
  const [stage, setStage] = useState<"intro" | "testing" | "submitted">("intro");
  const [studentName, setStudentName] = useState("");
  
  // 시험 풀이 상태
  const [currentIndex, setCurrentIndex] = useState(0);
  const [answers, setAnswers] = useState<Record<number, string>>({});
  const [remainingSec, setRemainingSec] = useState<number | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [result, setResult] = useState<SubmittedResult | null>(null);
  const [confirmSubmitModal, setConfirmSubmitModal] = useState(false);
  const [showKeypad, setShowKeypad] = useState(true);

  const timerRef = useRef<NodeJS.Timeout | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  // 시험 정보 로드
  useEffect(() => {
    fetch(`/api/exam?code=${examCode}`)
      .then((res) => res.json())
      .then((data) => {
        if (!data.success) {
          setError(data.error || "시험을 찾을 수 없습니다.");
        } else {
          setExam(data.exam);
          if (data.exam.studentName) {
            setStudentName(data.exam.studentName);
          }
          if (data.exam.status === "completed") {
            setError("이미 완료된 시험입니다. 재응시가 불가능합니다.");
          }
        }
      })
      .catch(() => setError("서버 통신 오류가 발생했습니다."))
      .finally(() => setLoading(false));
  }, [examCode]);

  // 타이머 작동
  useEffect(() => {
    if (stage !== "testing" || remainingSec === null) return;

    if (remainingSec <= 0) {
      // 시간 초과 시 자동 제출
      handleFinalSubmit(true);
      return;
    }

    timerRef.current = setTimeout(() => {
      setRemainingSec((prev) => (prev !== null ? prev - 1 : null));
    }, 1000);

    return () => {
      if (timerRef.current) clearTimeout(timerRef.current);
    };
  }, [stage, remainingSec]);

  // 시험 시작
  const handleStartExam = () => {
    if (!studentName.trim()) {
      alert("응시자 이름을 입력해 주세요.");
      return;
    }
    setStage("testing");
    if (exam?.timeLimitSec && exam.timeLimitSec > 0) {
      setRemainingSec(exam.timeLimitSec);
    } else {
      setRemainingSec(null);
    }
  };

  // 키패드 입력 핸들러
  const handleDigit = (digit: string) => {
    setAnswers((prev) => {
      const current = prev[currentIndex] || "";
      if (current.length >= 6) return prev;
      return { ...prev, [currentIndex]: current + digit };
    });
  };

  const handleBackspace = () => {
    setAnswers((prev) => {
      const current = prev[currentIndex] || "";
      if (!current) return prev;
      return { ...prev, [currentIndex]: current.slice(0, -1) };
    });
  };

  const handleClear = () => {
    setAnswers((prev) => ({ ...prev, [currentIndex]: "" }));
  };

  const handleNext = () => {
    if (!exam) return;
    if (currentIndex < exam.problems.length - 1) {
      setCurrentIndex((prev) => prev + 1);
    }
  };

  const handlePrev = () => {
    if (currentIndex > 0) {
      setCurrentIndex((prev) => prev - 1);
    }
  };

  // 최종 제출 처리
  const handleFinalSubmit = async (isAuto = false) => {
    if (submitting || !exam) return;
    setSubmitting(true);
    setConfirmSubmitModal(false);

    try {
      const formattedAnswers = exam.problems.map((prob, idx) => ({
        problemId: prob.id,
        index: idx,
        userAnswer: answers[idx] !== undefined && answers[idx] !== "" ? Number(answers[idx]) : null,
      }));

      const res = await fetch("/api/exam/submit", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          code: examCode,
          studentName: studentName.trim(),
          answers: formattedAnswers,
        }),
      });

      const data = await res.json();
      if (!data.success) {
        alert(data.error || "제출 중 오류가 발생했습니다.");
        setSubmitting(false);
        return;
      }

      setResult(data);
      setStage("submitted");
    } catch (e) {
      console.error(e);
      alert("제출 실패: 네트워크 상태를 확인해 주세요.");
      setSubmitting(false);
    }
  };

  // PC 물리 키보드 완벽 연동
  useEffect(() => {
    if (stage !== "testing") return;

    const handleKeyDown = (e: KeyboardEvent) => {
      // 제출 확인 모달이 열려있는 경우
      if (confirmSubmitModal) {
        if (e.key === "Escape") {
          e.preventDefault();
          setConfirmSubmitModal(false);
        } else if (e.key === "Enter") {
          e.preventDefault();
          handleFinalSubmit();
        }
        return;
      }

      // 다른 input/textarea에 포커스된 경우 무시
      if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) {
        if (e.target !== inputRef.current) return;
      }

      if (e.key >= "0" && e.key <= "9") {
        e.preventDefault();
        handleDigit(e.key);
      } else if (e.key === "Backspace") {
        e.preventDefault();
        handleBackspace();
      } else if (e.key === "Delete") {
        e.preventDefault();
        handleClear();
      } else if (e.key === "Enter") {
        e.preventDefault();
        if (exam && currentIndex < exam.problems.length - 1) {
          handleNext();
        } else {
          setConfirmSubmitModal(true);
        }
      } else if (e.key === "ArrowLeft") {
        e.preventDefault();
        handlePrev();
      } else if (e.key === "ArrowRight") {
        e.preventDefault();
        handleNext();
      }
    };

    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [stage, confirmSubmitModal, currentIndex, exam]);

  // 남은 시간 포맷팅 (MM:SS)
  const formatTime = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins.toString().padStart(2, "0")}:${secs.toString().padStart(2, "0")}`;
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-slate-900 flex items-center justify-center p-4">
        <div className="text-center text-slate-300 space-y-2">
          <div className="w-10 h-10 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
          <p className="text-sm font-semibold">시험지를 불러오는 중...</p>
        </div>
      </div>
    );
  }

  if (error || !exam) {
    return (
      <div className="min-h-screen bg-slate-900 flex items-center justify-center p-4">
        <div className="max-w-md w-full bg-slate-800 border border-slate-700 rounded-3xl p-8 text-center space-y-4">
          <AlertCircle className="w-12 h-12 text-rose-400 mx-auto" />
          <h2 className="text-xl font-bold text-white">시험 접속 안내</h2>
          <p className="text-xs text-slate-400 leading-relaxed">{error}</p>
          <a
            href="/exam"
            className="inline-block mt-4 px-6 py-2.5 bg-slate-700 hover:bg-slate-600 text-white rounded-xl text-xs font-semibold transition"
          >
            시험 코드 다시 입력하기
          </a>
        </div>
      </div>
    );
  }

  // ========================================================
  // 1. 대기실 (INTRO STAGE)
  // ========================================================
  if (stage === "intro") {
    return (
      <div className="min-h-screen bg-slate-950 text-white flex items-center justify-center p-4">
        <div className="max-w-lg w-full bg-slate-900 border border-slate-800 rounded-3xl p-8 shadow-2xl space-y-6">
          <div className="text-center">
            <span className="inline-block px-3 py-1 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-mono text-xs font-bold rounded-full mb-3">
              시험 코드: {exam.code}
            </span>
            <h1 className="text-2xl sm:text-3xl font-black text-white">
              {exam.title}
            </h1>
            <p className="text-xs text-slate-400 mt-2">
              초등학교 5학년 나눗셈 집중 연산 테스트
            </p>
          </div>

          <div className="grid grid-cols-2 gap-3 p-4 bg-slate-800/80 rounded-2xl border border-slate-700/60 text-center">
            <div>
              <div className="text-[11px] text-slate-400 mb-0.5">총 문항 수</div>
              <div className="text-xl font-mono font-bold text-white">
                {exam.problems.length}문항
              </div>
            </div>
            <div>
              <div className="text-[11px] text-slate-400 mb-0.5">제한 시간</div>
              <div className="text-xl font-mono font-bold text-emerald-400">
                {exam.timeLimitSec > 0 ? `${exam.timeLimitSec / 60}분` : "무제한"}
              </div>
            </div>
          </div>

          <div className="space-y-3">
            <label className="block text-xs font-semibold text-slate-300">
              응시 학생 이름
            </label>
            <div className="relative">
              <User className="w-5 h-5 text-slate-400 absolute left-3.5 top-3.5" />
              <input
                type="text"
                value={studentName}
                onChange={(e) => setStudentName(e.target.value)}
                placeholder="이름을 입력하세요 (예: 김민수)"
                className="w-full pl-11 pr-4 py-3 bg-slate-800 border-2 border-slate-700 rounded-2xl text-white font-medium focus:outline-none focus:border-emerald-500 text-sm"
              />
            </div>
          </div>

          <div className="p-4 bg-amber-500/10 border border-amber-500/20 rounded-2xl text-xs text-amber-300/90 space-y-1.5 leading-relaxed">
            <div className="font-bold flex items-center gap-1.5 text-amber-300">
              <ShieldAlert className="w-4 h-4 shrink-0" />
              응시 전 주의사항
            </div>
            <div>• [시험 시작하기]를 누르면 타이머가 즉시 시작됩니다.</div>
            <div>• 제한 시간이 끝나면 지금까지 작성한 답안이 자동 제출됩니다.</div>
            <div>• 문제를 다 푼 후 [시험 제출하기]를 눌러 완료해 주세요.</div>
          </div>

          <button
            onClick={handleStartExam}
            className="w-full py-4 bg-emerald-500 hover:bg-emerald-400 active:scale-98 text-slate-950 font-black text-base rounded-2xl shadow-lg shadow-emerald-500/20 transition flex items-center justify-center gap-2"
          >
            시험 시작하기
            <ArrowRight className="w-5 h-5" />
          </button>
        </div>
      </div>
    );
  }

  // ========================================================
  // 2. 시험 진행 화면 (TESTING STAGE)
  // ========================================================
  if (stage === "testing") {
    const currentProb = exam.problems[currentIndex];
    const answeredCount = Object.keys(answers).filter((k) => answers[Number(k)]?.trim() !== "").length;
    const isLastProblem = currentIndex === exam.problems.length - 1;
    const isUrgent = remainingSec !== null && remainingSec <= 60;

    return (
      <div className="min-h-screen bg-slate-950 text-white flex flex-col pb-12 select-none">
        {/* 상단 고정 헤더: 남은 시간, 학생 이름, 진행률 */}
        <header className="sticky top-0 z-40 bg-slate-900/95 backdrop-blur border-b border-slate-800 px-4 py-3">
          <div className="max-w-2xl mx-auto flex items-center justify-between">
            <div className="flex items-center gap-2">
              <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
              <span className="font-bold text-sm text-slate-200">
                {studentName} 학생
              </span>
            </div>

            {/* 타이머 */}
            {remainingSec !== null ? (
              <div
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl font-mono font-black text-sm tracking-wider transition ${
                  isUrgent
                    ? "bg-rose-500/20 text-rose-400 border border-rose-500/40 animate-bounce"
                    : "bg-slate-800 text-emerald-400 border border-slate-700"
                }`}
              >
                <Clock className="w-4 h-4" />
                <span>{formatTime(remainingSec)}</span>
              </div>
            ) : (
              <div className="text-xs text-slate-400 font-medium">자율 풀이</div>
            )}

            {/* 제출 버튼 */}
            <button
              onClick={() => setConfirmSubmitModal(true)}
              className="px-3.5 py-1.5 bg-emerald-500 hover:bg-emerald-400 active:scale-95 text-slate-950 font-bold text-xs rounded-xl transition shadow-sm"
            >
              제출하기
            </button>
          </div>

          {/* 진행도 게이지 */}
          <div className="max-w-2xl mx-auto mt-2.5">
            <div className="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
              <div
                className="bg-emerald-500 h-full transition-all duration-300"
                style={{ width: `${((currentIndex + 1) / exam.problems.length) * 100}%` }}
              ></div>
            </div>
          </div>
        </header>

        {/* 중앙 문제 풀이 영역 */}
        <main className="flex-1 max-w-lg w-full mx-auto px-4 py-6 flex flex-col justify-between">
          <div className="space-y-6">
            {/* 문항 번호 & 상태 */}
            <div className="flex items-center justify-between text-xs text-slate-400">
              <span className="font-bold font-mono text-emerald-400 text-sm">
                Q{currentIndex + 1} / {exam.problems.length}
              </span>
              <span>
                응답 완료: <strong className="text-white">{answeredCount}</strong> / {exam.problems.length}
              </span>
            </div>

            {/* 문제 카드 */}
            <div className="bg-slate-900 border-2 border-slate-800 rounded-3xl p-8 shadow-xl text-center space-y-5">
              <div className="text-3xl sm:text-5xl font-mono font-black text-white tracking-tight">
                {currentProb.question.replace(/\s*=\s*$/, "")}{" "}
                <span className="text-slate-500 font-light">=</span>
              </div>

              {/* 답안 입력 표시창 */}
              <div className="max-w-xs mx-auto">
                <div
                  className="w-full h-16 rounded-2xl bg-slate-950 border-2 border-emerald-500/40 shadow-inner flex items-center justify-center text-3xl sm:text-4xl font-mono font-black text-emerald-400 tracking-wider cursor-pointer"
                  onClick={() => inputRef.current?.focus()}
                >
                  {answers[currentIndex] ? (
                    <span className="flex items-center">
                      {answers[currentIndex]}
                      <span className="inline-block w-0.5 h-7 bg-emerald-400 ml-1 animate-pulse" />
                    </span>
                  ) : (
                    <span className="flex items-center text-slate-600 text-xl font-normal">
                      답 입력
                      <span className="inline-block w-0.5 h-6 bg-slate-600 ml-1 animate-pulse" />
                    </span>
                  )}
                </div>
                {/* 물리 키보드 대응용 hidden input */}
                <input
                  ref={inputRef}
                  type="text"
                  inputMode="numeric"
                  value={answers[currentIndex] || ""}
                  onChange={(e) => {
                    const val = e.target.value.replace(/[^0-9]/g, "");
                    setAnswers((prev) => ({ ...prev, [currentIndex]: val }));
                  }}
                  className="sr-only"
                />
              </div>

              {/* PC 키보드 & 터치 입력 안내 */}
              <div className="flex items-center justify-center">
                <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-800/80 rounded-full border border-slate-700/80 text-[11px] text-slate-300">
                  <Keyboard className="w-3.5 h-3.5 text-emerald-400" />
                  <strong className="text-emerald-400 font-semibold">PC 키보드 지원:</strong> 숫자(0~9) · Enter(다음) · Backspace(지우기)
                </span>
              </div>
            </div>

            {/* 가상 키패드 토글 바 (PC / 태블릿 환경 배려) */}
            <div className="flex items-center justify-between max-w-xs mx-auto text-xs px-1">
              <span className="text-slate-400 font-medium">화면 터치 키패드</span>
              <button
                type="button"
                onClick={() => setShowKeypad(!showKeypad)}
                className="text-emerald-400 hover:text-emerald-300 font-semibold flex items-center gap-1 transition"
              >
                {showKeypad ? (
                  <>
                    키패드 접기 <ChevronUp className="w-3.5 h-3.5" />
                  </>
                ) : (
                  <>
                    키패드 펼치기 <ChevronDown className="w-3.5 h-3.5" />
                  </>
                )}
              </button>
            </div>

            {/* 터치 전용 가상 키패드 */}
            {showKeypad && (
              <div
                className="bg-slate-900/80 border border-slate-800 rounded-3xl p-3 max-w-xs mx-auto grid grid-cols-3 gap-2 transition-all duration-200"
                style={{ touchAction: "manipulation" }}
              >
                {[1, 2, 3, 4, 5, 6, 7, 8, 9].map((num) => (
                  <button
                    key={num}
                    type="button"
                    onClick={() => handleDigit(String(num))}
                    className="py-3.5 bg-slate-800 hover:bg-slate-700 active:bg-slate-600 text-white font-mono font-bold text-xl rounded-2xl transition active:scale-95 shadow-sm"
                  >
                    {num}
                  </button>
                ))}
                <button
                  type="button"
                  onClick={handleBackspace}
                  className="py-3.5 bg-slate-800/60 hover:bg-slate-800 active:bg-slate-700 text-slate-400 font-semibold text-xs rounded-2xl transition active:scale-95 flex items-center justify-center gap-1"
                >
                  ⌫ 지우기
                </button>
                <button
                  type="button"
                  onClick={() => handleDigit("0")}
                  className="py-3.5 bg-slate-800 hover:bg-slate-700 active:bg-slate-600 text-white font-mono font-bold text-xl rounded-2xl transition active:scale-95 shadow-sm"
                >
                  0
                </button>
                <button
                  type="button"
                  onClick={() => {
                    if (!isLastProblem) handleNext();
                    else setConfirmSubmitModal(true);
                  }}
                  className="py-3.5 bg-emerald-500 hover:bg-emerald-400 active:bg-emerald-600 text-slate-950 font-black text-sm rounded-2xl transition active:scale-95 shadow-sm flex items-center justify-center"
                >
                  {isLastProblem ? "제출 ↵" : "다음 ➔"}
                </button>
              </div>
            )}

            {/* 문항 이전/다음 이동 바 */}
            <div className="flex items-center justify-between max-w-xs mx-auto pt-1">
              <button
                type="button"
                onClick={handlePrev}
                disabled={currentIndex === 0}
                className="px-4 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs font-semibold text-slate-300 disabled:opacity-30 disabled:pointer-events-none hover:bg-slate-800 transition flex items-center gap-1"
              >
                <ArrowLeft className="w-3.5 h-3.5" /> 이전 문제
              </button>
              <button
                type="button"
                onClick={handleNext}
                disabled={isLastProblem}
                className="px-4 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs font-semibold text-slate-300 disabled:opacity-30 disabled:pointer-events-none hover:bg-slate-800 transition flex items-center gap-1"
              >
                다음 문제 <ArrowRight className="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          {/* 하단 문항 네비게이터 (점 형태) */}
          <div className="mt-8 pt-4 border-t border-slate-800/80">
            <div className="text-[11px] text-slate-500 font-medium mb-2 text-center">
              문항 이동 (초록색: 답안 작성 완료)
            </div>
            <div className="flex flex-wrap justify-center gap-1.5 max-w-sm mx-auto">
              {exam.problems.map((_, idx) => {
                const hasAnswer = answers[idx] !== undefined && answers[idx].trim() !== "";
                const isCurrent = idx === currentIndex;
                return (
                  <button
                    key={idx}
                    type="button"
                    onClick={() => setCurrentIndex(idx)}
                    className={`w-7 h-7 rounded-lg font-mono text-xs font-bold transition ${
                      isCurrent
                        ? "bg-white text-slate-950 ring-2 ring-emerald-400"
                        : hasAnswer
                        ? "bg-emerald-500/20 text-emerald-400 border border-emerald-500/40"
                        : "bg-slate-800 text-slate-400 hover:bg-slate-700"
                    }`}
                  >
                    {idx + 1}
                  </button>
                );
              })}
            </div>
          </div>
        </main>

        {/* 미응답 확인 모달 */}
        {confirmSubmitModal && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-xs">
            <div className="bg-slate-900 border border-slate-800 rounded-3xl max-w-sm w-full p-6 text-center space-y-4 shadow-2xl">
              <div className="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mx-auto">
                <Send className="w-6 h-6" />
              </div>
              <h3 className="text-lg font-bold text-white">시험지를 제출하시겠습니까?</h3>
              
              <div className="p-3 bg-slate-800/80 rounded-xl text-xs text-slate-300 space-y-1">
                <div>총 문항: <strong>{exam.problems.length}문제</strong></div>
                <div>풀이 완료: <strong className="text-emerald-400">{answeredCount}문제</strong></div>
                {answeredCount < exam.problems.length && (
                  <div className="text-rose-400 font-semibold pt-1">
                    ⚠️ 아직 안 푼 문제가 {exam.problems.length - answeredCount}개 있습니다!
                  </div>
                )}
              </div>

              <div className="flex gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setConfirmSubmitModal(false)}
                  className="flex-1 py-3 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs rounded-xl transition"
                >
                  더 풀기
                </button>
                <button
                  type="button"
                  disabled={submitting}
                  onClick={() => handleFinalSubmit(false)}
                  className="flex-1 py-3 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs rounded-xl transition shadow-md"
                >
                  {submitting ? "제출 중..." : "제출하기"}
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    );
  }

  // ========================================================
  // 3. 제출 완료 화면 (SUBMITTED STAGE)
  // ========================================================
  return (
    <div className="min-h-screen bg-slate-950 text-white flex items-center justify-center p-4 py-12">
      <div className="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl p-8 shadow-2xl text-center space-y-6">
        <div className="w-16 h-16 rounded-3xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center mx-auto">
          <Award className="w-9 h-9" />
        </div>

        <div>
          <span className="px-3 py-1 bg-emerald-500/20 text-emerald-400 text-xs font-bold rounded-full">
            시험 완료
          </span>
          <h2 className="text-2xl font-black text-white mt-2">
            수고하셨습니다, {studentName} 학생!
          </h2>
          <p className="text-xs text-slate-400 mt-1">
            시험 결과가 선생님 대시보드로 안전하게 전송되었습니다.
          </p>
        </div>

        {/* 결과 공개 옵션이 켜져 있을 때 */}
        {result?.showResult && (
          <div className="space-y-4 pt-2">
            <div className="p-6 bg-slate-800/90 rounded-2xl border border-slate-700/60 text-center">
              <div className="text-xs text-slate-400 mb-1">내 시험 점수</div>
              <div className="text-5xl font-mono font-black text-emerald-400">
                {result.score}
                <span className="text-xl text-slate-400 font-normal">점</span>
              </div>
              <div className="text-xs text-slate-400 mt-2">
                총 {result.totalCount}문항 중 <strong className="text-white">{result.correctCount}문항</strong> 정답
              </div>
            </div>

            {/* 간이 정오표 */}
            <div className="text-left space-y-2 max-h-60 overflow-y-auto pr-1">
              <div className="text-xs font-semibold text-slate-400 px-1">
                문항별 풀이 결과
              </div>
              {result.evaluatedAnswers.map((item) => (
                <div
                  key={item.id}
                  className={`p-3 rounded-xl border flex items-center justify-between text-xs ${
                    item.isCorrect
                      ? "bg-emerald-500/10 border-emerald-500/30 text-emerald-300"
                      : "bg-rose-500/10 border-rose-500/30 text-rose-300"
                  }`}
                >
                  <div className="font-mono">
                    <span className="font-bold mr-2">{item.index}번.</span>
                    <span>{item.question} = </span>
                    <strong className="text-white ml-1">
                      {item.userAnswer !== null ? item.userAnswer : "미입력"}
                    </strong>
                  </div>
                  <div className="flex items-center gap-1 font-semibold">
                    {item.isCorrect ? (
                      <span className="flex items-center gap-1 text-emerald-400">
                        <CheckCircle2 className="w-4 h-4" /> 정답
                      </span>
                    ) : (
                      <span className="flex items-center gap-1 text-rose-400">
                        <XCircle className="w-4 h-4" /> 정답: {item.correctAnswer}
                      </span>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        <div className="pt-4 border-t border-slate-800 text-[11px] text-slate-500">
          시험창을 닫으셔도 좋습니다. 고생하셨습니다!
        </div>
      </div>
    </div>
  );
}
