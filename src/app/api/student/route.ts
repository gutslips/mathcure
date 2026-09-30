import { NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { getOrCreateDefaultStudent } from "@/lib/student";

export async function GET() {
  const student = await getOrCreateDefaultStudent();
  return NextResponse.json({ success: true, student });
}

export async function POST(request: Request) {
  try {
    const { name, grade, resetData } = await request.json();
    const student = await getOrCreateDefaultStudent();

    if (resetData) {
      // 진단 및 연습 기록 초기화
      await prisma.diagnosisAnswer.deleteMany({
        where: { diagnosis: { studentId: student.id } },
      });
      await prisma.diagnosis.deleteMany({
        where: { studentId: student.id },
      });
      await prisma.practiceSession.deleteMany({
        where: { studentId: student.id },
      });
      await prisma.worksheet.deleteMany({
        where: { studentId: student.id },
      });
    }

    const updated = await prisma.student.update({
      where: { id: student.id },
      data: {
        name: name || student.name,
        grade: Number(grade) || student.grade,
      },
    });

    return NextResponse.json({ success: true, student: updated });
  } catch (error) {
    console.error("Failed to update student settings:", error);
    return NextResponse.json(
      { success: false, error: "설정 변경에 실패했습니다." },
      { status: 500 }
    );
  }
}
