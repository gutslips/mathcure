import { prisma } from "./prisma";
import { cookies } from "next/headers";

export const STUDENT_COOKIE_NAME = "mathcure_active_student_id";

/**
 * 전체 등록된 학생 목록 조회
 */
export async function getAllStudents() {
  const students = await prisma.student.findMany({
    orderBy: { createdAt: "asc" },
  });

  if (students.length === 0) {
    const defaultStudent = await prisma.student.create({
      data: {
        name: "홍길동",
        grade: 5,
      },
    });
    return [defaultStudent];
  }

  return students;
}

/**
 * 현재 활성화된 학생 조회 (쿠키 기반)
 */
export async function getActiveStudent() {
  const cookieStore = await cookies();
  const activeId = cookieStore.get(STUDENT_COOKIE_NAME)?.value;

  if (activeId) {
    const found = await prisma.student.findUnique({
      where: { id: activeId },
    });
    if (found) return found;
  }

  // 쿠키가 없거나 유효하지 않으면 첫 번째 학생 반환
  const all = await getAllStudents();
  return all[0];
}

/**
 * 이전 호환용 함수
 */
export async function getOrCreateDefaultStudent() {
  return getActiveStudent();
}

/**
 * 새 학생 등록
 */
export async function createStudent(data: { name: string; grade: number }) {
  const created = await prisma.student.create({
    data: {
      name: data.name.trim() || "학생",
      grade: Number(data.grade) || 5,
    },
  });
  return created;
}

/**
 * 학생 정보 수정
 */
export async function updateStudent(id: string, data: { name?: string; grade?: number }) {
  return prisma.student.update({
    where: { id },
    data: {
      ...(data.name ? { name: data.name.trim() } : {}),
      ...(data.grade ? { grade: Number(data.grade) } : {}),
    },
  });
}

/**
 * 특정 학생의 데이터만 초기화 (다른 학생 데이터 보존)
 */
export async function resetStudentData(studentId: string) {
  await prisma.$transaction([
    prisma.diagnosisAnswer.deleteMany({
      where: { diagnosis: { studentId } },
    }),
    prisma.diagnosis.deleteMany({
      where: { studentId },
    }),
    prisma.practiceSession.deleteMany({
      where: { studentId },
    }),
    prisma.worksheet.deleteMany({
      where: { studentId },
    }),
  ]);
}

/**
 * 학생 삭제 (단, 마지막 남은 1명은 삭제 불가)
 */
export async function deleteStudent(studentId: string) {
  const count = await prisma.student.count();
  if (count <= 1) {
    throw new Error("최소 한 명의 학생은 유지되어야 합니다.");
  }

  await resetStudentData(studentId);
  return prisma.student.delete({
    where: { id: studentId },
  });
}
