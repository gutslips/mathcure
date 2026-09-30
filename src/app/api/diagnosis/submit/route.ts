import { NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { getActiveStudent } from "@/lib/student";
import { analyzeDiagnosis } from "@/lib/diagnosis/analyzer";
import { DiagnosisAnswerInput } from "@/types/diagnosis";

export async function POST(request: Request) {
  try {
    const body = await request.json();
    const { answers, startedAt, finishedAt, studentId } = body;

    if (!Array.isArray(answers) || answers.length === 0) {
      return NextResponse.json(
        { success: false, error: "제출된 답안이 없습니다." },
        { status: 400 }
      );
    }

    let student = null;
    if (studentId) {
      student = await prisma.student.findUnique({ where: { id: studentId } });
    }
    if (!student) {
      student = await getActiveStudent();
    }

    // 채점 및 답안 표준화
    const scoredAnswers: DiagnosisAnswerInput[] = answers.map((ans: Record<string, unknown>) => {
      const correct = Number(ans.userAnswer) === Number(ans.answer);
      return {
        problemId: String(ans.problemId || `p_${Math.random()}`),
        question: String(ans.question),
        dividend: Number(ans.dividend),
        divisor: Number(ans.divisor),
        answer: Number(ans.answer),
        userAnswer: ans.userAnswer !== null && ans.userAnswer !== undefined ? Number(ans.userAnswer) : null,
        correct,
        elapsedMs: Math.max(100, Number(ans.elapsedMs) || 1000),
        type: ans.type as DiagnosisAnswerInput["type"],
        difficulty: Number(ans.difficulty) || 2,
        usedHint: Boolean(ans.usedHint),
      };
    });

    // 분석기 실행
    const analysis = analyzeDiagnosis(scoredAnswers);

    // DB 저장
    const startDate = startedAt ? new Date(startedAt) : new Date(Date.now() - 300000);
    const finishDate = finishedAt ? new Date(finishedAt) : new Date();

    const diagnosisRecord = await prisma.diagnosis.create({
      data: {
        studentId: student.id,
        total: analysis.total,
        correct: analysis.correct,
        accuracy: analysis.accuracy,
        averageMs: analysis.averageMs,
        startedAt: startDate,
        finishedAt: finishDate,
        answers: {
          create: scoredAnswers.map((a) => ({
            question: a.question,
            answer: a.answer,
            userAnswer: a.userAnswer,
            correct: a.correct,
            elapsedMs: a.elapsedMs,
            type: a.type,
            difficulty: a.difficulty,
          })),
        },
      },
      include: {
        answers: true,
      },
    });

    return NextResponse.json({
      success: true,
      diagnosisId: diagnosisRecord.id,
      analysis,
    });
  } catch (error) {
    console.error("Failed to submit diagnosis:", error);
    return NextResponse.json(
      { success: false, error: "진단 결과 저장 및 분석에 실패했습니다." },
      { status: 500 }
    );
  }
}
