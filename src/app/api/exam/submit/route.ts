import { NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";

export async function POST(request: Request) {
  try {
    const body = await request.json();
    const { code, studentName, answers = [] } = body;

    if (!code) {
      return NextResponse.json({ success: false, error: "시험 코드가 누락되었습니다." }, { status: 400 });
    }

    const exam = await prisma.exam.findUnique({
      where: { code },
    });

    if (!exam) {
      return NextResponse.json({ success: false, error: "유효하지 않은 시험입니다." }, { status: 404 });
    }

    const originalProblems: any[] = JSON.parse(exam.problems || "[]");
    let correctCount = 0;
    const evaluatedAnswers: any[] = [];

    originalProblems.forEach((prob, index) => {
      const userSubmission = answers.find((a: any) => a.problemId === prob.id || a.index === index);
      const userAns = userSubmission?.userAnswer !== undefined && userSubmission?.userAnswer !== "" 
        ? Number(userSubmission.userAnswer) 
        : null;
      const isCorrect = userAns !== null && userAns === prob.answer;
      if (isCorrect) correctCount++;

      evaluatedAnswers.push({
        id: prob.id,
        index: index + 1,
        question: prob.question,
        correctAnswer: prob.answer,
        userAnswer: userAns,
        isCorrect,
        type: prob.type,
        difficulty: prob.difficulty,
        elapsedMs: userSubmission?.elapsedMs || 0,
      });
    });

    const totalCount = originalProblems.length;
    const score = totalCount > 0 ? Math.round((correctCount / totalCount) * 100) : 0;
    const finalStudentName = studentName?.trim() || exam.studentName || "학생";

    // 시험 결과 업데이트
    const updatedExam = await prisma.exam.update({
      where: { code },
      data: {
        studentName: finalStudentName,
        status: "completed",
        score,
        totalCount,
        results: JSON.stringify(evaluatedAnswers),
        submittedAt: new Date(),
      },
    });

    return NextResponse.json({
      success: true,
      score,
      totalCount,
      correctCount,
      evaluatedAnswers: exam.showResult ? evaluatedAnswers : [],
      showResult: exam.showResult,
      submittedAt: updatedExam.submittedAt,
    });
  } catch (error) {
    console.error("Failed to submit exam:", error);
    return NextResponse.json({ success: false, error: "시험 제출 처리 실패" }, { status: 500 });
  }
}
