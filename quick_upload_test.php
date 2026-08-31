<?php
// Quick upload test - simulates CKEditor 5 upload
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Quick CKEditor 5 Upload Test</h1>";

// Create a test image
$testImage = __DIR__ . '/test_upload_temp.png';
$im = imagecreatetruecolor(100, 100);
$color = imagecolorallocate($im, 0, 123, 255);
imagefill($im, 0, 0, $color);
imagepng($im, $testImage);
imagedestroy($im);

echo "<h2>Test 1: Simulate CKEditor 5 Upload</h2>";

// Simulate CKEditor 5 POST request
$uploadUrl = 'http://localhost/cbt_exam/upload_image.php';
$ch = curl_init();

$cfile = new CURLFile($testImage, 'image/png', 'test.png');
$postData = [
    'upload' => $cfile
];

curl_setopt($ch, CURLOPT_URL, $uploadUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Requested-With: XMLHttpRequest'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
echo "<p><strong>CURL Error:</strong> " . ($curlError ?: 'None') . "</p>";
echo "<p><strong>Response:</strong></p>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Decode and validate response
$data = json_decode($response, true);
if ($data && isset($data['url'])) {
    echo "<p style='color: green;'>✓ <strong>SUCCESS:</strong> Upload endpoint is working correctly!</p>";
    echo "<p><strong>Image URL:</strong> " . htmlspecialchars($data['url']) . "</p>";
    
    // Check if file actually exists
    $fullPath = __DIR__ . $data['url'];
    if (file_exists($fullPath)) {
        echo "<p style='color: green;'>✓ File exists on server</p>";
        echo "<p><img src='" . htmlspecialchars($data['url']) . "' style='max-width: 200px; border: 2px solid #007bff;'></p>";
    } else {
        echo "<p style='color: red;'>✗ File does NOT exist at: $fullPath</p>";
    }
} else {
    echo "<p style='color: red;'>✗ <strong>FAILED:</strong> Response format is incorrect</p>";
    echo "<p>Expected: {\"default\": \"...\", \"url\": \"...\"}</p>";
}

// Cleanup
if (file_exists($testImage)) {
    unlink($testImage);
}

echo "<hr>";
echo "<h2>Test 2: Check Upload Directory</h2>";
$uploadDir = __DIR__ . '/uploads/';
if (is_dir($uploadDir)) {
    echo "<p style='color: green;'>✓ Upload directory exists</p>";
    if (is_writable($uploadDir)) {
        echo "<p style='color: green;'>✓ Upload directory is writable</p>";
    } else {
        echo "<p style='color: red;'>✗ Upload directory is NOT writable</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Upload directory does NOT exist</p>";
}

echo "<hr>";
echo "<h2>Test 3: PHP Configuration</h2>";
echo "<p><strong>upload_max_filesize:</strong> " . ini_get('upload_max_filesize') . "</p>";
echo "<p><strong>post_max_size:</strong> " . ini_get('post_max_size') . "</p>";
echo "<p><strong>file_uploads:</strong> " . (ini_get('file_uploads') ? 'On' : 'Off') . "</p>";
echo "<p><strong>GD Library:</strong> " . (extension_loaded('gd') ? 'Available' : 'NOT Available') . "</p>";

echo "<hr>";
echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li>If tests passed, try uploading an image in CKEditor 5</li>";
echo "<li>Open browser console (F12) to see upload requests</li>";
echo "<li>Check Network tab for POST to /cbt_exam/upload_image.php</li>";
echo "</ol>";
?>
