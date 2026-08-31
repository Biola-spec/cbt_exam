<?php
// Check if database exists and is accessible
echo "Checking database connectivity...\n";

// Try to connect to MySQL server without specifying database
$mysqli = new mysqli("localhost", "root", "");

if ($mysqli->connect_error) {
    echo "Connection failed: " . $mysqli->connect_error . "\n";
    exit(1);
}

echo "Connected to MySQL server successfully\n";

// Check if the database exists
$result = $mysqli->query("SHOW DATABASES LIKE 'cbt_exam_system'");
if ($result && $result->num_rows > 0) {
    echo "Database 'cbt_exam_system' exists\n";
} else {
    echo "Database 'cbt_exam_system' does not exist\n";
    
    // Try to create it
    if ($mysqli->query("CREATE DATABASE IF NOT EXISTS cbt_exam_system")) {
        echo "Database 'cbt_exam_system' created successfully\n";
    } else {
        echo "Failed to create database: " . $mysqli->error . "\n";
    }
}

// Try to connect to the specific database
$mysqli->close();
$mysqli = new mysqli("localhost", "root", "", "cbt_exam_system");

if ($mysqli->connect_error) {
    echo "Failed to connect to database 'cbt_exam_system': " . $mysqli->connect_error . "\n";
} else {
    echo "Connected to database 'cbt_exam_system' successfully\n";
    
    // Check if subjects table exists
    $result = $mysqli->query("SHOW TABLES LIKE 'subjects'");
    if ($result && $result->num_rows > 0) {
        echo "subjects table exists\n";
        
        // Describe the table structure
        $result = $mysqli->query("DESCRIBE subjects");
        if ($result) {
            echo "subjects table structure:\n";
            while ($row = $result->fetch_assoc()) {
                echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
            }
        }
    } else {
        echo "subjects table does not exist\n";
    }
}

$mysqli->close();
echo "Database check completed.\n";
?>