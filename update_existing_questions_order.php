<?php
// Script to update existing questions with order positions based on their creation time
require_once 'config/database.php';

try {
    $mysqli = getDBConnection();
    
    if (!$mysqli) {
        throw new Exception("Failed to connect to database");
    }
    
    // Get all distinct exam_subject_ids
    $stmt = $mysqli->prepare("SELECT DISTINCT exam_subject_id FROM questions");
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $mysqli->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $updated_count = 0;
    
    while ($row = $result->fetch_assoc()) {
        $exam_subject_id = $row['exam_subject_id'];
        
        // Get all questions for this exam subject ordered by created_at
        $stmt_questions = $mysqli->prepare("SELECT id FROM questions WHERE exam_subject_id = ? ORDER BY created_at");
        if (!$stmt_questions) {
            throw new Exception("Failed to prepare statement: " . $mysqli->error);
        }
        $stmt_questions->bind_param("i", $exam_subject_id);
        $stmt_questions->execute();
        $result_questions = $stmt_questions->get_result();
        
        $position = 0;
        while ($question_row = $result_questions->fetch_assoc()) {
            // Update each question with its order position
            $stmt_update = $mysqli->prepare("UPDATE questions SET order_position = ? WHERE id = ?");
            if (!$stmt_update) {
                throw new Exception("Failed to prepare statement: " . $mysqli->error);
            }
            $stmt_update->bind_param("ii", $position, $question_row['id']);
            $stmt_update->execute();
            $stmt_update->close();
            
            $position++;
            $updated_count++;
        }
        
        $stmt_questions->close();
    }
    
    $stmt->close();
    
    echo "Successfully updated order positions for $updated_count questions.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>