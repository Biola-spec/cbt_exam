<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $changes = isset($input['changes']) && is_array($input['changes']) ? $input['changes'] : [];
    $mysqli = getDBConnection();

    if (!$mysqli) {
        throw new Exception('Database connection is not available.');
    }

    $accepted = [];
    $serverIds = [
        'exam_attempts' => [],
        'student_answers' => []
    ];
    $mysqli->begin_transaction();
    ensureSyncMap($mysqli);

    foreach ($changes as $change) {
        $entityType = $change['entity_type'] ?? '';
        $entityId = $change['entity_id'] ?? '';
        $payload = $change['payload'] ?? [];

        if ($entityType === 'exam_attempts') {
            $serverId = upsertAttempt($mysqli, $entityId, $payload);
            if ($serverId) {
                $serverIds['exam_attempts'][$entityId] = $serverId;
            }
            $accepted[] = $change['id'];
        } elseif ($entityType === 'student_answers') {
            $serverId = upsertAnswer($mysqli, $entityId, $payload);
            if ($serverId) {
                $serverIds['student_answers'][$entityId] = $serverId;
            }
            $accepted[] = $change['id'];
        }
    }

    $mysqli->commit();
    echo json_encode(['ok' => true, 'accepted_ids' => $accepted, 'server_ids' => $serverIds]);
} catch (Exception $e) {
    if (isset($mysqli) && $mysqli) {
        $mysqli->rollback();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}

function ensureSyncMap($mysqli) {
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS cbt_sync_map (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(64) NOT NULL,
            local_id VARCHAR(128) NOT NULL,
            server_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_entity_local (entity_type, local_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function upsertAttempt($mysqli, $localId, $payload) {
    $mappedId = findMappedServerId($mysqli, 'exam_attempts', $localId);
    $serverStudentId = (int)($payload['student_server_id'] ?? $payload['student_id'] ?? 0);
    $serverExamId = (int)($payload['exam_server_id'] ?? $payload['exam_id'] ?? 0);
    $serverExamSubjectId = (int)($payload['exam_subject_server_id'] ?? $payload['exam_subject_id'] ?? 0);

    if (!$serverStudentId || !$serverExamId || !$serverExamSubjectId) {
        return null;
    }

    $startedAt = dbDate($payload['started_at'] ?? null);
    $submittedAt = !empty($payload['submitted_at']) ? dbDate($payload['submitted_at']) : null;
    $score = isset($payload['score']) ? (float)$payload['score'] : null;
    $totalQuestions = (int)($payload['total_questions'] ?? 0);

    if ($mappedId) {
        $stmt = $mysqli->prepare("
            UPDATE exam_attempts
            SET submitted_at = ?, score = ?, total_questions = ?
            WHERE id = ?
        ");
        $stmt->bind_param('sdii', $submittedAt, $score, $totalQuestions, $mappedId);
        $stmt->execute();
        $stmt->close();
        return $mappedId;
    }

    $stmt = $mysqli->prepare("
        INSERT INTO exam_attempts (student_id, exam_id, exam_subject_id, started_at, submitted_at, score, total_questions)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iiissdi', $serverStudentId, $serverExamId, $serverExamSubjectId, $startedAt, $submittedAt, $score, $totalQuestions);
    $stmt->execute();
    $serverId = $mysqli->insert_id;
    $stmt->close();
    saveSyncMap($mysqli, 'exam_attempts', $localId, $serverId);
    return $serverId;
}

function upsertAnswer($mysqli, $localId, $payload) {
    $mappedId = findMappedServerId($mysqli, 'student_answers', $localId);
    $serverAttemptId = (int)($payload['attempt_server_id'] ?? $payload['attempt_id'] ?? 0);
    $serverQuestionId = (int)($payload['question_server_id'] ?? $payload['question_id'] ?? 0);

    if (!$serverAttemptId || !$serverQuestionId) {
        return null;
    }

    if ($mappedId) {
        $stmt = $mysqli->prepare("
            UPDATE student_answers
            SET selected_answer = ?, is_correct = ?, answered_at = ?
            WHERE id = ?
        ");
        $selectedAnswer = $payload['selected_answer'] ?? null;
        $isCorrect = isset($payload['is_correct']) ? (int)$payload['is_correct'] : null;
        $answeredAt = dbDate($payload['answered_at'] ?? null);
        $stmt->bind_param('sisi', $selectedAnswer, $isCorrect, $answeredAt, $mappedId);
        $stmt->execute();
        $stmt->close();
        return $mappedId;
    }

    $stmt = $mysqli->prepare("
        INSERT INTO student_answers (attempt_id, question_id, selected_answer, is_correct, answered_at)
        VALUES (?, ?, ?, ?, ?)
    ");

    $selectedAnswer = $payload['selected_answer'] ?? null;
    $isCorrect = isset($payload['is_correct']) ? (int)$payload['is_correct'] : null;
    $answeredAt = dbDate($payload['answered_at'] ?? null);

    $stmt->bind_param('iisis', $serverAttemptId, $serverQuestionId, $selectedAnswer, $isCorrect, $answeredAt);
    $stmt->execute();
    $serverId = $mysqli->insert_id;
    $stmt->close();
    saveSyncMap($mysqli, 'student_answers', $localId, $serverId);
    return $serverId;
}

function findMappedServerId($mysqli, $entityType, $localId) {
    if (!$localId) return null;

    $stmt = $mysqli->prepare("SELECT server_id FROM cbt_sync_map WHERE entity_type = ? AND local_id = ? LIMIT 1");
    $stmt->bind_param('ss', $entityType, $localId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int)$row['server_id'] : null;
}

function saveSyncMap($mysqli, $entityType, $localId, $serverId) {
    $stmt = $mysqli->prepare("
        INSERT INTO cbt_sync_map (entity_type, local_id, server_id)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE server_id = VALUES(server_id)
    ");
    $stmt->bind_param('ssi', $entityType, $localId, $serverId);
    $stmt->execute();
    $stmt->close();
}

function dbDate($value) {
    if (!$value) {
        return date('Y-m-d H:i:s');
    }

    return date('Y-m-d H:i:s', strtotime($value));
}
