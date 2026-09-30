import { NextResponse } from "next/server";
import { generateDiagnosisProblems } from "@/lib/generators";

export async function POST(request: Request) {
  try {
    const body = await request.json().catch(() => ({}));
    const seed = body.seed || `diag_${Date.now()}`;
    const problems = generateDiagnosisProblems(seed);

    return NextResponse.json({
      success: true,
      seed,
      problems,
      timeLimitSeconds: 300, // 5분
    });
  } catch (error) {
    console.error("Failed to generate diagnosis problems:", error);
    return NextResponse.json(
      { success: false, error: "진단 문제 생성에 실패했습니다." },
      { status: 500 }
    );
  }
}
