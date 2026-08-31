<?php
// Verification script for passage image upload functionality
echo "<h1>Passage Image Upload Verification</h1>";

// Check if uploads directory exists
$upload_dir = 'uploads/';
echo "<h2>Uploads Directory Check</h2>";
if (is_dir($upload_dir)) {
    echo "<p style='color: green;'>✓ Uploads directory exists</p>";
    
    // Check if writable
    if (is_writable($upload_dir)) {
        echo "<p style='color: green;'>✓ Uploads directory is writable</p>";
    } else {
        echo "<p style='color: red;'>✗ Uploads directory is not writable</p>";
        echo "<p>Please ensure the web server has write permissions to the uploads directory.</p>";
    }
    
    // List files in directory
    $files = scandir($upload_dir);
    echo "<p>Files in uploads directory:</p>";
    echo "<ul>";
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "<li>" . htmlspecialchars($file) . "</li>";
        }
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>✗ Uploads directory does not exist</p>";
    echo "<p>Please create the uploads directory in the project root.</p>";
}

// Check upload_image.php script
echo "<h2>Upload Script Check</h2>";
$upload_script = 'upload_image.php';
if (file_exists($upload_script)) {
    echo "<p style='color: green;'>✓ Upload script exists</p>";
} else {
    echo "<p style='color: red;'>✗ Upload script does not exist</p>";
}

// Check required PHP extensions
echo "<h2>PHP Extension Check</h2>";
$required_extensions = ['fileinfo', 'gd'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p style='color: green;'>✓ $ext extension is loaded</p>";
    } else {
        echo "<p style='color: orange;'>⚠ $ext extension is not loaded (may be needed for advanced image processing)</p>";
    }
}

// Test file upload limits
echo "<h2>PHP Configuration Check</h2>";
$upload_max_filesize = ini_get('upload_max_filesize');
$post_max_size = ini_get('post_max_size');
echo "<p>upload_max_filesize: " . htmlspecialchars($upload_max_filesize) . "</p>";
echo "<p>post_max_size: " . htmlspecialchars($post_max_size) . "</p>";

if (intval($upload_max_filesize) < 5 || intval($post_max_size) < 5) {
    echo "<p style='color: orange;'>⚠ Upload limits are less than 5MB. You may need to increase these values in php.ini for larger images.</p>";
} else {
    echo "<p style='color: green;'>✓ Upload limits are sufficient for images up to 5MB</p>";
}

echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li>Visit <a href='test_passage_image_upload.php'>Test Passage Image Upload</a> to try the functionality</li>";
echo "<li>Log in as a teacher and navigate to the question management page</li>";
echo "<li>Try uploading an image in a comprehension passage</li>";
echo "<li>Verify the image displays correctly in student view</li>";
echo "</ol>";
?>