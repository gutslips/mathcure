<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/student.php';
require_once __DIR__ . '/../lib/diagnosis.php';

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
    $answers = $data['answers'] ?? [];
    if (empty($answers)) {
        throw new Exception('제출된 답안이 없습니다.');
    }

    $active_student = get_active_student($pdo);
    $studentId = $active_student['id'];

    $startedAt = !empty($data['startedAt']) ? date('Y-m-d H:i:s', strtotime($data['startedAt'])) : date('Y-m-d H:i:s');
    $finishedAt = !empty($data['finishedAt']) ? date('Y-m-d H:i:s', strtotime($data['finishedAt'])) : date('Y-m-d H:i:s');

    $analysis = analyze_diagnosis($answers);

    $diagId = 'diag_' . bin2hex(random_bytes(8));
    $t_diag = table('diagnoses');
    $t_ans = table('diagnosis_answers');

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO `{$t_diag}` 
        (`id`, `student_id`, `total`, `correct`, `accuracy`, `average_ms`, `started_at`, `finished_at`) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $diagId,
        $studentId,
        $analysis['total'],
        $analysis['correct'],
        $analysis['accuracy'],
        $analysis['averageMs'],
        $startedAt,
        $finishedAt
    ]);

    $ansStmt = $pdo->prepare("
        INSERT INTO `{$t_ans}` 
        (`id`, `diagnosis_id`, `question`, `answer`, `user_answer`, `correct`, `elapsed_ms`, `type`, `difficulty`) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($answers as $ans) {
        $ansId = 'da_' . bin2hex(random_bytes(8));
        $userAns = isset($ans['userAnswer']) && $ans['userAnswer'] !== '' ? (int)$ans['userAnswer'] : null;
        $isCorrect = !empty($ans['correct']) ? 1 : 0;
        $elapsedMs = (int)($ans['elapsedMs'] ?? 0);
        $type = $ans['type'] ?? 'mixed';
        $difficulty = (int)($ans['difficulty'] ?? 2);

        $ansStmt->execute([
            $ansId,
            $diagId,
            $ans['question'],
            (int)$ans['answer'],
            $userAns,
            $isCorrect,
            $elapsedMs,
            $type,
            $difficulty
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'diagnosisId' => $diagId,
        'analysis' => $analysis
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
