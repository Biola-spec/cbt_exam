<?php
echo "<h2>Check Admin User in Database</h2>";

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
    
    // Check for admin user
    // Use explicit column names instead of SELECT *
    $stmt = $mysqli->prepare("SELECT id, email, full_name, role FROM users WHERE email = ? AND role = 'admin'");
    $stmt->bind_param("s", "admin@cbtexam.com");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        echo "<p style='color: green;'>✓ Admin user found:</p>";
        echo "<ul>";
        echo "<li>ID: " . $admin['id'] . "</li>";
        echo "<li>Email: " . htmlspecialchars($admin['email']) . "</li>";
        echo "<li>Name: " . htmlspecialchars($admin['full_name']) . "</li>";
        echo "<li>Role: " . $admin['role'] . "</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>✗ Admin user not found in database</p>";
        echo "<p>You may need to run the setup script to create the admin user.</p>";
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='login.php'>Go to Login</a></p>";
?>