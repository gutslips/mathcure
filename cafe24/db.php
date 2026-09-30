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
 * 테이블 자동 생성 함수 (설치 마법사 및 부팅 시 사용)
 */
function auto_install_tables(PDO $pdo, $prefix = 'mc_') {
    $sql = "
    CREATE TABLE IF NOT EXISTS `{$prefix}students` (
        `id` VARCHAR(36) NOT NULL PRIMARY KEY,
        `name` VARCHAR(50) NOT NULL,
        `grade` INT NOT NULL DEFAULT 5,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
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

    // 기본 학생 등록 (존재하지 않을 시)
    $stmt = $pdo->query("SELECT COUNT(*) FROM `{$prefix}students`");
    if ($stmt->fetchColumn() == 0) {
        $default_id = 'std_' . bin2hex(random_bytes(8));
        $insert = $pdo->prepare("INSERT INTO `{$prefix}students` (`id`, `name`, `grade`) VALUES (?, ?, ?)");
        $insert->execute([$default_id, '홍길동', 5]);
    }

    return true;
}
