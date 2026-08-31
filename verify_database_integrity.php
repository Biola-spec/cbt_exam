<?php
echo "<h2>Database Integrity Verification</h2>";

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
    
    // 1. Check users table
    echo "<h3>1. Users Table</h3>";
    $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    echo "<p>Total users: " . $row['count'] . "</p>";
    
    // Show admins
    $stmt = $mysqli->prepare("SELECT id, email, full_name, role FROM users WHERE role = 'admin'");
    $stmt->execute();
    $result = $stmt->get_result();
    echo "<p>Admin users:</p><ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>ID: " . $row['id'] . " - " . htmlspecialchars($row['full_name']) . " (" . htmlspecialchars($row['email']) . ")</li>";
    }
    echo "</ul>";
    
    // Show teachers
    $stmt = $mysqli->prepare("SELECT id, email, full_name, role FROM users WHERE role = 'teacher'");
    $stmt->execute();
    $result = $stmt->get_result();
    echo "<p>Teacher users:</p><ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>ID: " . $row['id'] . " - " . htmlspecialchars($row['full_name']) . " (" . htmlspecialchars($row['email']) . ")</li>";
    }
    echo "</ul>";
    
    // Show students
    $stmt = $mysqli->prepare("SELECT id, email, full_name, role FROM users WHERE role = 'student'");
    $stmt->execute();
    $result = $stmt->get_result();
    echo "<p>Student users:</p><ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>ID: " . $row['id'] . " - " . htmlspecialchars($row['full_name']) . " (" . htmlspecialchars($row['email']) . ")</li>";
    }
    echo "</ul>";
    
    // 2. Check classes table
    echo "<h3>2. Classes Table</h3>";
    $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM classes");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    echo "<p>Total classes: " . $row['count'] . "</p>";
    
    $stmt = $mysqli->prepare("SELECT id, name, description FROM classes");
    $stmt->execute();
    $result = $stmt->get_result();
    echo "<p>Classes:</p><ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>ID: " . $row['id'] . " - " . htmlspecialchars($row['name']) . " (" . htmlspecialchars($row['description']) . ")</li>";
    }
    echo "</ul>";
    
    // 3. Check subjects table
    echo "<h3>3. Subjects Table</h3>";
    $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM subjects");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    echo "<p>Total subjects: " . $row['count'] . "</p>";
    
    $stmt = $mysqli->prepare("SELECT id, name, description FROM subjects");
    $stmt->execute();
    $result = $stmt->get_result();
    echo "<p>Subjects:</p><ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>ID: " . $row['id'] . " - " . htmlspecialchars($row['name']) . " (" . htmlspecialchars($row['description']) . ")</li>";
    }
    echo "</ul>";
    
    // 4. Check teacher allocations table
    echo "<h3>4. Teacher Allocations Table</h3>";
    $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM teacher_allocations");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    echo "<p>Total teacher allocations: " . $row['count'] . "</p>";
    
    // 5. Check school_config table
    echo "<h3>5. School Configuration Table</h3>";
    $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM school_config");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    echo "<p>School configurations: " . $row['count'] . "</p>";
    
    if ($row['count'] > 0) {
        $stmt = $mysqli->prepare("SELECT id, school_name FROM school_config LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        echo "<p>School name: " . htmlspecialchars($row['school_name']) . "</p>";
    }
    
    // 6. Check exams table
    echo "<h3>6. Exams Table</h3>";
    $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM exams");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    echo "<p>Total exams: " . $row['count'] . "</p>";
    
    // 7. Check exam_subjects table
    echo "<h3>7. Exam Subjects Table</h3>";
    $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM exam_subjects");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    echo "<p>Total exam subjects: " . $row['count'] . "</p>";
    
    // 8. Check questions table
    echo "<h3>8. Questions Table</h3>";
    $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM questions");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    echo "<p>Total questions: " . $row['count'] . "</p>";
    
    // 9. Check subject_class_link table
    echo "<h3>9. Subject-Class Link Table</h3>";
    $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM subject_class_link");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    echo "<p>Total subject-class links: " . $row['count'] . "</p>";
    
    $stmt->close();
    $mysqli->close();
    
    echo "<h3>Summary</h3>";
    echo "<p style='color: green;'>✓ All database tables are accessible</p>";
    echo "<p>If all the counts above look correct, your data is properly saving to the database.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='admin/dashboard.php'>Go to Admin Dashboard</a></p>";
?>