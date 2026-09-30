"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { 
  Calculator, 
  FileText, 
  PlayCircle, 
  History as HistoryIcon, 
  Layers, 
  Settings as SettingsIcon,
  Home
} from "lucide-react";

export function Navbar() {
  const pathname = usePathname();

  const navItems = [
    { href: "/", label: "대시보드", icon: Home },
    { href: "/diagnosis", label: "연산 진단", icon: Calculator },
    { href: "/worksheet", label: "맞춤 문제지", icon: FileText },
    { href: "/practice", label: "온라인 풀이", icon: PlayCircle },
    { href: "/history", label: "학습 기록", icon: HistoryIcon },
    { href: "/problem-bank", label: "문제 유형", icon: Layers },
    { href: "/settings", label: "설정", icon: SettingsIcon },
  ];

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

          <div className="flex items-center gap-2 text-xs text-slate-600 bg-slate-50 px-3 py-1.5 rounded-full border border-slate-200">
            <span className="w-2 h-2 rounded-full bg-emerald-500"></span>
            <span className="font-semibold text-slate-900">홍길동</span>
            <span className="text-slate-400">|</span>
            <span>초등학교 5학년</span>
          </div>
        </div>

        {/* 모바일 하단 바 */}
        <div className="md:hidden flex items-center justify-between py-2 border-t border-slate-100 overflow-x-auto gap-2">
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
                className={`flex flex-col items-center py-1 px-2 rounded text-[11px] whitespace-nowrap ${
                  isActive ? "text-slate-900 font-bold" : "text-slate-500"
                }`}
              >
                <Icon className="w-4 h-4 mb-0.5" />
                {item.label}
              </Link>
            );
          })}
        </div>
      </div>
    </header>
  );
}
