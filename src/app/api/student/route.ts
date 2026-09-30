import { NextResponse } from "next/server";
import { cookies } from "next/headers";
import { 
  getActiveStudent, 
  getAllStudents, 
  createStudent, 
  updateStudent, 
  deleteStudent, 
  resetStudentData,
  STUDENT_COOKIE_NAME 
} from "@/lib/student";

export async function GET() {
  try {
    const activeStudent = await getActiveStudent();
    const students = await getAllStudents();

    return NextResponse.json({
      success: true,
      activeStudent,
      student: activeStudent, // 하위 호환
      students,
    });
  } catch (error) {
    console.error("Failed to get student:", error);
    return NextResponse.json({ success: false, error: "학생 정보 조회 실패" }, { status: 500 });
  }
}

export async function POST(request: Request) {
  try {
    const body = await request.json();
    const { action, id, name, grade } = body;
    const cookieStore = await cookies();

    // 1. 학생 전환 (Switch Active Student)
    if (action === "switch") {
      if (!id) {
        return NextResponse.json({ success: false, error: "학생 ID가 필요합니다." }, { status: 400 });
      }
      cookieStore.set(STUDENT_COOKIE_NAME, id, { path: "/", maxAge: 60 * 60 * 24 * 365 });
      const students = await getAllStudents();
      const switched = students.find((s) => s.id === id) || students[0];
      return NextResponse.json({ success: true, activeStudent: switched });
    }

    // 2. 새 학생 등록 (Create New Student)
    if (action === "create") {
      if (!name || !name.trim()) {
        return NextResponse.json({ success: false, error: "학생 이름을 입력해주세요." }, { status: 400 });
      }
      const newStudent = await createStudent({ name, grade: Number(grade) || 5 });
      cookieStore.set(STUDENT_COOKIE_NAME, newStudent.id, { path: "/", maxAge: 60 * 60 * 24 * 365 });
      const students = await getAllStudents();
      return NextResponse.json({ success: true, activeStudent: newStudent, students });
    }

    // 3. 학생 데이터 초기화 (Reset Student Data)
    if (action === "reset" || body.resetData) {
      const activeStudent = await getActiveStudent();
      const targetId = id || activeStudent.id;
      await resetStudentData(targetId);
      return NextResponse.json({ success: true, message: "학생 데이터가 초기화되었습니다." });
    }

    // 4. 학생 삭제 (Delete Student)
    if (action === "delete") {
      if (!id) {
        return NextResponse.json({ success: false, error: "삭제할 학생 ID가 필요합니다." }, { status: 400 });
      }
      await deleteStudent(id);
      const remaining = await getAllStudents();
      cookieStore.set(STUDENT_COOKIE_NAME, remaining[0].id, { path: "/", maxAge: 60 * 60 * 24 * 365 });
      return NextResponse.json({ success: true, activeStudent: remaining[0], students: remaining });
    }

    // 5. 학생 정보 수정 (기본 액션)
    const activeStudent = await getActiveStudent();
    const targetId = id || activeStudent.id;
    const updated = await updateStudent(targetId, { name, grade: Number(grade) });

    return NextResponse.json({
      success: true,
      activeStudent: updated,
      student: updated,
    });
  } catch (error) {
    console.error("Failed to update student settings:", error);
    return NextResponse.json(
      { success: false, error: (error as Error).message || "설정 처리에 실패했습니다." },
      { status: 500 }
    );
  }
}
