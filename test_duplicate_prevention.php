<?php
// Test script for duplicate prevention feature
require_once 'config/database.php';

try {
    $mysqli = getDBConnection();
    
    // Test the duplicate checking query
    $exam_subject_id = 1; // Example exam subject ID
    $question_text = "Sample question text";
    $option_a = "Option A";
    $option_b = "Option B";
    $option_c = "Option C";
    $option_d = "Option D";
    $correct_answer = "A";
    
    $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM questions WHERE exam_subject_id = ? AND question_text = ? AND option_a = ? AND option_b = ? AND option_c = ? AND option_d = ? AND correct_answer = ?");
    if ($stmt) {
        $stmt->bind_param("issssss", $exam_subject_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        echo "Duplicate check result: " . $row['count'] . " duplicates found\n";
    } else {
        echo "Failed to prepare statement: " . $mysqli->error . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>