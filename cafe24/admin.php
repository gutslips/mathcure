<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/lib/auth.php';

$current_user = require_admin($pdo);

$t_users = table('users');
$t_students = table('students');

$action_message = '';
$action_error = '';

// 관리자 액션 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $target_id = $_POST['target_id'] ?? '';

    try {
        if ($action === 'approve' && $target_id) {
            $stmt = $pdo->prepare("UPDATE `{$t_users}` SET `status` = 'approved' WHERE `id` = ? AND `role` != 'admin'");
            $stmt->execute([$target_id]);
            $action_message = '회원 가입이 승인되었습니다.';
        } elseif ($action === 'reject' && $target_id) {
            $stmt = $pdo->prepare("UPDATE `{$t_users}` SET `status` = 'rejected' WHERE `id` = ? AND `role` != 'admin'");
            $stmt->execute([$target_id]);
            $action_message = '회원이 반려/차단 처리되었습니다.';
        } elseif ($action === 'reset_password' && $target_id) {
            $new_pass = trim($_POST['new_password'] ?? '');
            if (empty($new_pass)) {
                $new_pass = 'mc' . mt_rand(1000, 9999) . '!';
            }
            $hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE `{$t_users}` SET `password_hash` = ? WHERE `id` = ?");
            $stmt->execute([$hash, $target_id]);
            $action_message = "비밀번호가 성공적으로 초기화되었습니다. 임시 비밀번호: <strong class='underline text-slate-900'>{$new_pass}</strong>";
        } elseif ($action === 'delete_user' && $target_id) {
            // 회원 및 소속 학생 데이터 삭제
            $delStudents = $pdo->prepare("DELETE FROM `{$t_students}` WHERE `user_id` = ?");
            $delStudents->execute([$target_id]);

            $delUser = $pdo->prepare("DELETE FROM `{$t_users}` WHERE `id` = ? AND `role` != 'admin'");
            $delUser->execute([$target_id]);
            $action_message = '회원 및 관련 데이터가 완전히 삭제되었습니다.';
        }
    } catch (Exception $e) {
        $action_error = '작업 중 오류 발생: ' . $e->getMessage();
    }
}

// 탭 필터
$status_filter = $_GET['status'] ?? 'all';
$query = "SELECT * FROM `{$t_users}` WHERE 1=1";
$params = [];

if (in_array($status_filter, ['pending', 'approved', 'rejected'])) {
    $query .= " AND `status` = ?";
    $params[] = $status_filter;
}
$query .= " ORDER BY `created_at` DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// 통계 카운트
$countAll = (int)$pdo->query("SELECT COUNT(*) FROM `{$t_users}`")->fetchColumn();
$countPending = (int)$pdo->query("SELECT COUNT(*) FROM `{$t_users}` WHERE `status` = 'pending'")->fetchColumn();
$countApproved = (int)$pdo->query("SELECT COUNT(*) FROM `{$t_users}` WHERE `status` = 'approved'")->fetchColumn();
$countRejected = (int)$pdo->query("SELECT COUNT(*) FROM `{$t_users}` WHERE `status` = 'rejected'")->fetchColumn();
?>

<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded bg-slate-900 text-white text-xs font-semibold mb-2">
                🛡️ 최고 관리자 콘솔
            </div>
            <h1 class="text-2xl font-bold text-slate-900">회원 관리 및 가입 승인</h1>
            <p class="text-xs text-slate-500 mt-1">
                신규 회원 가입을 검토하고 승인, 차단, 비밀번호 초기화를 관리합니다.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="index.php" class="px-4 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                트레이너 대시보드로 이동
            </a>
        </div>
    </div>

    <?php if ($action_message): ?>
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-xs font-semibold">
            ✓ <?php echo $action_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($action_error): ?>
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-xs font-semibold">
            ✗ <?php echo $action_error; ?>
        </div>
    <?php endif; ?>

    <!-- 통계 카드 -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <a href="admin.php?status=all" class="p-5 bg-white border border-slate-200 rounded-xl hover:border-slate-400 transition <?php echo $status_filter === 'all' ? 'ring-2 ring-slate-900' : ''; ?>">
            <div class="text-xs font-semibold text-slate-500">전체 회원</div>
            <div class="text-2xl font-black text-slate-900 mt-1"><?php echo $countAll; ?>명</div>
        </a>

        <a href="admin.php?status=pending" class="p-5 bg-white border border-slate-200 rounded-xl hover:border-slate-400 transition relative <?php echo $status_filter === 'pending' ? 'ring-2 ring-slate-900' : ''; ?>">
            <div class="text-xs font-semibold text-amber-600">승인 대기</div>
            <div class="text-2xl font-black text-amber-700 mt-1"><?php echo $countPending; ?>명</div>
            <?php if ($countPending > 0): ?>
                <span class="absolute top-3 right-3 w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
            <?php endif; ?>
        </a>

        <a href="admin.php?status=approved" class="p-5 bg-white border border-slate-200 rounded-xl hover:border-slate-400 transition <?php echo $status_filter === 'approved' ? 'ring-2 ring-slate-900' : ''; ?>">
            <div class="text-xs font-semibold text-emerald-600">승인 완료</div>
            <div class="text-2xl font-black text-emerald-700 mt-1"><?php echo $countApproved; ?>명</div>
        </a>

        <a href="admin.php?status=rejected" class="p-5 bg-white border border-slate-200 rounded-xl hover:border-slate-400 transition <?php echo $status_filter === 'rejected' ? 'ring-2 ring-slate-900' : ''; ?>">
            <div class="text-xs font-semibold text-rose-600">반려 / 차단</div>
            <div class="text-2xl font-black text-rose-700 mt-1"><?php echo $countRejected; ?>명</div>
        </a>
    </div>

    <!-- 회원 목록 테이블 -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 space-y-4 shadow-xs">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-900">
                회원 목록 (<?php echo count($users); ?>명)
            </h2>
            <div class="flex gap-1 text-xs">
                <a href="admin.php?status=all" class="px-2.5 py-1 rounded <?php echo $status_filter === 'all' ? 'bg-slate-900 text-white font-bold' : 'text-slate-600 hover:bg-slate-100'; ?>">전체</a>
                <a href="admin.php?status=pending" class="px-2.5 py-1 rounded <?php echo $status_filter === 'pending' ? 'bg-amber-700 text-white font-bold' : 'text-slate-600 hover:bg-slate-100'; ?>">승인 대기</a>
                <a href="admin.php?status=approved" class="px-2.5 py-1 rounded <?php echo $status_filter === 'approved' ? 'bg-emerald-700 text-white font-bold' : 'text-slate-600 hover:bg-slate-100'; ?>">승인 완료</a>
                <a href="admin.php?status=rejected" class="px-2.5 py-1 rounded <?php echo $status_filter === 'rejected' ? 'bg-rose-700 text-white font-bold' : 'text-slate-600 hover:bg-slate-100'; ?>">반려</a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase">
                        <th class="py-3 px-3">아이디</th>
                        <th class="py-3 px-3">이름</th>
                        <th class="py-3 px-3">연락처</th>
                        <th class="py-3 px-3">소속</th>
                        <th class="py-3 px-3">가입일</th>
                        <th class="py-3 px-3 text-center">상태</th>
                        <th class="py-3 px-3 text-right">관리 작업</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3 px-3 font-mono font-semibold text-slate-900">
                                <?php echo htmlspecialchars($u['username']); ?>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="ml-1 text-[10px] font-bold text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded">관리자</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-3 font-semibold text-slate-800">
                                <?php echo htmlspecialchars($u['name']); ?>
                            </td>
                            <td class="py-3 px-3 font-mono text-xs text-slate-600">
                                <?php echo htmlspecialchars($u['phone']); ?>
                            </td>
                            <td class="py-3 px-3 text-xs text-slate-600">
                                <?php echo htmlspecialchars($u['affiliation'] ?? '-'); ?>
                            </td>
                            <td class="py-3 px-3 font-mono text-xs text-slate-500">
                                <?php echo date('Y.m.d', strtotime($u['created_at'])); ?>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <?php if ($u['status'] === 'approved'): ?>
                                    <span class="inline-block px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-800">승인완료</span>
                                <?php elseif ($u['status'] === 'pending'): ?>
                                    <span class="inline-block px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-800 animate-pulse">승인대기</span>
                                <?php else: ?>
                                    <span class="inline-block px-2 py-0.5 rounded text-xs font-bold bg-rose-100 text-rose-800">반려/차단</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-3 text-right space-x-1">
                                <?php if ($u['role'] !== 'admin'): ?>
                                    <?php if ($u['status'] !== 'approved'): ?>
                                        <form method="POST" action="admin.php" class="inline">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="target_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-semibold transition">
                                                승인
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($u['status'] !== 'rejected'): ?>
                                        <form method="POST" action="admin.php" class="inline">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="target_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="px-2.5 py-1 bg-amber-100 hover:bg-amber-200 text-amber-900 rounded text-xs font-semibold transition" onclick="return confirm('이 회원을 반려/차단하시겠습니까?')">
                                                차단
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <button onclick="promptResetPassword('<?php echo $u['id']; ?>', '<?php echo htmlspecialchars($u['username']); ?>')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded text-xs font-semibold transition">
                                        비번 초기화
                                    </button>

                                    <form method="POST" action="admin.php" class="inline" onsubmit="return confirm('정말 이 회원을 삭제하시겠습니까? 관련된 학생 데이터도 모두 삭제됩니다.')">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="target_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="px-2 py-1 text-rose-600 hover:text-rose-900 rounded text-xs font-semibold transition">
                                            삭제
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">최고 관리자</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 비밀번호 초기화 폼 (숨김) -->
<form id="reset-pw-form" method="POST" action="admin.php" class="hidden">
    <input type="hidden" name="action" value="reset_password">
    <input type="hidden" name="target_id" id="reset-target-id">
    <input type="hidden" name="new_password" id="reset-new-password">
</form>

<script>
function promptResetPassword(userId, username) {
    const newPass = prompt(`'${username}' 회원의 새로운 비밀번호를 입력해 주세요.\n(비워두면 무작위 임시 비밀번호로 자동 생성됩니다)`);
    if (newPass === null) return; // 취소

    document.getElementById('reset-target-id').value = userId;
    document.getElementById('reset-new-password').value = newPass;
    document.getElementById('reset-pw-form').submit();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
