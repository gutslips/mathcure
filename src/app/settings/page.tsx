"use client";

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { 
  User, 
  Save, 
  Trash2, 
  Check, 
  UserPlus, 
  Users, 
  RotateCcw,
  CheckCircle2
} from "lucide-react";

interface StudentInfo {
  id: string;
  name: string;
  grade: number;
}

export default function SettingsPage() {
  const router = useRouter();

  const [activeStudent, setActiveStudent] = useState<StudentInfo | null>(null);
  const [students, setStudents] = useState<StudentInfo[]>([]);
  const [name, setName] = useState("");
  const [grade, setGrade] = useState(5);

  const [newStudentName, setNewStudentName] = useState("");
  const [newStudentGrade, setNewStudentGrade] = useState(5);

  const [saved, setSaved] = useState(false);
  const [loading, setLoading] = useState(false);

  const loadData = () => {
    fetch("/api/student")
      .then((res) => res.json())
      .then((data) => {
        if (data.activeStudent) {
          setActiveStudent(data.activeStudent);
          setName(data.activeStudent.name);
          setGrade(data.activeStudent.grade);
        }
        if (data.students) {
          setStudents(data.students);
        }
      });
  };

  useEffect(() => {
    loadData();
  }, []);

  // 현재 활성 학생 정보 수정
  const handleSaveActive = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!activeStudent) return;
    setLoading(true);
    setSaved(false);

    try {
      const res = await fetch("/api/student", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          id: activeStudent.id,
          name: name.trim(),
          grade: Number(grade),
        }),
      });
      const data = await res.json();
      if (data.success) {
        setSaved(true);
        setActiveStudent(data.activeStudent);
        loadData();
        router.refresh();
        setTimeout(() => setSaved(false), 2500);
      }
    } catch {
      alert("설정 저장에 실패했습니다.");
    } finally {
      setLoading(false);
    }
  };

  // 학생 전환
  const handleSwitch = async (id: string) => {
    setLoading(true);
    try {
      const res = await fetch("/api/student", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "switch", id }),
      });
      const data = await res.json();
      if (data.success) {
        setActiveStudent(data.activeStudent);
        setName(data.activeStudent.name);
        setGrade(data.activeStudent.grade);
        sessionStorage.removeItem("lastDiagnosisAnalysis");
        router.refresh();
      }
    } catch {
      alert("학생 전환에 실패했습니다.");
    } finally {
      setLoading(false);
    }
  };

  // 새 학생 등록
  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newStudentName.trim()) return;
    setLoading(true);

    try {
      const res = await fetch("/api/student", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "create",
          name: newStudentName.trim(),
          grade: Number(newStudentGrade),
        }),
      });
      const data = await res.json();
      if (data.success) {
        setNewStudentName("");
        loadData();
        router.refresh();
      }
    } catch {
      alert("학생 등록에 실패했습니다.");
    } finally {
      setLoading(false);
    }
  };

  // 현재 학생의 데이터만 초기화
  const handleResetData = async () => {
    if (!activeStudent) return;
    if (
      confirm(
        `'${activeStudent.name}' 학생의 모든 진단 및 훈련 기록을 초기화하시겠습니까?\n(다른 학생의 데이터는 안전하게 보존됩니다)`
      )
    ) {
      setLoading(true);
      try {
        const res = await fetch("/api/student", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ action: "reset", id: activeStudent.id }),
        });
        if (res.ok) {
          sessionStorage.removeItem("lastDiagnosisAnalysis");
          alert(`'${activeStudent.name}' 학생의 기록이 초기화되었습니다.`);
          router.refresh();
          window.location.reload();
        }
      } catch {
        alert("데이터 초기화에 실패했습니다.");
      } finally {
        setLoading(false);
      }
    }
  };

  // 학생 삭제
  const handleDeleteStudent = async (id: string, sName: string) => {
    if (students.length <= 1) {
      alert("최소 한 명의 학생은 유지되어야 하므로 삭제할 수 없습니다.");
      return;
    }

    if (confirm(`정말로 '${sName}' 학생과 관련된 모든 기록을 삭제하시겠습니까?`)) {
      setLoading(true);
      try {
        const res = await fetch("/api/student", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ action: "delete", id }),
        });
        const data = await res.json();
        if (data.success) {
          sessionStorage.removeItem("lastDiagnosisAnalysis");
          loadData();
          router.refresh();
        } else {
          alert(data.error || "삭제에 실패했습니다.");
        }
      } catch {
        alert("삭제 처리 중 오류가 발생했습니다.");
      } finally {
        setLoading(false);
      }
    }
  };

  return (
    <div className="max-w-3xl mx-auto space-y-8">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">학생 관리 및 앱 설정</h1>
        <p className="text-sm text-slate-600 mt-1">
          다중 학생을 등록하여 개별적으로 진단 기록과 맞춤 훈련을 독립 관리할 수 있습니다.
        </p>
      </div>

      {/* 1. 현재 선택된 학생 정보 수정 */}
      <div className="bg-white border border-slate-200 rounded-xl p-6 sm:p-8 space-y-6">
        <form onSubmit={handleSaveActive} className="space-y-5">
          <div className="flex items-center justify-between pb-4 border-b border-slate-100">
            <div className="flex items-center gap-2.5">
              <User className="w-5 h-5 text-slate-700" />
              <h2 className="text-base font-bold text-slate-900">
                현재 활성화된 학생 프로필
              </h2>
            </div>
            {activeStudent && (
              <span className="text-xs px-2.5 py-1 bg-slate-100 text-slate-700 rounded-full font-semibold">
                ID: {activeStudent.id.slice(0, 8)}...
              </span>
            )}
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                학생 이름
              </label>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="이름 입력"
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
                <option value={5}>초등학교 5학년 (표준 나눗셈 과정)</option>
                <option value={6}>초등학교 6학년</option>
              </select>
            </div>
          </div>

          <div className="pt-2 flex items-center justify-between">
            <span className="text-xs text-slate-500">
              * 이름을 변경하고 저장하면 상단 네비게이션과 문제지 등에 즉시 적용됩니다.
            </span>

            <button
              type="submit"
              disabled={loading}
              className="inline-flex items-center gap-2 px-5 py-2 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 transition disabled:opacity-50"
            >
              {saved ? (
                <>
                  <Check className="w-4 h-4 text-emerald-400" />
                  저장 완료
                </>
              ) : (
                <>
                  <Save className="w-4 h-4" />
                  프로필 저장
                </>
              )}
            </button>
          </div>
        </form>

        {/* 현재 학생 데이터 초기화 */}
        <div className="pt-6 border-t border-slate-200 flex items-center justify-between">
          <div>
            <h3 className="text-sm font-bold text-amber-800 flex items-center gap-1.5">
              <RotateCcw className="w-4 h-4" />
              현재 학생 진단 기록 초기화
            </h3>
            <p className="text-xs text-slate-500 mt-0.5">
              &apos;{activeStudent?.name}&apos; 학생의 진단 내역만 삭제되며, 다른 학생 데이터에는 영향이 없습니다.
            </p>
          </div>
          <button
            onClick={handleResetData}
            disabled={loading}
            className="inline-flex items-center gap-1.5 px-3 py-2 bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200 rounded-lg text-xs font-semibold transition"
          >
            기록 초기화
          </button>
        </div>
      </div>

      {/* 2. 전체 등록된 학생 목록 관리 (다중 사용자 지원) */}
      <div className="bg-white border border-slate-200 rounded-xl p-6 sm:p-8 space-y-6">
        <div className="flex items-center justify-between pb-4 border-b border-slate-100">
          <div className="flex items-center gap-2.5">
            <Users className="w-5 h-5 text-slate-700" />
            <h2 className="text-base font-bold text-slate-900">
              등록된 학생 목록 ({students.length}명)
            </h2>
          </div>
        </div>

        {/* 새 학생 등록 폼 */}
        <form onSubmit={handleCreate} className="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
          <span className="text-xs font-bold text-slate-700 block">
            ➕ 새 학생 등록
          </span>
          <div className="flex flex-col sm:flex-row gap-3">
            <input
              type="text"
              placeholder="학생 이름 (예: 김철수)"
              value={newStudentName}
              onChange={(e) => setNewStudentName(e.target.value)}
              className="flex-1 px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-slate-900"
              required
            />
            <select
              value={newStudentGrade}
              onChange={(e) => setNewStudentGrade(Number(e.target.value))}
              className="px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white"
            >
              <option value={3}>초3</option>
              <option value={4}>초4</option>
              <option value={5}>초5</option>
              <option value={6}>초6</option>
            </select>
            <button
              type="submit"
              disabled={loading || !newStudentName.trim()}
              className="inline-flex items-center justify-center gap-1.5 px-5 py-2 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 disabled:opacity-40 transition"
            >
              <UserPlus className="w-4 h-4" />
              학생 추가
            </button>
          </div>
        </form>

        {/* 학생 목록 테이블 */}
        <div className="divide-y divide-slate-100 border border-slate-200 rounded-xl overflow-hidden">
          {students.map((st) => {
            const isCurrent = activeStudent?.id === st.id;

            return (
              <div
                key={st.id}
                className={`p-4 flex items-center justify-between transition ${
                  isCurrent ? "bg-slate-50/80" : "bg-white hover:bg-slate-50/40"
                }`}
              >
                <div className="flex items-center gap-3">
                  <div
                    className={`w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm ${
                      isCurrent
                        ? "bg-slate-900 text-white"
                        : "bg-slate-100 text-slate-600"
                    }`}
                  >
                    {st.name.charAt(0)}
                  </div>
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="font-bold text-slate-900 text-sm">
                        {st.name}
                      </span>
                      <span className="text-xs text-slate-500">
                        초등학교 {st.grade}학년
                      </span>
                      {isCurrent && (
                        <span className="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                          <CheckCircle2 className="w-3 h-3" /> 사용 중
                        </span>
                      )}
                    </div>
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  {!isCurrent ? (
                    <button
                      onClick={() => handleSwitch(st.id)}
                      disabled={loading}
                      className="px-3 py-1.5 border border-slate-300 text-slate-700 hover:bg-slate-100 rounded-lg text-xs font-semibold transition"
                    >
                      이 학생으로 전환
                    </button>
                  ) : (
                    <span className="text-xs text-slate-400 font-medium px-2">
                      현재 활성
                    </span>
                  )}

                  {students.length > 1 && (
                    <button
                      onClick={() => handleDeleteStudent(st.id, st.name)}
                      disabled={loading}
                      className="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                      title="학생 삭제"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
