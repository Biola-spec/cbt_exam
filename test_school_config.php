<?php
require_once 'config/database.php';

echo "Testing school configuration...\n";

try {
    $mysqli = getDBConnection();
    
    if ($mysqli) {
        echo "✓ Database connection successful\n";
        
        // Use explicit column names instead of SELECT *
        $stmt = $mysqli->prepare("SELECT id, school_name, updated_at, updated_by FROM school_config LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $config = $result->fetch_assoc();
            echo "✓ School configuration found:\n";
            echo "  ID: " . $config['id'] . "\n";
            echo "  School Name: " . $config['school_name'] . "\n";
            echo "  Updated At: " . $config['updated_at'] . "\n";
            echo "  Updated By: " . $config['updated_by'] . "\n";
        } else {
            echo "✗ School configuration not found\n";
        }
        
        $stmt->close();
        $mysqli->close();
    } else {
        echo "✗ Database connection failed\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Test completed.\n";
?>