<?php
/**
 * MathCure - Cafe24 MySQL Database Connection & Auto Installer
 */

$config_file = __DIR__ . '/db_config.php';

if (!file_exists($config_file)) {
    $current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($current_script !== 'install.php') {
        header('Location: install.php');
        exit;
    }
} else {
    require_once $config_file;
}

function get_db() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
        return null;
    }

    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        die("데이터베이스 연결 실패: " . htmlspecialchars($e->getMessage()));
    }
}

function table($name) {
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'mc_';
    return $prefix . $name;
}

/**
 * 테이블 자동 생성 및 스키마 마이그레이션 함수
 */
function auto_install_tables(PDO $pdo, $prefix = 'mc_') {
    $sql = "
    CREATE TABLE IF NOT EXISTS `{$prefix}users` (
        `id` VARCHAR(36) NOT NULL PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password_hash` VARCHAR(255) NOT NULL,
        `name` VARCHAR(50) NOT NULL,
        `phone` VARCHAR(30) NOT NULL,
        `affiliation` VARCHAR(100) NULL,
        `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
        `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX (`status`),
        INDEX (`role`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `{$prefix}students` (
        `id` VARCHAR(36) NOT NULL PRIMARY KEY,
        `user_id` VARCHAR(36) NULL,
        `name` VARCHAR(50) NOT NULL,
        `grade` INT NOT NULL DEFAULT 5,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `{$prefix}diagnoses` (
        `id` VARCHAR(36) NOT NULL PRIMARY KEY,
        `student_id` VARCHAR(36) NOT NULL,
        `total` INT NOT NULL,
        `correct` INT NOT NULL,
        `accuracy` FLOAT NOT NULL,
        `average_ms` FLOAT NOT NULL,
        `started_at` DATETIME NOT NULL,
        `finished_at` DATETIME NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (`student_id`),
        INDEX (`finished_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `{$prefix}diagnosis_answers` (
        `id` VARCHAR(36) NOT NULL PRIMARY KEY,
        `diagnosis_id` VARCHAR(36) NOT NULL,
        `question` VARCHAR(100) NOT NULL,
        `answer` INT NOT NULL,
        `user_answer` INT NULL,
        `correct` TINYINT(1) NOT NULL,
        `elapsed_ms` INT NOT NULL,
        `type` VARCHAR(50) NOT NULL,
        `difficulty` INT NOT NULL,
        INDEX (`diagnosis_id`),
        INDEX (`type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `{$prefix}practice_sessions` (
        `id` VARCHAR(36) NOT NULL PRIMARY KEY,
        `student_id` VARCHAR(36) NOT NULL,
        `worksheet_id` VARCHAR(36) NULL,
        `total` INT NOT NULL,
        `correct` INT NOT NULL,
        `accuracy` FLOAT NOT NULL,
        `average_ms` FLOAT NOT NULL,
        `started_at` DATETIME NOT NULL,
        `finished_at` DATETIME NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (`student_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `{$prefix}worksheets` (
        `id` VARCHAR(36) NOT NULL PRIMARY KEY,
        `student_id` VARCHAR(36) NULL,
        `title` VARCHAR(100) NOT NULL,
        `subject` VARCHAR(50) NOT NULL,
        `grade` INT NOT NULL,
        `difficulty` INT NOT NULL,
        `problem_type` VARCHAR(50) NOT NULL,
        `count` INT NOT NULL,
        `seed` VARCHAR(100) NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (`student_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $pdo->exec($sql);

    // 기존 students 테이블에 user_id 컬럼이 없을 경우 대비 마이그레이션
    try {
        $pdo->exec("ALTER TABLE `{$prefix}students` ADD COLUMN `user_id` VARCHAR(36) NULL AFTER `id`, ADD INDEX (`user_id`)");
    } catch (Exception $e) {
        // 이미 존재하면 무시
    }

    // 기본 관리자 계정 생성 (존재하지 않을 시)
    $stmt = $pdo->query("SELECT COUNT(*) FROM `{$prefix}users` WHERE `role` = 'admin'");
    if ($stmt->fetchColumn() == 0) {
        $admin_id = 'usr_admin_' . bin2hex(random_bytes(4));
        $admin_hash = password_hash('admin1234!', PASSWORD_BCRYPT);
        $insertAdmin = $pdo->prepare("
            INSERT INTO `{$prefix}users`
            (`id`, `username`, `password_hash`, `name`, `phone`, `affiliation`, `role`, `status`)
            VALUES (?, ?, ?, ?, ?, ?, 'admin', 'approved')
        ");
        $insertAdmin->execute([
            $admin_id,
            'admin',
            $admin_hash,
            '최고관리자',
            '010-0000-0000',
            '본부'
        ]);
    }

    return true;
}
