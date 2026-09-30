<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/student.php';

$pdo = get_db();
$active_student = get_active_student($pdo);
$all_students = get_all_students($pdo);
$current_page = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
?>
<!DOCTYPE html>
<html lang="ko" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>초5 연산 트레이너 | 나눗셈 계산 진단 &amp; 맞춤형 훈련</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
            .print-page { page-break-after: always; break-after: page; }
            .print-page:last-child { page-break-after: auto; break-after: auto; }
            @page { size: A4 portrait; margin: 15mm 15mm 15mm 15mm; }
        }
    </style>
</head>
<body class="min-h-full flex flex-col bg-slate-50 text-slate-900 font-sans">
    <header class="no-print bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- 로고 -->
                <div class="flex items-center gap-3">
                    <a href="index.php" class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center font-bold text-sm tracking-wider">
                            MC
                        </span>
                        <div>
                            <span class="font-bold text-slate-900 text-base block leading-tight">초5 연산 트레이너</span>
                            <span class="text-[11px] text-slate-500 block">나눗셈 진단 &amp; 맞춤 훈련</span>
                        </div>
                    </a>
                </div>

                <!-- 데스크톱 네비게이션 -->
                <nav class="hidden md:flex items-center gap-1">
                    <a href="index.php" class="px-3 py-1.5 rounded-md text-sm font-medium transition <?php echo $current_page === 'index.php' ? 'bg-slate-100 text-slate-900 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'; ?>">
                        대시보드
                    </a>
                    <a href="diagnosis.php" class="px-3 py-1.5 rounded-md text-sm font-medium transition <?php echo in_array($current_page, ['diagnosis.php', 'diagnosis_result.php']) ? 'bg-slate-100 text-slate-900 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'; ?>">
                        연산 진단
                    </a>
                    <a href="worksheet.php" class="px-3 py-1.5 rounded-md text-sm font-medium transition <?php echo $current_page === 'worksheet.php' ? 'bg-slate-100 text-slate-900 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'; ?>">
                        맞춤 문제지
                    </a>
                    <a href="practice.php" class="px-3 py-1.5 rounded-md text-sm font-medium transition <?php echo $current_page === 'practice.php' ? 'bg-slate-100 text-slate-900 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'; ?>">
                        온라인 풀이
                    </a>
                    <a href="history.php" class="px-3 py-1.5 rounded-md text-sm font-medium transition <?php echo $current_page === 'history.php' ? 'bg-slate-100 text-slate-900 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'; ?>">
                        학습 기록
                    </a>
                    <a href="problem_bank.php" class="px-3 py-1.5 rounded-md text-sm font-medium transition <?php echo $current_page === 'problem_bank.php' ? 'bg-slate-100 text-slate-900 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'; ?>">
                        문제 유형
                    </a>
                    <a href="settings.php" class="px-3 py-1.5 rounded-md text-sm font-medium transition <?php echo $current_page === 'settings.php' ? 'bg-slate-100 text-slate-900 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'; ?>">
                        설정
                    </a>
                </nav>

                <!-- 학생 전환 선택 드롭다운 -->
                <div class="relative">
                    <button id="student-dropdown-btn" onclick="toggleStudentMenu()" class="flex items-center gap-2 text-xs text-slate-700 bg-slate-50 hover:bg-slate-100 px-3 py-1.5 rounded-full border border-slate-200 transition">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span class="font-bold text-slate-900"><?php echo htmlspecialchars($active_student['name']); ?></span>
                        <span class="text-slate-400">|</span>
                        <span>초<?php echo $active_student['grade']; ?></span>
                        <svg class="w-3.5 h-3.5 text-slate-400 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"/></svg>
                    </button>

                    <!-- 드롭다운 메뉴 -->
                    <div id="student-dropdown-menu" class="hidden absolute right-0 mt-2 w-56 bg-white border border-slate-200 rounded-xl shadow-lg py-2 z-50">
                        <div class="px-3 py-1.5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                            학생 선택 및 전환
                        </div>
                        <div class="max-h-48 overflow-y-auto">
                            <?php foreach ($all_students as $std): ?>
                                <button onclick="switchStudent('<?php echo $std['id']; ?>')" class="w-full text-left px-3 py-2 text-xs flex items-center justify-between hover:bg-slate-50 <?php echo $std['id'] === $active_student['id'] ? 'font-bold text-slate-900 bg-slate-50' : 'text-slate-600'; ?>">
                                    <span><?php echo htmlspecialchars($std['name']); ?> (초<?php echo $std['grade']; ?>)</span>
                                    <?php if ($std['id'] === $active_student['id']): ?>
                                        <span class="text-emerald-600 text-xs">✓ 선택됨</span>
                                    <?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <div class="pt-1 mt-1 border-t border-slate-100 px-2">
                            <a href="settings.php" class="block w-full text-center py-1.5 text-xs text-slate-600 hover:text-slate-900 rounded bg-slate-50 font-medium">
                                + 새 학생 등록 / 관리
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 모바일 네비게이션 가로 스크롤 -->
            <div class="md:hidden flex items-center justify-between py-2 border-t border-slate-100 overflow-x-auto gap-1 text-xs">
                <a href="index.php" class="py-1 px-2.5 rounded whitespace-nowrap <?php echo $current_page === 'index.php' ? 'font-bold text-slate-900 bg-slate-100' : 'text-slate-600'; ?>">대시보드</a>
                <a href="diagnosis.php" class="py-1 px-2.5 rounded whitespace-nowrap <?php echo $current_page === 'diagnosis.php' ? 'font-bold text-slate-900 bg-slate-100' : 'text-slate-600'; ?>">연산 진단</a>
                <a href="worksheet.php" class="py-1 px-2.5 rounded whitespace-nowrap <?php echo $current_page === 'worksheet.php' ? 'font-bold text-slate-900 bg-slate-100' : 'text-slate-600'; ?>">맞춤 문제지</a>
                <a href="practice.php" class="py-1 px-2.5 rounded whitespace-nowrap <?php echo $current_page === 'practice.php' ? 'font-bold text-slate-900 bg-slate-100' : 'text-slate-600'; ?>">온라인 풀이</a>
                <a href="history.php" class="py-1 px-2.5 rounded whitespace-nowrap <?php echo $current_page === 'history.php' ? 'font-bold text-slate-900 bg-slate-100' : 'text-slate-600'; ?>">학습 기록</a>
                <a href="settings.php" class="py-1 px-2.5 rounded whitespace-nowrap <?php echo $current_page === 'settings.php' ? 'font-bold text-slate-900 bg-slate-100' : 'text-slate-600'; ?>">설정</a>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8">
