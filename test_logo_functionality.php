<?php
require_once 'config/database.php';
require_once 'config/session.php';

echo "<h2>Testing School Logo Functionality</h2>";

// Test 1: Check if logo_path column exists
echo "<h3>1. Checking database schema</h3>";
try {
    $mysqli = getDBConnection();
    
    if ($mysqli) {
        $check_query = "SHOW COLUMNS FROM `school_config` LIKE 'logo_path'";
        $result = $mysqli->query($check_query);
        
        if ($result && $result->num_rows > 0) {
            echo "<p style='color: green;'>✓ logo_path column exists in school_config table</p>";
        } else {
            echo "<p style='color: red;'>✗ logo_path column does not exist in school_config table</p>";
            echo "<p>Please run the database update script.</p>";
        }
        
        // Check if there's any school config data
        $stmt = $mysqli->prepare("SELECT id, school_name, logo_path FROM school_config LIMIT 1");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                echo "<p style='color: green;'>✓ School configuration found:</p>";
                echo "<ul>";
                echo "<li>ID: " . htmlspecialchars($row['id']) . "</li>";
                echo "<li>School Name: " . htmlspecialchars($row['school_name']) . "</li>";
                echo "<li>Logo Path: " . ($row['logo_path'] ? htmlspecialchars($row['logo_path']) : 'Not set') . "</li>";
                echo "</ul>";
            } else {
                echo "<p>No school configuration found in database.</p>";
            }
            $stmt->close();
        }
        
        $mysqli->close();
    } else {
        echo "<p style='color: red;'>✗ Failed to connect to database</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 2: Check uploads directory
echo "<h3>2. Checking uploads directory</h3>";
$upload_dir = 'uploads/';
if (is_dir($upload_dir)) {
    echo "<p style='color: green;'>✓ Uploads directory exists</p>";
    
    // Check if it's writable
    if (is_writable($upload_dir)) {
        echo "<p style='color: green;'>✓ Uploads directory is writable</p>";
    } else {
        echo "<p style='color: red;'>✗ Uploads directory is not writable</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Uploads directory does not exist</p>";
    echo "<p>Please create the uploads directory with proper permissions.</p>";
}

// Test 3: Check session functions
echo "<h3>3. Checking session functions</h3>";
if (function_exists('getSchoolLogo')) {
    echo "<p style='color: green;'>✓ getSchoolLogo function exists</p>";
    
    $logo = getSchoolLogo();
    echo "<p>School logo from session/function: " . ($logo ? htmlspecialchars($logo) : 'Not set') . "</p>";
} else {
    echo "<p style='color: red;'>✗ getSchoolLogo function does not exist</p>";
}

echo "<h3>4. Instructions</h3>";
echo "<ol>";
echo "<li>Log in as admin to the system</li>";
echo "<li>Go to Admin Dashboard > School Settings</li>";
echo "<li>Upload a school logo using the file upload field</li>";
echo "<li>Save the settings</li>";
echo "<li>Check if the logo appears on the login page and in exam results</li>";
echo "</ol>";

echo "<p><a href='login.php'>Go to Login Page</a></p>";
?>