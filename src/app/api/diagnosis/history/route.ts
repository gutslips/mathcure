import { NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { getOrCreateDefaultStudent } from "@/lib/student";

export async function GET() {
  try {
    const student = await getOrCreateDefaultStudent();

    const diagnoses = await prisma.diagnosis.findMany({
      where: { studentId: student.id },
      orderBy: { finishedAt: "desc" },
      include: {
        answers: true,
      },
      take: 20,
    });

    return NextResponse.json({
      success: true,
      student,
      diagnoses,
    });
  } catch (error) {
    console.error("Failed to load diagnosis history:", error);
    return NextResponse.json(
      { success: false, error: "학습 기록 조회에 실패했습니다." },
      { status: 500 }
    );
  }
}
