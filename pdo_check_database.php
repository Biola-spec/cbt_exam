<?php
// Check if database exists and is accessible using PDO
echo "Checking database connectivity with PDO and subjects table...\n";

try {
    // Try to connect to MySQL server without specifying database
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to MySQL server successfully\n";
    
    // Check if the database exists
    $stmt = $pdo->prepare("SHOW DATABASES LIKE 'cbt_exam_system'");
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($result) > 0) {
        echo "Database 'cbt_exam_system' exists\n";
    } else {
        echo "Database 'cbt_exam_system' does not exist\n";
        
        // Try to create it
        $pdo->exec("CREATE DATABASE IF NOT EXISTS cbt_exam_system");
        echo "Database 'cbt_exam_system' created successfully\n";
    }
    
    // Try to connect to the specific database
    $pdo = null; // Close previous connection
    $pdo = new PDO("mysql:host=localhost;dbname=cbt_exam_system", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database 'cbt_exam_system' successfully\n";
    
    // Check if subjects table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'subjects'");
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($result) > 0) {
        echo "subjects table exists\n";
        
        // Describe the table structure
        $stmt = $pdo->prepare("DESCRIBE subjects");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "subjects table structure:\n";
        foreach ($result as $row) {
            echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "subjects table does not exist\n";
    }
    
    echo "Database check completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>