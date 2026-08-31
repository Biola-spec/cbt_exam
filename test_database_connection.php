<?php
// Test database connection
include 'config/database.php';

$mysqli = getDBConnection();
if ($mysqli) {
    echo "<h1>Database Connection Test</h1>";
    echo "<p style='color: green;'>✓ Successfully connected to database</p>";
    
    // Check if comprehension_passages table exists
    $result = $mysqli->query("SHOW TABLES LIKE 'comprehension_passages'");
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>✓ comprehension_passages table exists</p>";
        
        // Count passages
        $result = $mysqli->query("SELECT COUNT(*) as count FROM comprehension_passages");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "<p>Total passages: " . $row['count'] . "</p>";
            
            // Check for passages with base64 content
            $result = $mysqli->query("SELECT COUNT(*) as count FROM comprehension_passages WHERE passage_content LIKE '%data:image%'");
            if ($result) {
                $row = $result->fetch_assoc();
                echo "<p>Passages with base64 images: " . $row['count'] . "</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>✗ comprehension_passages table does not exist</p>";
    }
    
    $mysqli->close();
} else {
    echo "<h1>Database Connection Test</h1>";
    echo "<p style='color: red;'>✗ Could not connect to database</p>";
    echo "<p>Check your database configuration in config/database.php</p>";
}
?>