<?php
session_start();

// Simulate teacher login
$_SESSION['user_id'] = 2; // Gbenga Tosin's ID
$_SESSION['role'] = 'teacher';
$_SESSION['full_name'] = 'Gbenga Tosin';
$_SESSION['email'] = 'gb.to@gmail.com';

echo "Teacher logged in successfully!\n";
echo "Redirecting to teacher dashboard...\n";

// Redirect to teacher dashboard
header("Location: teacher/dashboard.php");
exit();
?>