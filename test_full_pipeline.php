<?php
// Test the full pipeline from saving to displaying
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

function sanitizeHtml($text) {
    // Allow only safe HTML tags (like img, p, br, strong, em)
    $allowed_tags = '<p><br><strong><em><img><span><div><ul><ol><li><h1><h2><h3><h4><h5><h6>';
    $text = strip_tags($text, $allowed_tags);
    
    // Additional security: Remove any javascript or on* attributes
    $text = preg_replace('/(<[^>]+) on[a-zA-Z]*="[^"]*"/i', '$1', $text);
    $text = preg_replace("/(<[^>]+) on[a-zA-Z]*='[^']*'/i", '$1', $text);
    
    return $text;
}

// Sample passage content with base64 image (similar to what was shown in the issue)
$original_content = '<p>This is a sample passage with an image:</p>
<img src="data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=" alt="Test Image">
<p>Images should display properly.</p>';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Full Pipeline Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .content-box { border: 1px solid #ccc; padding: 15px; background: #f9f9f9; margin: 10px 0; }
        .error { color: red; }
        .success { color: green; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <h1>Full Pipeline Test: From Saving to Displaying</h1>
    
    <h2>Step 1: Original Content (What teacher enters)</h2>
    <div class='content-box'>
        <pre>" . htmlspecialchars($original_content) . "</pre>
    </div>
    
    <h2>Step 2: After Processing for Database Storage</h2>
    <div class='content-box'>
        <pre>" . htmlspecialchars(cleanWpsContent($original_content)) . "</pre>
    </div>
    
    <h2>Step 3: Simulate Database Storage and Retrieval</h2>
    <p>Content as it would be retrieved from the database:</p>
    <div class='content-box'>
        <pre>" . htmlspecialchars(cleanWpsContent($original_content)) . "</pre>
    </div>
    
    <h2>Step 4: After HTML Sanitization</h2>
    <div class='content-box'>
        <pre>" . htmlspecialchars(sanitizeHtml(cleanWpsContent($original_content))) . "</pre>
    </div>
    
    <h2>Step 5: Final Display (How it should appear to students)</h2>
    <p>This is how the content should be displayed to students:</p>
    <div class='content-box'>
        " . sanitizeHtml(cleanWpsContent($original_content)) . "
    </div>
    
    <h2>Verification:</h2>
    <p>Checking if the image is properly displayed:</p>
    ";

// Check if the image is properly displayed
$sanitized_content = sanitizeHtml(cleanWpsContent($original_content));
if (strpos($sanitized_content, 'data:image') !== false) {
    echo "<p class='success'>✓ Base64 image data detected in final output</p>";
    
    // Check if it's properly formatted as an img tag
    if (strpos($sanitized_content, '<img src="data:image') !== false) {
        echo "<p class='success'>✓ Image properly formatted as HTML img tag</p>";
    } else {
        echo "<p class='error'>✗ Image not properly formatted as HTML img tag</p>";
    }
} else {
    echo "<p class='error'>✗ Base64 image data not found in final output</p>";
}

echo "
    <h2>Comparison with Problematic Display:</h2>
    <p>This is how the content would appear if it were displayed incorrectly (as raw text):</p>
    <div class='content-box' style='color: red;'>
        " . nl2br(htmlspecialchars($sanitized_content)) . "
    </div>
    
    <h2>Conclusion:</h2>
    ";

// Final verification
if (strpos($sanitized_content, 'data:image') !== false && strpos($sanitized_content, '<img src="data:image') !== false) {
    echo "<p class='success'>✓ The full pipeline is working correctly. Images should display properly.</p>";
} else {
    echo "<p class='error'>✗ There may be an issue with the pipeline.</p>";
}

echo "
</body>
</html>";
?>