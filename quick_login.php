<?php
session_start();
require_once 'config/database.php';

$email = 'admin@cbtexam.com';
$password = 'Admin@2024';

$mysqli = getDBConnection();

if ($mysqli) {
    // Use explicit column names instead of SELECT *
    $stmt = $mysqli->prepare("SELECT id, email, password, full_name, role, created_at FROM users WHERE email = ? AND role = 'admin'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        header('Location: admin/dashboard.php');
        exit();
    } else {
        echo "Invalid credentials";
    }
} else {
    echo "Database connection failed";
}
?>