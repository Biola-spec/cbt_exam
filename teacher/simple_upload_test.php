<?php
// Simple test to verify upload endpoint accessibility
echo "<h1>Simple Upload Endpoint Test</h1>";

// Test accessing the upload endpoint directly
$upload_endpoint = '../upload_image.php';

echo "<h2>File Existence Check</h2>";
echo "<p>Upload endpoint path: " . $upload_endpoint . "</p>";
echo "<p>File exists: " . (file_exists($upload_endpoint) ? 'Yes' : 'No') . "</p>";

if (file_exists($upload_endpoint)) {
    echo "<p style='color: green;'>✓ File exists</p>";
    
    // Try to get the real path
    $real_path = realpath($upload_endpoint);
    echo "<p>Real path: " . ($real_path ? $real_path : 'N/A') . "</p>";
} else {
    echo "<p style='color: red;'>✗ File does not exist</p>";
    
    // List parent directory contents
    $parent_dir = dirname(__DIR__);
    echo "<p>Parent directory: " . $parent_dir . "</p>";
    
    if (is_dir($parent_dir)) {
        $files = scandir($parent_dir);
        echo "<p>Files in parent directory:</p>";
        echo "<ul>";
        foreach ($files as $file) {
            if ($file === 'upload_image.php') {
                echo "<li style='color: green; font-weight: bold;'>$file ✓</li>";
            } else {
                echo "<li>$file</li>";
            }
        }
        echo "</ul>";
    }
}
?>