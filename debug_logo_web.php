<?php
require_once 'config/database.php';
require_once 'config/session.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Debug School Logo Issue</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
<div class='container mt-4'>
    <h2>Debugging School Logo Issue</h2>";

// Check database connection
echo "<h3>1. Database Connection</h3>";
$mysqli = getDBConnection();
if ($mysqli) {
    echo "<p class='text-success'>✓ Database connection successful</p>";
    
    // Check if logo_path column exists
    echo "<h3>2. Database Schema Check</h3>";
    $result = $mysqli->query("SHOW COLUMNS FROM `school_config` LIKE 'logo_path'");
    if ($result && $result->num_rows > 0) {
        echo "<p class='text-success'>✓ logo_path column exists</p>";
    } else {
        echo "<p class='text-danger'>✗ logo_path column does not exist</p>";
        echo "<p class='alert alert-warning'>Please run the database update to add this column.</p>";
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
                echo "<p>Logo file exists: " . (file_exists($row['logo_path']) ? '<span class=text-success>Yes</span>' : '<span class=text-danger>No</span>') . "</p>";
                echo "<p>Full path: " . realpath($row['logo_path']) . "</p>";
                echo "<p>File permissions: " . substr(sprintf('%o', fileperms($row['logo_path'])), -4) . "</p>";
            }
        } else {
            echo "<p>No school configuration found</p>";
        }
        $stmt->close();
    }
    
    $mysqli->close();
} else {
    echo "<p class='text-danger'>✗ Database connection failed</p>";
    echo "<p class='alert alert-warning'>Check your database configuration in config/database.php</p>";
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
    echo "<p class='text-danger'>✗ getSchoolLogo() function not found</p>";
}

// Check uploads directory
echo "<h3>6. Uploads Directory</h3>";
$upload_dir = 'uploads/';
if (is_dir($upload_dir)) {
    echo "<p class='text-success'>✓ Uploads directory exists</p>";
    echo "<p>Writable: " . (is_writable($upload_dir) ? '<span class=text-success>Yes</span>' : '<span class=text-danger>No</span>') . "</p>";
    
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
    echo "<p class='text-danger'>✗ Uploads directory does not exist</p>";
    echo "<p class='alert alert-warning'>Please create the uploads directory with proper permissions.</p>";
}

// Check the actual login page header display logic
echo "<h3>7. Login Page Header Display Logic</h3>";
echo "<div class='card'>";
echo "<div class='card-header'>Current Login Page Header Logic</div>";
echo "<div class='card-body'>";

// Get school logo if exists
$school_logo = null;
$mysqli = getDBConnection();
if ($mysqli) {
    $stmt = $mysqli->prepare("SELECT logo_path FROM school_config LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $school_logo = $row['logo_path'];
        }
        $stmt->close();
    }
}

echo "<p>Logo path from database: " . ($school_logo ? htmlspecialchars($school_logo) : 'null') . "</p>";
echo "<p>Logo file exists: " . ($school_logo && file_exists($school_logo) ? '<span class=text-success>Yes</span>' : '<span class=text-danger>No</span>') . "</p>";

// Display logo if exists, otherwise show icon
if (!empty($school_logo) && file_exists($school_logo)) {
    echo '<div class="alert alert-info">Logo would be displayed: <img src="' . htmlspecialchars($school_logo) . '" alt="School Logo" style="height: 40px; margin-right: 10px;"></div>';
} else {
    echo '<div class="alert alert-warning">Default icon would be displayed: <i class="fas fa-graduation-cap me-2"></i></div>';
}

echo "</div>";
echo "</div>";

echo "<h3>Recommendations</h3>";
echo "<ol>";
echo "<li>Make sure you've uploaded a logo through the admin panel (School Settings)</li>";
echo "<li>Check that the database update was run to add the logo_path column</li>";
echo "<li>Ensure the uploads directory exists and is writable</li>";
echo "<li>Verify the logo file path is correct and accessible</li>";
echo "<li>If a logo is uploaded but not showing, check file permissions</li>";
echo "</ol>";

echo "<a href='login.php' class='btn btn-primary'>Go to Login Page</a>";

echo "</div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>";
?>