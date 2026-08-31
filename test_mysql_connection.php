<?php
require_once 'config/database.php';

echo "Testing MySQL connection and user lookup...\n";

try {
    $mysqli = getDBConnection();
    
    if ($mysqli) {
        echo "✓ Database connection successful\n";
        
        // Test with a specific email
        $test_email = 'admin@cbtexam.com';
        echo "Looking up user with email: " . $test_email . "\n";
        
        // Use explicit column names instead of SELECT *
        $stmt = $mysqli->prepare("SELECT id, email, full_name, role FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $test_email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            echo "✓ User found:\n";
            echo "  ID: " . $user['id'] . "\n";
            echo "  Email: " . $user['email'] . "\n";
            echo "  Name: " . $user['full_name'] . "\n";
            echo "  Role: " . $user['role'] . "\n";
        } else {
            echo "✗ User not found\n";
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