<?php
// Comprehensive test to diagnose image display issues
function sanitizeHtml($text) {
    // Allow only safe HTML tags (like img, p, br, strong, em)
    $allowed_tags = '<p><br><strong><em><img><span><div><ul><ol><li><h1><h2><h3><h4><h5><h6>';
    $text = strip_tags($text, $allowed_tags);
    
    // Additional security: Remove any javascript or on* attributes
    $text = preg_replace('/(<[^>]+) on[a-zA-Z]*="[^"]*"/i', '$1', $text);
    $text = preg_replace("/(<[^>]+) on[a-zA-Z]*='[^']*'/i", '$1', $text);
    
    return $text;
}

// Sample content that should be in the database
$content_with_image = '<p>This is a test passage with an image:</p>
<img src="uploads/img_6926edd8df5153.81050369.JPG" alt="Test Image" style="max-width: 100%; height: auto;">
<p>End of passage.</p>';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Comprehensive Image Display Test</title>
    <style>
        .test-box { border: 1px solid #ccc; padding: 15px; margin: 15px 0; background: #f9f9f9; }
        .error { color: red; }
        .success { color: green; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <h1>Comprehensive Image Display Test</h1>
    
    <h2>1. Raw Content (What should be in database):</h2>
    <div class='test-box'>
        <pre>" . htmlspecialchars($content_with_image) . "</pre>
    </div>
    
    <h2>2. After sanitizeHtml function:</h2>
    <div class='test-box'>
        " . sanitizeHtml($content_with_image) . "
    </div>
    
    <h2>3. Direct HTML output:</h2>
    <div class='test-box'>
        " . $content_with_image . "
    </div>
    
    <h2>4. File Existence Check:</h2>
    <div class='test-box'>";

// Check if the image file exists
$image_path = 'uploads/img_6926edd8df5153.81050369.JPG';
if (file_exists($image_path)) {
    echo "<p class='success'>✓ Image file exists</p>";
    echo "<p>File size: " . filesize($image_path) . " bytes</p>";
    
    // Try to get image info
    $image_info = getimagesize($image_path);
    if ($image_info) {
        echo "<p class='success'>✓ File is a valid image</p>";
        echo "<p>Image dimensions: " . $image_info[0] . "x" . $image_info[1] . "</p>";
        echo "<p>MIME type: " . $image_info['mime'] . "</p>";
    } else {
        echo "<p class='error'>✗ File exists but is not a valid image</p>";
    }
} else {
    echo "<p class='error'>✗ Image file does not exist at: " . $image_path . "</p>";
}

echo "</div>
    
    <h2>5. Direct Image Display Test:</h2>
    <div class='test-box'>";

if (file_exists($image_path)) {
    echo "<img src='" . $image_path . "' alt='Test Image' style='max-width: 100%; height: auto;'>";
} else {
    echo "<p class='error'>Cannot display image - file not found</p>";
}

echo "</div>
    
    <h2>6. Path Resolution Test:</h2>
    <div class='test-box'>
        <p>Current working directory: " . getcwd() . "</p>
        <p>Image path: " . $image_path . "</p>
        <p>Full image path: " . realpath($image_path) . "</p>
        <p>File permissions: " . substr(sprintf('%o', fileperms($image_path)), -4) . "</p>
    </div>
    
    <h2>Conclusion:</h2>
    <div class='test-box'>";

// Check all conditions
$issues = [];

if (!file_exists($image_path)) {
    $issues[] = "Image file does not exist";
}

if (file_exists($image_path)) {
    $image_info = getimagesize($image_path);
    if (!$image_info) {
        $issues[] = "File exists but is not a valid image";
    }
}

if (empty($issues)) {
    echo "<p class='success'>✓ All tests passed. Images should display correctly.</p>";
    echo "<p>If images are still not displaying in the teacher or student interface, the issue might be:</p>
          <ul>
              <li>HTML sanitization removing image tags (check sanitizeHtml function)</li>
              <li>Database content not being saved correctly</li>
              <li>JavaScript not properly inserting content into the editor</li>
              <li>Web server configuration issues</li>
          </ul>";
} else {
    echo "<p class='error'>✗ Issues found:</p><ul>";
    foreach ($issues as $issue) {
        echo "<li>" . $issue . "</li>";
    }
    echo "</ul>";
}

echo "</div>
</body>
</html>";
?>