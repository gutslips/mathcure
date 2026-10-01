"use client";

import { useState, useEffect, useRef } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { 
  Calculator, 
  FileText, 
  PlayCircle, 
  History as HistoryIcon, 
  Layers, 
  Settings as SettingsIcon,
  Home,
  ChevronDown,
  UserPlus,
  Check,
  User
} from "lucide-react";

interface StudentInfo {
  id: string;
  name: string;
  grade: number;
}

export function Navbar() {
  const pathname = usePathname();

  const [activeStudent, setActiveStudent] = useState<StudentInfo | null>(null);
  const [students, setStudents] = useState<StudentInfo[]>([]);
  const [dropdownOpen, setDropdownOpen] = useState(false);
  const [isAdding, setIsAdding] = useState(false);
  const [newName, setNewName] = useState("");
  const [newGrade, setNewGrade] = useState(5);

  const dropdownRef = useRef<HTMLDivElement>(null);

  // 학생 목록 및 현재 활성 학생 로드
  const fetchStudentData = () => {
    fetch("/api/student")
      .then((res) => res.json())
      .then((data) => {
        if (data.activeStudent) {
          setActiveStudent(data.activeStudent);
        }
        if (data.students) {
          setStudents(data.students);
        }
      })
      .catch((err) => console.error("학생 정보 로드 실패:", err));
  };

  useEffect(() => {
    fetchStudentData();
  }, [pathname]);

  // 바깥 클릭 시 드롭다운 닫기
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        setDropdownOpen(false);
        setIsAdding(false);
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  // 학생 전환
  const handleSwitchStudent = async (studentId: string) => {
    try {
      const res = await fetch("/api/student", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "switch", id: studentId }),
      });
      const data = await res.json();
      if (data.success) {
        sessionStorage.removeItem("lastDiagnosisAnalysis");
        setDropdownOpen(false);
        window.location.reload();
      }
    } catch (e) {
      console.error(e);
    }
  };

  // 새 학생 생성
  const handleCreateStudent = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newName.trim()) return;

    try {
      const res = await fetch("/api/student", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "create", name: newName.trim(), grade: newGrade }),
      });
      const data = await res.json();
      if (data.success) {
        sessionStorage.removeItem("lastDiagnosisAnalysis");
        setNewName("");
        setIsAdding(false);
        setDropdownOpen(false);
        window.location.reload();
      }
    } catch (e) {
      console.error(e);
    }
  };

  const navItems = [
    { href: "/", label: "대시보드", icon: Home },
    { href: "/diagnosis", label: "연산 진단", icon: Calculator },
    { href: "/worksheet", label: "맞춤 문제지", icon: FileText },
    { href: "/practice", label: "온라인 풀이", icon: PlayCircle },
    { href: "/history", label: "학습 기록", icon: HistoryIcon },
    { href: "/problem-bank", label: "문제 유형", icon: Layers },
    { href: "/settings", label: "설정", icon: SettingsIcon },
  ];

  // 학생 전용 시험 화면(/exam/...)에서는 상단/하단 네비게이션을 완전히 숨김
  if (pathname.startsWith("/exam")) {
    return null;
  }

  return (
    <header className="no-print bg-white border-b border-slate-200 sticky top-0 z-30">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16">
          <div className="flex items-center gap-3">
            <Link href="/" className="flex items-center gap-2">
              <span className="w-8 h-8 rounded bg-slate-900 text-white flex items-center justify-center font-bold text-sm tracking-wider">
                MC
              </span>
              <div>
                <span className="font-bold text-slate-900 text-base block leading-tight">
                  초5 연산 트레이너
                </span>
                <span className="text-xs text-slate-500 block">
                  나눗셈 진단 & 맞춤 훈련
                </span>
              </div>
            </Link>
          </div>

          <nav className="hidden md:flex items-center gap-1">
            {navItems.map((item) => {
              const Icon = item.icon;
              const isActive =
                item.href === "/"
                  ? pathname === "/"
                  : pathname.startsWith(item.href);

              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className={`flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-colors ${
                    isActive
                      ? "bg-slate-100 text-slate-900"
                      : "text-slate-600 hover:text-slate-900 hover:bg-slate-50"
                  }`}
                >
                  <Icon className="w-4 h-4" />
                  {item.label}
                </Link>
              );
            })}
          </nav>

          {/* 다중 학생 전환 드롭다운 */}
          <div className="relative" ref={dropdownRef}>
            <button
              onClick={() => setDropdownOpen((prev) => !prev)}
              className="flex items-center gap-2 text-xs text-slate-700 bg-slate-50 hover:bg-slate-100 px-3 py-1.5 rounded-full border border-slate-200 transition"
            >
              <span className="w-2 h-2 rounded-full bg-emerald-500"></span>
              <span className="font-bold text-slate-900">
                {activeStudent ? activeStudent.name : "학생 선택"}
              </span>
              <span className="text-slate-400">|</span>
              <span>초{activeStudent ? activeStudent.grade : 5}</span>
              <ChevronDown className="w-3.5 h-3.5 text-slate-400 ml-0.5" />
            </button>

            {dropdownOpen && (
              <div className="absolute right-0 mt-2 w-64 bg-white border border-slate-200 rounded-xl shadow-lg p-2 z-50 animate-in fade-in zoom-in-95">
                <div className="px-3 py-2 border-b border-slate-100 text-[11px] font-semibold text-slate-400 uppercase tracking-wider flex justify-between items-center">
                  <span>등록된 학생 목록</span>
                  <span className="text-slate-500 font-mono">{students.length}명</span>
                </div>

                <div className="max-h-52 overflow-y-auto py-1 space-y-1">
                  {students.map((st) => {
                    const isSelected = activeStudent?.id === st.id;
                    return (
                      <button
                        key={st.id}
                        onClick={() => handleSwitchStudent(st.id)}
                        className={`w-full flex items-center justify-between px-3 py-2 rounded-lg text-xs transition ${
                          isSelected
                            ? "bg-slate-100 font-bold text-slate-900"
                            : "text-slate-700 hover:bg-slate-50"
                        }`}
                      >
                        <div className="flex items-center gap-2">
                          <User className="w-3.5 h-3.5 text-slate-400" />
                          <span>{st.name}</span>
                          <span className="text-[11px] text-slate-400">(초{st.grade})</span>
                        </div>
                        {isSelected && <Check className="w-4 h-4 text-emerald-600" />}
                      </button>
                    );
                  })}
                </div>

                {/* 새 학생 등록 폼 */}
                {!isAdding ? (
                  <button
                    onClick={() => setIsAdding(true)}
                    className="w-full mt-2 pt-2 border-t border-slate-100 flex items-center justify-center gap-1.5 py-1.5 text-xs font-semibold text-slate-700 hover:text-slate-900 hover:bg-slate-50 rounded-lg transition"
                  >
                    <UserPlus className="w-3.5 h-3.5" />
                    새 학생 추가
                  </button>
                ) : (
                  <form onSubmit={handleCreateStudent} className="mt-2 pt-2 border-t border-slate-100 space-y-2">
                    <input
                      type="text"
                      placeholder="새 학생 이름"
                      value={newName}
                      onChange={(e) => setNewName(e.target.value)}
                      className="w-full px-2.5 py-1.5 text-xs border border-slate-300 rounded-md focus:outline-none focus:ring-1 focus:ring-slate-900"
                      autoFocus
                    />
                    <div className="flex gap-1.5">
                      <select
                        value={newGrade}
                        onChange={(e) => setNewGrade(Number(e.target.value))}
                        className="text-xs border border-slate-300 rounded-md px-2 py-1 bg-white"
                      >
                        <option value={3}>초3</option>
                        <option value={4}>초4</option>
                        <option value={5}>초5</option>
                        <option value={6}>초6</option>
                      </select>
                      <button
                        type="submit"
                        className="flex-1 bg-slate-900 text-white rounded-md text-xs font-semibold py-1 hover:bg-slate-800"
                      >
                        추가
                      </button>
                      <button
                        type="button"
                        onClick={() => setIsAdding(false)}
                        className="px-2 bg-slate-100 text-slate-600 rounded-md text-xs hover:bg-slate-200"
                      >
                        취소
                      </button>
                    </div>
                  </form>
                )}
              </div>
            )}
          </div>
        </div>

        {/* 모바일 하단 고정 네비게이션 탭바 (md 이상에서는 숨김) */}
        <nav className="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur border-t border-slate-200 flex items-center justify-around py-2 pb-safe shadow-lg">
          {[
            navItems[0], // 대시보드
            navItems[1], // 연산 진단
            navItems[2], // 맞춤 문제지
            navItems[3], // 온라인 풀이
            navItems[6], // 설정
          ].map((item) => {
            const Icon = item.icon;
            const isActive =
              item.href === "/"
                ? pathname === "/"
                : pathname.startsWith(item.href);

            return (
              <Link
                key={item.href}
                href={item.href}
                className={`flex flex-col items-center py-1 px-2.5 rounded-lg text-[10px] font-medium transition ${
                  isActive ? "text-slate-950 font-bold" : "text-slate-500 hover:text-slate-800"
                }`}
              >
                <Icon className={`w-5 h-5 mb-0.5 ${isActive ? "stroke-[2.5]" : ""}`} />
                {item.label}
              </Link>
            );
          })}
        </nav>
      </div>
    </header>
  );
}
