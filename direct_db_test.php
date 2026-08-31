<?php
// Direct database connection test
echo "Testing direct database connection...\n";

// Database configuration from config/database.php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'cbt_exam_system';

try {
    // Create connection using MySQLi
    $mysqli = new mysqli($host, $user, $pass, $dbname);
    
    // Check connection
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "✓ Database connection successful\n";
    
    // Test a simple query
    $result = $mysqli->query("SELECT VERSION() as version");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "MySQL Version: " . $row['version'] . "\n";
    }
    
    // Test inserting a comprehension passage
    $exam_subject_id = 1;
    $passage_title = "Direct Test Passage";
    $passage_content = "This is a test passage inserted directly for debugging.";
    $start_question = 1;
    $end_question = 5;
    $user_id = 1;
    
    $stmt = $mysqli->prepare("INSERT INTO comprehension_passages (exam_subject_id, passage_title, passage_content, start_question_number, end_question_number, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $mysqli->error);
    }
    
    $stmt->bind_param("issiii", $exam_subject_id, $passage_title, $passage_content, $start_question, $end_question, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }
    
    $passage_id = $mysqli->insert_id;
    echo "✓ Successfully inserted test passage with ID: $passage_id\n";
    
    // Retrieve the passage to verify
    $stmt2 = $mysqli->prepare("SELECT id, passage_title, passage_content, start_question_number, end_question_number FROM comprehension_passages WHERE id = ?");
    if (!$stmt2) {
        throw new Exception("Failed to prepare select statement: " . $mysqli->error);
    }
    
    $stmt2->bind_param("i", $passage_id);
    $stmt2->execute();
    $result = $stmt2->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo "✓ Successfully retrieved test passage\n";
        echo "Title: " . $row['passage_title'] . "\n";
        echo "Content: " . $row['passage_content'] . "\n";
        echo "Start Question: " . $row['start_question_number'] . "\n";
        echo "End Question: " . $row['end_question_number'] . "\n";
    } else {
        echo "✗ Failed to retrieve test passage\n";
    }
    
    // Clean up - delete the test passage
    $stmt3 = $mysqli->prepare("DELETE FROM comprehension_passages WHERE id = ?");
    if ($stmt3) {
        $stmt3->bind_param("i", $passage_id);
        $stmt3->execute();
        $stmt3->close();
        echo "✓ Cleaned up test passage\n";
    }
    
    $stmt2->close();
    $stmt->close();
    $mysqli->close();
    
    echo "\n✓ All tests passed! The database connection is working correctly.\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "File: " . $e->getFile() . "\n";
}
?>