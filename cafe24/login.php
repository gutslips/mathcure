<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/auth.php';

$pdo = get_db();
$error_message = '';

if (isset($_GET['error']) && $_GET['error'] === 'rejected') {
    $error_message = '이용이 제한 또는 반려된 계정입니다. 관리자에게 문의하세요.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = '아이디와 비밀번호를 모두 입력해 주세요.';
    } else {
        $t_users = table('users');
        $stmt = $pdo->prepare("SELECT * FROM `{$t_users}` WHERE `username` = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] === 'pending') {
                login_user($user);
                header('Location: pending_approval.php');
                exit;
            } elseif ($user['status'] === 'rejected') {
                $error_message = '이용이 제한된 계정입니다. 관리자에게 문의하세요.';
            } else {
                login_user($user);
                if ($user['role'] === 'admin') {
                    header('Location: admin.php');
                } else {
                    header('Location: index.php');
                }
                exit;
            }
        } else {
            $error_message = '아이디 또는 비밀번호가 올바르지 않습니다.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>로그인 | 초5 연산 트레이너</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-full flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-2xl border border-slate-200 shadow-sm p-8 space-y-6">
        <div class="text-center">
            <span class="w-12 h-12 rounded-xl bg-slate-900 text-white inline-flex items-center justify-center font-bold text-xl mb-3 shadow-xs">
                MC
            </span>
            <h1 class="text-2xl font-bold text-slate-900">초5 연산 트레이너 로그인</h1>
            <p class="text-slate-500 text-xs mt-1">학생별 맞춤 연산 진단 및 훈련 시스템</p>
        </div>

        <?php if ($error_message): ?>
            <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-xs font-semibold leading-relaxed">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">아이디</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required placeholder="아이디 입력"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">비밀번호</label>
                <input type="password" name="password" required placeholder="비밀번호 입력"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full py-3 px-4 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-sm transition shadow-sm">
                    로그인
                </button>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs">
                <span class="text-slate-500">아직 회원이 아니신가요?</span>
                <a href="register.php" class="font-bold text-slate-900 hover:underline">
                    회원가입 신청 ➔
                </a>
            </div>
        </form>

        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-[11px] text-slate-500 leading-relaxed">
            <strong>관리자 계정 안내:</strong> 최초 기본 관리자 아이디는 <code>admin</code> / 비밀번호는 <code>admin1234!</code> 입니다. 로그인 후 비밀번호를 변경해 주세요.
        </div>
    </div>
</body>
</html>
