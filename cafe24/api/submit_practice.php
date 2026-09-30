<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/student.php';

$pdo = get_db();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'DB 연결 실패']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data && !empty($_POST)) {
    $data = $_POST;
}

try {
    $active_student = get_active_student($pdo);
    $studentId = $active_student['id'];

    $total = (int)($data['total'] ?? 0);
    $correct = (int)($data['correct'] ?? 0);
    $accuracy = (float)($data['accuracy'] ?? 0);
    $averageMs = (float)($data['averageMs'] ?? 0);
    $worksheetId = $data['worksheetId'] ?? null;
    $startedAt = !empty($data['startedAt']) ? date('Y-m-d H:i:s', strtotime($data['startedAt'])) : date('Y-m-d H:i:s');
    $finishedAt = !empty($data['finishedAt']) ? date('Y-m-d H:i:s', strtotime($data['finishedAt'])) : date('Y-m-d H:i:s');

    $sessionId = 'prac_' . bin2hex(random_bytes(8));
    $t_prac = table('practice_sessions');

    $stmt = $pdo->prepare("
        INSERT INTO `{$t_prac}`
        (`id`, `student_id`, `worksheet_id`, `total`, `correct`, `accuracy`, `average_ms`, `started_at`, `finished_at`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $sessionId,
        $studentId,
        $worksheetId,
        $total,
        $correct,
        $accuracy,
        $averageMs,
        $startedAt,
        $finishedAt
    ]);

    echo json_encode(['success' => true, 'sessionId' => $sessionId]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
