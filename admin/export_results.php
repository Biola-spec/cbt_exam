<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is logged in and is an admin
requireLogin();
if (getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selected_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$selected_exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

try {
    $mysqli = getDBConnection();
    
    // Build the query based on filters
    $sql = "SELECT ea.*, e.title as exam_title, c.name as class_name, s.name as subject_name, u.full_name as student_name, COALESCE(c.marks_per_question, 1) as marks_per_question,
            (SELECT COUNT(*) FROM questions q WHERE q.exam_subject_id = ea.exam_subject_id AND COALESCE(q.question_type, 'objective') = 'objective') AS objective_questions,
            (SELECT COALESCE(SUM(q.theory_marks), 0) FROM questions q WHERE q.exam_subject_id = ea.exam_subject_id AND q.question_type = 'theory') AS theory_total_marks
            FROM exam_attempts ea
            JOIN exams e ON ea.exam_id = e.id
            JOIN exam_subjects es ON ea.exam_subject_id = es.id
            JOIN classes c ON es.class_id = c.id
            JOIN subjects s ON es.subject_id = s.id
            JOIN users u ON ea.student_id = u.id
            WHERE ea.submitted_at IS NOT NULL";
    
    // Add student filter based on class if class is selected
    if ($selected_class_id > 0 && $selected_student_id > 0) {
        // Verify the student is in the selected class
        $verify_stmt = $mysqli->prepare("SELECT COUNT(*) as count 
                                        FROM exam_attempts ea
                                        JOIN exam_subjects es ON ea.exam_subject_id = es.id
                                        WHERE ea.student_id = ? AND es.class_id = ?");
        $verify_stmt->bind_param("ii", $selected_student_id, $selected_class_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        $verify_row = $verify_result->fetch_assoc();
        
        if ($verify_row['count'] == 0) {
            // Student not in this class, reset student filter
            $selected_student_id = 0;
        }
    }
    
    $params = array();
    $types = "";
    
    if ($selected_class_id > 0) {
        $sql .= " AND c.id = ?";
        $params[] = $selected_class_id;
        $types .= "i";
    }
    
    if ($selected_student_id > 0) {
        $sql .= " AND u.id = ?";
        $params[] = $selected_student_id;
        $types .= "i";
    }
    
    if ($selected_exam_id > 0) {
        $sql .= " AND e.id = ?";
        $params[] = $selected_exam_id;
        $types .= "i";
    }
    
    $sql .= " ORDER BY u.full_name, e.title, ea.submitted_at DESC";
    
    $stmt = $mysqli->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $results = array();
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="exam_results_' . date('Y-m-d_H-i-s') . '.csv"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM to handle UTF-8 in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write header row
    fputcsv($output, array(
        'Student Name',
        'Exam Title',
        'Class',
        'Subject',
        'Score Obtained',
        'Total Questions',
        'Marks Per Question',
        'Total Possible Score',
        'Percentage',
        'Submitted At'
    ));
    
    // Write data rows
    foreach ($results as $result) {
        $marks_per_question = (float)$result['marks_per_question'];
        $objective_questions = (int)($result['objective_questions'] ?? $result['total_questions']);
        $theory_total_marks = (float)($result['theory_total_marks'] ?? 0);
        $total_score = ($objective_questions * $marks_per_question) + $theory_total_marks;
        if ($total_score <= 0) {
            $total_score = (float)$result['total_questions'] * $marks_per_question;
        }
        $percentage = 0;
        if ($total_score > 0) {
            $percentage = ($result['score'] / $total_score) * 100;
        }
        
        fputcsv($output, array(
            $result['student_name'],
            $result['exam_title'],
            $result['class_name'],
            $result['subject_name'],
            $result['score'],
            $result['total_questions'],
            $marks_per_question,
            $total_score,
            round($percentage, 2) . '%',
            date('Y-m-d H:i:s', strtotime($result['submitted_at']))
        ));
    }
    
    fclose($output);
    exit();
    
} catch (Exception $e) {
    echo 'Error exporting results: ' . $e->getMessage();
}
?>