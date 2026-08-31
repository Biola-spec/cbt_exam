<?php
require_once 'config/database.php';

try {
    $mysqli = getDBConnection();
    
    if ($mysqli) {
        // Add default_time_limit column to school_config table
        $sql = "ALTER TABLE `school_config` ADD COLUMN `default_time_limit` INT DEFAULT 60 AFTER `school_name`";
        
        if ($mysqli->query($sql)) {
            echo "Column 'default_time_limit' added successfully to school_config table.\n";
        } else {
            echo "Error adding column: " . $mysqli->error . "\n";
        }
        
        $mysqli->close();
    } else {
        echo "Database connection failed.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>