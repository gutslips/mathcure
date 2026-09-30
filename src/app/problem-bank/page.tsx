"use client";

import { useState } from "react";
import { ProblemType, PROBLEM_TYPE_LABELS } from "@/types/problem";
import { generateProblemsByType } from "@/lib/generators";
import { RefreshCw } from "lucide-react";

export default function ProblemBankPage() {
  const [selectedType, setSelectedType] = useState<ProblemType>("divide5");
  const [difficulty, setDifficulty] = useState(2);
  const [sampleProblems, setSampleProblems] = useState(() =>
    generateProblemsByType("divide5", 6, 2)
  );

  const handleRefresh = (t: ProblemType, d: number) => {
    setSelectedType(t);
    setDifficulty(d);
    setSampleProblems(generateProblemsByType(t, 6, d));
  };

  const typesMeta = [
    {
      type: "basic" as ProblemType,
      title: "TYPE A — 기본 나눗셈",
      desc: "구구단과 나눗셈 사실의 역연산 관계 자동화",
      examples: "24 ÷ 4, 35 ÷ 5, 48 ÷ 6, 72 ÷ 8, 90 ÷ 10",
      strategy: "구구단을 거꾸로 곱하는 사실을 완전히 암산화합니다.",
    },
    {
      type: "divide2" as ProblemType,
      title: "TYPE B1 — ÷2 자동화",
      desc: "절반 구하기 암산 전략",
      examples: "240 ÷ 2, 450 ÷ 2, 860 ÷ 2, 1,200 ÷ 2",
      strategy: "어떤 수의 반토막을 신속하게 쪼개는 기본 분할 전략입니다.",
    },
    {
      type: "divide4" as ProblemType,
      title: "TYPE B2 — ÷4 자동화",
      desc: "절반의 절반 나누기 전략",
      examples: "240 ÷ 4, 320 ÷ 4, 480 ÷ 4, 1,200 ÷ 4",
      strategy: "2로 나누고 한 번 더 2로 나누어 복잡한 나눗셈을 단순화합니다.",
    },
    {
      type: "divide5" as ProblemType,
      title: "TYPE B3 — ÷5 자동화 (핵심)",
      desc: "2배 후 10 나누기(자리값) 전략",
      examples: "800 ÷ 5, 1,200 ÷ 5, 2,500 ÷ 5, 8,000 ÷ 5",
      strategy: "어떤 수에 2를 곱하고 끝의 0을 제거하면 암산 속도가 3배 향상됩니다.",
    },
    {
      type: "divide8" as ProblemType,
      title: "TYPE B4 — ÷8 자동화",
      desc: "반의 반의 반 전략 (÷2 세 번)",
      examples: "480 ÷ 8, 800 ÷ 8, 1,600 ÷ 8, 6,400 ÷ 8",
      strategy: "8로 직접 나누기보다 2로 세 번 나누는 사고를 훈련합니다.",
    },
    {
      type: "largeNumber" as ProblemType,
      title: "TYPE C — 큰 수 자리값",
      desc: "천 단위 및 0의 개수 자리값 처리",
      examples: "1,200 ÷ 4, 2,500 ÷ 5, 3,200 ÷ 8, 8,000 ÷ 5",
      strategy: "앞의 유효 자릿수를 나누고 0의 개수를 오차 없이 배치하는 자리값 감각을 기릅니다.",
    },
    {
      type: "mixed" as ProblemType,
      title: "TYPE D — 초5 교과 혼합",
      desc: "실제 학교 시험 및 교과서 종합 연산",
      examples: "6,400 ÷ 8, 7,200 ÷ 9, 4,500 ÷ 5, 9,600 ÷ 4",
      strategy: "여러 나눗셈 전략을 직관적으로 선택하여 빠르게 해결하는 종합 응용력입니다.",
    },
  ];

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">문제 유형 및 생성 엔진 뱅크</h1>
        <p className="text-sm text-slate-600 mt-1">
          초5 나눗셈 진단 엔진에 탑재된 문제 유형 규격과 규칙을 확인하고 즉시 생성해볼 수 있습니다.
        </p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* 유형 카드 목록 */}
        <div className="lg:col-span-2 space-y-4">
          {typesMeta.map((meta) => {
            const isSelected = selectedType === meta.type;

            return (
              <div
                key={meta.type}
                onClick={() => handleRefresh(meta.type, difficulty)}
                className={`p-5 rounded-xl border transition cursor-pointer ${
                  isSelected
                    ? "bg-white border-slate-900 shadow-sm ring-1 ring-slate-900"
                    : "bg-white border-slate-200 hover:border-slate-300"
                }`}
              >
                <div className="flex items-center justify-between mb-2">
                  <span className="font-bold text-slate-900 text-base">
                    {meta.title}
                  </span>
                  <span className="text-xs px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 font-medium">
                    {PROBLEM_TYPE_LABELS[meta.type]}
                  </span>
                </div>
                <p className="text-xs text-slate-600 mb-2">{meta.desc}</p>
                <div className="text-xs font-mono text-slate-800 bg-slate-50 p-2.5 rounded-lg border border-slate-200">
                  대표 예시: {meta.examples}
                </div>
                <p className="text-[11px] text-slate-500 mt-2">
                  💡 {meta.strategy}
                </p>
              </div>
            );
          })}
        </div>

        {/* 실시간 생성 시연 패널 */}
        <div className="bg-white border border-slate-200 rounded-xl p-6 h-fit sticky top-24">
          <div className="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <div>
              <span className="text-xs font-bold text-slate-400 block uppercase">
                실시간 생성기
              </span>
              <h3 className="font-bold text-slate-900 text-lg">
                {PROBLEM_TYPE_LABELS[selectedType]}
              </h3>
            </div>
            <button
              onClick={() => handleRefresh(selectedType, difficulty)}
              className="p-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition"
              title="새로고침"
            >
              <RefreshCw className="w-4 h-4" />
            </button>
          </div>

          <div className="space-y-3 mb-6">
            <label className="block text-xs font-semibold text-slate-600">
              난이도 조정
            </label>
            <div className="grid grid-cols-3 gap-2">
              {[1, 2, 3].map((lvl) => (
                <button
                  key={lvl}
                  onClick={() => handleRefresh(selectedType, lvl)}
                  className={`py-1.5 text-xs font-semibold rounded-lg border transition ${
                    difficulty === lvl
                      ? "bg-slate-900 text-white border-slate-900"
                      : "bg-white border-slate-200 text-slate-600 hover:bg-slate-50"
                  }`}
                >
                  Level {lvl}
                </button>
              ))}
            </div>
          </div>

          <div className="space-y-2">
            <span className="text-xs font-bold text-slate-500 block mb-2">
              생성된 6문제 샘플 (정답 포함)
            </span>
            {sampleProblems.map((p) => (
              <div
                key={p.id}
                className="p-3 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between text-sm font-mono"
              >
                <span className="font-bold text-slate-800">{p.question}</span>
                <span className="font-bold text-slate-900 bg-white px-2 py-0.5 rounded border border-slate-300 text-xs">
                  = {p.answer}
                </span>
              </div>
            ))}
          </div>

          <p className="text-[11px] text-slate-400 mt-4 leading-relaxed">
            * 100만 문제를 DB에 고정 저장하지 않고 규칙 기반 Generator 알고리즘으로 동적 생성합니다 (명세 17).
          </p>
        </div>
      </div>
    </div>
  );
}
