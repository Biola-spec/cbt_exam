<?php
echo "<h2>Check All Teachers in System</h2>";

// Check teachers in users.json file
echo "<h3>Teachers in users.json file:</h3>";
if (file_exists('users.json')) {
    $users = json_decode(file_get_contents('users.json'), true);
    if ($users) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Email</th><th>Full Name</th><th>Role</th></tr>";
        
        $teacher_count = 0;
        foreach ($users as $email => $user) {
            if ($user['role'] === 'teacher') {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($email) . "</td>";
                echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
                echo "<td>" . $user['role'] . "</td>";
                echo "</tr>";
                $teacher_count++;
            }
        }
        echo "</table>";
        echo "<p>Total teachers in file: " . $teacher_count . "</p>";
    } else {
        echo "<p>Error parsing users.json file</p>";
    }
} else {
    echo "<p>users.json file not found</p>";
}

// Check teachers in database
echo "<h3>Teachers in database:</h3>";

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
    
    // Check teachers in database
    $stmt = $mysqli->prepare("SELECT id, email, full_name, role FROM users WHERE role = 'teacher' ORDER BY id");
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
        echo "<p>No teachers found in database.</p>";
    }
    
    $stmt->close();
    $mysqli->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='admin/dashboard.php?tab=teachers'>Go to Teacher Allocation</a></p>";
?>