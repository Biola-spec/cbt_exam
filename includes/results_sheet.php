<?php
/** Shared queries and calculations for student result sheets. */

function resultSheetGetScale(mysqli $mysqli): array {
    $scale = [];
    $result = $mysqli->query('SELECT id, minimum_score, maximum_score, grade_letter, remark, is_passing FROM grade_settings ORDER BY minimum_score');
    if ($result) {
        while ($row = $result->fetch_assoc()) $scale[] = $row;
    }
    return $scale;
}

function resultSheetGrade(array $scale, ?float $percentage): ?array {
    if ($percentage === null) return null;
    $percentage = round(max(0, min(100, $percentage)), 2);
    foreach ($scale as $grade) {
        if ($percentage >= (float)$grade['minimum_score'] && $percentage <= (float)$grade['maximum_score']) return $grade;
    }
    return null;
}

function resultSheetExamList(mysqli $mysqli, int $studentId): array {
    $stmt = $mysqli->prepare("SELECT e.id, e.title, e.academic_session,
            MAX(ea.submitted_at) AS last_submitted_at,
            COUNT(*) AS subjects_submitted,
            SUM(CASE WHEN EXISTS (
                SELECT 1 FROM student_answers sa JOIN questions q ON q.id = sa.question_id
                WHERE sa.attempt_id = ea.id AND q.question_type = 'theory'
                  AND COALESCE(sa.answer_text, '') <> '' AND sa.marked_at IS NULL
            ) THEN 1 ELSE 0 END) AS pending_subjects
        FROM (
            SELECT student_id, exam_id, exam_subject_id, MAX(id) AS attempt_id
            FROM exam_attempts WHERE student_id = ? AND submitted_at IS NOT NULL
            GROUP BY student_id, exam_id, exam_subject_id
        ) latest
        JOIN exam_attempts ea ON ea.id = latest.attempt_id
        JOIN exams e ON e.id = ea.exam_id
        JOIN exam_subjects es ON es.id = ea.exam_subject_id AND es.exam_id = e.id
        WHERE e.is_published = 1 AND es.is_published = 1
        GROUP BY e.id, e.title, e.academic_session
        ORDER BY MAX(ea.submitted_at) DESC, e.title");
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function resultSheetLoad(mysqli $mysqli, int $studentId, int $examId): ?array {
    // Resolve the exam through a published subject in the student's current class
    // and an actual submitted attempt. This prevents URL-only access to arbitrary exams.
    $stmt = $mysqli->prepare("SELECT e.id, e.title, e.academic_session, u.full_name, u.admission_number,
                c.id AS class_id, c.name AS class_name, sc.school_name
            FROM exams e
            JOIN exam_subjects access_es ON access_es.exam_id = e.id AND access_es.is_published = 1
            JOIN users u ON u.id = ? AND u.role = 'student'
            JOIN classes c ON c.id = access_es.class_id
            JOIN exam_attempts access_ea ON access_ea.exam_id = e.id AND access_ea.student_id = u.id
                AND access_ea.exam_subject_id = access_es.id AND access_ea.submitted_at IS NOT NULL
            LEFT JOIN school_config sc ON sc.id = (SELECT MIN(id) FROM school_config)
            WHERE e.id = ? AND e.is_published = 1 LIMIT 1");
    $stmt->bind_param('ii', $studentId, $examId);
    $stmt->execute();
    $exam = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$exam) return null;

    $stmt = $mysqli->prepare("SELECT es.id AS exam_subject_id, s.name AS subject_name, es.class_id,
                ea.id AS attempt_id, ea.score, ea.submitted_at, c.marks_per_question,
                stats.objective_count, stats.theory_total,
                COALESCE(pending.pending_count, 0) AS pending_theory
            FROM exam_subjects es
            JOIN subjects s ON s.id = es.subject_id
            JOIN classes c ON c.id = es.class_id
            LEFT JOIN (
                SELECT x.exam_subject_id, MAX(x.id) AS attempt_id
                FROM exam_attempts x
                WHERE x.student_id = ? AND x.exam_id = ? AND x.submitted_at IS NOT NULL
                GROUP BY x.exam_subject_id
            ) latest ON latest.exam_subject_id = es.id
            LEFT JOIN exam_attempts ea ON ea.id = latest.attempt_id
            LEFT JOIN (
                SELECT q.exam_subject_id,
                    SUM(CASE WHEN COALESCE(q.question_type,'objective')='objective' THEN 1 ELSE 0 END) AS objective_count,
                    SUM(CASE WHEN q.question_type='theory' THEN COALESCE(q.theory_marks,0) ELSE 0 END) AS theory_total
                FROM questions q GROUP BY q.exam_subject_id
            ) stats ON stats.exam_subject_id = es.id
            LEFT JOIN (
                SELECT sa.attempt_id, COUNT(*) AS pending_count
                FROM student_answers sa JOIN questions q ON q.id=sa.question_id
                WHERE q.question_type='theory' AND COALESCE(sa.answer_text,'')<>'' AND sa.marked_at IS NULL
                GROUP BY sa.attempt_id
            ) pending ON pending.attempt_id = ea.id
            WHERE es.exam_id = ? AND es.class_id = ? AND es.is_published = 1
            ORDER BY s.name");
    $stmt->bind_param('iiii', $studentId, $examId, $examId, $exam['class_id']);
    $stmt->execute();
    $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $scale = resultSheetGetScale($mysqli);
    $available = 0; $taken = 0; $passed = 0; $failed = 0; $total = 0.0; $percentageTotal = 0.0; $allReleased = true;
    foreach ($subjects as &$subject) {
        $subject['status'] = 'No result';
        $subject['score_released'] = false;
        $subject['max_score'] = ((int)($subject['objective_count'] ?? 0) * (float)$subject['marks_per_question']) + (float)($subject['theory_total'] ?? 0);
        $subject['percentage'] = null; $subject['grade'] = null;
        if (empty($subject['attempt_id'])) { $allReleased = false; continue; }
        $taken++;
        if ((int)$subject['pending_theory'] > 0 || $subject['score'] === null || $subject['max_score'] <= 0) {
            $subject['status'] = 'Pending'; $allReleased = false; continue;
        }
        $subject['percentage'] = round(((float)$subject['score'] / $subject['max_score']) * 100, 2);
        $subject['grade'] = resultSheetGrade($scale, $subject['percentage']);
        $subject['score_released'] = true;
        $subject['status'] = $subject['grade'] ? 'Released' : 'Grade unavailable';
        $available++;
        $total += (float)$subject['score'];
        $percentageTotal += (float)$subject['percentage'];
        if ($subject['grade']) {
            if ((int)$subject['grade']['is_passing'] === 1) $passed++; else $failed++;
        }
    }
    unset($subject);

    return [
        'exam' => $exam, 'subjects' => $subjects, 'scale' => $scale,
        'summary' => [
            'count' => $taken, 'exam_subjects' => count($subjects), 'available' => $available, 'passed' => $passed, 'failed' => $failed,
            'total' => $total, 'average' => $available ? $percentageTotal / $available : null,
            'all_released' => $allReleased && $available > 0,
        ],
    ];
}
