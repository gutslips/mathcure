<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/lib/auth.php';

$current_user = require_login($pdo);

$msg_success = '';
$msg_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $affiliation = trim($_POST['affiliation'] ?? '');

        if (!$name || !$phone) {
            $msg_error = '이름과 연락처는 필수입니다.';
        } else {
            $t_users = table('users');
            $stmt = $pdo->prepare("UPDATE `{$t_users}` SET `name` = ?, `phone` = ?, `affiliation` = ? WHERE `id` = ?");
            $stmt->execute([$name, $phone, $affiliation ?: null, $current_user['id']]);
            $msg_success = '프로필 정보가 수정되었습니다.';
            $current_user = get_logged_in_user($pdo);
        }
    } elseif ($action === 'change_password') {
        $current_pw = $_POST['current_password'] ?? '';
        $new_pw = $_POST['new_password'] ?? '';
        $confirm_pw = $_POST['confirm_password'] ?? '';

        $t_users = table('users');
        $stmt = $pdo->prepare("SELECT `password_hash` FROM `{$t_users}` WHERE `id` = ?");
        $stmt->execute([$current_user['id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current_pw, $row['password_hash'])) {
            $msg_error = '현재 비밀번호가 일치하지 않습니다.';
        } elseif ($new_pw !== $confirm_pw) {
            $msg_error = '새 비밀번호와 확인 비밀번호가 일치하지 않습니다.';
        } elseif (strlen($new_pw) < 4) {
            $msg_error = '새 비밀번호는 4자 이상이어야 합니다.';
        } else {
            $new_hash = password_hash($new_pw, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE `{$t_users}` SET `password_hash` = ? WHERE `id` = ?");
            $stmt->execute([$new_hash, $current_user['id']]);
            $msg_success = '비밀번호가 안전하게 변경되었습니다.';
        }
    }
}
?>

<div class="max-w-2xl mx-auto space-y-8">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">내 계정 정보 및 보안</h1>
        <p class="text-xs text-slate-500 mt-1">
            선생님 프로필 정보와 로그인 비밀번호를 변경할 수 있습니다.
        </p>
    </div>

    <?php if ($msg_success): ?>
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-xs font-semibold">
            ✓ <?php echo $msg_success; ?>
        </div>
    <?php endif; ?>

    <?php if ($msg_error): ?>
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-xs font-semibold">
            ✗ <?php echo $msg_error; ?>
        </div>
    <?php endif; ?>

    <!-- 프로필 정보 수정 -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 space-y-4 shadow-xs">
        <h2 class="text-lg font-bold text-slate-900">프로필 정보</h2>
        <form method="POST" action="profile.php" class="space-y-4">
            <input type="hidden" name="action" value="change_profile">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">아이디</label>
                <input type="text" value="<?php echo htmlspecialchars($current_user['username']); ?>" disabled
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-500 text-sm font-mono cursor-not-allowed">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">이름</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($current_user['name']); ?>" required
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">연락처</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($current_user['phone']); ?>" required
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">소속</label>
                <input type="text" name="affiliation" value="<?php echo htmlspecialchars($current_user['affiliation'] ?? ''); ?>" placeholder="학원 또는 학교명"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
            </div>

            <div class="pt-2">
                <button type="submit" class="py-2.5 px-5 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-sm transition">
                    프로필 저장
                </button>
            </div>
        </form>
    </div>

    <!-- 비밀번호 변경 -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 space-y-4 shadow-xs">
        <h2 class="text-lg font-bold text-slate-900">비밀번호 변경</h2>
        <form method="POST" action="profile.php" class="space-y-4">
            <input type="hidden" name="action" value="change_password">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">현재 비밀번호</label>
                <input type="password" name="current_password" required
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">새 비밀번호</label>
                    <input type="password" name="new_password" required placeholder="4자 이상"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">새 비밀번호 확인</label>
                    <input type="password" name="confirm_password" required placeholder="새 비밀번호 재입력"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="py-2.5 px-5 bg-slate-800 hover:bg-slate-700 text-white font-semibold rounded-xl text-sm transition">
                    비밀번호 변경하기
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
