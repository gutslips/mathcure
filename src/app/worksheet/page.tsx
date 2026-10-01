"use client";

import { useState, useMemo, useEffect, Suspense } from "react";
import { useSearchParams } from "next/navigation";
import Link from "next/link";
import { Problem, ProblemType, PROBLEM_TYPE_LABELS } from "@/types/problem";
import { generateWorksheet } from "@/lib/worksheet/generator";
import { 
  Printer, 
  RefreshCw, 
  CheckSquare, 
  BookmarkPlus, 
  FolderArchive, 
  Share2, 
  PlayCircle, 
  Trash2, 
  Check, 
  Clock, 
  Calendar,
  ExternalLink,
  ChevronRight,
  Sparkles
} from "lucide-react";
import { ExamModal } from "@/components/exam/ExamModal";

interface SavedWorksheet {
  id: string;
  title: string;
  studentId: string | null;
  subject: string;
  grade: number;
  difficulty: number;
  problemType: string;
  count: number;
  seed: string;
  problems?: Problem[];
  hasProblems: boolean;
  createdAt: string;
}

function WorksheetContent() {
  const searchParams = useSearchParams();
  const initialType = (searchParams.get("type") as ProblemType) || "divide5";
  const initialDifficulty = Number(searchParams.get("difficulty")) || 2;

  const [studentName, setStudentName] = useState("홍길동");
  const [targetType, setTargetType] = useState<ProblemType>(initialType);
  const [problemCount, setProblemCount] = useState(20);
  const [difficulty, setDifficulty] = useState(initialDifficulty);
  const [includeTimerRecord, setIncludeTimerRecord] = useState(true);
  const [activeTab, setActiveTab] = useState<"worksheet" | "answers" | "archive">("worksheet");
  const [refreshKey, setRefreshKey] = useState(0);

  // 보관함 관련 상태
  const [savedList, setSavedList] = useState<SavedWorksheet[]>([]);
  const [selectedSaved, setSelectedSaved] = useState<SavedWorksheet | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  const [saveSuccessMsg, setSaveSuccessMsg] = useState("");

  // 온라인 시험 모달 관련 상태
  const [examModalOpen, setExamModalOpen] = useState(false);
  const [examModalProps, setExamModalProps] = useState<{
    worksheetId?: string;
    title: string;
    studentName: string;
    problems: Problem[];
  }>({
    title: "",
    studentName: "",
    problems: [],
  });

  // 현재 활성 학생 이름 로드
  useEffect(() => {
    fetch("/api/student")
      .then((res) => res.json())
      .then((data) => {
        if (data.activeStudent?.name) {
          setStudentName(data.activeStudent.name);
        }
      })
      .catch(() => {});
  }, []);

  // 보관함 목록 로드
  const fetchSavedWorksheets = () => {
    fetch("/api/worksheet")
      .then((res) => res.json())
      .then((data) => {
        if (data.success && data.worksheets) {
          setSavedList(data.worksheets);
        }
      })
      .catch(() => {});
  };

  useEffect(() => {
    fetchSavedWorksheets();
  }, []);

  // 생성기 문제 세트
  const generatedWorksheet = useMemo(() => {
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

  // 보관된 문제집을 선택한 경우 해당 문제를 사용, 아니면 새로 생성된 문제 사용
  const currentWorksheet = useMemo(() => {
    if (selectedSaved && selectedSaved.problems && selectedSaved.problems.length > 0) {
      return {
        title: selectedSaved.title,
        subtitle: `${PROBLEM_TYPE_LABELS[selectedSaved.problemType as ProblemType] || selectedSaved.problemType} · Level ${selectedSaved.difficulty}`,
        studentName: studentName || "학생",
        date: new Date(selectedSaved.createdAt).toLocaleDateString("ko-KR"),
        problems: selectedSaved.problems,
        includeTimerRecord: true,
        seed: selectedSaved.seed,
      };
    }
    return generatedWorksheet;
  }, [selectedSaved, generatedWorksheet, studentName]);

  // 인쇄 처리
  const handlePrint = (tab: "worksheet" | "answers") => {
    setActiveTab(tab);
    setTimeout(() => {
      window.print();
    }, 100);
  };

  // 문제 세트 보관함에 저장하기
  const handleSaveToArchive = async () => {
    const defaultTitle = `${studentName}의 ${(PROBLEM_TYPE_LABELS[targetType] || targetType).replace(" 자동화", "")} ${problemCount}제`;
    const titlePrompt = prompt("보관할 문제집의 이름을 입력해 주세요:", defaultTitle);
    if (!titlePrompt || !titlePrompt.trim()) return;

    setIsSaving(true);
    try {
      const res = await fetch("/api/worksheet", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          title: titlePrompt.trim(),
          studentName,
          difficulty,
          problemType: targetType,
          count: currentWorksheet.problems.length,
          seed: currentWorksheet.seed,
          problems: currentWorksheet.problems,
        }),
      });
      const data = await res.json();
      if (data.success) {
        setSaveSuccessMsg(`'${titlePrompt}' 문제집이 보관함에 저장되었습니다!`);
        fetchSavedWorksheets();
        setTimeout(() => setSaveSuccessMsg(""), 3500);
      } else {
        alert(data.error || "저장에 실패했습니다.");
      }
    } catch (e) {
      console.error(e);
      alert("보관 중 오류가 발생했습니다.");
    } finally {
      setIsSaving(false);
    }
  };

  // 보관된 문제집 선택 및 보기
  const handleSelectSavedWorksheet = (saved: SavedWorksheet, printMode?: "worksheet" | "answers") => {
    fetch(`/api/worksheet?id=${saved.id}`)
      .then((res) => res.json())
      .then((data) => {
        if (data.success && data.worksheet) {
          setSelectedSaved(data.worksheet);
          if (printMode) {
            setActiveTab(printMode);
            setTimeout(() => window.print(), 150);
          } else {
            setActiveTab("worksheet");
          }
        }
      });
  };

  // 보관된 문제집 삭제
  const handleDeleteSavedWorksheet = async (id: string, title: string) => {
    if (!confirm(`'${title}' 문제집을 보관함에서 삭제하시겠습니까?`)) return;

    try {
      const res = await fetch(`/api/worksheet?id=${id}`, { method: "DELETE" });
      const data = await res.json();
      if (data.success) {
        if (selectedSaved?.id === id) {
          setSelectedSaved(null);
        }
        fetchSavedWorksheets();
      } else {
        alert(data.error || "삭제 실패");
      }
    } catch (e) {
      console.error(e);
    }
  };

  // 온라인 시험 링크 발급 모달 열기
  const handleOpenExamModal = (
    problems: Problem[], 
    title: string, 
    worksheetId?: string,
    student?: string
  ) => {
    setExamModalProps({
      problems,
      title: title || `${studentName}의 맞춤 온라인 시험`,
      worksheetId,
      studentName: student || studentName,
    });
    setExamModalOpen(true);
  };

  return (
    <div className="space-y-8">
      {/* 훈련지 상단 액션 및 설정 패널 (화면 전용, 인쇄시 숨김) */}
      <div className="no-print bg-white border border-slate-200 rounded-2xl p-6 shadow-xs">
        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6 pb-4 border-b border-slate-100">
          <div>
            <div className="flex items-center gap-2">
              <span className="w-2.5 h-2.5 rounded-full bg-slate-900"></span>
              <h1 className="text-xl font-bold text-slate-900">
                맞춤형 연산 훈련지 & 보관함
              </h1>
            </div>
            <p className="text-xs text-slate-500 mt-1">
              마음에 드는 문제 세트를 보관하여 언제든 다시 인쇄하거나 학생 전용 시험 링크로 전송하세요.
            </p>
          </div>

          {/* 핵심 액션 버튼 모음 */}
          <div className="flex flex-wrap items-center gap-2">
            <button
              onClick={() => handlePrint("worksheet")}
              className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-900 text-white text-xs font-semibold rounded-xl hover:bg-slate-800 transition shadow-sm active:scale-95"
            >
              <Printer className="w-3.5 h-3.5" />
              문제지 인쇄
            </button>
            <button
              onClick={() => handlePrint("answers")}
              className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-slate-300 text-slate-700 text-xs font-semibold rounded-xl hover:bg-slate-50 transition active:scale-95"
            >
              <CheckSquare className="w-3.5 h-3.5 text-slate-600" />
              정답지 인쇄
            </button>
            <button
              onClick={handleSaveToArchive}
              disabled={isSaving}
              className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-xl transition shadow-sm active:scale-95 disabled:opacity-50"
            >
              <BookmarkPlus className="w-3.5 h-3.5" />
              {isSaving ? "보관 중..." : "이 문제집 보관하기"}
            </button>
            <button
              onClick={() =>
                handleOpenExamModal(
                  currentWorksheet.problems,
                  currentWorksheet.title,
                  selectedSaved?.id,
                  studentName
                )
              }
              className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl transition shadow-sm active:scale-95"
            >
              <Share2 className="w-3.5 h-3.5" />
              온라인 시험 링크 & QR
            </button>
          </div>
        </div>

        {/* 저장 성공 알림 메시지 */}
        {saveSuccessMsg && (
          <div className="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs flex items-center justify-between">
            <span className="font-semibold flex items-center gap-1.5">
              <Check className="w-4 h-4 text-emerald-600" />
              {saveSuccessMsg}
            </span>
            <button
              onClick={() => setActiveTab("archive")}
              className="text-xs text-emerald-950 font-bold underline underline-offset-2 hover:opacity-80"
            >
              보관함에서 확인 ➔
            </button>
          </div>
        )}

        {/* 보관된 문제집을 보고 있을 때의 안내 배너 */}
        {selectedSaved && (
          <div className="mb-5 p-3.5 bg-slate-900 text-white rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <div className="flex items-center gap-2">
              <span className="px-2 py-0.5 bg-emerald-500 text-slate-950 font-bold rounded">보관된 문제집 선택됨</span>
              <strong className="text-sm font-semibold">{selectedSaved.title}</strong>
              <span className="text-slate-400">({selectedSaved.count}문제 · {new Date(selectedSaved.createdAt).toLocaleDateString()})</span>
            </div>
            <div className="flex items-center gap-2">
              <Link
                href={`/practice?worksheetId=${selectedSaved.id}`}
                className="px-2.5 py-1 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold rounded-lg transition"
              >
                이 문제로 온라인 풀기
              </Link>
              <button
                onClick={() => setSelectedSaved(null)}
                className="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition"
              >
                새 생성기로 전환
              </button>
            </div>
          </div>
        )}

        {/* 생성 옵션 필드 그리드 */}
        <div className="grid grid-cols-2 sm:grid-cols-5 gap-4 text-sm">
          <div>
            <label className="block text-xs font-semibold text-slate-600 mb-1">
              학생 이름
            </label>
            <input
              type="text"
              value={studentName}
              onChange={(e) => setStudentName(e.target.value)}
              className="w-full px-3 py-2 border border-slate-300 rounded-xl text-slate-800 font-medium focus:outline-none focus:border-slate-900"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-600 mb-1">
              집중 훈련 유형
            </label>
            <select
              value={targetType}
              onChange={(e) => {
                setTargetType(e.target.value as ProblemType);
                setSelectedSaved(null);
              }}
              className="w-full px-3 py-2 border border-slate-300 rounded-xl text-slate-800 font-medium bg-white focus:outline-none focus:border-slate-900"
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
              onChange={(e) => {
                setDifficulty(Number(e.target.value));
                setSelectedSaved(null);
              }}
              className="w-full px-3 py-2 border border-slate-300 rounded-xl text-slate-800 font-medium bg-white focus:outline-none focus:border-slate-900"
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
              onChange={(e) => {
                setProblemCount(Number(e.target.value));
                setSelectedSaved(null);
              }}
              className="w-full px-3 py-2 border border-slate-300 rounded-xl text-slate-800 font-medium bg-white focus:outline-none focus:border-slate-900"
            >
              <option value={20}>20문제 (A4 1장 최적)</option>
              <option value={10}>10문제 (간이 시험)</option>
              <option value={30}>30문제 (집중 훈련)</option>
            </select>
          </div>

          <div className="flex flex-col justify-end">
            <button
              onClick={() => {
                setSelectedSaved(null);
                setRefreshKey((prev) => prev + 1);
              }}
              className="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold rounded-xl text-xs transition active:scale-95"
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
            Seed: {currentWorksheet.seed}
          </span>
        </div>
      </div>

      {/* 탭 전환 바 (화면 전용) */}
      <div className="no-print flex items-center justify-center gap-2">
        <button
          onClick={() => setActiveTab("worksheet")}
          className={`px-5 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 ${
            activeTab === "worksheet"
              ? "bg-slate-900 text-white shadow-sm"
              : "bg-white border border-slate-200 text-slate-600 hover:bg-slate-50"
          }`}
        >
          📄 문제지 미리보기
        </button>
        <button
          onClick={() => setActiveTab("answers")}
          className={`px-5 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 ${
            activeTab === "answers"
              ? "bg-slate-900 text-white shadow-sm"
              : "bg-white border border-slate-200 text-slate-600 hover:bg-slate-50"
          }`}
        >
          ✅ 정답지 미리보기
        </button>
        <button
          onClick={() => setActiveTab("archive")}
          className={`px-5 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 ${
            activeTab === "archive"
              ? "bg-emerald-600 text-white shadow-sm"
              : "bg-white border border-emerald-300 text-emerald-800 hover:bg-emerald-50"
          }`}
        >
          <FolderArchive className="w-4 h-4" />
          저장된 보관함 ({savedList.length})
        </button>
      </div>

      {/* ======================================================== */}
      {/* 탭별 뷰 렌더링 */}
      {/* ======================================================== */}

      {activeTab === "archive" ? (
        /* [0] 저장된 문제집 보관함 뷰 */
        <div className="no-print bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4">
          <div className="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
              <h2 className="text-lg font-bold text-slate-900">
                보관된 맞춤 문제집 목록
              </h2>
              <p className="text-xs text-slate-500">
                저장해둔 문제집을 다시 인쇄하거나, 그 문제 그대로 학생 온라인 시험 및 풀이를 진행합니다.
              </p>
            </div>
            <span className="text-xs text-slate-500 font-medium">
              총 <strong>{savedList.length}</strong>개 문제집 보관 중
            </span>
          </div>

          {savedList.length === 0 ? (
            <div className="py-16 text-center text-slate-400 space-y-3">
              <FolderArchive className="w-12 h-12 mx-auto stroke-1 text-slate-300" />
              <p className="text-sm">아직 보관된 문제집이 없습니다.</p>
              <p className="text-xs text-slate-400">
                문제 생성 화면에서 마음에 드는 문제가 나오면 <strong>[이 문제집 보관하기]</strong>를 눌러주세요.
              </p>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {savedList.map((ws) => (
                <div
                  key={ws.id}
                  className={`p-5 rounded-2xl border transition flex flex-col justify-between ${
                    selectedSaved?.id === ws.id
                      ? "border-emerald-500 bg-emerald-50/30 ring-1 ring-emerald-500"
                      : "border-slate-200 bg-white hover:border-slate-300 hover:shadow-xs"
                  }`}
                >
                  <div className="space-y-2">
                    <div className="flex items-start justify-between gap-2">
                      <h3 className="font-bold text-base text-slate-900 line-clamp-1">
                        {ws.title}
                      </h3>
                      <button
                        onClick={() => handleDeleteSavedWorksheet(ws.id, ws.title)}
                        className="text-slate-400 hover:text-rose-500 p-1 rounded transition"
                        title="문제집 삭제"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 text-xs">
                      <span className="px-2 py-0.5 bg-slate-100 font-semibold text-slate-700 rounded-md">
                        {PROBLEM_TYPE_LABELS[ws.problemType as ProblemType] || ws.problemType}
                      </span>
                      <span className="px-2 py-0.5 bg-slate-100 font-mono text-slate-600 rounded-md">
                        Level {ws.difficulty}
                      </span>
                      <span className="px-2 py-0.5 bg-slate-100 font-mono text-slate-600 rounded-md">
                        {ws.count}문항
                      </span>
                      <span className="text-slate-400 text-[11px] flex items-center gap-1 ml-auto">
                        <Calendar className="w-3 h-3" />
                        {new Date(ws.createdAt).toLocaleDateString()}
                      </span>
                    </div>
                  </div>

                  {/* 카드 액션 버튼 그룹 */}
                  <div className="mt-5 pt-3 border-t border-slate-100 flex flex-wrap gap-1.5">
                    <button
                      onClick={() => handleSelectSavedWorksheet(ws, "worksheet")}
                      className="px-2.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold flex items-center gap-1 transition"
                    >
                      <Printer className="w-3 h-3" />
                      문제지 인쇄
                    </button>
                    <button
                      onClick={() => handleSelectSavedWorksheet(ws, "answers")}
                      className="px-2.5 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-xs font-semibold flex items-center gap-1 transition"
                    >
                      <CheckSquare className="w-3 h-3 text-slate-500" />
                      정답지 인쇄
                    </button>
                    <Link
                      href={`/practice?worksheetId=${ws.id}`}
                      className="px-2.5 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-lg text-xs font-semibold flex items-center gap-1 transition"
                    >
                      <PlayCircle className="w-3 h-3 text-emerald-600" />
                      온라인 풀기
                    </Link>
                    <button
                      onClick={() => {
                        // 문제 로드 후 모달 열기
                        fetch(`/api/worksheet?id=${ws.id}`)
                          .then((r) => r.json())
                          .then((d) => {
                            if (d.success && d.worksheet?.problems) {
                              handleOpenExamModal(d.worksheet.problems, ws.title, ws.id);
                            }
                          });
                      }}
                      className="px-2.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-800 border border-indigo-200 rounded-lg text-xs font-semibold flex items-center gap-1 transition"
                    >
                      <Share2 className="w-3 h-3 text-indigo-600" />
                      시험 링크/QR
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      ) : activeTab === "worksheet" ? (
        /* [1] 문제지 인쇄 영역 */
        <div className="a4-preview-box worksheet-sheet text-slate-900">
          <div className="border-b-2 border-slate-900 pb-3 mb-6 flex justify-between items-end">
            <div>
              <span className="text-[12px] font-bold text-slate-500 uppercase tracking-widest block">
                MATH CURE · 초5 연산 트레이닝
              </span>
              <h2 className="text-2xl font-black text-slate-900 mt-0.5">
                {currentWorksheet.title}
              </h2>
              <span className="text-xs text-slate-600 font-medium">
                {currentWorksheet.subtitle}
              </span>
            </div>

            <div className="text-right text-xs space-y-1">
              <div>
                <span className="text-slate-500">이름:</span>{" "}
                <span className="font-bold underline underline-offset-4 inline-block min-w-[70px] text-center">
                  {currentWorksheet.studentName}
                </span>
              </div>
              <div>
                <span className="text-slate-500">날짜:</span>{" "}
                <span className="font-bold underline underline-offset-4 inline-block min-w-[70px] text-center">
                  {currentWorksheet.date}
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
            {currentWorksheet.problems.map((prob, idx) => (
              <div
                key={prob.id || idx}
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
                  {currentWorksheet.includeTimerRecord && (
                    <span className="text-[10px] text-slate-400 font-sans tracking-tighter whitespace-nowrap">
                      (___초)
                    </span>
                  )}
                </div>
              </div>
            ))}
          </div>

          <div className="mt-12 pt-4 border-t border-slate-300 flex justify-between items-center text-[11px] text-slate-400">
            <span>초5 나눗셈 자동화 프로젝트 · MathCure</span>
            <span>Seed: {currentWorksheet.seed}</span>
            <span>Page 1 / 1</span>
          </div>
        </div>
      ) : (
        /* [2] 정답지 인쇄 영역 */
        <div className="a4-preview-box worksheet-sheet text-slate-900">
          <div className="border-b-2 border-slate-900 pb-3 mb-6 flex justify-between items-end">
            <div>
              <span className="text-[12px] font-bold text-slate-500 uppercase tracking-widest block">
                ANSWER KEY · 정답 및 빠른 채점표
              </span>
              <h2 className="text-2xl font-black text-slate-900 mt-0.5">
                {currentWorksheet.title} - [ 정답지 ]
              </h2>
              <span className="text-xs text-slate-600 font-medium">
                {currentWorksheet.subtitle} · Seed: {currentWorksheet.seed}
              </span>
            </div>

            <div className="text-right text-xs">
              <span className="px-2 py-1 bg-slate-100 border border-slate-300 rounded font-bold">
                교사용 / 채점용
              </span>
            </div>
          </div>

          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 my-6">
            {currentWorksheet.problems.map((prob, idx) => (
              <div
                key={prob.id || idx}
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
            15초 이상 지체된 문제의 경우 나눗셈 전략을 다시 짚어주세요.
          </div>

          <div className="mt-8 pt-4 border-t border-slate-300 flex justify-between items-center text-[11px] text-slate-400">
            <span>초5 나눗셈 자동화 프로젝트 · MathCure 정답지</span>
            <span>Page 1 / 1</span>
          </div>
        </div>
      )}

      {/* 온라인 시험 링크 발급 모달 */}
      <ExamModal
        isOpen={examModalOpen}
        onClose={() => setExamModalOpen(false)}
        worksheetId={examModalProps.worksheetId}
        defaultTitle={examModalProps.title}
        defaultStudentName={examModalProps.studentName}
        problems={examModalProps.problems}
      />
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
