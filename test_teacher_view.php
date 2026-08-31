<?php
// Simple test to check if teachers can view student results
echo "<h2>Teacher View Test</h2>";

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cbt_exam_system');

try {
    // Connect to database
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Check if we have teachers
    $stmt = $mysqli->prepare("SELECT id, email, full_name FROM users WHERE role = 'teacher' LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $teacher = $result->fetch_assoc();
        echo "<p>Found teacher: " . htmlspecialchars($teacher['full_name']) . " (ID: " . $teacher['id'] . ")</p>";
        
        // Check teacher allocations
        $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM teacher_allocations WHERE teacher_id = ?");
        $stmt->bind_param("i", $teacher['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        echo "<p>Teacher allocations: " . $row['count'] . "</p>";
        
        // Check if teacher can see exams
        $stmt = $mysqli->prepare("SELECT DISTINCT e.id, e.title 
                              FROM exams e
                              JOIN exam_subjects es ON e.id = es.exam_id
                              JOIN teacher_allocations ta ON es.class_id = ta.class_id AND es.subject_id = ta.subject_id
                              WHERE ta.teacher_id = ?");
        $stmt->bind_param("i", $teacher['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        echo "<p>Exams teacher can see: " . $result->num_rows . "</p>";
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<p>- " . htmlspecialchars($row['title']) . " (ID: " . $row['id'] . ")</p>";
            }
        }
        
        // Check if there are any student attempts for this teacher's exams
        $stmt = $mysqli->prepare("SELECT COUNT(*) as count
                              FROM exam_attempts ea
                              JOIN exam_subjects es ON ea.exam_subject_id = es.id
                              JOIN teacher_allocations ta ON es.class_id = ta.class_id AND es.subject_id = ta.subject_id
                              WHERE ta.teacher_id = ? AND ea.submitted_at IS NOT NULL");
        $stmt->bind_param("i", $teacher['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        echo "<p>Student attempts for teacher's exams: " . $row['count'] . "</p>";
        
    } else {
        echo "<p style='color: red;'>No teachers found in database</p>";
    }
    
    $stmt->close();
    $mysqli->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='teacher/dashboard.php?tab=results'>Try Teacher Results Page</a></p>";
?>