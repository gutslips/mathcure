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
  Share2
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
  const [timeLimitSec, setTimeLimitSec] = useState(600); // 10분 기본
  const [showResult, setShowResult] = useState(true);
  const [loading, setLoading] = useState(false);
  const [createdExam, setCreatedExam] = useState<{
    code: string;
    id: string;
    title: string;
  } | null>(null);
  const [qrDataUrl, setQrDataUrl] = useState<string>("");
  const [copiedLink, setCopiedLink] = useState(false);
  const [copiedCode, setCopiedCode] = useState(false);
  const [copiedMsg, setCopiedMsg] = useState(false);

  useEffect(() => {
    if (isOpen) {
      setTitle(defaultTitle);
      setStudentName(defaultStudentName);
      setCreatedExam(null);
      setQrDataUrl("");
      setCopiedLink(false);
      setCopiedCode(false);
      setCopiedMsg(false);
    }
  }, [isOpen, defaultTitle, defaultStudentName]);

  if (!isOpen) return null;

  const handleCreate = async () => {
    if (!problems || problems.length === 0) {
      alert("출제할 문제가 없습니다.");
      return;
    }
    setLoading(true);
    try {
      const res = await fetch("/api/exam", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          title,
          worksheetId,
          studentName: studentName.trim() || undefined,
          timeLimitSec,
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
  const examUrl = createdExam ? `${origin}/exam/${createdExam.code}` : "";

  const copyToClipboard = (text: string, type: "link" | "code" | "msg") => {
    navigator.clipboard.writeText(text);
    if (type === "link") {
      setCopiedLink(true);
      setTimeout(() => setCopiedLink(false), 2000);
    } else if (type === "code") {
      setCopiedCode(true);
      setTimeout(() => setCopiedCode(false), 2000);
    } else {
      setCopiedMsg(true);
      setTimeout(() => setCopiedMsg(false), 2000);
    }
  };

  const getShareMessage = () => {
    const minText = timeLimitSec > 0 ? `${timeLimitSec / 60}분` : "무제한";
    return `[초5 연산 트레이너] 온라인 시험 안내\n\n📌 시험명: ${title}\n⏱️ 제한 시간: ${minText} (${problems.length}문항)\n\n👉 바로 풀기 링크: ${examUrl}\n(또는 mathcure.vercel.app/exam 에서 입장 코드 [ ${createdExam?.code} ] 입력)\n\n태블릿이나 스마트폰, PC로 접속하여 편하게 응시하세요!`;
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
      <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 relative animate-in fade-in zoom-in-95 duration-150 my-8">
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
                  선생님 화면 노출 없이 태블릿/스마트폰으로 학생이 바로 응시합니다.
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
                  className="w-full px-3 py-2 border border-slate-300 rounded-xl focus:outline-none focus:border-slate-900 font-medium"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1 flex items-center gap-1">
                    <Clock className="w-3.5 h-3.5 text-slate-500" />
                    제한 시간
                  </label>
                  <select
                    value={timeLimitSec}
                    onChange={(e) => setTimeLimitSec(Number(e.target.value))}
                    className="w-full px-3 py-2 border border-slate-300 rounded-xl bg-white focus:outline-none focus:border-slate-900"
                  >
                    <option value={300}>5분 (스피드 진단)</option>
                    <option value={600}>10분 (표준 20제 권장)</option>
                    <option value={900}>15분 (집중 풀이)</option>
                    <option value={1200}>20분</option>
                    <option value={0}>무제한 (자율 풀이)</option>
                  </select>
                </div>

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
                    className="w-full px-3 py-2 border border-slate-300 rounded-xl focus:outline-none focus:border-slate-900"
                  />
                </div>
              </div>

              <div className="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                <div className="text-xs font-semibold text-slate-800">
                  시험 세부 설정
                </div>
                <label className="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={showResult}
                    onChange={(e) => setShowResult(e.target.checked)}
                    className="w-4 h-4 rounded text-slate-900 focus:ring-0"
                  />
                  <span>제출 후 학생에게 즉시 점수 및 정오표 공개</span>
                </label>
                <div className="text-[11px] text-slate-400">
                  * 학생이 제출한 결과는 점수 공개 여부와 상관없이 선생님 DB에 즉시 자동 회수됩니다.
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
                학생에게 아래 QR코드를 보여주거나 링크를 전송하세요.
              </p>
            </div>

            {/* 6자리 간편 코드 강조 */}
            <div className="bg-slate-900 text-white p-4 rounded-2xl shadow-sm">
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

            {/* 태블릿 촬영용 QR 코드 */}
            {qrDataUrl && (
              <div className="flex flex-col items-center justify-center py-2">
                <div className="p-3 bg-white border-2 border-slate-200 rounded-2xl shadow-sm inline-block">
                  <img
                    src={qrDataUrl}
                    alt="시험 접속용 QR 코드"
                    className="w-44 h-44 mx-auto"
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
                  {copiedLink ? "복사됨" : "링크 복사"}
                </button>
              </div>

              <button
                type="button"
                onClick={() => copyToClipboard(getShareMessage(), "msg")}
                className="w-full py-2 px-3 border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold rounded-xl text-xs flex items-center justify-center gap-1.5 transition"
              >
                <Share2 className="w-3.5 h-3.5 text-slate-500" />
                {copiedMsg ? "카톡/문자 안내 문구가 복사되었습니다!" : "카카오톡 / 문자 안내 문구 전체 복사"}
              </button>
            </div>

            <div className="pt-2 flex gap-2">
              <a
                href={`/exam/${createdExam.code}`}
                target="_blank"
                rel="noreferrer"
                className="flex-1 py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold rounded-xl text-xs flex items-center justify-center gap-1 transition"
              >
                <ExternalLink className="w-3.5 h-3.5" />
                학생 시험창 새 창 열기
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
