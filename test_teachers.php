<?php
echo "<h2>Test Teachers Management</h2>";

// Load users from JSON file
$users_file = 'users.json';

if (file_exists($users_file)) {
    $users = json_decode(file_get_contents($users_file), true);
    
    echo "<h3>Current Users:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Email</th><th>Full Name</th><th>Role</th></tr>";
    
    foreach ($users as $email => $user) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($email) . "</td>";
        echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
        echo "<td>" . $user['role'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Count teachers
    $teacher_count = 0;
    foreach ($users as $user) {
        if ($user['role'] === 'teacher') {
            $teacher_count++;
        }
    }
    
    echo "<p>Total Teachers: " . $teacher_count . "</p>";
} else {
    echo "<p>Users file not found.</p>";
}

echo "<p><a href='admin/add_teacher.php'>Add New Teacher</a></p>";
echo "<p><a href='admin/dashboard.php?tab=teachers'>Teacher Allocation</a></p>";
?>