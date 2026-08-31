<?php
// Check database connection
include 'config/database.php';

echo "<h1>Database Connection Check</h1>";

$mysqli = getDBConnection();
if ($mysqli) {
    echo "<p style='color: green;'>✓ Successfully connected to database</p>";
    
    // Check if the database exists
    $result = $mysqli->query("SHOW DATABASES LIKE 'cbt_exam_system'");
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>✓ Database 'cbt_exam_system' exists</p>";
        
        // Check if the comprehension_passages table exists
        $result = $mysqli->query("SHOW TABLES LIKE 'comprehension_passages'");
        if ($result && $result->num_rows > 0) {
            echo "<p style='color: green;'>✓ Table 'comprehension_passages' exists</p>";
            
            // Count the number of passages
            $result = $mysqli->query("SELECT COUNT(*) as count FROM comprehension_passages");
            if ($result) {
                $row = $result->fetch_assoc();
                echo "<p>Total passages: " . $row['count'] . "</p>";
                
                // Check for passages with base64 images
                $result = $mysqli->query("SELECT COUNT(*) as count FROM comprehension_passages WHERE passage_content LIKE '%data:image%'");
                if ($result) {
                    $row = $result->fetch_assoc();
                    echo "<p>Passages with base64 images: " . $row['count'] . "</p>";
                }
            }
        } else {
            echo "<p style='color: red;'>✗ Table 'comprehension_passages' does not exist</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Database 'cbt_exam_system' does not exist</p>";
    }
    
    $mysqli->close();
} else {
    echo "<p style='color: red;'>✗ Could not connect to database</p>";
    echo "<p>Possible issues:</p>";
    echo "<ul>";
    echo "<li>MySQL service is not running</li>";
    echo "<li>Database credentials in 'config/database.php' are incorrect</li>";
    echo "<li>Database 'cbt_exam_system' has not been created</li>";
    echo "</ul>";
    
    // Show the database configuration
    echo "<h2>Database Configuration:</h2>";
    echo "<ul>";
    echo "<li>Host: " . DB_HOST . "</li>";
    echo "<li>User: " . DB_USER . "</li>";
    echo "<li>Password: " . (DB_PASS ? "[HIDDEN]" : "[EMPTY]") . "</li>";
    echo "<li>Database: " . DB_NAME . "</li>";
    echo "</ul>";
}
?>