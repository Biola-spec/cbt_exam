<?php
require_once 'config/session.php';
require_once 'config/database.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo "User is not logged in\n";
    exit();
}

echo "User is logged in\n";
echo "User ID: " . getUserId() . "\n";
echo "User Email: " . $_SESSION['email'] . "\n";
echo "User Role: " . getUserRole() . "\n";
echo "User Name: " . getUserName() . "\n";

// Test database connection
$mysqli = getDBConnection();
if (!$mysqli) {
    echo "Database connection failed\n";
    exit();
}

echo "Database connection successful\n";

// Test user lookup
$current_user_id = getUserId();
echo "Current user ID: $current_user_id\n";

$stmt = $mysqli->prepare("SELECT id, email, full_name, class_id FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($user = $result->fetch_assoc()) {
    echo "User found in database:\n";
    echo "  ID: " . $user['id'] . "\n";
    echo "  Email: " . $user['email'] . "\n";
    echo "  Full Name: " . $user['full_name'] . "\n";
    echo "  Class ID: " . $user['class_id'] . "\n";
} else {
    echo "User not found in database\n";
}
$stmt->close();
?>