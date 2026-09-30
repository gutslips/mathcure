import { prisma } from "./prisma";

export async function getOrCreateDefaultStudent() {
  let student = await prisma.student.findFirst({
    orderBy: { createdAt: "asc" },
  });

  if (!student) {
    student = await prisma.student.create({
      data: {
        name: "홍길동",
        grade: 5,
      },
    });
  }

  return student;
}
