<?php
// This script is for manually testing the logo functionality
// It will try to add a test logo path to the database

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Manual Logo Test</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
<div class='container mt-4'>
    <h2>Manual Logo Test</h2>";

$mysqli = getDBConnection();

if ($mysqli) {
    echo "<p class='text-success'>✓ Database connection successful</p>";
    
    // Check if school_config table exists
    $result = $mysqli->query("SHOW TABLES LIKE 'school_config'");
    if ($result && $result->num_rows > 0) {
        echo "<p class='text-success'>✓ school_config table exists</p>";
        
        // Check if logo_path column exists
        $result = $mysqli->query("SHOW COLUMNS FROM `school_config` LIKE 'logo_path'");
        if ($result && $result->num_rows > 0) {
            echo "<p class='text-success'>✓ logo_path column exists</p>";
            
            // Try to update the first record with a test logo path
            $test_logo_path = 'uploads/test_logo.png';
            $stmt = $mysqli->prepare("UPDATE school_config SET logo_path = ? WHERE id = (SELECT id FROM (SELECT id FROM school_config LIMIT 1) as t)");
            
            if ($stmt) {
                $stmt->bind_param("s", $test_logo_path);
                if ($stmt->execute()) {
                    echo "<p class='text-success'>✓ Test logo path added to database</p>";
                    echo "<p>Test logo path: " . htmlspecialchars($test_logo_path) . "</p>";
                } else {
                    echo "<p class='text-danger'>✗ Failed to update logo path: " . $stmt->error . "</p>";
                }
                $stmt->close();
            } else {
                echo "<p class='text-danger'>✗ Failed to prepare statement: " . $mysqli->error . "</p>";
            }
            
            // Verify the update
            $stmt = $mysqli->prepare("SELECT id, school_name, logo_path FROM school_config LIMIT 1");
            if ($stmt) {
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    echo "<h3>Current School Configuration:</h3>";
                    echo "<p>ID: " . htmlspecialchars($row['id']) . "</p>";
                    echo "<p>School Name: " . htmlspecialchars($row['school_name']) . "</p>";
                    echo "<p>Logo Path: " . ($row['logo_path'] ? htmlspecialchars($row['logo_path']) : '<em>Not set</em>') . "</p>";
                }
                $stmt->close();
            }
        } else {
            echo "<p class='text-warning'>⚠ logo_path column does not exist</p>";
            echo "<p>Please run the database update to add this column.</p>";
            echo "<p>You can run this SQL command in PHPMyAdmin:</p>";
            echo "<pre>ALTER TABLE `school_config` ADD COLUMN `logo_path` VARCHAR(255) NULL DEFAULT NULL AFTER `default_time_limit`;</pre>";
        }
    } else {
        echo "<p class='text-danger'>✗ school_config table does not exist</p>";
    }
    
    $mysqli->close();
} else {
    echo "<p class='text-danger'>✗ Database connection failed</p>";
    echo "<p>Please check your database configuration in config/database.php</p>";
}

echo "<div class='mt-4'>
    <a href='login.php' class='btn btn-primary'>Go to Login Page</a>
    <a href='admin/dashboard.php' class='btn btn-secondary'>Go to Admin Dashboard</a>
    <a href='test_db_web.php' class='btn btn-info'>Database Test</a>
</div>";

echo "</div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>";
?>