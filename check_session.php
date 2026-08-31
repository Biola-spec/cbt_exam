<?php
session_start();

echo "Session data:\n";
echo "User ID: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not set') . "\n";
echo "Email: " . (isset($_SESSION['email']) ? $_SESSION['email'] : 'Not set') . "\n";
echo "Full Name: " . (isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Not set') . "\n";
echo "Role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'Not set') . "\n";
echo "School Name: " . (isset($_SESSION['school_name']) ? $_SESSION['school_name'] : 'Not set') . "\n";

echo "\nAll session data:\n";
print_r($_SESSION);
?>