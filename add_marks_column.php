<?php
// Simple script to add marks_per_question column to classes table
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'cbt_exam_system';

// Create connection
$mysqli = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Add column
$sql = "ALTER TABLE classes ADD COLUMN marks_per_question DECIMAL(5,2) DEFAULT 1.00 NOT NULL AFTER description";

if ($mysqli->query($sql) === TRUE) {
    echo "Column 'marks_per_question' added successfully to 'classes' table.";
} else {
    echo "Error adding column: " . $mysqli->error;
}

$mysqli->close();
?>