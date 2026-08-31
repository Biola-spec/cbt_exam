<?php
// Test file to verify the current implementation for image display
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

// Sample passage content with base64 image (similar to what was shown in the issue)
$passage_content = '<p>This is a sample passage with an image:</p>
<img src="data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=" alt="Test Image">
<p>Images should display properly.</p>';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test: Current Implementation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4">Current Implementation Test</h2>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Raw Content (What's stored in database)</h5>
            </div>
            <div class="card-body">
                <pre><?php echo htmlspecialchars($passage_content); ?></pre>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Processed Content (How it should display)</h5>
            </div>
            <div class="card-body">
                <div class="bg-light p-3 rounded">
                    <?php 
                    // Apply the same logic as in the student view
                    $sanitized_content = sanitizeHtml(ensureUtf8Encoding($passage_content));
                    if (strpos($sanitized_content, 'data:image') !== false) {
                        // Content contains base64 images, output directly without nl2br
                        echo $sanitized_content;
                    } else {
                        // Content doesn't contain base64 images, safe to use nl2br
                        echo nl2br($sanitized_content);
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>