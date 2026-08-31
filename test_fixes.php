<?php
require_once 'config/database.php';
require_once 'config/session.php';

echo "<h2>System Fix Verification</h2>";

// Test 1: Check if MySQLi class is available
echo "<p>1. Checking MySQLi availability...</p>";
if (class_exists('mysqli')) {
    echo "<p style='color: green;'>✓ MySQLi class is available</p>";
} else {
    echo "<p style='color: orange;'>⚠ MySQLi class is not available (using file-based fallback)</p>";
}

// Test 2: Check database connection
echo "<p>2. Testing database connection...</p>";
$mysqli = getDBConnection();
if ($mysqli) {
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Test a simple query
    $result = $mysqli->query("SELECT 1");
    if ($result) {
        echo "<p style='color: green;'>✓ Database query successful</p>";
    } else {
        echo "<p style='color: red;'>✗ Database query failed</p>";
    }
    
    if (method_exists($mysqli, 'close')) {
        $mysqli->close();
    }
} else {
    echo "<p style='color: orange;'>⚠ Database connection not available (using file-based fallback)</p>";
}

// Test 3: Check if database is available function works
echo "<p>3. Testing database availability check...</p>";
if (function_exists('isDatabaseAvailable')) {
    $dbAvailable = isDatabaseAvailable();
    if ($dbAvailable) {
        echo "<p style='color: green;'>✓ Database is available</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Database is not available (using file-based fallback)</p>";
    }
} else {
    echo "<p style='color: red;'>✗ isDatabaseAvailable function not found</p>";
}

// Test 4: Test file-based authentication
echo "<p>4. Testing file-based authentication...</p>";
$user = authenticateUser('admin@cbtexam.com', 'Admin@2024');
if ($user) {
    echo "<p style='color: green;'>✓ File-based authentication successful</p>";
    echo "<ul>";
    echo "<li>ID: " . $user['id'] . "</li>";
    echo "<li>Email: " . $user['email'] . "</li>";
    echo "<li>Full Name: " . $user['full_name'] . "</li>";
    echo "<li>Role: " . $user['role'] . "</li>";
    echo "</ul>";
} else {
    echo "<p style='color: red;'>✗ File-based authentication failed</p>";
}

// Test 5: Test session functions
echo "<p>5. Testing session functions...</p>";
if (function_exists('isLoggedIn') && 
    function_exists('getUserRole') && 
    function_exists('getUserId') && 
    function_exists('getUserName')) {
    echo "<p style='color: green;'>✓ All session functions are available</p>";
} else {
    echo "<p style='color: red;'>✗ Some session functions are missing</p>";
}

echo "<h3>Summary</h3>";
echo "<p>The system should now work with either:</p>";
echo "<ul>";
echo "<li>Database connection (if MySQL extensions are properly configured)</li>";
echo "<li>File-based fallback (if MySQL extensions are not available)</li>";
echo "</ul>";

echo "<p><a href='login.php'>Go to Login Page</a></p>";
?>