<?php
require_once 'config/database.php';

echo "Updating database for school logo functionality...\n";

try {
    $mysqli = getDBConnection();
    
    if ($mysqli) {
        // Check if the column already exists
        $check_query = "SHOW COLUMNS FROM `school_config` LIKE 'logo_path'";
        $result = $mysqli->query($check_query);
        
        if ($result && $result->num_rows > 0) {
            echo "Column 'logo_path' already exists in school_config table.\n";
        } else {
            // Add the logo_path column
            $alter_query = "ALTER TABLE `school_config` ADD COLUMN `logo_path` VARCHAR(255) NULL DEFAULT NULL AFTER `default_time_limit`";
            
            if ($mysqli->query($alter_query)) {
                echo "Successfully added 'logo_path' column to school_config table.\n";
            } else {
                echo "Error adding 'logo_path' column: " . $mysqli->error . "\n";
            }
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            if (mkdir($upload_dir, 0755, true)) {
                echo "Created uploads directory.\n";
            } else {
                echo "Failed to create uploads directory.\n";
            }
        } else {
            echo "Uploads directory already exists.\n";
        }
        
        $mysqli->close();
    } else {
        echo "Failed to connect to database.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Database update completed.\n";
?>