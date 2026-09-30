<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/auth.php';

$pdo = get_db();
$error_message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $affiliation = trim($_POST['affiliation'] ?? ''); // 비필수 (선택)

    if (empty($username) || empty($password) || empty($name) || empty($phone)) {
        $error_message = '아이디, 비밀번호, 이름, 연락처는 필수 입력 항목입니다.';
    } elseif ($password !== $password_confirm) {
        $error_message = '비밀번호와 비밀번호 확인이 일치하지 않습니다.';
    } elseif (strlen($password) < 4) {
        $error_message = '비밀번호는 최소 4자 이상이어야 합니다.';
    } else {
        $t_users = table('users');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$t_users}` WHERE `username` = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $error_message = '이미 사용 중인 아이디입니다. 다른 아이디를 입력해 주세요.';
        } else {
            $id = 'usr_' . bin2hex(random_bytes(8));
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $insert = $pdo->prepare("
                INSERT INTO `{$t_users}`
                (`id`, `username`, `password_hash`, `name`, `phone`, `affiliation`, `role`, `status`)
                VALUES (?, ?, ?, ?, ?, ?, 'user', 'pending')
            ");

            if ($insert->execute([$id, $username, $hash, $name, $phone, $affiliation ?: null])) {
                $success = true;
            } else {
                $error_message = '회원가입 처리 중 오류가 발생했습니다.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>회원가입 신청 | 초5 연산 트레이너</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-full flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-2xl border border-slate-200 shadow-sm p-8 space-y-6">
        <div class="text-center">
            <a href="index.php" class="inline-flex items-center gap-2 mb-3">
                <span class="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center font-bold text-base tracking-wider">
                    MC
                </span>
            </a>
            <h1 class="text-2xl font-bold text-slate-900">회원가입 신청</h1>
            <p class="text-slate-500 text-xs mt-1">초5 연산 트레이너 교사용 계정 생성</p>
        </div>

        <?php if ($success): ?>
            <div class="p-6 bg-emerald-50 border border-emerald-200 rounded-2xl text-center space-y-4">
                <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center mx-auto text-xl font-bold">
                    ✓
                </div>
                <div>
                    <h2 class="text-lg font-bold text-emerald-900">가입 신청이 완료되었습니다!</h2>
                    <p class="text-xs text-emerald-800 mt-2 leading-relaxed">
                        관리자 승인 후 즉시 서비스를 이용하실 수 있습니다.<br>
                        승인이 완료되면 등록하신 아이디로 로그인해 주세요.
                    </p>
                </div>
                <div class="pt-2">
                    <a href="login.php" class="block w-full py-2.5 px-4 bg-emerald-700 hover:bg-emerald-800 text-white font-semibold rounded-xl text-sm transition">
                        로그인 화면으로 이동
                    </a>
                </div>
            </div>
        <?php else: ?>

            <?php if ($error_message): ?>
                <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-xs font-medium">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="bg-blue-50 border border-blue-200 rounded-xl p-3.5 text-xs text-blue-900 leading-relaxed">
                💡 <strong>안내:</strong> 안전한 학생 데이터 관리를 위해 <strong>관리자 승인제</strong>로 운영됩니다. 가입 신청 후 승인되면 로그인하실 수 있습니다.
            </div>

            <form method="POST" action="register.php" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        아이디 <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required placeholder="영문/숫자 아이디"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            비밀번호 <span class="text-rose-500">*</span>
                        </label>
                        <input type="password" name="password" required placeholder="4자 이상"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            비밀번호 확인 <span class="text-rose-500">*</span>
                        </label>
                        <input type="password" name="password_confirm" required placeholder="비밀번호 재입력"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        이름 <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required placeholder="선생님 / 학부모 실명"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        연락처 <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required placeholder="010-0000-0000"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        소속 <span class="text-slate-400 font-normal">(선택)</span>
                    </label>
                    <input type="text" name="affiliation" value="<?php echo htmlspecialchars($_POST['affiliation'] ?? ''); ?>" placeholder="예: OO수학학원, OO초등학교, 가정 등"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3 px-4 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-sm transition shadow-sm">
                        가입 신청하기
                    </button>
                </div>

                <div class="text-center pt-2">
                    <a href="login.php" class="text-xs text-slate-600 hover:text-slate-900 underline font-medium">
                        이미 계정이 있으신가요? 로그인
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
