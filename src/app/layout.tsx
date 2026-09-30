import type { Metadata } from "next";
import "./globals.css";
import { Navbar } from "@/components/ui/Navbar";

export const metadata: Metadata = {
  title: "초5 연산 트레이너 | 나눗셈 계산 진단 & 맞춤형 연산 훈련",
  description: "초등학교 5학년 학생의 나눗셈 계산 수준 진단, 속도 측정, 오답 분석 및 개인별 맞춤 훈련지 생성 웹앱",
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="ko" className="h-full">
      <body className="min-h-full flex flex-col bg-slate-50 text-slate-900 font-sans">
        <Navbar />
        <main className="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8">
          {children}
        </main>
      </body>
    </html>
  );
}
