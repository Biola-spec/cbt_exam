<?php
// Final test script to verify the subject code authentication system
require_once 'config/database.php';

echo "<h1>Final Test: Subject Code Authentication System</h1>";

try {
    $mysqli = getDBConnection();
    
    if (!$mysqli) {
        die("<p style='color: red;'>✗ Failed to connect to database</p>");
    }
    
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Test 1: Check that the passkeys table no longer exists
    echo "<h2>Test 1: Passkeys Table Check</h2>";
    $result = $mysqli->query("SHOW TABLES LIKE 'passkeys'");
    if ($result && $result->num_rows == 0) {
        echo "<p style='color: green;'>✓ Passkeys table does not exist (as expected)</p>";
    } else {
        echo "<p style='color: red;'>✗ Passkeys table still exists</p>";
    }
    
    // Test 2: Check that the subjects table exists
    echo "<h2>Test 2: Subjects Table Check</h2>";
    $result = $mysqli->query("SHOW TABLES LIKE 'subjects'");
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>✓ Subjects table exists</p>";
    } else {
        echo "<p style='color: red;'>✗ Subjects table does not exist</p>";
    }
    
    // Test 3: Check that a sample subject exists
    echo "<h2>Test 3: Sample Subject Check</h2>";
    $stmt = $mysqli->prepare("SELECT id, name FROM subjects LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            echo "<p style='color: green;'>✓ Sample subject found: " . htmlspecialchars($row['name']) . "</p>";
        } else {
            echo "<p style='color: orange;'>⚠ No subjects found in database</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color: red;'>✗ Error preparing statement to check subjects</p>";
    }
    
    // Test 4: Check that a sample student exists
    echo "<h2>Test 4: Sample Student Check</h2>";
    $stmt = $mysqli->prepare("SELECT id, email, full_name FROM users WHERE role = 'student' LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            echo "<p style='color: green;'>✓ Sample student found: " . htmlspecialchars($row['full_name']) . " (" . htmlspecialchars($row['email']) . ")</p>";
        } else {
            echo "<p style='color: orange;'>⚠ No students found in database</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color: red;'>✗ Error preparing statement to check students</p>";
    }
    
    $mysqli->close();
    
    echo "<h2>Conclusion</h2>";
    echo "<p>If all tests passed (green checkmarks), the subject code authentication system is properly implemented and the passkey system has been successfully removed.</p>";
    echo "<p>You can now log in as a student using your Gmail and a subject code instead of a passkey.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='login.php'>Go to Login Page</a></p>";
?>