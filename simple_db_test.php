<?php
// Simple database connection test
echo "Testing database connection...\n";

// Include the database configuration
require_once 'config/database.php';

// Try to connect
$mysqli = getDBConnection();

if ($mysqli) {
    echo "SUCCESS: Database connection established!\n";
    
    // Test a simple query
    if ($result = $mysqli->query("SELECT DATABASE()")) {
        $row = $result->fetch_row();
        echo "Connected to database: " . $row[0] . "\n";
        $result->close();
    }
    
    // Check if school_config table exists
    if ($result = $mysqli->query("SHOW TABLES LIKE 'school_config'")) {
        if ($result->num_rows > 0) {
            echo "SUCCESS: school_config table exists\n";
            
            // Check columns in school_config table
            if ($columns_result = $mysqli->query("SHOW COLUMNS FROM school_config")) {
                echo "Columns in school_config table:\n";
                while ($column = $columns_result->fetch_assoc()) {
                    echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
                }
                $columns_result->close();
            }
        } else {
            echo "ERROR: school_config table does not exist\n";
        }
        $result->close();
    }
    
    $mysqli->close();
} else {
    echo "ERROR: Could not connect to database\n";
    echo "Please check:\n";
    echo "1. MySQL service is running\n";
    echo "2. Database credentials in config/database.php\n";
    echo "3. Database 'cbt_exam_system' exists\n";
}
?>