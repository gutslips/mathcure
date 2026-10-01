<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/student.php';

$pdo = get_db();
$current_page = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');

// 인증 제외 페이지 목록
$public_pages = ['login.php', 'register.php', 'install.php', 'pending_approval.php'];

$logged_user = null;
if (!in_array($current_page, $public_pages)) {
    $logged_user = require_login($pdo);
    $active_student = get_active_student($pdo, $logged_user['id']);
    $all_students = get_all_students($pdo, $logged_user['id']);
} else {
    $logged_user = get_logged_in_user($pdo);
    $active_student = $logged_user ? get_active_student($pdo, $logged_user['id']) : null;
    $all_students = $logged_user ? get_all_students($pdo, $logged_user['id']) : [];
}

$pending_count = ($logged_user && $logged_user['role'] === 'admin') ? get_pending_users_count($pdo) : 0;
?>
<!DOCTYPE html>
<html lang="ko" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>초5 연산 트레이너 | 나눗셈 계산 진단 &amp; 맞춤형 훈련</title>

    <!-- PWA & Mobile Web App Meta Tags -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0f172a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="MathCure">
    <link rel="apple-touch-icon" href="icons/icon-192.png">
    <link rel="icon" type="image/svg+xml" href="icons/icon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="icons/icon-192.png">

    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Safe Area Padding for iOS Devices */
        .pb-safe { padding-bottom: max(0.75rem, env(safe-area-inset-bottom)); }
        
        /* 브라우저 기본 머리글/바닥글(URL, 날짜, 페이지 번호) 원천 제거: 여백 0 처리 */
        @page {
            size: A4 portrait;
            margin: 0 !important;
        }

        @media print {
            /* 브라우저 기본 요소 여백 초기화 */
            html, body {
                width: 210mm !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
                color: #0f172a !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .no-print {
                display: none !important;
            }

            /* 메인 레이아웃 패딩 초기화 (모바일 pb-24 등 밀림 방지) */
            main {
                max-width: 210mm !important;
                width: 210mm !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* A4 용지 규격 (210mm x 297mm) 정확한 1페이지 핏 */
            .a4-sheet {
                box-sizing: border-box !important;
                width: 210mm !important;
                max-width: 210mm !important;
                min-height: 285mm !important;
                max-height: 290mm !important;
                height: 287mm !important;
                padding: 13mm 16mm 9mm 16mm !important; /* 문서 자체 여백으로 안전 마진 확보 */
                margin: 0 auto !important;
                border: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                background: #ffffff !important;
                page-break-after: always !important;
                break-after: page !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                overflow: hidden !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: space-between !important;
            }

            .a4-sheet:last-of-type,
            .a4-sheet:last-child {
                page-break-after: auto !important;
                break-after: auto !important;
            }

            .page-break {
                display: none !important;
            }

            /* 문제지 헤더 영역 인쇄 최적화 (모바일에서도 가로 배치 유지) */
            .sheet-header {
                display: flex !important;
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: flex-end !important;
                border-bottom: 2px solid #0f172a !important;
                padding-bottom: 2.5mm !important;
                margin-bottom: 3.5mm !important;
            }

            .sheet-student-info {
                text-align: right !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: flex-end !important;
                gap: 1mm !important;
                font-size: 8.5pt !important;
            }

            /* 문제 리스트 (모바일 인쇄 시에도 무조건 2열 그리드 강제) */
            #problems-grid-container {
                display: grid !important;
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                column-gap: 12mm !important;
                row-gap: 4.5mm !important;
                margin-top: 1.5mm !important;
                margin-bottom: auto !important;
            }

            .print-problem-row {
                display: flex !important;
                align-items: baseline !important;
                justify-content: space-between !important;
                border-bottom: 1px solid #e2e8f0 !important;
                padding-bottom: 1.5mm !important;
                font-size: 14pt !important;
            }

            .print-problem-row .prob-num {
                font-size: 11pt !important;
                width: 7mm !important;
            }

            .print-problem-row .prob-eq {
                font-size: 15pt !important;
                font-weight: 700 !important;
            }

            /* 정답지 (2페이지) 그리드 인쇄 최적화 (4열) */
            #answers-grid-container {
                display: grid !important;
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
                gap: 2.5mm !important;
                margin-top: 3.5mm !important;
                margin-bottom: 3.5mm !important;
            }

            .print-answer-item {
                padding: 2mm 3mm !important;
                font-size: 10pt !important;
                border: 1px solid #cbd5e1 !important;
                background-color: #f8fafc !important;
                border-radius: 4px !important;
            }

            .sheet-tip-box {
                margin-top: 3mm !important;
                padding: 2.5mm 3.5mm !important;
                font-size: 8.5pt !important;
                line-height: 1.4 !important;
                border: 1px solid #e2e8f0 !important;
                background-color: #f8fafc !important;
                border-radius: 6px !important;
            }

            /* 문제지/정답지 하단 정보 바 */
            .sheet-footer {
                border-top: 1px solid #cbd5e1 !important;
                padding-top: 2.5mm !important;
                margin-top: auto !important;
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                font-size: 8.5pt !important;
                color: #64748b !important;
            }
        }
    </style>
</head>
<body class="min-h-full flex flex-col bg-slate-50 text-slate-900 font-sans antialiased">
    <!-- PWA 앱 설치 유도 배너 (조건 충족 시 모바일에서 표시) -->
    <div id="pwa-install-banner" class="no-print hidden bg-slate-900 text-white px-4 py-2.5 text-xs flex items-center justify-between shadow-md">
        <div class="flex items-center gap-2">
            <span class="w-6 h-6 rounded bg-emerald-500 text-slate-950 font-bold flex items-center justify-center text-[10px]">앱</span>
            <span>홈 화면에 추가하여 앱처럼 편리하게 이용하세요!</span>
        </div>
        <div class="flex items-center gap-2">
            <button id="pwa-install-btn" class="px-2.5 py-1 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold rounded text-[11px] transition">
                설치
            </button>
            <button onclick="dismissPwaBanner()" class="text-slate-400 hover:text-white px-1">✕</button>
        </div>
    </div>

    <!-- 상단 헤더 -->
    <header class="no-print bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-14 sm:h-16">
                <!-- 로고 -->
                <div class="flex items-center gap-2.5">
                    <a href="index.php" class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center font-bold text-sm tracking-wider shadow-xs">
                            MC
                        </span>
                        <div>
                            <span class="font-bold text-slate-900 text-sm sm:text-base block leading-tight">초5 연산 트레이너</span>
                            <span class="text-[10px] sm:text-[11px] text-slate-500 block">나눗셈 진단 &amp; 맞춤 훈련</span>
                        </div>
                    </a>
                </div>

                <?php if ($logged_user && $logged_user['status'] === 'approved'): ?>
                    <!-- 데스크톱 네비게이션 (태블릿/PC) -->
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
                            학생 관리
                        </a>

                        <?php if ($logged_user['role'] === 'admin'): ?>
                            <a href="admin.php" class="ml-2 inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-bold bg-amber-100 text-amber-900 hover:bg-amber-200 transition">
                                🛡️ 회원 관리
                                <?php if ($pending_count > 0): ?>
                                    <span class="px-1.5 py-0.2 rounded-full bg-rose-600 text-white text-[10px] font-bold">
                                        <?php echo $pending_count; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>
                    </nav>

                    <!-- 우측: 학생 전환 & 사용자 메뉴 -->
                    <div class="flex items-center gap-2 sm:gap-3">
                        <!-- 학생 선택 드롭다운 -->
                        <?php if ($active_student): ?>
                            <div class="relative">
                                <button id="student-dropdown-btn" onclick="toggleStudentMenu()" class="flex items-center gap-1.5 text-xs text-slate-700 bg-slate-50 hover:bg-slate-100 px-2.5 sm:px-3 py-1.5 rounded-full border border-slate-200 transition">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <span class="font-bold text-slate-900 max-w-[70px] sm:max-w-none truncate"><?php echo htmlspecialchars($active_student['name']); ?></span>
                                    <span class="text-slate-400">|</span>
                                    <span>초<?php echo $active_student['grade']; ?></span>
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"/></svg>
                                </button>

                                <div id="student-dropdown-menu" class="hidden absolute right-0 mt-2 w-56 bg-white border border-slate-200 rounded-xl shadow-lg py-2 z-50">
                                    <div class="px-3 py-1.5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                        내 학생 선택
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
                        <?php endif; ?>

                        <!-- 로그인 사용자 프로필 및 로그아웃 -->
                        <div class="flex items-center gap-2 pl-1.5 sm:pl-2 border-l border-slate-200 text-xs">
                            <a href="profile.php" class="text-slate-700 hover:text-slate-900 font-semibold hidden sm:inline" title="내 정보 / 비번변경">
                                <?php echo htmlspecialchars($logged_user['name']); ?>
                            </a>
                            <a href="logout.php" class="text-slate-400 hover:text-slate-600 p-1" title="로그아웃">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="flex items-center gap-2">
                        <a href="login.php" class="text-xs font-semibold px-3 py-1.5 rounded-lg text-slate-700 hover:bg-slate-100 transition">
                            로그인
                        </a>
                        <a href="register.php" class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-slate-900 text-white hover:bg-slate-800 transition">
                            회원가입
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- 모바일 하단 네비게이션 탭바 (md 이상에서는 숨김) -->
    <?php if ($logged_user && $logged_user['status'] === 'approved'): ?>
        <nav class="md:hidden no-print fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur border-t border-slate-200 flex items-center justify-around py-2 pb-safe shadow-lg">
            <a href="index.php" class="flex flex-col items-center py-1 px-3 rounded-lg text-[10px] font-medium transition <?php echo $current_page === 'index.php' ? 'text-slate-950 font-bold' : 'text-slate-500 hover:text-slate-800'; ?>">
                <svg class="w-5 h-5 mb-0.5 <?php echo $current_page === 'index.php' ? 'stroke-[2.5]' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                홈
            </a>

            <a href="diagnosis.php" class="flex flex-col items-center py-1 px-3 rounded-lg text-[10px] font-medium transition <?php echo in_array($current_page, ['diagnosis.php', 'diagnosis_result.php']) ? 'text-slate-950 font-bold' : 'text-slate-500 hover:text-slate-800'; ?>">
                <svg class="w-5 h-5 mb-0.5 <?php echo in_array($current_page, ['diagnosis.php', 'diagnosis_result.php']) ? 'stroke-[2.5]' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                5분진단
            </a>

            <a href="worksheet.php" class="flex flex-col items-center py-1 px-3 rounded-lg text-[10px] font-medium transition <?php echo $current_page === 'worksheet.php' ? 'text-slate-950 font-bold' : 'text-slate-500 hover:text-slate-800'; ?>">
                <svg class="w-5 h-5 mb-0.5 <?php echo $current_page === 'worksheet.php' ? 'stroke-[2.5]' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                문제지
            </a>

            <a href="practice.php" class="flex flex-col items-center py-1 px-3 rounded-lg text-[10px] font-medium transition <?php echo $current_page === 'practice.php' ? 'text-slate-950 font-bold' : 'text-slate-500 hover:text-slate-800'; ?>">
                <svg class="w-5 h-5 mb-0.5 <?php echo $current_page === 'practice.php' ? 'stroke-[2.5]' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                온라인풀이
            </a>

            <a href="settings.php" class="flex flex-col items-center py-1 px-3 rounded-lg text-[10px] font-medium transition <?php echo in_array($current_page, ['settings.php', 'history.php', 'profile.php', 'admin.php']) ? 'text-slate-950 font-bold' : 'text-slate-500 hover:text-slate-800'; ?> relative">
                <svg class="w-5 h-5 mb-0.5 <?php echo in_array($current_page, ['settings.php', 'history.php', 'profile.php', 'admin.php']) ? 'stroke-[2.5]' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                학생/설정
                <?php if ($pending_count > 0): ?>
                    <span class="absolute top-1 right-2 w-2 h-2 rounded-full bg-rose-500"></span>
                <?php endif; ?>
            </a>
        </nav>
    <?php endif; ?>

    <!-- 메인 컨텐츠 영역: 모바일에서는 하단 탭바를 위해 pb-24 패딩 부여 -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8 pb-24 md:pb-8">
