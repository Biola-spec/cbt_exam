<?php
// Test the full display pipeline
function sanitizeHtml($text) {
    // Allow only safe HTML tags (like img, p, br, strong, em)
    $allowed_tags = '<p><br><strong><em><img><span><div><ul><ol><li><h1><h2><h3><h4><h5><h6>';
    $text = strip_tags($text, $allowed_tags);
    
    // Additional security: Remove any javascript or on* attributes
    $text = preg_replace('/(<[^>]+) on[a-zA-Z]*="[^"]*"/i', '$1', $text);
    $text = preg_replace("/(<[^>]+) on[a-zA-Z]*='[^']*'/i", '$1', $text);
    
    return $text;
}

function ensureUtf8Encoding($content) {
    // If content is already UTF-8, return as is
    if (mb_check_encoding($content, 'UTF-8')) {
        return $content;
    }
    
    // Try to convert from common encodings
    $encodings = ['Windows-1252', 'ISO-8859-1'];
    foreach ($encodings as $encoding) {
        if (mb_check_encoding($content, $encoding)) {
            return mb_convert_encoding($content, 'UTF-8', $encoding);
        }
    }
    
    // Fallback
    return mb_convert_encoding($content, 'UTF-8', 'auto');
}

// Simulate what would happen in the student view
$content_from_db = '<p>This is a test passage with an image:</p>
<img src="data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=" alt="Test Image">
<p>End of passage.</p>';

echo "<h1>Full Display Pipeline Test</h1>";

echo "<h2>1. Content from database:</h2>";
echo "<pre>" . htmlspecialchars($content_from_db) . "</pre>";

echo "<h2>2. After ensureUtf8Encoding:</h2>";
$utf8_content = ensureUtf8Encoding($content_from_db);
echo "<pre>" . htmlspecialchars($utf8_content) . "</pre>";

echo "<h2>3. After sanitizeHtml:</h2>";
$sanitized_content = sanitizeHtml($utf8_content);
echo "<pre>" . htmlspecialchars($sanitized_content) . "</pre>";

echo "<h2>4. Check for base64 data:</h2>";
if (strpos($sanitized_content, 'data:image') !== false) {
    echo "<p style='color: green;'>✓ Base64 data detected</p>";
} else {
    echo "<p style='color: red;'>✗ Base64 data not detected</p>";
}

echo "<h2>5. Final display (with nl2br check):</h2>";
echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9;'>";
if (strpos($sanitized_content, 'data:image') !== false) {
    // Content contains base64 images, output directly without nl2br
    echo $sanitized_content;
} else {
    // Content doesn't contain base64 images, safe to use nl2br
    echo nl2br($sanitized_content);
}
echo "</div>";

echo "<h2>6. Comparison with direct output:</h2>";
echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9;'>";
echo $content_from_db;
echo "</div>";
?>