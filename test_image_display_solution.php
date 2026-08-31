<?php
// Test to demonstrate the solution for image display in passage content
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
$passage_content = '<p>This is a sample passage with an image:</p>
<img src="data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=" alt="Test Image">
<p>Images should display properly when the database is working correctly.</p>';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Image Display Solution Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .content-box { border: 1px solid #ccc; padding: 15px; background: #f9f9f9; margin: 10px 0; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <h1>Image Display Solution Test</h1>
    
    <h2>Problem:</h2>
    <p>Base64 image data is being displayed as raw text instead of being rendered as an image.</p>
    
    <h2>Solution Implemented:</h2>
    <p>In the student view (<code>student/take_exam.php</code>), we modified the passage content display logic to:</p>
    <ol>
        <li>Check if content contains base64 images</li>
        <li>If it does, output directly without applying <code>nl2br</code> (which can corrupt base64 data)</li>
        <li>If it doesn't, apply <code>nl2br</code> for proper line break formatting</li>
    </ol>
    
    <h2>How it should work:</h2>
    
    <h3>1. Raw Content from Database:</h3>
    <div class='content-box'>
        <pre>" . htmlspecialchars($passage_content) . "</pre>
    </div>
    
    <h3>2. After HTML Sanitization:</h3>
    <div class='content-box'>
        <pre>" . htmlspecialchars(sanitizeHtml($passage_content)) . "</pre>
    </div>
    
    <h3>3. Final Display (How it should appear to students):</h3>
    <div class='content-box'>
        " . sanitizeHtml($passage_content) . "
    </div>
    
    <h2>Key Code Changes:</h2>
    <p>In <code>student/take_exam.php</code>, we replaced:</p>
    <pre>" . htmlspecialchars('<?php echo nl2br(sanitizeHtml(ensureUtf8Encoding($current_passage["passage_content"]))); ?>') . "</pre>
    
    <p>With:</p>
    <pre>" . htmlspecialchars('<?php 
// For content with images, we need to be careful with nl2br as it can corrupt base64 data
// Only apply nl2br to content that doesn\'t contain base64 images
$sanitized_content = sanitizeHtml(ensureUtf8Encoding($current_passage[\'passage_content\']));
if (strpos($sanitized_content, \'data:image\') !== false) {
    // Content contains base64 images, output directly without nl2br
    echo $sanitized_content;
} else {
    // Content doesn\'t contain base64 images, safe to use nl2br
    echo nl2br($sanitized_content);
}
?>') . "</pre>
    
    <h2>Additional Fix:</h2>
    <p>We also fixed the <code>cleanWpsContent</code> function in <code>teacher/question_management.php</code> to avoid corrupting base64 data:</p>
    <pre>" . htmlspecialchars('function cleanWpsContent($content) {
    // Remove null bytes and other control characters that might cause issues
    // Be careful not to remove characters that are part of base64 data
    $content = preg_replace(\'/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/\', \'\', $content);
    
    // Normalize line endings
    $content = str_replace([\"\r\n\", \"\r\"], \"\n\", $content);
    
    // Remove any invisible Unicode characters that WPS might insert
    $content = preg_replace(\'/[\x{200B}-\x{200D}\x{FEFF}]/u\', \'\', $content);
    
    // Decode any HTML entities that might have been inserted
    // Skip this for content that contains base64 images to avoid corrupting the data
    if (strpos($content, \'data:image\') === false) {
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, \'UTF-8\');
    }
    
    // Trim whitespace but preserve line breaks
    $content = rtrim($content);
    
    return $content;
}') . "</pre>
    
    <h2>Conclusion:</h2>
    <p class='success'>With these fixes, images inserted into passage content should now display properly as actual images rather than raw base64 text.</p>
    
    <p><strong>Note:</strong> The database connection appears to be unavailable, which is why we're using simulated content for this test.</p>
</body>
</html>";
?>