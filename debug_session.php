<?php
session_start();

echo "<h2>Debug Session Information</h2>";

echo "<h3>Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

if (isset($_SESSION['user_id'])) {
    echo "<p>Session User ID: " . $_SESSION['user_id'] . "</p>";
    echo "<p>Session User ID Type: " . gettype($_SESSION['user_id']) . "</p>";
    
    // Check if it's a valid integer
    if (is_numeric($_SESSION['user_id'])) {
        echo "<p style='color: green;'>User ID is numeric</p>";
    } else {
        echo "<p style='color: red;'>User ID is NOT numeric (it's a string)</p>";
    }
}

// Check users in database
echo "<h3>Users in Database:</h3>";

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
    
    // Check all users in database
    $stmt = $mysqli->prepare("SELECT id, email, full_name, role FROM users ORDER BY id");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
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
    
    $stmt->close();
    $mysqli->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='admin/dashboard.php?tab=teachers'>Go to Teacher Allocation</a></p>";
?>