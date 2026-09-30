import { NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { getOrCreateDefaultStudent } from "@/lib/student";
import { generateWorksheet } from "@/lib/worksheet/generator";
import { WorksheetConfig } from "@/types/worksheet";

export async function POST(request: Request) {
  try {
    const config: WorksheetConfig = await request.json();
    const student = await getOrCreateDefaultStudent();

    const worksheet = generateWorksheet({
      ...config,
      studentName: config.studentName || student.name,
      grade: config.grade || student.grade,
    });

    // DB에 기록
    const record = await prisma.worksheet.create({
      data: {
        studentId: student.id,
        title: worksheet.title,
        subject: "나눗셈",
        grade: worksheet.grade,
        difficulty: worksheet.difficulty,
        problemType: worksheet.targetType,
        count: worksheet.totalCount,
        seed: worksheet.seed,
      },
    });

    return NextResponse.json({
      success: true,
      worksheetId: record.id,
      worksheet,
    });
  } catch (error) {
    console.error("Failed to generate worksheet:", error);
    return NextResponse.json(
      { success: false, error: "문제지 생성에 실패했습니다." },
      { status: 500 }
    );
  }
}
