<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cbt_exam_system');

echo "<h2>Test Exam Creation</h2>";

try {
    // Connect to database
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Get admin user ID
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? AND role = 'admin'");
    $admin_email = 'admin@cbtexam.com';
    $stmt->bind_param("s", $admin_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        $admin_user_id = $admin['id'];
        echo "<p style='color: green;'>✓ Admin user found with ID: " . $admin_user_id . "</p>";
        
        // Try to create an exam
        $exam_title = "Test Exam";
        $exam_description = "This is a test exam created to verify the fix";
        
        $stmt = $mysqli->prepare("INSERT INTO exams (title, description, created_by) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $exam_title, $exam_description, $admin_user_id);
        
        if ($stmt->execute()) {
            $exam_id = $mysqli->insert_id;
            echo "<p style='color: green;'>✓ Exam created successfully with ID: " . $exam_id . "</p>";
            
            // Verify the exam was created
            $stmt = $mysqli->prepare("SELECT id, title, description, is_published, published_at, created_by, created_at FROM exams WHERE id = ?");
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $exam = $result->fetch_assoc();
                echo "<p>Exam details:</p>";
                echo "<ul>";
                echo "<li>Title: " . htmlspecialchars($exam['title']) . "</li>";
                echo "<li>Description: " . htmlspecialchars($exam['description']) . "</li>";
                echo "<li>Created by: " . $exam['created_by'] . "</li>";
                echo "</ul>";
            }
        } else {
            echo "<p style='color: red;'>✗ Error creating exam: " . $stmt->error . "</p>";
        }
        
        $stmt->close();
    } else {
        echo "<p style='color: red;'>✗ Admin user not found in database</p>";
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='admin/exam_management.php'>Go to Exam Management</a></p>";
?>