<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cbt_exam_system');

echo "<h2>Test Subject-Class Link</h2>";

try {
    // Connect to database
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Check if we have any subjects and classes
    $subjects_result = $mysqli->query("SELECT id, name FROM subjects LIMIT 1");
    $classes_result = $mysqli->query("SELECT id, name FROM classes LIMIT 1");
    
    if ($subjects_result && $classes_result && $subjects_result->num_rows > 0 && $classes_result->num_rows > 0) {
        $subject = $subjects_result->fetch_assoc();
        $class = $classes_result->fetch_assoc();
        
        echo "<p>Testing with Subject: " . htmlspecialchars($subject['name']) . " (ID: " . $subject['id'] . ")</p>";
        echo "<p>Testing with Class: " . htmlspecialchars($class['name']) . " (ID: " . $class['id'] . ")</p>";
        
        // Try to insert a subject-class link
        $stmt = $mysqli->prepare("INSERT IGNORE INTO subject_class_link (subject_id, class_id) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param("ii", $subject['id'], $class['id']);
            if ($stmt->execute()) {
                echo "<p style='color: green;'>✓ Subject-Class link created successfully!</p>";
            } else {
                echo "<p style='color: red;'>✗ Error creating subject-class link: " . $stmt->error . "</p>";
            }
            $stmt->close();
        } else {
            echo "<p style='color: red;'>✗ Error preparing statement: " . $mysqli->error . "</p>";
        }
    } else {
        echo "<p>No subjects or classes found in database. Please create some first.</p>";
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='admin/subject_management.php'>Go to Subject Management</a></p>";
?>