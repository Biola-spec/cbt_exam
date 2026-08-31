<?php
// Test content saving process
function cleanWpsContent($content) {
    // Remove null bytes and other control characters that might cause issues
    // Be careful not to remove characters that are part of base64 data
    $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
    
    // Normalize line endings
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    
    // Remove any invisible Unicode characters that WPS might insert
    $content = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $content);
    
    // Decode any HTML entities that might have been inserted
    // Skip this for content that contains base64 images to avoid corrupting the data
    if (strpos($content, 'data:image') === false) {
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // Trim whitespace but preserve line breaks
    $content = rtrim($content);
    
    return $content;
}

// Sample passage content with base64 image
$original_content = '<p>This is a test passage with an image:</p>
<img src="data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=" alt="Test Image">
<p>End of passage.</p>';

echo "<h1>Content Saving Process Test</h1>";

echo "<h2>1. Original Content:</h2>";
echo "<pre>" . htmlspecialchars($original_content) . "</pre>";

echo "<h2>2. After cleanWpsContent:</h2>";
$processed_content = cleanWpsContent($original_content);
echo "<pre>" . htmlspecialchars($processed_content) . "</pre>";

echo "<h2>3. Comparison:</h2>";
if ($original_content === $processed_content) {
    echo "<p style='color: green;'>✓ Content unchanged (base64 data preserved)</p>";
} else {
    echo "<p style='color: orange;'>⚠ Content modified (checking for data integrity)</p>";
    
    // Check if base64 data is still intact
    if (strpos($processed_content, '/9j/4AAQSkZJRgABAQAAAQABAAD') !== false) {
        echo "<p style='color: green;'>✓ Base64 data appears to be intact</p>";
    } else {
        echo "<p style='color: red;'>✗ Base64 data may be corrupted</p>";
    }
}

// Test with content that doesn't have base64 images
$text_content = "<p>This is a test with &quot;quotes&quot; and &amp; symbols.</p>\n<p>Second line.</p>";

echo "<h2>4. Test with non-base64 content:</h2>";
echo "<p><strong>Original:</strong> " . htmlspecialchars($text_content) . "</p>";
echo "<p><strong>After cleanWpsContent:</strong> " . htmlspecialchars(cleanWpsContent($text_content)) . "</p>";

$processed_text = cleanWpsContent($text_content);
if ($text_content !== $processed_text) {
    echo "<p style='color: green;'>✓ HTML entities correctly decoded for non-base64 content</p>";
} else {
    echo "<p style='color: orange;'>⚠ No HTML entity decoding occurred</p>";
}

echo "<h2>5. Database Simulation:</h2>";
echo "<p>If this content were saved to the database and retrieved, it would be:</p>";
echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9;'>";
echo $processed_content;
echo "</div>";
?>