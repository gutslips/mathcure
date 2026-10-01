"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { KeyRound, ArrowRight, ShieldCheck } from "lucide-react";

export default function ExamHubPage() {
  const router = useRouter();
  const [code, setCode] = useState("");
  const [error, setError] = useState("");

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const cleanCode = code.trim().replace(/[^0-9]/g, "");
    if (cleanCode.length < 4) {
      setError("올바른 시험 코드를 입력해주세요.");
      return;
    }
    router.push(`/exam/${cleanCode}`);
  };

  return (
    <div className="min-h-screen bg-slate-900 text-white flex flex-col justify-center items-center p-4">
      <div className="max-w-md w-full bg-slate-800/90 border border-slate-700 rounded-3xl p-8 shadow-2xl backdrop-blur text-center space-y-6">
        <div className="inline-flex p-3 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400">
          <KeyRound className="w-8 h-8" />
        </div>

        <div>
          <h1 className="text-2xl font-black text-white">
            초5 연산 온라인 시험 입장
          </h1>
          <p className="text-xs text-slate-400 mt-1.5 leading-relaxed">
            선생님께 전달받은 <strong>6자리 시험 코드</strong>를 입력해 주세요.
          </p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <input
              type="text"
              inputMode="numeric"
              maxLength={8}
              autoFocus
              value={code}
              onChange={(e) => {
                setCode(e.target.value);
                setError("");
              }}
              placeholder="예: 839102"
              className="w-full text-center text-3xl font-mono font-black tracking-widest py-4 px-6 rounded-2xl bg-slate-900 border-2 border-slate-700 text-emerald-400 placeholder:text-slate-600 focus:outline-none focus:border-emerald-500 transition"
            />
            {error && (
              <p className="text-rose-400 text-xs mt-2 font-medium">{error}</p>
            )}
          </div>

          <button
            type="submit"
            className="w-full py-4 px-6 rounded-2xl bg-emerald-500 hover:bg-emerald-400 active:scale-98 text-slate-950 font-bold text-base flex items-center justify-center gap-2 transition shadow-lg shadow-emerald-500/20"
          >
            시험장 입장하기
            <ArrowRight className="w-5 h-5" />
          </button>
        </form>

        <div className="pt-4 border-t border-slate-700/60 flex items-center justify-center gap-1.5 text-[11px] text-slate-400">
          <ShieldCheck className="w-4 h-4 text-emerald-400" />
          <span>별도의 로그인 없이 태블릿·모바일·PC에서 바로 응시 가능</span>
        </div>
      </div>
    </div>
  );
}
