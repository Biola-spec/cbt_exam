<?php
// Verify that images are being displayed correctly
function sanitizeHtml($text) {
    // Allow only safe HTML tags (like img, p, br, strong, em)
    $allowed_tags = '<p><br><strong><em><img><span><div><ul><ol><li><h1><h2><h3><h4><h5><h6>';
    $text = strip_tags($text, $allowed_tags);
    
    // Additional security: Remove any javascript or on* attributes
    $text = preg_replace('/(<[^>]+) on[a-zA-Z]*="[^"]*"/i', '$1', $text);
    $text = preg_replace("/(<[^>]+) on[a-zA-Z]*='[^']*'/i", '$1', $text);
    
    return $text;
}

// Test content with a real image URL
$content = '<p>This is a test passage with an image:</p>
<img src="uploads/img_6926edd8df5153.81050369.JPG" alt="Test Image" style="max-width: 100%; height: auto;">
<p>This is text after the image.</p>';

echo "<h1>Image Display Verification</h1>";

echo "<h2>Raw Content:</h2>";
echo "<pre>" . htmlspecialchars($content) . "</pre>";

echo "<h2>After sanitizeHtml():</h2>";
$sanitized = sanitizeHtml($content);
echo "<div style='border: 1px solid #ccc; padding: 10px;'>";
echo $sanitized;
echo "</div>";

echo "<h2>Expected Display:</h2>";
echo "<div style='border: 1px solid #ccc; padding: 10px;'>";
echo $content;
echo "</div>";

// Check if image file exists
$image_path = 'uploads/img_6926edd8df5153.81050369.JPG';
if (file_exists($image_path)) {
    echo "<h2>Image File Status:</h2>";
    echo "<p style='color: green;'>✓ Image file exists</p>";
    echo "<p>File size: " . filesize($image_path) . " bytes</p>";
    echo "<img src='$image_path' alt='Test Image' style='max-width: 300px;'>";
} else {
    echo "<h2>Image File Status:</h2>";
    echo "<p style='color: red;'>✗ Image file does not exist at: $image_path</p>";
}
?>