<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $since = !empty($input['since']) ? $input['since'] : null;
    $mysqli = getDBConnection();

    if (!$mysqli) {
        throw new Exception('Database connection is not available.');
    }

    $data = [
        'classes' => fetchRows($mysqli, 'classes', ['id', 'id AS server_id', 'name', 'description', 'COALESCE(marks_per_question, 1) AS marks_per_question', 'created_at AS updated_at', 'NULL AS deleted_at'], $since),
        'subjects' => fetchRows($mysqli, 'subjects', ['id', 'id AS server_id', 'name', 'subject_code', 'description', 'created_at AS updated_at', 'NULL AS deleted_at'], $since),
        'users' => fetchRows($mysqli, 'users', ['id', 'id AS server_id', 'email', 'password AS password_hash', 'full_name', 'role', 'class_id', 'subject_code', 'created_at AS updated_at', 'NULL AS deleted_at'], $since),
        'exams' => fetchRows($mysqli, 'exams', ['id', 'id AS server_id', 'title', 'description', 'is_published', 'published_at', 'created_at AS updated_at', 'NULL AS deleted_at'], $since),
        'exam_subjects' => fetchRows($mysqli, 'exam_subjects', ['id', 'id AS server_id', 'exam_id', 'subject_id', 'class_id', 'time_limit', 'is_published', 'published_at', 'COALESCE(show_calculator, 0) AS show_calculator', 'created_at AS updated_at', 'NULL AS deleted_at'], $since),
        'questions' => fetchRows($mysqli, 'questions', ['id', 'id AS server_id', 'exam_subject_id', 'COALESCE(order_position, 0) AS order_position', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'option_f', 'correct_answer', 'created_at AS updated_at', 'NULL AS deleted_at'], $since),
        'comprehension_passages' => fetchRows($mysqli, 'comprehension_passages', ['id', 'id AS server_id', 'exam_subject_id', 'passage_title', 'passage_content', 'start_question_number', 'end_question_number', 'created_at AS updated_at', 'NULL AS deleted_at'], $since),
    ];

    echo json_encode(['ok' => true, 'data' => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}

function fetchRows($mysqli, $table, $columns, $since) {
    $sql = 'SELECT ' . implode(', ', $columns) . ' FROM ' . $table;

    if ($since) {
        $sql .= ' WHERE created_at >= ?';
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('s', $since);
    } else {
        $stmt = $mysqli->prepare($sql);
    }

    if (!$stmt) {
        return [];
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        foreach ($row as $key => $value) {
            if ($value !== null) {
                $row[$key] = (string)$value;
            }
        }
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}
