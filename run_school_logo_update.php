        <?php
require_once 'config/database.php';

echo "Running school logo column update...\n";

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
        
        $mysqli->close();
    } else {
        echo "Failed to connect to database.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Update completed.\n";
?>