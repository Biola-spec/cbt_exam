<?php
// Simple database connection test
echo "<h2>Database Connection Test</h2>";

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cbt_exam_system');

try {
    echo "<p>Connecting to database...</p>";
    
    // Create connection
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($mysqli->connect_error) {
        echo "<p style='color: red;'>Connection failed: " . $mysqli->connect_error . "</p>";
        exit();
    }
    
    echo "<p style='color: green;'>Connected successfully</p>";
    
    // Test a simple query
    echo "<p>Running test query...</p>";
    $result = $mysqli->query("SELECT COUNT(*) as count FROM users");
    
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p style='color: green;'>Query successful. Found " . $row['count'] . " users in database.</p>";
    } else {
        echo "<p style='color: red;'>Query failed: " . $mysqli->error . "</p>";
    }
    
    // Close connection
    $mysqli->close();
    echo "<p>Connection closed.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='login.php'>Back to Login</a></p>";
?>