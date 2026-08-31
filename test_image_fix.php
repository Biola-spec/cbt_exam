<?php
// Test file to verify image display fixes
function sanitizeHtml($text) {
    // Allow only safe HTML tags (like img, p, br, strong, em)
    $allowed_tags = '<p><br><strong><em><img><span><div><ul><ol><li><h1><h2><h3><h4><h5><h6>';
    $text = strip_tags($text, $allowed_tags);
    
    // Additional security: Remove any javascript or on* attributes
    $text = preg_replace('/(<[^>]+) on[a-zA-Z]*="[^"]*"/i', '$1', $text);
    $text = preg_replace("/(<[^>]+) on[a-zA-Z]*='[^']*'/i", '$1', $text);
    
    return $text;
}

function cleanWpsContent($content) {
    // Remove null bytes and other control characters that might cause issues
    // Be careful not to remove characters that are part of base64 data
    $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
    
    // Normalize line endings
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    
    // Remove any invisible Unicode characters that WPS might insert
    $content = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $content);
    
    // Decode any HTML entities that might have been inserted
    // Skip this for content that contains base64 images or image URLs to avoid corrupting the data
    if (strpos($content, 'data:image') === false && strpos($content, '<img') === false) {
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // Trim whitespace but preserve line breaks
    $content = rtrim($content);
    
    return $content;
}

// Test content with URL-based image
$content_with_url_image = '<p>This is a test passage with a URL-based image:</p>
<img src="uploads/img_6926edd8df5153.81050369.JPG" alt="Test Image" style="max-width: 100%; height: auto;">
<p>End of passage.</p>';

// Test content with base64 image
$content_with_base64_image = '<p>This is a test passage with a base64 image:</p>
<img src="data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAMCAgMCAgMDAwMEAwMEBQgFBAQE" alt="Test Image" style="max-width: 100%; height: auto;">
<p>End of passage.</p>';

// Test content without images
$content_without_images = '<p>This is a test passage without any images.</p>
<p>This is another paragraph.</p>';

echo "<h1>Image Display Fix Test</h1>";

echo "<h2>Original Content with URL Image:</h2>";
echo "<pre>" . htmlspecialchars($content_with_url_image) . "</pre>";

echo "<h2>After cleanWpsContent():</h2>";
$cleaned_url = cleanWpsContent($content_with_url_image);
echo "<pre>" . htmlspecialchars($cleaned_url) . "</pre>";

echo "<h2>After sanitizeHtml():</h2>";
$sanitized_url = sanitizeHtml($cleaned_url);
echo "<div style='border: 1px solid #ccc; padding: 10px;'>" . $sanitized_url . "</div>";

echo "<h2>With nl2br (incorrect for images):</h2>";
echo "<div style='border: 1px solid #ccc; padding: 10px;'>" . nl2br($sanitized_url) . "</div>";

echo "<hr>";

echo "<h2>Original Content with Base64 Image:</h2>";
echo "<pre>" . htmlspecialchars($content_with_base64_image) . "</pre>";

echo "<h2>After cleanWpsContent():</h2>";
$cleaned_base64 = cleanWpsContent($content_with_base64_image);
echo "<pre>" . htmlspecialchars($cleaned_base64) . "</pre>";

echo "<h2>After sanitizeHtml():</h2>";
$sanitized_base64 = sanitizeHtml($cleaned_base64);
echo "<div style='border: 1px solid #ccc; padding: 10px;'>" . $sanitized_base64 . "</div>";

echo "<hr>";

echo "<h2>Original Content without Images:</h2>";
echo "<pre>" . htmlspecialchars($content_without_images) . "</pre>";

echo "<h2>After cleanWpsContent():</h2>";
$cleaned_no_images = cleanWpsContent($content_without_images);
echo "<pre>" . htmlspecialchars($cleaned_no_images) . "</pre>";

echo "<h2>After sanitizeHtml():</h2>";
$sanitized_no_images = sanitizeHtml($cleaned_no_images);
echo "<div style='border: 1px solid #ccc; padding: 10px;'>" . $sanitized_no_images . "</div>";

echo "<h2>With nl2br (correct for content without images):</h2>";
echo "<div style='border: 1px solid #ccc; padding: 10px;'>" . nl2br($sanitized_no_images) . "</div>";
?>