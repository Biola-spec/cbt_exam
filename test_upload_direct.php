<?php
// Test the upload endpoint directly
echo "<h1>Testing Upload Endpoint Directly</h1>";

// Test accessing the upload endpoint
$upload_endpoint = 'upload_image.php';

echo "<h2>Testing Upload Endpoint Accessibility</h2>";

if (file_exists($upload_endpoint)) {
    echo "<p style='color: green;'>✓ Upload endpoint file exists</p>";
    
    // Try to include and test basic functionality
    try {
        // Test the endpoint with invalid action
        ob_start();
        include $upload_endpoint;
        $output = ob_get_clean();
        
        // Check if we got a JSON response
        $json = json_decode($output, true);
        if ($json && isset($json['success'])) {
            echo "<p style='color: green;'>✓ Upload endpoint is accessible and responding correctly</p>";
            echo "<p>Response: " . htmlspecialchars($output) . "</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Upload endpoint is accessible but returned unexpected response</p>";
            echo "<p>Response: " . htmlspecialchars($output) . "</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error testing upload endpoint: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Upload endpoint file does not exist at expected location</p>";
    echo "<p>Looking for: " . realpath($upload_endpoint) . "</p>";
}

echo "<h2>Directory Structure Check</h2>";
echo "<p>Current directory: " . __DIR__ . "</p>";
echo "<p>Upload endpoint path: " . $upload_endpoint . "</p>";

// List files in current directory to verify upload_image.php exists
$files = scandir(__DIR__);
echo "<p>Files in current directory:</p>";
echo "<ul>";
foreach ($files as $file) {
    if ($file === 'upload_image.php') {
        echo "<li style='color: green; font-weight: bold;'>$file ✓</li>";
    } else {
        echo "<li>$file</li>";
    }
}
echo "</ul>";
?>