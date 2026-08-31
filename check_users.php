<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cbt_exam_system');

echo "<h2>Check Users in Database</h2>";

try {
    // Connect to database
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Check users table
    $result = $mysqli->query("SELECT id, email, full_name, role FROM users");
    
    if ($result && $result->num_rows > 0) {
        echo "<h3>Existing Users:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Email</th><th>Full Name</th><th>Role</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
            echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
            echo "<td>" . $row['role'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No users found in database.</p>";
    }
    
    // Check if admin user exists
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? AND role = 'admin'");
    $admin_email = 'admin@cbtexam.com';
    $stmt->bind_param("s", $admin_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        echo "<p style='color: green;'>✓ Admin user exists with ID: " . $admin['id'] . "</p>";
        echo "<p>You can use this admin user ID (" . $admin['id'] . ") as the created_by value when creating exams.</p>";
    } else {
        echo "<p style='color: red;'>✗ Admin user not found. You need to create an admin user first.</p>";
    }
    
    $stmt->close();
    $mysqli->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>