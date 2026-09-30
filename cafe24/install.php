<?php
/**
 * MathCure - Cafe24 자동 설치 마법사
 */

$config_file = __DIR__ . '/db_config.php';
$is_installed = file_exists($config_file);
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'install') {
    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_name = trim($_POST['db_name'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = $_POST['db_pass'] ?? '';
    $db_prefix = trim($_POST['db_prefix'] ?? 'mc_');

    if (empty($db_host) || empty($db_name) || empty($db_user)) {
        $error_message = '호스트, DB 이름, DB 아이디는 필수 입력 사항입니다.';
    } else {
        try {
            $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            // 테이블 자동 생성
            require_once __DIR__ . '/db.php';
            auto_install_tables($pdo, $db_prefix);

            // db_config.php 파일 생성
            $config_content = "<?php\n";
            $config_content .= "// MathCure Database Configuration\n";
            $config_content .= "define('DB_HOST', " . var_export($db_host, true) . ");\n";
            $config_content .= "define('DB_NAME', " . var_export($db_name, true) . ");\n";
            $config_content .= "define('DB_USER', " . var_export($db_user, true) . ");\n";
            $config_content .= "define('DB_PASS', " . var_export($db_pass, true) . ");\n";
            $config_content .= "define('DB_PREFIX', " . var_export($db_prefix, true) . ");\n";

            if (file_put_contents($config_file, $config_content) === false) {
                $error_message = 'db_config.php 파일 생성에 실패했습니다. 폴더 쓰기 권한(707 또는 777)을 확인해 주세요.';
            } else {
                $is_installed = true;
                $success_message = '데이터베이스 테이블이 성공적으로 생성되고 설치가 완료되었습니다!';
            }
        } catch (PDOException $e) {
            $error_message = 'DB 연결 실패: ' . $e->getMessage() . '<br><small class="text-slate-500">카페24 DB 아이디, 비밀번호, 호스트(보통 localhost)를 다시 확인해 주세요.</small>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MathCure 설치 마법사 (카페24 MySQL)</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-full flex items-center justify-center p-4">
    <div class="max-w-lg w-full bg-white rounded-2xl border border-slate-200 shadow-sm p-8 space-y-6">
        <div class="text-center">
            <div class="w-12 h-12 rounded-xl bg-slate-900 text-white inline-flex items-center justify-center font-bold text-xl mb-3">
                MC
            </div>
            <h1 class="text-2xl font-bold text-slate-900">초5 연산 트레이너 설치</h1>
            <p class="text-slate-500 text-sm mt-1">카페24 웹호스팅 MySQL 자동 연동 마법사</p>
        </div>

        <?php if ($success_message): ?>
            <div class="p-5 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-800 text-sm space-y-3">
                <div class="font-bold text-base">🎉 <?php echo $success_message; ?></div>
                <p class="text-xs text-emerald-700 leading-relaxed">
                    접두사(<code><?php echo htmlspecialchars($db_prefix); ?></code>)가 적용된 6개 테이블(회원, 학생, 진단, 답안, 연습, 문제지)이 자동 생성되었습니다.
                </p>
                <div class="p-3 bg-white rounded-xl border border-emerald-200 text-xs text-slate-700 space-y-1">
                    <div class="font-bold text-slate-900">🛡️ 최고 관리자 기본 계정 안내</div>
                    <div>• 관리자 아이디: <strong class="font-mono text-slate-900">admin</strong></div>
                    <div>• 관리자 비밀번호: <strong class="font-mono text-slate-900">admin1234!</strong></div>
                    <div class="text-[11px] text-slate-500 pt-1">로그인 후 [내 정보] 메뉴에서 비밀번호를 꼭 변경해 주세요.</div>
                </div>
                <div class="pt-2">
                    <a href="login.php" class="block w-full text-center py-2.5 px-4 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-sm transition">
                        로그인 화면으로 이동 ➔
                    </a>
                </div>
            </div>
        <?php elseif ($is_installed): ?>
            <div class="p-4 bg-blue-50 border border-blue-200 rounded-xl text-blue-800 text-sm">
                <div class="font-bold">이미 설치가 완료되어 있습니다.</div>
                <p class="text-xs text-blue-700 mt-1">
                    데이터베이스 설정(db_config.php)이 이미 존재합니다. 다시 설치하려면 서버의 <code>db_config.php</code> 파일을 삭제해 주세요.
                </p>
                <div class="mt-4">
                    <a href="index.php" class="block w-full text-center py-2.5 px-4 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-lg text-sm transition">
                        대시보드로 이동
                    </a>
                </div>
            </div>
        <?php else: ?>

            <?php if ($error_message): ?>
                <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-sm leading-relaxed">
                    <div class="font-bold mb-1">설치 오류</div>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-xs text-amber-900 leading-relaxed">
                💡 <strong>알림:</strong> 카페24는 1개 DB를 여러 프로그램과 함께 사용하므로, <strong>테이블 접두사(Prefix)</strong>를 붙여 기존 데이터와 섞이지 않도록 안전하게 분리합니다.
            </div>

            <form method="POST" action="install.php" class="space-y-4">
                <input type="hidden" name="action" value="install">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">DB 호스트</label>
                    <input type="text" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required
                        class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                    <p class="text-[11px] text-slate-500 mt-1">카페24 기본값은 <code>localhost</code> 입니다.</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">DB 아이디</label>
                        <input type="text" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? ''); ?>" placeholder="카페24 계정ID" required
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">DB 이름</label>
                        <input type="text" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? ''); ?>" placeholder="보통 계정ID와 동일" required
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">DB 비밀번호</label>
                    <input type="password" name="db_pass" placeholder="MySQL 비밀번호" required
                        class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">테이블 접두사 (Prefix)</label>
                    <input type="text" name="db_prefix" value="<?php echo htmlspecialchars($_POST['db_prefix'] ?? 'mc_'); ?>" required
                        class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-slate-900">
                    <p class="text-[11px] text-slate-500 mt-1">기본값 <code>mc_</code> 사용 시 테이블명이 <code>mc_students</code> 등으로 생성됩니다.</p>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3 px-4 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-lg text-sm transition shadow-sm">
                        DB 연결 및 자동 테이블 생성
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
