<?php
// Script to add order_position column to questions table
require_once 'config/database.php';

echo "<h2>Adding order_position Column to Questions Table</h2>";

try {
    $mysqli = getDBConnection();
    
    if (!$mysqli) {
        throw new Exception("Failed to connect to database");
    }
    
    // Check if column already exists
    $check_stmt = $mysqli->prepare("SHOW COLUMNS FROM questions LIKE 'order_position'");
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>✓ Column 'order_position' already exists</p>";
    } else {
        echo "<p>Adding 'order_position' column...</p>";
        
        // Add the column
        $sql = "ALTER TABLE questions ADD COLUMN order_position INT(11) DEFAULT NULL AFTER exam_subject_id";
        if ($mysqli->query($sql)) {
            echo "<p style='color: green;'>✓ Column 'order_position' added successfully</p>";
        } else {
            throw new Exception("Error adding column: " . $mysqli->error);
        }
        
        // Add index for better performance
        echo "<p>Adding index for performance optimization...</p>";
        $index_sql = "ALTER TABLE questions ADD INDEX idx_questions_order_position (exam_subject_id, order_position)";
        if ($mysqli->query($index_sql)) {
            echo "<p style='color: green;'>✓ Index added successfully</p>";
        } else {
            // Index might already exist, that's okay
            echo "<p style='color: orange;'>⚠ Index may already exist (this is okay): " . $mysqli->error . "</p>";
        }
    }
    
    // Update existing questions to have sequential order positions
    echo "<p>Updating existing questions with order positions...</p>";
    
    $exam_subjects = $mysqli->query("SELECT DISTINCT exam_subject_id FROM questions");
    $updated_count = 0;
    
    while ($subject = $exam_subjects->fetch_assoc()) {
        $exam_subject_id = $subject['exam_subject_id'];
        
        // Get questions for this exam subject ordered by id
        $questions = $mysqli->query("SELECT id FROM questions WHERE exam_subject_id = $exam_subject_id ORDER BY id");
        $position = 1;
        
        while ($question = $questions->fetch_assoc()) {
            $update_stmt = $mysqli->prepare("UPDATE questions SET order_position = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $position, $question['id']);
            $update_stmt->execute();
            $position++;
            $updated_count++;
        }
    }
    
    echo "<p style='color: green;'>✓ Updated $updated_count questions with order positions</p>";
    
    echo "<hr>";
    echo "<h3>✅ Database update completed successfully!</h3>";
    echo "<p><a href='teacher/question_management.php'>Return to Question Management</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
}
?>
