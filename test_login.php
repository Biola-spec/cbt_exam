<?php
require_once 'config/database.php';

$email = 'admin@cbtexam.com';
$password = 'Admin@2024';
$role = 'admin';

try {
    $mysqli = getDBConnection();
    
    if ($mysqli) {
        // Use explicit column names instead of SELECT *
        $stmt = $mysqli->prepare("SELECT id, email, password, full_name, role FROM users WHERE email = ? AND role = ?");
        $stmt->bind_param("ss", $email, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user) {
            echo "User found:\n";
            echo "ID: " . $user['id'] . "\n";
            echo "Email: " . $user['email'] . "\n";
            echo "Name: " . $user['full_name'] . "\n";
            echo "Role: " . $user['role'] . "\n";
            echo "Password hash: " . $user['password'] . "\n";
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                echo "Password verification: SUCCESS\n";
            } else {
                echo "Password verification: FAILED\n";
            }
        } else {
            echo "User not found\n";
        }
    } else {
        echo "Database connection failed\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>