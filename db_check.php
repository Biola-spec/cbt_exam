<?php
echo "<h2>Database Connection Check</h2>";

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cbt_exam_system');

echo "<p>Checking database connection...</p>";

try {
    // Test basic connection
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "<p style='color: green;'>✓ Basic connection successful</p>";
    
    // Check if database exists
    $result = $mysqli->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
    $db_exists = $result->num_rows > 0;
    
    if ($db_exists) {
        echo "<p style='color: green;'>✓ Database '" . DB_NAME . "' exists</p>";
        
        // Select database
        $mysqli->select_db(DB_NAME);
        
        // Check if users table exists
        $result = $mysqli->query("SHOW TABLES LIKE 'users'");
        $table_exists = $result->num_rows > 0;
        
        if ($table_exists) {
            echo "<p style='color: green;'>✓ Users table exists</p>";
            
            echo "<h3>Checking for admin user...</h3>";
            try {
                // Use explicit column names instead of SELECT *
                $stmt = $mysqli->prepare("SELECT id, email, full_name, role FROM users WHERE email = ? AND role = 'admin'");
                $stmt->bind_param("s", "admin@cbtexam.com");
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $result->num_rows > 0) {
                    $admin = $result->fetch_assoc();
                    echo "<p style='color: green;'>✓ Admin user found: " . htmlspecialchars($admin['email']) . " (" . htmlspecialchars($admin['full_name']) . ")</p>";
                } else {
                    echo "<p style='color: red;'>✗ Admin user not found. You may need to run the setup script.</p>";
                    $needs_setup = true;
                }
                
                $stmt->close();
            } catch (Exception $e) {
                echo "<p style='color: red;'>Error checking for admin user: " . $e->getMessage() . "</p>";
                $needs_setup = true;
            }
            
        } else {
            echo "<p style='color: red;'>✗ Users table does not exist</p>";
            echo "<p>Run setup.php to create tables</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Database '" . DB_NAME . "' does not exist</p>";
        echo "<p>Run setup.php to create database and tables</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>MySQL service is running</li>";
    echo "<li>Database credentials in 'config/database.php' are correct</li>";
    echo "<li>Database server is accessible</li>";
    echo "</ul>";
}

echo "<p><a href='login.php'>Go to Login Page</a></p>";
?>