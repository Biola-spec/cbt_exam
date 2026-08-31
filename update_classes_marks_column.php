<?php
require_once 'config/database.php';

$mysqli = getDBConnection();

if (!$mysqli) {
    die("Failed to connect to database. Please check your database configuration.\n");
}

try {
    // Check if the column already exists
    $result = $mysqli->query("SHOW COLUMNS FROM `classes` LIKE 'marks_per_question'");
    
    if ($result->num_rows == 0) {
        // Column doesn't exist, add it
        $sql = "ALTER TABLE `classes` ADD COLUMN `marks_per_question` DECIMAL(5,2) DEFAULT 1.00 NOT NULL AFTER `description`";
        if ($mysqli->query($sql)) {
            echo "Column 'marks_per_question' added successfully to 'classes' table.\n";
        } else {
            echo "Error adding column: " . $mysqli->error . "\n";
        }
    } else {
        echo "Column 'marks_per_question' already exists in 'classes' table.\n";
    }
    
    $mysqli->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>