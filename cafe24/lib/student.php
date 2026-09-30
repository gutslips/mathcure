<?php
/**
 * MathCure - Multi-Student Management with User Isolation for Cafe24
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';

const STUDENT_COOKIE_NAME = 'mathcure_cafe24_student_id';

function get_current_user_id(PDO $pdo = null) {
    $user = get_logged_in_user($pdo);
    return $user ? $user['id'] : null;
}

function get_all_students(PDO $pdo, $user_id = null) {
    if ($user_id === null) {
        $user_id = get_current_user_id($pdo);
    }

    $table = table('students');

    if ($user_id) {
        $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE `user_id` = ? ORDER BY `created_at` ASC");
        $stmt->execute([$user_id]);
        $students = $stmt->fetchAll();
    } else {
        $stmt = $pdo->query("SELECT * FROM `{$table}` ORDER BY `created_at` ASC");
        $students = $stmt->fetchAll();
    }

    if (empty($students)) {
        $id = 'std_' . bin2hex(random_bytes(8));
        $insert = $pdo->prepare("INSERT INTO `{$table}` (`id`, `user_id`, `name`, `grade`) VALUES (?, ?, ?, ?)");
        $insert->execute([$id, $user_id, '홍길동', 5]);
        return [['id' => $id, 'user_id' => $user_id, 'name' => '홍길동', 'grade' => 5, 'created_at' => date('Y-m-d H:i:s')]];
    }

    return $students;
}

function get_active_student(PDO $pdo, $user_id = null) {
    if ($user_id === null) {
        $user_id = get_current_user_id($pdo);
    }

    $activeId = $_COOKIE[STUDENT_COOKIE_NAME] ?? null;
    $table = table('students');

    if ($activeId) {
        if ($user_id) {
            $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE `id` = ? AND `user_id` = ?");
            $stmt->execute([$activeId, $user_id]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE `id` = ?");
            $stmt->execute([$activeId]);
        }
        $found = $stmt->fetch();
        if ($found) return $found;
    }

    $all = get_all_students($pdo, $user_id);
    set_active_student_cookie($all[0]['id']);
    return $all[0];
}

function set_active_student_cookie($id) {
    setcookie(STUDENT_COOKIE_NAME, $id, time() + (86400 * 365), '/');
}

function create_student(PDO $pdo, $name, $grade, $user_id = null) {
    if ($user_id === null) {
        $user_id = get_current_user_id($pdo);
    }

    $id = 'std_' . bin2hex(random_bytes(8));
    $name = trim($name) ?: '새 학생';
    $grade = (int)$grade ?: 5;

    $table = table('students');
    $stmt = $pdo->prepare("INSERT INTO `{$table}` (`id`, `user_id`, `name`, `grade`) VALUES (?, ?, ?, ?)");
    $stmt->execute([$id, $user_id, $name, $grade]);

    set_active_student_cookie($id);
    return ['id' => $id, 'user_id' => $user_id, 'name' => $name, 'grade' => $grade];
}

function update_student(PDO $pdo, $id, $name, $grade, $user_id = null) {
    if ($user_id === null) {
        $user_id = get_current_user_id($pdo);
    }
    $name = trim($name);
    $grade = (int)$grade;

    $table = table('students');
    if ($user_id) {
        $stmt = $pdo->prepare("UPDATE `{$table}` SET `name` = ?, `grade` = ? WHERE `id` = ? AND `user_id` = ?");
        return $stmt->execute([$name, $grade, $id, $user_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE `{$table}` SET `name` = ?, `grade` = ? WHERE `id` = ?");
        return $stmt->execute([$name, $grade, $id]);
    }
}

function reset_student_data(PDO $pdo, $studentId, $user_id = null) {
    if ($user_id === null) {
        $user_id = get_current_user_id($pdo);
    }

    $t_students = table('students');
    if ($user_id) {
        $check = $pdo->prepare("SELECT `id` FROM `{$t_students}` WHERE `id` = ? AND `user_id` = ?");
        $check->execute([$studentId, $user_id]);
        if (!$check->fetch()) {
            throw new Exception("해당 학생을 초기화할 권한이 없습니다.");
        }
    }

    $t_answers = table('diagnosis_answers');
    $t_diag = table('diagnoses');
    $t_practice = table('practice_sessions');
    $t_worksheets = table('worksheets');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("DELETE a FROM `{$t_answers}` a INNER JOIN `{$t_diag}` d ON a.diagnosis_id = d.id WHERE d.student_id = ?");
        $stmt->execute([$studentId]);

        $stmt = $pdo->prepare("DELETE FROM `{$t_diag}` WHERE `student_id` = ?");
        $stmt->execute([$studentId]);

        $stmt = $pdo->prepare("DELETE FROM `{$t_practice}` WHERE `student_id` = ?");
        $stmt->execute([$studentId]);

        $stmt = $pdo->prepare("DELETE FROM `{$t_worksheets}` WHERE `student_id` = ?");
        $stmt->execute([$studentId]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function delete_student(PDO $pdo, $studentId, $user_id = null) {
    if ($user_id === null) {
        $user_id = get_current_user_id($pdo);
    }

    $t_students = table('students');
    if ($user_id) {
        $count = (int)$pdo->prepare("SELECT COUNT(*) FROM `{$t_students}` WHERE `user_id` = ?");
        $count->execute([$user_id]);
        if ($count->fetchColumn() <= 1) {
            throw new Exception("최소 한 명의 학생은 유지되어야 합니다.");
        }
    } else {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$t_students}`")->fetchColumn();
        if ($count <= 1) {
            throw new Exception("최소 한 명의 학생은 유지되어야 합니다.");
        }
    }

    reset_student_data($pdo, $studentId, $user_id);

    if ($user_id) {
        $stmt = $pdo->prepare("DELETE FROM `{$t_students}` WHERE `id` = ? AND `user_id` = ?");
        $stmt->execute([$studentId, $user_id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM `{$t_students}` WHERE `id` = ?");
        $stmt->execute([$studentId]);
    }

    if (($_COOKIE[STUDENT_COOKIE_NAME] ?? '') === $studentId) {
        if ($user_id) {
            $first = $pdo->prepare("SELECT `id` FROM `{$t_students}` WHERE `user_id` = ? ORDER BY `created_at` ASC LIMIT 1");
            $first->execute([$user_id]);
            $row = $first->fetch();
        } else {
            $row = $pdo->query("SELECT `id` FROM `{$t_students}` ORDER BY `created_at` ASC LIMIT 1")->fetch();
        }
        if ($row) {
            set_active_student_cookie($row['id']);
        }
    }

    return true;
}
