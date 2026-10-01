import { NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { getActiveStudent } from "@/lib/student";

import { generateWorksheet } from "@/lib/worksheet/generator";
import { ProblemType } from "@/types/problem";

export async function GET(request: Request) {
  try {
    const { searchParams } = new URL(request.url);
    const id = searchParams.get("id");

    if (id) {
      const worksheet = await prisma.worksheet.findUnique({
        where: { id },
      });
      if (!worksheet) {
        return NextResponse.json({ success: false, error: "문제지를 찾을 수 없습니다." }, { status: 404 });
      }

      let parsedProblems = worksheet.problems ? JSON.parse(worksheet.problems) : [];

      if (!parsedProblems || parsedProblems.length === 0) {
        const generated = generateWorksheet({
          studentName: "학생",
          grade: 5,
          subject: worksheet.subject || "나눗셈",
          targetType: (worksheet.problemType as ProblemType) || "divide5",
          count: worksheet.count || 20,
          difficulty: worksheet.difficulty || 2,
          seed: worksheet.seed,
        });
        parsedProblems = generated.problems;
        await prisma.worksheet.update({
          where: { id },
          data: { problems: JSON.stringify(parsedProblems) },
        });
      }

      return NextResponse.json({
        success: true,
        worksheet: {
          ...worksheet,
          problems: parsedProblems,
        },
      });
    }

    const worksheets = await prisma.worksheet.findMany({
      orderBy: { createdAt: "desc" },
      include: {
        exams: {
          select: {
            id: true,
            code: true,
            status: true,
            studentName: true,
            score: true,
            totalCount: true,
          },
        },
      },
    });

    const parsed = worksheets.map((w) => ({
      ...w,
      problemCount: w.count,
      hasProblems: !!w.problems,
    }));

    return NextResponse.json({ success: true, worksheets: parsed });
  } catch (error) {
    console.error("Failed to fetch worksheets:", error);
    return NextResponse.json({ success: false, error: "문제지 조회 실패" }, { status: 500 });
  }
}

export async function POST(request: Request) {
  try {
    const body = await request.json();
    const { 
      title, 
      studentName, 
      difficulty = 2, 
      problemType = "divide5", 
      count = 20, 
      seed = "", 
      problems = [] 
    } = body;

    const activeStudent = await getActiveStudent();

    const created = await prisma.worksheet.create({
      data: {
        studentId: activeStudent?.id || null,
        title: title || `${studentName ? studentName + "의 " : ""}맞춤 훈련지`,
        subject: "나눗셈",
        grade: 5,
        difficulty: Number(difficulty) || 2,
        problemType: String(problemType),
        count: Number(count) || problems.length,
        seed: String(seed || `seed_${Date.now()}`),
        problems: JSON.stringify(problems),
      },
    });

    return NextResponse.json({
      success: true,
      worksheet: {
        ...created,
        problems,
      },
    });
  } catch (error) {
    console.error("Failed to save worksheet:", error);
    return NextResponse.json({ success: false, error: "문제지 저장 실패" }, { status: 500 });
  }
}

export async function DELETE(request: Request) {
  try {
    const { searchParams } = new URL(request.url);
    const id = searchParams.get("id");

    if (!id) {
      return NextResponse.json({ success: false, error: "ID가 필요합니다." }, { status: 400 });
    }

    await prisma.worksheet.delete({
      where: { id },
    });

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error("Failed to delete worksheet:", error);
    return NextResponse.json({ success: false, error: "문제지 삭제 실패" }, { status: 500 });
  }
}
