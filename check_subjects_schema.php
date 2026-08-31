<?php
require_once 'config/database.php';

echo "Checking subjects table schema...\n";

try {
    $mysqli = getDBConnection();
    
    if (!$mysqli) {
        die("Failed to connect to database\n");
    }
    
    // Describe the subjects table
    $result = $mysqli->query("DESCRIBE subjects");
    if ($result) {
        echo "Current subjects table structure:\n";
        echo str_pad("Field", 20) . str_pad("Type", 20) . "Null\n";
        echo str_repeat("-", 50) . "\n";
        
        while ($row = $result->fetch_assoc()) {
            echo str_pad($row['Field'], 20) . str_pad($row['Type'], 20) . $row['Null'] . "\n";
        }
    } else {
        echo "Error describing table: " . $mysqli->error . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>