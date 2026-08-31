<?php
// Test CKEditor 5 Image Upload
// Access this file directly in browser: http://localhost/cbt_exam/test_ckeditor_upload.php

echo "<!DOCTYPE html>
<html>
<head>
    <title>CKEditor 5 Upload Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .test-result { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>CKEditor 5 Image Upload Test</h1>
    
    <h2>1. Checking Upload Directory</h2>";

$uploadDir = __DIR__ . '/uploads/';
if (is_dir($uploadDir)) {
    echo "<div class='test-result success'>✓ Upload directory exists: $uploadDir</div>";
    if (is_writable($uploadDir)) {
        echo "<div class='test-result success'>✓ Upload directory is writable</div>";
    } else {
        echo "<div class='test-result error'>✗ Upload directory is NOT writable</div>";
        echo "<p><strong>Fix:</strong> Run: icacls \"$uploadDir\" /grant Users:F</p>";
    }
} else {
    echo "<div class='test-result error'>✗ Upload directory does NOT exist</div>";
    echo "<p><strong>Fix:</strong> Create the uploads directory</p>";
}

echo "<h2>2. Checking PHP Configuration</h2>";
echo "<div class='test-result info'>";
echo "<pre>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "\n";
echo "file_uploads: " . (ini_get('file_uploads') ? 'On' : 'Off') . "\n";
echo "GD Library: " . (extension_loaded('gd') ? 'Available' : 'NOT Available') . "\n";
echo "finfo: " . (function_exists('finfo_open') ? 'Available' : 'NOT Available') . "\n";
echo "</pre>";
echo "</div>";

echo "<h2>3. Testing Upload Endpoint</h2>";

// Test the upload endpoint
$testUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/cbt_exam/upload_image.php';
echo "<div class='test-result info'>";
echo "<p>Upload URL: <code>$testUrl</code></p>";
echo "<p><strong>Note:</strong> CKEditor 5 will send POST requests with file in 'upload' field</p>";
echo "</div>";

echo "<h2>4. Manual Upload Test</h2>";
echo "<form method='POST' enctype='multipart/form-data' action='upload_image.php'>
    <input type='hidden' name='action' value='upload_image'>
    <input type='file' name='image' accept='image/*' required>
    <button type='submit'>Test Upload</button>
</form>";

echo "<h2>5. CKEditor 5 Configuration</h2>";
echo "<div class='test-result info'>";
echo "<p>Your CKEditor 5 should be configured with:</p>";
echo "<pre>
simpleUpload: {
    uploadUrl: '/cbt_exam/upload_image.php',
    headers: {
        'X-Requested-With': 'XMLHttpRequest'
    }
}
</pre>";
echo "</div>";

echo "<h2>6. Common Issues Checklist</h2>";
echo "<div class='test-result info'>";
echo "<ul>";
echo "<li>✓ Upload directory exists and is writable</li>";
echo "<li>✓ PHP file uploads are enabled</li>";
echo "<li>✓ Upload URL is absolute path: /cbt_exam/upload_image.php</li>";
echo "<li>✓ Response format matches CKEditor 5 requirements</li>";
echo "<li>✓ File field name is 'upload' for CKEditor requests</li>";
echo "</ul>";
echo "</div>";

echo "</body>
</html>";
?>
