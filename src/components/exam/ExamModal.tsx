"use client";

import { useState, useEffect } from "react";
import QRCode from "qrcode";
import { 
  X, 
  Copy, 
  Check, 
  ExternalLink, 
  QrCode as QrIcon, 
  Clock, 
  User, 
  Sparkles,
  Share2,
  Hourglass,
  KeyRound
} from "lucide-react";
import { Problem } from "@/types/problem";

interface ExamModalProps {
  isOpen: boolean;
  onClose: () => void;
  worksheetId?: string;
  defaultTitle?: string;
  defaultStudentName?: string;
  problems: Problem[];
}

export function ExamModal({
  isOpen,
  onClose,
  worksheetId,
  defaultTitle = "초5 나눗셈 맞춤 온라인 시험",
  defaultStudentName = "",
  problems,
}: ExamModalProps) {
  const [title, setTitle] = useState(defaultTitle);
  const [studentName, setStudentName] = useState(defaultStudentName);
  
  // 제한 시간 설정 (분 단위 직접 입력 지원)
  const [timeMode, setTimeMode] = useState<"preset" | "custom">("preset");
  const [presetTimeSec, setPresetTimeSec] = useState(600); // 10분 기본
  const [customMinutes, setCustomMinutes] = useState(10);

  // 시험 링크 유효기간 (시간 단위)
  const [expireMode, setExpireMode] = useState<"preset" | "custom">("preset");
  const [presetExpireHours, setPresetExpireHours] = useState(3); // 3시간 기본
  const [customExpireHours, setCustomExpireHours] = useState(3);

  const [showResult, setShowResult] = useState(true);
  const [loading, setLoading] = useState(false);
  const [createdExam, setCreatedExam] = useState<{
    code: string;
    id: string;
    title: string;
    timeLimitSec: number;
    expiresAt?: string;
  } | null>(null);

  const [qrDataUrl, setQrDataUrl] = useState<string>("");
  const [copiedLink, setCopiedLink] = useState(false);
  const [copiedCode, setCopiedCode] = useState(false);
  const [copiedMsg, setCopiedMsg] = useState(false);
  const [copiedHub, setCopiedHub] = useState(false);

  useEffect(() => {
    if (isOpen) {
      setTitle(defaultTitle);
      setStudentName(defaultStudentName);
      setTimeMode("preset");
      setPresetTimeSec(600);
      setCustomMinutes(10);
      setExpireMode("preset");
      setPresetExpireHours(3);
      setCustomExpireHours(3);
      setCreatedExam(null);
      setQrDataUrl("");
      setCopiedLink(false);
      setCopiedCode(false);
      setCopiedMsg(false);
      setCopiedHub(false);
    }
  }, [isOpen, defaultTitle, defaultStudentName]);

  if (!isOpen) return null;

  const getEffectiveTimeSec = () => {
    if (timeMode === "custom") {
      return Math.max(1, Math.min(180, Number(customMinutes) || 10)) * 60;
    }
    return presetTimeSec;
  };

  const getEffectiveExpireHours = () => {
    if (expireMode === "custom") {
      return Math.max(1, Math.min(720, Number(customExpireHours) || 3));
    }
    return presetExpireHours;
  };

  const handleCreate = async () => {
    if (!problems || problems.length === 0) {
      alert("출제할 문제가 없습니다.");
      return;
    }
    setLoading(true);
    const finalTimeSec = getEffectiveTimeSec();
    const finalExpireHours = getEffectiveExpireHours();

    try {
      const res = await fetch("/api/exam", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          title,
          worksheetId,
          studentName: studentName.trim() || undefined,
          timeLimitSec: finalTimeSec,
          expireHours: finalExpireHours,
          showResult,
          problems,
        }),
      });
      const data = await res.json();
      if (!data.success) {
        alert(data.error || "시험 생성 실패");
        return;
      }

      setCreatedExam(data.exam);

      // QR 코드 생성
      const origin = typeof window !== "undefined" ? window.location.origin : "";
      const examUrl = `${origin}/exam/${data.exam.code}`;
      const qr = await QRCode.toDataURL(examUrl, {
        width: 240,
        margin: 1,
        color: {
          dark: "#0f172a",
          light: "#ffffff",
        },
      });
      setQrDataUrl(qr);
    } catch (e) {
      console.error(e);
      alert("시험 생성 중 오류가 발생했습니다.");
    } finally {
      setLoading(false);
    }
  };

  const origin = typeof window !== "undefined" ? window.location.origin : "";
  const hubUrl = `${origin}/exam`;
  const examUrl = createdExam ? `${origin}/exam/${createdExam.code}` : "";

  const copyToClipboard = (text: string, type: "link" | "code" | "msg" | "hub") => {
    navigator.clipboard.writeText(text);
    if (type === "link") {
      setCopiedLink(true);
      setTimeout(() => setCopiedLink(false), 2000);
    } else if (type === "code") {
      setCopiedCode(true);
      setTimeout(() => setCopiedCode(false), 2000);
    } else if (type === "hub") {
      setCopiedHub(true);
      setTimeout(() => setCopiedHub(false), 2000);
    } else {
      setCopiedMsg(true);
      setTimeout(() => setCopiedMsg(false), 2000);
    }
  };

  const getShareMessage = () => {
    const finalTimeSec = getEffectiveTimeSec();
    const minText = finalTimeSec > 0 ? `${Math.round(finalTimeSec / 60)}분` : "무제한";
    const expireHours = getEffectiveExpireHours();
    const expireText = expireHours > 0 ? `${expireHours}시간 후 만료` : "무제한 (만료 없음)";

    return `[초5 연산 트레이너] 온라인 시험 안내\n\n📌 시험명: ${title}\n⏱️ 제한 시간: ${minText} (${problems.length}문항)\n⏰ 링크 유효기간: ${expireText}\n\n👉 [방법 1] 전용 링크로 바로 입장 (클릭 시 자동 시작):\n${examUrl}\n\n👉 [방법 2] 태블릿/PC 브라우저에서 간편 입장:\n1. 브라우저 주소창에 ${hubUrl} 접속\n2. 6자리 입장 코드 [ ${createdExam?.code} ] 입력 후 시작\n\n👉 [방법 3] 학원 태블릿 카메라로 QR 코드 스캔\n\n편한 방법으로 접속하여 시험을 치러주세요!`;
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
      <div className="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl border border-slate-200 relative animate-in fade-in zoom-in-95 duration-150 my-8 max-h-[90vh] overflow-y-auto">
        <button
          onClick={onClose}
          className="absolute top-4 right-4 text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition"
        >
          <X className="w-5 h-5" />
        </button>

        {!createdExam ? (
          /* [1] 시험 생성 옵션 설정 화면 */
          <div className="space-y-5">
            <div className="flex items-center gap-2.5">
              <div className="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center">
                <Share2 className="w-5 h-5" />
              </div>
              <div>
                <h3 className="text-lg font-bold text-slate-900">
                  온라인 시험 링크 & QR 생성
                </h3>
                <p className="text-xs text-slate-500">
                  선생님 화면 노출 없이 태블릿/모바일로 학생이 독립 응시합니다.
                </p>
              </div>
            </div>

            <div className="space-y-4 text-sm">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  시험 제목
                </label>
                <input
                  type="text"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  className="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl focus:outline-none focus:border-slate-900 font-medium"
                />
              </div>

              {/* 시간 제한 (직접 입력 지원) */}
              <div className="p-3.5 bg-slate-50 border border-slate-200 rounded-2xl space-y-2">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                    <Clock className="w-3.5 h-3.5 text-slate-600" />
                    시험 풀이 시간 제한
                  </label>
                  <div className="flex items-center gap-2 text-xs">
                    <button
                      type="button"
                      onClick={() => setTimeMode("preset")}
                      className={`px-2 py-0.5 rounded-md font-semibold transition ${
                        timeMode === "preset" ? "bg-slate-900 text-white" : "text-slate-500 hover:bg-slate-200"
                      }`}
                    >
                      목록 선택
                    </button>
                    <button
                      type="button"
                      onClick={() => setTimeMode("custom")}
                      className={`px-2 py-0.5 rounded-md font-semibold transition ${
                        timeMode === "custom" ? "bg-slate-900 text-white" : "text-slate-500 hover:bg-slate-200"
                      }`}
                    >
                      직접 입력
                    </button>
                  </div>
                </div>

                {timeMode === "preset" ? (
                  <select
                    value={presetTimeSec}
                    onChange={(e) => setPresetTimeSec(Number(e.target.value))}
                    className="w-full px-3 py-2 border border-slate-300 rounded-xl bg-white text-xs font-semibold focus:outline-none focus:border-slate-900"
                  >
                    <option value={300}>5분 (스피드 진단)</option>
                    <option value={600}>10분 (표준 20제 권장)</option>
                    <option value={900}>15분 (집중 풀이)</option>
                    <option value={1200}>20분</option>
                    <option value={0}>무제한 (자율 풀이)</option>
                  </select>
                ) : (
                  <div className="flex items-center gap-2">
                    <input
                      type="number"
                      min={1}
                      max={180}
                      value={customMinutes}
                      onChange={(e) => setCustomMinutes(Number(e.target.value))}
                      placeholder="분 입력"
                      className="w-24 px-3 py-2 border border-slate-300 rounded-xl bg-white text-xs font-bold text-center focus:outline-none focus:border-slate-900"
                    />
                    <span className="text-xs text-slate-600 font-semibold">분 동안 응시 가능 (1~180분)</span>
                  </div>
                )}
              </div>

              {/* 링크 유효기간 (자동 만료 시간) */}
              <div className="p-3.5 bg-slate-50 border border-slate-200 rounded-2xl space-y-2">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                    <Hourglass className="w-3.5 h-3.5 text-slate-600" />
                    시험 링크 유효기간 (자동 만료)
                  </label>
                  <div className="flex items-center gap-2 text-xs">
                    <button
                      type="button"
                      onClick={() => setExpireMode("preset")}
                      className={`px-2 py-0.5 rounded-md font-semibold transition ${
                        expireMode === "preset" ? "bg-slate-900 text-white" : "text-slate-500 hover:bg-slate-200"
                      }`}
                    >
                      목록 선택
                    </button>
                    <button
                      type="button"
                      onClick={() => setExpireMode("custom")}
                      className={`px-2 py-0.5 rounded-md font-semibold transition ${
                        expireMode === "custom" ? "bg-slate-900 text-white" : "text-slate-500 hover:bg-slate-200"
                      }`}
                    >
                      직접 지정
                    </button>
                  </div>
                </div>

                {expireMode === "preset" ? (
                  <select
                    value={presetExpireHours}
                    onChange={(e) => setPresetExpireHours(Number(e.target.value))}
                    className="w-full px-3 py-2 border border-slate-300 rounded-xl bg-white text-xs font-semibold focus:outline-none focus:border-slate-900"
                  >
                    <option value={1}>1시간 후 만료 (즉시 응시용)</option>
                    <option value={3}>3시간 후 만료 (표준 수업 권장)</option>
                    <option value={5}>5시간 후 만료</option>
                    <option value={8}>8시간 후 만료</option>
                    <option value={24}>24시간 (1일) 후 만료</option>
                    <option value={0}>무제한 (만료 없음)</option>
                  </select>
                ) : (
                  <div className="flex items-center gap-2">
                    <input
                      type="number"
                      min={1}
                      max={720}
                      value={customExpireHours}
                      onChange={(e) => setCustomExpireHours(Number(e.target.value))}
                      placeholder="시간 입력"
                      className="w-24 px-3 py-2 border border-slate-300 rounded-xl bg-white text-xs font-bold text-center focus:outline-none focus:border-slate-900"
                    />
                    <span className="text-xs text-slate-600 font-semibold">시간 후 링크 자동 비활성화</span>
                  </div>
                )}
                <div className="text-[11px] text-slate-400">
                  * 만료 시 학생 접속이 차단되며, 시험지 원본 데이터는 선생님 서버에 안전하게 보존됩니다.
                </div>
              </div>

              {/* 학생 이름 & 결과 공개 */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1 flex items-center gap-1">
                    <User className="w-3.5 h-3.5 text-slate-500" />
                    응시 학생 이름
                  </label>
                  <input
                    type="text"
                    value={studentName}
                    placeholder="미입력 시 시작 때 입력"
                    onChange={(e) => setStudentName(e.target.value)}
                    className="w-full px-3 py-2 border border-slate-300 rounded-xl focus:outline-none focus:border-slate-900 text-xs"
                  />
                </div>

                <div className="flex flex-col justify-end">
                  <label className="flex items-center gap-2 text-xs text-slate-700 cursor-pointer pb-2">
                    <input
                      type="checkbox"
                      checked={showResult}
                      onChange={(e) => setShowResult(e.target.checked)}
                      className="w-4 h-4 rounded text-slate-900 focus:ring-0"
                    />
                    <span className="font-semibold">제출 후 점수/정오표 즉시 공개</span>
                  </label>
                </div>
              </div>

              <div className="text-xs text-slate-500 bg-emerald-50 border border-emerald-200 text-emerald-800 p-3 rounded-xl flex items-center gap-2">
                <Sparkles className="w-4 h-4 text-emerald-600 shrink-0" />
                <span>총 <strong>{problems.length}문항</strong>이 담긴 시험지가 준비되었습니다.</span>
              </div>
            </div>

            <div className="flex gap-2 pt-2">
              <button
                type="button"
                onClick={onClose}
                className="flex-1 py-2.5 px-4 border border-slate-300 rounded-xl text-slate-600 font-semibold text-xs hover:bg-slate-50 transition"
              >
                취소
              </button>
              <button
                type="button"
                disabled={loading}
                onClick={handleCreate}
                className="flex-1 py-2.5 px-4 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-xs transition shadow-sm disabled:opacity-50"
              >
                {loading ? "생성 중..." : "시험 링크 & QR 발급하기"}
              </button>
            </div>
          </div>
        ) : (
          /* [2] 생성 완료 (QR 코드 & 6자리 코드 & 링크) */
          <div className="space-y-5 text-center">
            <div>
              <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-full mb-2">
                <Check className="w-3.5 h-3.5" /> 시험 생성 완료
              </span>
              <h3 className="text-lg font-bold text-slate-900">
                {createdExam.title}
              </h3>
              <p className="text-xs text-slate-500 mt-0.5">
                학생에게 코드를 알려주거나 링크를 전송하세요.
              </p>
            </div>

            {/* 6자리 간편 코드 및 허브 주소 안내 박스 */}
            <div className="bg-slate-900 text-white p-4 rounded-2xl shadow-sm space-y-3">
              <div>
                <div className="text-[11px] text-slate-400 font-medium uppercase tracking-wider mb-1">
                  태블릿 / PC 간편 입장 코드
                </div>
                <div className="flex items-center justify-center gap-3">
                  <span className="text-3xl font-mono font-black tracking-widest text-emerald-400">
                    {createdExam.code}
                  </span>
                  <button
                    type="button"
                    onClick={() => copyToClipboard(createdExam.code, "code")}
                    className="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs rounded-lg transition"
                  >
                    {copiedCode ? "복사됨!" : "코드 복사"}
                  </button>
                </div>
              </div>

              {/* 입장 허브 주소 명시 */}
              <div className="pt-2 border-t border-slate-800 flex items-center justify-between text-xs text-slate-300">
                <span className="flex items-center gap-1 text-[11px]">
                  <KeyRound className="w-3.5 h-3.5 text-emerald-400" />
                  코드 입력 주소: <strong className="font-mono text-white underline underline-offset-2">{hubUrl}</strong>
                </span>
                <button
                  type="button"
                  onClick={() => copyToClipboard(hubUrl, "hub")}
                  className="text-[11px] text-emerald-400 hover:text-emerald-300 font-semibold"
                >
                  {copiedHub ? "주소 복사됨!" : "주소 복사"}
                </button>
              </div>
            </div>

            {/* 태블릿 촬영용 QR 코드 */}
            {qrDataUrl && (
              <div className="flex flex-col items-center justify-center py-1">
                <div className="p-3 bg-white border-2 border-slate-200 rounded-2xl shadow-sm inline-block">
                  <img
                    src={qrDataUrl}
                    alt="시험 접속용 QR 코드"
                    className="w-40 h-40 mx-auto"
                  />
                </div>
                <span className="text-[11px] text-slate-500 mt-2 flex items-center gap-1 font-medium">
                  <QrIcon className="w-3.5 h-3.5 text-slate-400" />
                  학원 태블릿 카메라로 비추면 1초 만에 시험 시작
                </span>
              </div>
            )}

            {/* URL 복사 및 공유 */}
            <div className="space-y-2 text-left">
              <div className="flex items-center gap-2">
                <input
                  type="text"
                  readOnly
                  value={examUrl}
                  className="flex-1 px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono text-slate-700 select-all"
                />
                <button
                  type="button"
                  onClick={() => copyToClipboard(examUrl, "link")}
                  className="px-3 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold rounded-xl flex items-center gap-1 transition shrink-0"
                >
                  {copiedLink ? <Check className="w-3.5 h-3.5" /> : <Copy className="w-3.5 h-3.5" />}
                  {copiedLink ? "복사됨" : "전용 링크 복사"}
                </button>
              </div>

              <button
                type="button"
                onClick={() => copyToClipboard(getShareMessage(), "msg")}
                className="w-full py-2.5 px-3 border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold rounded-xl text-xs flex items-center justify-center gap-1.5 transition"
              >
                <Share2 className="w-3.5 h-3.5 text-slate-500" />
                {copiedMsg ? "카톡/문자 안내 문구가 복사되었습니다!" : "카카오톡 / 문자 안내 문구 전체 복사"}
              </button>
            </div>

            <div className="pt-2 flex gap-2">
              <a
                href={hubUrl}
                target="_blank"
                rel="noreferrer"
                className="flex-1 py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold rounded-xl text-xs flex items-center justify-center gap-1 transition"
              >
                <KeyRound className="w-3.5 h-3.5" />
                코드 입력창 열기
              </a>
              <button
                type="button"
                onClick={onClose}
                className="flex-1 py-2.5 px-4 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-xs transition"
              >
                완료 및 닫기
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
