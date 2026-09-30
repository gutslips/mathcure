"use client";

import { useState, useEffect } from "react";
import { User, Save, Trash2, Check } from "lucide-react";

export default function SettingsPage() {
  const [name, setName] = useState("홍길동");
  const [grade, setGrade] = useState(5);
  const [saved, setSaved] = useState(false);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    fetch("/api/student")
      .then((res) => res.json())
      .then((data) => {
        if (data.student) {
          setName(data.student.name);
          setGrade(data.student.grade);
        }
      });
  }, []);

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setSaved(false);

    try {
      const res = await fetch("/api/student", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name, grade }),
      });
      if (res.ok) {
        setSaved(true);
        setTimeout(() => setSaved(false), 3000);
      }
    } catch {
      alert("설정 저장에 실패했습니다.");
    } finally {
      setLoading(false);
    }
  };

  const handleReset = async () => {
    if (confirm("정말로 모든 진단 및 훈련 기록을 초기화하시겠습니까? 이 작업은 되돌릴 수 없습니다.")) {
      setLoading(true);
      await fetch("/api/student", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name, grade, resetData: true }),
      });
      alert("모든 기록이 초기화되었습니다.");
      window.location.reload();
    }
  };

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">학생 및 앱 설정</h1>
        <p className="text-sm text-slate-600 mt-1">
          진단 대상 학생 정보와 로컬 진단 데이터를 관리합니다.
        </p>
      </div>

      <div className="bg-white border border-slate-200 rounded-xl p-6 sm:p-8 space-y-6">
        <form onSubmit={handleSave} className="space-y-5">
          <div className="flex items-center gap-3 pb-4 border-b border-slate-100">
            <User className="w-5 h-5 text-slate-700" />
            <h2 className="text-base font-bold text-slate-800">학생 프로필</h2>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1.5">
              학생 이름
            </label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-slate-800 text-sm font-medium focus:ring-2 focus:ring-slate-900 focus:outline-none"
              required
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1.5">
              학년
            </label>
            <select
              value={grade}
              onChange={(e) => setGrade(Number(e.target.value))}
              className="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-slate-800 text-sm font-medium focus:ring-2 focus:ring-slate-900 focus:outline-none bg-white"
            >
              <option value={3}>초등학교 3학년</option>
              <option value={4}>초등학교 4학년</option>
              <option value={5}>초등학교 5학년 (현재 표준 진단 과정)</option>
              <option value={6}>초등학교 6학년</option>
            </select>
          </div>

          <div className="pt-2 flex items-center justify-between">
            <button
              type="submit"
              disabled={loading}
              className="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 transition disabled:opacity-50"
            >
              {saved ? (
                <>
                  <Check className="w-4 h-4 text-emerald-400" />
                  저장되었습니다
                </>
              ) : (
                <>
                  <Save className="w-4 h-4" />
                  설정 저장
                </>
              )}
            </button>
          </div>
        </form>

        <div className="pt-6 border-t border-slate-200">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-sm font-bold text-red-600">진단 데이터 초기화</h3>
              <p className="text-xs text-slate-500 mt-0.5">
                모든 과거 진단 기록과 오답 분석 데이터를 데이터베이스에서 삭제합니다.
              </p>
            </div>
            <button
              onClick={handleReset}
              disabled={loading}
              className="inline-flex items-center gap-1.5 px-3 py-2 bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 rounded-lg text-xs font-semibold transition"
            >
              <Trash2 className="w-3.5 h-3.5" />
              데이터 초기화
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
