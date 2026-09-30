<?php
/**
 * MathCure - Multi-Student Management for Cafe24
 */

require_once __DIR__ . '/../db.php';

const STUDENT_COOKIE_NAME = 'mathcure_cafe24_student_id';

function get_all_students(PDO $pdo) {
    $table = table('students');
    $stmt = $pdo->query("SELECT * FROM `{$table}` ORDER BY `created_at` ASC");
    $students = $stmt->fetchAll();

    if (empty($students)) {
        $id = 'std_' . bin2hex(random_bytes(8));
        $insert = $pdo->prepare("INSERT INTO `{$table}` (`id`, `name`, `grade`) VALUES (?, ?, ?)");
        $insert->execute([$id, '홍길동', 5]);
        return [['id' => $id, 'name' => '홍길동', 'grade' => 5, 'created_at' => date('Y-m-d H:i:s')]];
    }

    return $students;
}

function get_active_student(PDO $pdo) {
    $activeId = $_COOKIE[STUDENT_COOKIE_NAME] ?? null;
    $table = table('students');

    if ($activeId) {
        $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE `id` = ?");
        $stmt->execute([$activeId]);
        $found = $stmt->fetch();
        if ($found) return $found;
    }

    $all = get_all_students($pdo);
    set_active_student_cookie($all[0]['id']);
    return $all[0];
}

function set_active_student_cookie($id) {
    // 365일 유지 쿠키
    setcookie(STUDENT_COOKIE_NAME, $id, time() + (86400 * 365), '/');
}

function create_student(PDO $pdo, $name, $grade) {
    $id = 'std_' . bin2hex(random_bytes(8));
    $name = trim($name) ?: '새 학생';
    $grade = (int)$grade ?: 5;

    $table = table('students');
    $stmt = $pdo->prepare("INSERT INTO `{$table}` (`id`, `name`, `grade`) VALUES (?, ?, ?)");
    $stmt->execute([$id, $name, $grade]);

    set_active_student_cookie($id);
    return ['id' => $id, 'name' => $name, 'grade' => $grade];
}

function update_student(PDO $pdo, $id, $name, $grade) {
    $name = trim($name);
    $grade = (int)$grade;

    $table = table('students');
    $stmt = $pdo->prepare("UPDATE `{$table}` SET `name` = ?, `grade` = ? WHERE `id` = ?");
    return $stmt->execute([$name, $grade, $id]);
}

function reset_student_data(PDO $pdo, $studentId) {
    $t_answers = table('diagnosis_answers');
    $t_diag = table('diagnoses');
    $t_practice = table('practice_sessions');
    $t_worksheets = table('worksheets');

    $pdo->beginTransaction();
    try {
        // 해당 학생의 진단에 속한 답안 삭제
        $stmt = $pdo->prepare("DELETE a FROM `{$t_answers}` a INNER JOIN `{$t_diag}` d ON a.diagnosis_id = d.id WHERE d.student_id = ?");
        $stmt->execute([$studentId]);

        // 진단 삭제
        $stmt = $pdo->prepare("DELETE FROM `{$t_diag}` WHERE `student_id` = ?");
        $stmt->execute([$studentId]);

        // 연습 세션 삭제
        $stmt = $pdo->prepare("DELETE FROM `{$t_practice}` WHERE `student_id` = ?");
        $stmt->execute([$studentId]);

        // 워크시트 삭제
        $stmt = $pdo->prepare("DELETE FROM `{$t_worksheets}` WHERE `student_id` = ?");
        $stmt->execute([$studentId]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function delete_student(PDO $pdo, $studentId) {
    $t_students = table('students');
    $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$t_students}`")->fetchColumn();
    if ($count <= 1) {
        throw new Exception("최소 한 명의 학생은 유지되어야 합니다.");
    }

    reset_student_data($pdo, $studentId);

    $stmt = $pdo->prepare("DELETE FROM `{$t_students}` WHERE `id` = ?");
    $stmt->execute([$studentId]);

    // 삭제된 학생이 활성 학생이었던 경우 첫 번째 학생으로 쿠키 변경
    if (($_COOKIE[STUDENT_COOKIE_NAME] ?? '') === $studentId) {
        $first = $pdo->query("SELECT `id` FROM `{$t_students}` ORDER BY `created_at` ASC LIMIT 1")->fetch();
        if ($first) {
            set_active_student_cookie($first['id']);
        }
    }

    return true;
}
