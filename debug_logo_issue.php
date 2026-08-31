<?php
require_once 'config/database.php';
require_once 'config/session.php';

echo "<h2>Debugging School Logo Issue</h2>";

// Check database connection
echo "<h3>1. Database Connection</h3>";
$mysqli = getDBConnection();
if ($mysqli) {
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Check if logo_path column exists
    echo "<h3>2. Database Schema Check</h3>";
    $result = $mysqli->query("SHOW COLUMNS FROM `school_config` LIKE 'logo_path'");
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>✓ logo_path column exists</p>";
    } else {
        echo "<p style='color: red;'>✗ logo_path column does not exist</p>";
        echo "<p>Please run the database update to add this column.</p>";
        exit;
    }
    
    // Check school config data
    echo "<h3>3. School Configuration Data</h3>";
    $stmt = $mysqli->prepare("SELECT id, school_name, logo_path FROM school_config LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            echo "<p>ID: " . htmlspecialchars($row['id']) . "</p>";
            echo "<p>School Name: " . htmlspecialchars($row['school_name']) . "</p>";
            echo "<p>Logo Path: " . ($row['logo_path'] ? htmlspecialchars($row['logo_path']) : 'Not set') . "</p>";
            
            if ($row['logo_path']) {
                echo "<p>Logo file exists: " . (file_exists($row['logo_path']) ? 'Yes' : 'No') . "</p>";
                echo "<p>Full path: " . realpath($row['logo_path']) . "</p>";
            }
        } else {
            echo "<p>No school configuration found</p>";
        }
        $stmt->close();
    }
    
    $mysqli->close();
} else {
    echo "<p style='color: red;'>✗ Database connection failed</p>";
    exit;
}

// Check session data
echo "<h3>4. Session Data</h3>";
echo "<p>School Name in Session: " . (isset($_SESSION['school_name']) ? htmlspecialchars($_SESSION['school_name']) : 'Not set') . "</p>";
echo "<p>School Logo in Session: " . (isset($_SESSION['school_logo']) ? htmlspecialchars($_SESSION['school_logo']) : 'Not set') . "</p>";

// Check getSchoolLogo function
echo "<h3>5. getSchoolLogo() Function Test</h3>";
if (function_exists('getSchoolLogo')) {
    $logo = getSchoolLogo();
    echo "<p>getSchoolLogo() returned: " . ($logo ? htmlspecialchars($logo) : 'null') . "</p>";
} else {
    echo "<p style='color: red;'>✗ getSchoolLogo() function not found</p>";
}

// Check uploads directory
echo "<h3>6. Uploads Directory</h3>";
$upload_dir = 'uploads/';
if (is_dir($upload_dir)) {
    echo "<p style='color: green;'>✓ Uploads directory exists</p>";
    echo "<p>Writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . "</p>";
    
    // List files in uploads directory
    $files = scandir($upload_dir);
    echo "<p>Files in uploads directory:</p><ul>";
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "<li>" . htmlspecialchars($file) . "</li>";
        }
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>✗ Uploads directory does not exist</p>";
}

echo "<h3>Recommendations</h3>";
echo "<ol>";
echo "<li>Make sure you've uploaded a logo through the admin panel (School Settings)</li>";
echo "<li>Check that the database update was run to add the logo_path column</li>";
echo "<li>Ensure the uploads directory exists and is writable</li>";
echo "<li>Verify the logo file path is correct and accessible</li>";
echo "</ol>";
?>