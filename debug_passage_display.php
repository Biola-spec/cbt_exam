<?php
// Debug script to check passage display issues
function sanitizeHtml($text) {
    // Allow only safe HTML tags (like img, p, br, strong, em)
    $allowed_tags = '<p><br><strong><em><img><span><div><ul><ol><li><h1><h2><h3><h4><h5><h6>';
    $text = strip_tags($text, $allowed_tags);
    
    // Additional security: Remove any javascript or on* attributes
    $text = preg_replace('/(<[^>]+) on[a-zA-Z]*="[^"]*"/i', '$1', $text);
    $text = preg_replace("/(<[^>]+) on[a-zA-Z]*='[^']*'/i", '$1', $text);
    
    return $text;
}

// Simulate what might be in the database
$sample_content = '<p>This is a test passage with an image:</p>
<img src="uploads/img_6926edd8df5153.81050369.JPG" alt="Test Image" style="max-width: 100%; height: auto;">
<p>End of passage.</p>';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Debug Passage Display</title>
</head>
<body>
    <h1>Debug Passage Display</h1>
    
    <h2>1. Raw Content:</h2>
    <pre>" . htmlspecialchars($sample_content) . "</pre>
    
    <h2>2. After sanitizeHtml:</h2>
    <div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9;'>
        " . sanitizeHtml($sample_content) . "
    </div>
    
    <h2>3. With nl2br (student interface method for non-base64 content):</h2>
    <div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9;'>
        " . nl2br(sanitizeHtml($sample_content)) . "
    </div>
    
    <h2>4. Direct HTML output:</h2>
    <div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9;'>
        " . $sample_content . "
    </div>
    
    <h2>5. Image File Test:</h2>
    <p>Let's check if the image file can be displayed directly:</p>
    <img src='uploads/img_6926edd8df5153.81050369.JPG' alt='Test Image' style='max-width: 300px; height: auto;'>
    
    <h2>Analysis:</h2>
    <p>If the image is not displaying in step 2 or 4, the issue is likely one of the following:</p>
    <ol>
        <li>The image file path is incorrect</li>
        <li>The web server doesn't have permission to read the image file</li>
        <li>The image file is corrupted</li>
        <li>There's an issue with the HTML sanitization</li>
        <li>The content is not being saved correctly to the database</li>
    </ol>
</body>
</html>";
?>