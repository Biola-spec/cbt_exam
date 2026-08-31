<?php
require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test Database Connection</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
<div class='container mt-4'>
    <h2>Database Connection Test</h2>";

$mysqli = getDBConnection();

if ($mysqli) {
    echo "<p class='text-success'>✓ Database connection successful</p>";
    
    // Test a simple query
    $result = $mysqli->query("SELECT VERSION() as version");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>MySQL Version: " . htmlspecialchars($row['version']) . "</p>";
    }
    
    // Check if school_config table exists
    $result = $mysqli->query("SHOW TABLES LIKE 'school_config'");
    if ($result && $result->num_rows > 0) {
        echo "<p class='text-success'>✓ school_config table exists</p>";
        
        // Check if logo_path column exists
        $result = $mysqli->query("SHOW COLUMNS FROM `school_config` LIKE 'logo_path'");
        if ($result && $result->num_rows > 0) {
            echo "<p class='text-success'>✓ logo_path column exists</p>";
        } else {
            echo "<p class='text-warning'>⚠ logo_path column does not exist</p>";
            echo "<p>You need to run the database update to add this column.</p>";
        }
        
        // Check current data
        $stmt = $mysqli->prepare("SELECT id, school_name, logo_path FROM school_config LIMIT 1");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                echo "<h3>Current School Configuration:</h3>";
                echo "<p>ID: " . htmlspecialchars($row['id']) . "</p>";
                echo "<p>School Name: " . htmlspecialchars($row['school_name']) . "</p>";
                echo "<p>Logo Path: " . ($row['logo_path'] ? htmlspecialchars($row['logo_path']) : '<em>Not set</em>') . "</p>";
            } else {
                echo "<p>No school configuration found in database.</p>";
            }
            $stmt->close();
        }
    } else {
        echo "<p class='text-danger'>✗ school_config table does not exist</p>";
    }
    
    $mysqli->close();
} else {
    echo "<p class='text-danger'>✗ Database connection failed</p>";
    echo "<p>Please check your database configuration in config/database.php</p>";
    echo "<p>Ensure that:</p>";
    echo "<ul>";
    echo "<li>MySQL service is running</li>";
    echo "<li>Database credentials are correct</li>";
    echo "<li>Database 'cbt_exam_system' exists</li>";
    echo "</ul>";
}

echo "<div class='mt-4'>
    <a href='login.php' class='btn btn-primary'>Go to Login Page</a>
    <a href='admin/dashboard.php' class='btn btn-secondary'>Go to Admin Dashboard</a>
    <a href='ensure_logo_column_exists.php' class='btn btn-info'>Run Database Update</a>
</div>";

echo "</div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>";
?>