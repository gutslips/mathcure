<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/student.php';

$pdo = get_db();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => '데이터베이스 연결 실패']);
    exit;
}

$user = get_logged_in_user($pdo);
if (!$user || $user['status'] !== 'approved') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '로그인이 필요합니다.']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'switch') {
        $studentId = $_POST['student_id'] ?? '';
        if (!$studentId) throw new Exception('학생 ID가 필요합니다.');
        set_active_student_cookie($studentId);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $grade = (int)($_POST['grade'] ?? 5);
        if (!$name) throw new Exception('학생 이름을 입력해주세요.');
        $created = create_student($pdo, $name, $grade, $user['id']);
        echo json_encode(['success' => true, 'student' => $created]);
        exit;
    }

    if ($action === 'update') {
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $grade = (int)($_POST['grade'] ?? 5);
        if (!$id || !$name) throw new Exception('유효하지 않은 요청입니다.');
        update_student($pdo, $id, $name, $grade, $user['id']);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'reset') {
        $studentId = $_POST['student_id'] ?? '';
        if (!$studentId) throw new Exception('학생 ID가 필요합니다.');
        reset_student_data($pdo, $studentId, $user['id']);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $studentId = $_POST['student_id'] ?? '';
        if (!$studentId) throw new Exception('학생 ID가 필요합니다.');
        delete_student($pdo, $studentId, $user['id']);
        echo json_encode(['success' => true]);
        exit;
    }

    throw new Exception('알 수 없는 작업 요청입니다.');
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
