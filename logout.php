<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle both GET and POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Include session configuration
    require_once 'config/session.php';

    // Perform logout
    logout();
} else {
    // If not a POST or GET request, redirect to login
    header('Location: login.php');
    exit();
}
?>