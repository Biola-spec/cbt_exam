<?php
// Debug student display issue
function sanitizeHtml($text) {
    // Allow only safe HTML tags (like img, p, br, strong, em)
    $allowed_tags = '<p><br><strong><em><img><span><div><ul><ol><li><h1><h2><h3><h4><h5><h6>';
    $text = strip_tags($text, $allowed_tags);
    
    // Additional security: Remove any javascript or on* attributes
    $text = preg_replace('/(<[^>]+) on[a-zA-Z]*="[^"]*"/i', '$1', $text);
    $text = preg_replace("/(<[^>]+) on[a-zA-Z]*='[^']*'/i", '$1', $text);
    
    return $text;
}

// Sample content with URL-based image
$content = '<p>This is a test passage with an image:</p>
<img src="uploads/img_6926edd8df5153.81050369.JPG" alt="Test Image" style="max-width: 100%; height: auto;">
<p>End of passage.</p>';

echo "<h1>Debug Student Display</h1>";

echo "<h2>Raw Content:</h2>";
echo "<pre>" . htmlspecialchars($content) . "</pre>";

echo "<h2>After sanitizeHtml():</h2>";
$sanitized = sanitizeHtml($content);
echo "<div style='border: 1px solid #ccc; padding: 10px;'>";
echo $sanitized;
echo "</div>";

echo "<h2>Expected Display:</h2>";
echo "<div style='border: 1px solid #ccc; padding: 10px;'>";
echo $content; // Without sanitization for comparison
echo "</div>";
?>