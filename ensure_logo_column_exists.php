<?php
// This script ensures the logo_path column exists in the school_config table
require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Ensure Logo Column Exists</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
<div class='container mt-4'>
    <h2>Database Update - Ensure Logo Column Exists</h2>";

try {
    $mysqli = getDBConnection();
    
    if ($mysqli) {
        echo "<p class='text-success'>✓ Database connection successful</p>";
        
        // Check if the column already exists
        $check_query = "SHOW COLUMNS FROM `school_config` LIKE 'logo_path'";
        $result = $mysqli->query($check_query);
        
        if ($result && $result->num_rows > 0) {
            echo "<p class='text-success'>✓ logo_path column already exists in school_config table</p>";
        } else {
            // Add the logo_path column
            $alter_query = "ALTER TABLE `school_config` ADD COLUMN `logo_path` VARCHAR(255) NULL DEFAULT NULL AFTER `default_time_limit`";
            
            if ($mysqli->query($alter_query)) {
                echo "<p class='text-success'>✓ Successfully added 'logo_path' column to school_config table</p>";
            } else {
                echo "<p class='text-danger'>✗ Error adding 'logo_path' column: " . $mysqli->error . "</p>";
            }
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            if (mkdir($upload_dir, 0755, true)) {
                echo "<p class='text-success'>✓ Created uploads directory</p>";
            } else {
                echo "<p class='text-danger'>✗ Failed to create uploads directory</p>";
            }
        } else {
            echo "<p class='text-success'>✓ Uploads directory already exists</p>";
            
            // Check if it's writable
            if (is_writable($upload_dir)) {
                echo "<p class='text-success'>✓ Uploads directory is writable</p>";
            } else {
                echo "<p class='text-warning'>⚠ Uploads directory is not writable. Please check permissions.</p>";
            }
        }
        
        $mysqli->close();
    } else {
        echo "<p class='text-danger'>✗ Failed to connect to database</p>";
        echo "<p>Please check your database configuration in config/database.php</p>";
    }
} catch (Exception $e) {
    echo "<p class='text-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<div class='mt-4'>
    <a href='login.php' class='btn btn-primary'>Go to Login Page</a>
    <a href='admin/dashboard.php' class='btn btn-secondary'>Go to Admin Dashboard</a>
</div>";

echo "</div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>";
?>