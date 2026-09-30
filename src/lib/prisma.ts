import { PrismaClient } from "@prisma/client";
import fs from "fs";
import path from "path";

const globalForPrisma = globalThis as unknown as { prisma: PrismaClient | undefined };

function getDatabaseUrl(): string {
  // If running in Vercel serverless environment
  if (process.env.VERCEL) {
    const tmpDbPath = path.join("/tmp", "dev.db");

    const templateDbPath = path.join(process.cwd(), "prisma", "template.db");

    if (!fs.existsSync(tmpDbPath)) {
      if (fs.existsSync(templateDbPath)) {
        try {
          fs.copyFileSync(templateDbPath, tmpDbPath);
          console.log(`[Prisma] Initialized SQLite DB from template to ${tmpDbPath}`);
        } catch (e) {
          console.error("[Prisma] Failed to copy SQLite DB from template:", e);
        }
      } else {
        console.warn(`[Prisma] Template DB not found at ${templateDbPath}`);
        try {
          fs.writeFileSync(tmpDbPath, "");
        } catch (e) {
          console.error("[Prisma] Failed to create empty /tmp/dev.db:", e);
        }
      }
    }

    return `file:${tmpDbPath}`;
  }

  return process.env.DATABASE_URL || "file:./dev.db";
}

const dbUrl = getDatabaseUrl();
process.env.DATABASE_URL = dbUrl;

export const prisma =
  globalForPrisma.prisma ??
  new PrismaClient({
    datasources: {
      db: {
        url: dbUrl,
      },
    },
    log: process.env.NODE_ENV === "development" ? ["error", "warn"] : ["error"],
  });

if (process.env.NODE_ENV !== "production") globalForPrisma.prisma = prisma;

