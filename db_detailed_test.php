<?php
// Detailed database connection test
echo "<h2>Detailed Database Connection Test</h2>";

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
    
    // Check MySQL version
    $version = $mysqli->server_info;
    echo "<p>MySQL Version: " . $version . "</p>";
    
    // Check connection timeouts
    $result = $mysqli->query("SHOW VARIABLES LIKE 'wait_timeout'");
    if ($result && $row = $result->fetch_assoc()) {
        echo "<p>Wait Timeout: " . $row['Value'] . " seconds</p>";
    }
    
    $result = $mysqli->query("SHOW VARIABLES LIKE 'interactive_timeout'");
    if ($result && $row = $result->fetch_assoc()) {
        echo "<p>Interactive Timeout: " . $row['Value'] . " seconds</p>";
    }
    
    // Check max packet size
    $result = $mysqli->query("SHOW VARIABLES LIKE 'max_allowed_packet'");
    if ($result && $row = $result->fetch_assoc()) {
        $packet_size = $row['Value'];
        $packet_mb = round($packet_size / (1024 * 1024), 2);
        echo "<p>Max Allowed Packet: " . $packet_size . " bytes (" . $packet_mb . " MB)</p>";
    }
    
    // Test a simple query
    echo "<p>Running test query...</p>";
    $result = $mysqli->query("SELECT COUNT(*) as count FROM users");
    
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p style='color: green;'>Query successful. Found " . $row['count'] . " users in database.</p>";
    } else {
        echo "<p style='color: red;'>Query failed: " . $mysqli->error . "</p>";
    }
    
    // Test prepared statement (this is what was failing)
    echo "<p>Testing prepared statement...</p>";
    $stmt = $mysqli->prepare("SELECT logo_path FROM school_config LIMIT 1");
    if ($stmt) {
        echo "<p style='color: green;'>Prepared statement created successfully</p>";
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            echo "<p style='color: green;'>Prepared statement executed successfully</p>";
            echo "<p>Logo path: " . ($row['logo_path'] ? htmlspecialchars($row['logo_path']) : 'Not set') . "</p>";
        } else {
            echo "<p>No school config found, but query executed successfully</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color: red;'>Failed to create prepared statement: " . $mysqli->error . "</p>";
    }
    
    // Close connection
    $mysqli->close();
    echo "<p>Connection closed.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='login.php'>Back to Login</a></p>";
?>