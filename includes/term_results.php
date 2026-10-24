<?php
function termResultsCsrfToken(): string {
    if (empty($_SESSION['term_results_csrf'])) {
        $_SESSION['term_results_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['term_results_csrf'];
}

function termResultsVerifyCsrf($token): bool {
    return isset($_SESSION['term_results_csrf']) && hash_equals($_SESSION['term_results_csrf'], (string)$token);
}

function termLabel(int $term): string {
    return [1 => 'First Term', 2 => 'Second Term', 3 => 'Third Term'][$term] ?? 'Term';
}

function termResultsStudentNotifications(mysqli $mysqli, int $studentId): int {
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total
        FROM term_scores ts
        WHERE ts.student_id = ? AND ts.release_status = 'approved'");
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    return $count;
}

function termResultsSessionRows(mysqli $mysqli): array {
    $rows = [];
    $result = $mysqli->query('SELECT id, name, is_current FROM academic_sessions ORDER BY is_current DESC, name DESC');
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}
