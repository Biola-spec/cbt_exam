<?php
// Test image upload functionality
include 'config/database.php';

echo "<h1>Image Upload Functionality Test</h1>";

// Test 1: Check if uploads directory exists and is writable
echo "<h2>Uploads Directory Test</h2>";
$upload_dir = __DIR__ . '/uploads';

if (is_dir($upload_dir)) {
    echo "<p style='color: green;'>✓ Uploads directory exists</p>";
    
    if (is_writable($upload_dir)) {
        echo "<p style='color: green;'>✓ Uploads directory is writable</p>";
    } else {
        echo "<p style='color: red;'>✗ Uploads directory is not writable</p>";
        echo "<p>Please check directory permissions.</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠ Uploads directory does not exist</p>";
    echo "<p>It will be created automatically when you upload an image.</p>";
}

// Test 2: Check PHP upload settings
echo "<h2>PHP Upload Settings</h2>";
$upload_max_filesize = ini_get('upload_max_filesize');
$post_max_size = ini_get('post_max_size');

echo "<p>upload_max_filesize: $upload_max_filesize</p>";
echo "<p>post_max_size: $post_max_size</p>";

// Convert to bytes for comparison
function convertToBytes($value) {
    $unit = strtolower(substr($value, -1));
    $number = substr($value, 0, -1);
    
    switch ($unit) {
        case 'g':
            return $number * 1024 * 1024 * 1024;
        case 'm':
            return $number * 1024 * 1024;
        case 'k':
            return $number * 1024;
        default:
            return $value;
    }
}

$upload_limit = min(convertToBytes($upload_max_filesize), convertToBytes($post_max_size));
$our_limit = 5 * 1024 * 1024; // 5MB

if ($upload_limit >= $our_limit) {
    echo "<p style='color: green;'>✓ PHP upload limits are sufficient for our 5MB limit</p>";
} else {
    echo "<p style='color: orange;'>⚠ PHP upload limits are lower than our 5MB limit</p>";
    echo "<p>You may need to increase upload_max_filesize and post_max_size in php.ini</p>";
}

// Test 3: Test database connection
echo "<h2>Database Connection Test</h2>";
$mysqli = getDBConnection();

if ($mysqli) {
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    $mysqli->close();
} else {
    echo "<p style='color: red;'>✗ Database connection failed</p>";
    echo "<p>Please check your database configuration.</p>";
}

echo "<h2>Manual Test Instructions</h2>";
echo "<p>To test image upload functionality:</p>";
echo "<ol>";
echo "<li>Go to the teacher question management page</li>";
echo "<li>Create or edit a comprehension passage</li>";
echo "<li>Click the 'Upload Image' button</li>";
echo "<li>Select an image file (JPG, PNG, GIF, or WebP, under 5MB)</li>";
echo "<li>Check if the image is uploaded successfully</li>";
echo "</ol>";

echo "<h2>Troubleshooting Tips</h2>";
echo "<ul>";
echo "<li>Check browser console for JavaScript errors</li>";
echo "<li>Check PHP error logs for server-side errors</li>";
echo "<li>Ensure the uploads directory has write permissions</li>";
echo "<li>Verify PHP upload settings in php.ini</li>";
echo "<li>Try uploading smaller image files first</li>";
echo "</ul>";
?>