<?php
// Test file to verify the image display fix
function sanitizeHtml($text) {
    // Allow only safe HTML tags (like img, p, br, strong, em)
    $allowed_tags = '<p><br><strong><em><img><span><div><ul><ol><li><h1><h2><h3><h4><h5><h6>';
    $text = strip_tags($text, $allowed_tags);
    
    // Additional security: Remove any javascript or on* attributes
    $text = preg_replace('/(<[^>]+) on[a-zA-Z]*="[^"]*"/i', '$1', $text);
    $text = preg_replace("/(<[^>]+) on[a-zA-Z]*='[^']*'/i", '$1', $text);
    
    return $text;
}

// Sample passage content with an image
$passage_content = '<p>This is a sample passage with an image:</p>
<img src="uploads/img_692cbb9e89f789.03480421.png" alt="Uploaded Image" style="max-width: 100%; height: auto; border: 2px dashed #007bff; padding: 5px; margin: 5px;">
<p>Images should display properly.</p>';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Image Display Fix Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Image Display Fix Test</h1>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Raw Content (What's stored in database)</h5>
            </div>
            <div class="card-body">
                <pre><?php echo htmlspecialchars($passage_content); ?></pre>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Teacher Interface Display (Fixed)</h5>
            </div>
            <div class="card-body">
                <p><strong>Preview in passage list:</strong> 
                <?php 
                // Show a preview of the passage content (first 100 characters)
                $preview_content = substr($passage_content, 0, 100);
                // If it contains an image, show a placeholder
                if (strpos($passage_content, '<img') !== false) {
                    echo 'Contains image(s)';
                } else {
                    echo htmlspecialchars($preview_content);
                }
                ?>
                </p>
                
                <p><strong>Full content in editor:</strong></p>
                <div class="bg-light p-3 rounded">
                    <?php echo $passage_content; ?>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Student Interface Display (Fixed)</h5>
            </div>
            <div class="card-body">
                <div class="bg-light p-3 rounded">
                    <?php 
                    // Apply the same logic as in the student view
                    $sanitized_content = sanitizeHtml($passage_content);
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