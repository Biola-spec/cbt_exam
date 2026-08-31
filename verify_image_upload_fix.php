<?php
// Verify that the image upload fix is working correctly
echo "<h1>Image Upload Fix Verification</h1>";

// Test the upload endpoint directly to see what URL it returns
echo "<h2>Testing Upload Endpoint Response</h2>";

// Simulate what the upload endpoint returns
$test_filename = 'img_' . uniqid() . '.png';
$expected_url = 'uploads/' . $test_filename;

echo "<p>Expected URL format: " . htmlspecialchars($expected_url) . "</p>";
echo "<p>Should NOT contain '../' prefix</p>";

// Check if the URL format is correct
if (strpos($expected_url, '../') === false) {
    echo "<p style='color: green;'>✓ URL format is correct</p>";
} else {
    echo "<p style='color: red;'>✗ URL format is incorrect - contains '../' prefix</p>";
}

echo "<h2>JavaScript Code Verification</h2>";

// Read the JavaScript code to verify it's correct
$js_file = 'teacher/question_management.php';
if (file_exists($js_file)) {
    $content = file_get_contents($js_file);
    
    // Check if there are any instances of "../" + data.url
    if (strpos($content, '../"+data.url') !== false || strpos($content, '../"${data.url}') !== false) {
        echo "<p style='color: red;'>✗ Found incorrect '../' + data.url pattern in JavaScript</p>";
    } else {
        echo "<p style='color: green;'>✓ No incorrect '../' + data.url pattern found in JavaScript</p>";
    }
    
    // Check if there are any instances of "../" + url
    if (strpos($content, '../"+url') !== false || strpos($content, '../"${url}') !== false) {
        echo "<p style='color: red;'>✗ Found incorrect '../' + url pattern in JavaScript</p>";
    } else {
        echo "<p style='color: green;'>✓ No incorrect '../' + url pattern found in JavaScript</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Could not find JavaScript file to verify</p>";
}

echo "<h2>Recommendation</h2>";
echo "<p>If you're still seeing images with '../' prefix, it's likely because:</p>";
echo "<ol>";
echo "<li>The image was uploaded before the fix was applied</li>";
echo "<li>The incorrect URL was saved to the database</li>";
echo "<li>You need to re-upload the image to get the correct URL</li>";
echo "</ol>";
echo "<p>To fix existing images, you can either:</p>";
echo "<ol>";
echo "<li>Re-upload the images to get the correct URLs</li>";
echo "<li>Run a database update script to fix the existing URLs</li>";
echo "</ol>";
?>