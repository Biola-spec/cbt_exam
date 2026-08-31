<?php
require_once 'config/session.php';

// If user is logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    redirectBasedOnRole();
} else {
    // Otherwise, redirect to login page
    header('Location: login.php');
    exit();
}
?>