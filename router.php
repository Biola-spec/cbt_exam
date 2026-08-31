<?php
// Simple router script for PHP built-in server
// This script will handle routing for the PHP built-in server

// Get the requested URI
$uri = $_SERVER['REQUEST_URI'];

// Remove query string if present
if (($pos = strpos($uri, '?')) !== false) {
    $uri = substr($uri, 0, $pos);
}

// Handle the upload_image.php request
if ($uri === '/upload_image.php') {
    // Include the upload_image.php file
    include __DIR__ . '/upload_image.php';
    return true;
}

// For all other requests, let the server handle them normally
return false;
?>