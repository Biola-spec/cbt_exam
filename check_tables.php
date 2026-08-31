<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cbt_exam_system');

echo "<h2>Database Tables Check</h2>";

try {
    // Connect to database
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Show all tables
    $result = $mysqli->query("SHOW TABLES");
    if ($result) {
        echo "<h3>Existing Tables:</h3>";
        echo "<ul>";
        while ($row = $result->fetch_array()) {
            echo "<li>" . $row[0] . "</li>";
        }
        echo "</ul>";
    }
    
    // Check if subject_class_link table exists
    $result = $mysqli->query("SHOW TABLES LIKE 'subject_class_link'");
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>✓ subject_class_link table exists</p>";
    } else {
        echo "<p style='color: red;'>✗ subject_class_link table does not exist</p>";
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>