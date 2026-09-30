<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/auth.php';

$pdo = get_db();
start_session_safe();
$user = get_logged_in_user($pdo);

if (!$user) {
    header('Location: login.php');
    exit;
}

if ($user['status'] === 'approved') {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>승인 대기 중 | 초5 연산 트레이너</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-full flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-2xl border border-slate-200 shadow-sm p-8 text-center space-y-6">
        <div class="w-16 h-16 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center mx-auto text-2xl font-bold">
            ⏳
        </div>

        <div>
            <h1 class="text-2xl font-bold text-slate-900">가입 승인 대기 중입니다</h1>
            <p class="text-slate-600 text-xs mt-2 leading-relaxed">
                <strong><?php echo htmlspecialchars($user['name']); ?></strong> 선생님의 가입 신청이 정상 접수되었으며, 현재 최고 관리자의 가입 승인을 기다리고 있습니다.
            </p>
        </div>

        <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl text-left text-xs space-y-1.5 text-slate-600">
            <div>• <strong>신청 아이디:</strong> <span class="font-mono text-slate-900"><?php echo htmlspecialchars($user['username']); ?></span></div>
            <div>• <strong>연락처:</strong> <?php echo htmlspecialchars($user['phone']); ?></div>
            <?php if (!empty($user['affiliation'])): ?>
                <div>• <strong>소속:</strong> <?php echo htmlspecialchars($user['affiliation']); ?></div>
            <?php endif; ?>
            <div>• <strong>신청 일시:</strong> <?php echo date('Y.m.d H:i', strtotime($user['created_at'])); ?></div>
        </div>

        <div class="space-y-2 pt-2">
            <button onclick="window.location.reload()" class="w-full py-2.5 px-4 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-sm transition">
                승인 상태 새로고침 ↻
            </button>
            <a href="logout.php" class="block w-full py-2.5 px-4 border border-slate-300 text-slate-700 hover:bg-slate-50 font-semibold rounded-xl text-sm transition">
                로그아웃
            </a>
        </div>
    </div>
</body>
</html>
