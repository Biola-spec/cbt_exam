<?php
// Simple fix for passage saving issue
// This script handles AJAX requests to save passages without causing HTTP 500 errors

// Capture any output errors
ob_start();

// Set JSON header
header('Content-Type: application/json');

try {
    // Check if this is a passage save request
    if (!isset($_POST['action']) || $_POST['action'] !== 'save_passage') {
        throw new Exception('Invalid action');
    }
    
    // Get POST data
    $passage_id = !empty($_POST['passage_id']) ? (int)$_POST['passage_id'] : 0;
    $exam_subject_id = !empty($_POST['exam_subject_id']) ? (int)$_POST['exam_subject_id'] : 0;
    $passage_title = !empty($_POST['passage_title']) ? trim($_POST['passage_title']) : null;
    $passage_content = !empty($_POST['passage_content']) ? $_POST['passage_content'] : '';
    $start_question = !empty($_POST['start_question']) ? (int)$_POST['start_question'] : 1;
    $end_question = !empty($_POST['end_question']) ? (int)$_POST['end_question'] : 1;
    
    // Validate required data
    if (!$exam_subject_id) {
        throw new Exception('Exam subject ID is required');
    }
    
    if (empty($passage_content)) {
        throw new Exception('Passage content cannot be empty');
    }
    
    if ($start_question < 1 || $end_question < $start_question) {
        throw new Exception('Invalid question range');
    }
    
    // Database configuration
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $dbname = 'cbt_exam_system';
    
    // Create PDO connection
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Save passage
    if ($passage_id > 0) {
        // Update existing passage
        $stmt = $pdo->prepare("UPDATE comprehension_passages SET passage_title = ?, passage_content = ?, start_question_number = ?, end_question_number = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND exam_subject_id = ?");
        $result = $stmt->execute([$passage_title, $passage_content, $start_question, $end_question, $passage_id, $exam_subject_id]);
    } else {
        // Insert new passage
        $user_id = 1; // Default user ID for now
        $stmt = $pdo->prepare("INSERT INTO comprehension_passages (exam_subject_id, passage_title, passage_content, start_question_number, end_question_number, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$exam_subject_id, $passage_title, $passage_content, $start_question, $end_question, $user_id]);
        $passage_id = $pdo->lastInsertId();
    }
    
    if ($result) {
        // Get updated list of passages
        $stmt = $pdo->prepare("SELECT id, passage_title, passage_content, start_question_number, end_question_number FROM comprehension_passages WHERE exam_subject_id = ? ORDER BY start_question_number");
        $stmt->execute([$exam_subject_id]);
        $passages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Clear any previous output
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Passage saved successfully',
            'passage_id' => $passage_id,
            'passages' => $passages
        ]);
    } else {
        throw new Exception('Failed to save passage');
    }
    
} catch (Exception $e) {
    // Clear any previous output
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Error $e) {
    // Handle PHP errors
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}

// Flush output buffer
ob_end_flush();
?>