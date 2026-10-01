import { NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";

function generateExamCode(): string {
  // 6자리 난수 코드 (예: 742918)
  return Math.floor(100000 + Math.random() * 900000).toString();
}

export async function GET(request: Request) {
  try {
    const { searchParams } = new URL(request.url);
    const code = searchParams.get("code");
    const id = searchParams.get("id");
    const worksheetId = searchParams.get("worksheetId");
    const forTeacher = searchParams.get("role") === "teacher";

    // 1. 특정 시험 조회 (코드 또는 ID)
    if (code || id) {
      const exam = await prisma.exam.findFirst({
        where: code ? { code } : { id: id! },
        include: {
          worksheet: true,
        },
      });

      if (!exam) {
        return NextResponse.json({ success: false, error: "시험을 찾을 수 없습니다." }, { status: 404 });
      }

      const parsedProblems = JSON.parse(exam.problems || "[]");

      // 학생에게 제공할 때는 정답(answer) 및 해설(explanation)을 숨김 (부정행위 방지)
      const sanitizedProblems = forTeacher || exam.status === "completed" 
        ? parsedProblems 
        : parsedProblems.map((p: any) => ({
            id: p.id,
            question: p.question,
            type: p.type,
            difficulty: p.difficulty,
          }));

      return NextResponse.json({
        success: true,
        exam: {
          id: exam.id,
          code: exam.code,
          title: exam.title,
          studentName: exam.studentName,
          timeLimitSec: exam.timeLimitSec,
          status: exam.status,
          showResult: exam.showResult,
          score: exam.score,
          totalCount: exam.totalCount || parsedProblems.length,
          results: exam.results ? JSON.parse(exam.results) : null,
          startedAt: exam.startedAt,
          submittedAt: exam.submittedAt,
          createdAt: exam.createdAt,
          problems: sanitizedProblems,
        },
      });
    }

    // 2. 워크시트별 또는 전체 시험 목록 (교사용)
    const where = worksheetId ? { worksheetId } : {};
    const exams = await prisma.exam.findMany({
      where,
      orderBy: { createdAt: "desc" },
    });

    return NextResponse.json({
      success: true,
      exams: exams.map((e) => ({
        ...e,
        results: e.results ? JSON.parse(e.results) : null,
      })),
    });
  } catch (error) {
    console.error("Failed to query exam:", error);
    return NextResponse.json({ success: false, error: "시험 조회 실패" }, { status: 500 });
  }
}

export async function POST(request: Request) {
  try {
    const body = await request.json();
    const {
      title,
      worksheetId,
      studentName,
      timeLimitSec = 600,
      showResult = true,
      problems = [],
    } = body;

    if (!problems || problems.length === 0) {
      return NextResponse.json({ success: false, error: "문제가 포함되지 않았습니다." }, { status: 400 });
    }

    // 유니크한 6자리 코드 생성
    let code = generateExamCode();
    let exists = await prisma.exam.findUnique({ where: { code } });
    let attempts = 0;
    while (exists && attempts < 5) {
      code = generateExamCode();
      exists = await prisma.exam.findUnique({ where: { code } });
      attempts++;
    }

    const exam = await prisma.exam.create({
      data: {
        code,
        title: title || "초5 나눗셈 온라인 시험",
        worksheetId: worksheetId || null,
        studentName: studentName?.trim() || null,
        timeLimitSec: Number(timeLimitSec) || 0,
        status: "active",
        problems: JSON.stringify(problems),
        showResult: Boolean(showResult),
        totalCount: problems.length,
      },
    });

    return NextResponse.json({
      success: true,
      exam: {
        id: exam.id,
        code: exam.code,
        title: exam.title,
        timeLimitSec: exam.timeLimitSec,
        totalCount: exam.totalCount,
      },
    });
  } catch (error) {
    console.error("Failed to create exam:", error);
    return NextResponse.json({ success: false, error: "시험 생성 실패" }, { status: 500 });
  }
}
