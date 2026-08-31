<?php
echo "Testing PDO database connection...\n";

try {
    $pdo = new PDO("mysql:host=localhost;dbname=cbt_exam_system", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected successfully!\n";
    
    // Test a simple query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Users table has " . $row['count'] . " records\n";
    
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Test completed.\n";
?>