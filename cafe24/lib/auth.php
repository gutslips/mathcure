<?php
/**
 * MathCure - Authentication & Session Helper
 */

require_once __DIR__ . '/../db.php';

function start_session_safe() {
    if (session_status() === PHP_SESSION_NONE) {
        // 세션 쿠키 보안 설정
        ini_set('session.cookie_httponly', '1');
        session_start();
    }
}

function get_logged_in_user(PDO $pdo = null) {
    start_session_safe();
    $userId = $_SESSION['mathcure_user_id'] ?? null;
    if (!$userId) return null;

    if ($pdo === null) {
        $pdo = get_db();
    }
    if (!$pdo) return null;

    $t_users = table('users');
    $stmt = $pdo->prepare("SELECT `id`, `username`, `name`, `phone`, `affiliation`, `role`, `status`, `created_at` FROM `{$t_users}` WHERE `id` = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        logout_user();
        return null;
    }

    return $user;
}

function require_login(PDO $pdo = null) {
    start_session_safe();
    $user = get_logged_in_user($pdo);

    if (!$user) {
        header('Location: login.php');
        exit;
    }

    if ($user['status'] === 'pending') {
        header('Location: pending_approval.php');
        exit;
    }

    if ($user['status'] === 'rejected') {
        header('Location: login.php?error=rejected');
        exit;
    }

    return $user;
}

function require_admin(PDO $pdo = null) {
    $user = require_login($pdo);
    if ($user['role'] !== 'admin') {
        die('접근 권한이 없습니다. 최고 관리자만 이용할 수 있는 메뉴입니다.');
    }
    return $user;
}

function login_user(array $user) {
    start_session_safe();
    session_regenerate_id(true);
    $_SESSION['mathcure_user_id'] = $user['id'];
    $_SESSION['mathcure_username'] = $user['username'];
    $_SESSION['mathcure_name'] = $user['name'];
    $_SESSION['mathcure_role'] = $user['role'];
}

function logout_user() {
    start_session_safe();
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

function get_pending_users_count(PDO $pdo) {
    $t_users = table('users');
    return (int)$pdo->query("SELECT COUNT(*) FROM `{$t_users}` WHERE `status` = 'pending'")->fetchColumn();
}
