<?php
// Test if the upload endpoint is accessible from the teacher directory
echo "<h1>Upload Endpoint Access Test</h1>";

// Test using file_get_contents to see if we can access the endpoint
echo "<h2>Testing file_get_contents access</h2>";

$endpoint_path = '../upload_image.php';
echo "<p>Endpoint path: " . htmlspecialchars($endpoint_path) . "</p>";

if (file_exists($endpoint_path)) {
    echo "<p style='color: green;'>✓ File exists</p>";
    
    // Try to include the file to see what it returns
    try {
        ob_start();
        include $endpoint_path;
        $output = ob_get_clean();
        
        echo "<p>Output from endpoint:</p>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
        
        // Check if it's valid JSON
        $json = json_decode($output, true);
        if ($json !== null) {
            echo "<p style='color: green;'>✓ Valid JSON response</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Not valid JSON</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error including file: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>✗ File does not exist</p>";
    
    // List parent directory
    $parent_dir = dirname(__DIR__);
    echo "<p>Parent directory: " . htmlspecialchars($parent_dir) . "</p>";
    
    if (is_dir($parent_dir)) {
        $files = scandir($parent_dir);
        echo "<p>Files in parent directory:</p>";
        echo "<ul>";
        foreach ($files as $file) {
            echo "<li>" . htmlspecialchars($file) . "</li>";
        }
        echo "</ul>";
    }
}
?>