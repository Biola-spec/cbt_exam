<?php
// Debug script to check passage content
include 'config/database.php';

$mysqli = getDBConnection();
if ($mysqli) {
    // Get a passage with base64 content if available
    $result = $mysqli->query("SELECT id, passage_title, passage_content FROM comprehension_passages WHERE passage_content LIKE '%data:image%' ORDER BY id DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        echo "<h1>Debug: Passage Content Analysis</h1>";
        echo "<h2>Passage ID: " . $row['id'] . "</h2>";
        echo "<h3>Title: " . htmlspecialchars($row['passage_title']) . "</h3>";
        
        echo "<h3>Raw Content from Database:</h3>";
        echo "<pre>" . htmlspecialchars($row['passage_content']) . "</pre>";
        
        echo "<h3>Content Analysis:</h3>";
        echo "<p>Content length: " . strlen($row['passage_content']) . " characters</p>";
        
        // Check for base64 data
        if (strpos($row['passage_content'], 'data:image') !== false) {
            echo "<p style='color: green;'>✓ Base64 image data detected</p>";
        } else {
            echo "<p style='color: red;'>✗ No base64 image data found</p>";
        }
        
        // Simulate the display process
        echo "<h3>Simulated Display Process:</h3>";
        
        // Step 1: UTF-8 encoding (simplified)
        $utf8_content = $row['passage_content'];
        echo "<p>Step 1 - UTF-8 encoding: <span style='color: green;'>✓ Applied</span></p>";
        
        // Step 2: HTML sanitization
        $allowed_tags = '<p><br><strong><em><img><span><div><ul><ol><li><h1><h2><h3><h4><h5><h6>';
        $sanitized_content = strip_tags($utf8_content, $allowed_tags);
        // Remove javascript attributes
        $sanitized_content = preg_replace('/(<[^>]+) on[a-zA-Z]*="[^"]*"/i', '$1', $sanitized_content);
        $sanitized_content = preg_replace("/(<[^>]+) on[a-zA-Z]*='[^']*'/i", '$1', $sanitized_content);
        echo "<p>Step 2 - HTML sanitization: <span style='color: green;'>✓ Applied</span></p>";
        
        // Step 3: Check for base64 images
        $has_base64_images = (strpos($sanitized_content, 'data:image') !== false);
        echo "<p>Step 3 - Base64 image check: " . ($has_base64_images ? "<span style='color: green;'>✓ Images found</span>" : "<span style='color: red;'>✗ No images</span>") . "</p>";
        
        // Step 4: Final display
        echo "<h3>Final Display Output:</h3>";
        echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9;'>";
        if ($has_base64_images) {
            // Content contains base64 images, output directly without nl2br
            echo $sanitized_content;
        } else {
            // Content doesn't contain base64 images, safe to use nl2br
            echo nl2br($sanitized_content);
        }
        echo "</div>";
        
    } else {
        echo "<h1>Debug: No passages with base64 content found</h1>";
        echo "<p>Checking for any passages...</p>";
        
        // Check if there are any passages at all
        $result = $mysqli->query("SELECT id, passage_title, passage_content FROM comprehension_passages ORDER BY id DESC LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo "<h2>Latest Passage (no base64 content):</h2>";
            echo "<p><strong>ID:</strong> " . $row['id'] . "</p>";
            echo "<p><strong>Title:</strong> " . htmlspecialchars($row['passage_title']) . "</p>";
            echo "<pre>" . htmlspecialchars(substr($row['passage_content'], 0, 200)) . "...</pre>";
        } else {
            echo "<p>No passages found in database</p>";
        }
    }
    $mysqli->close();
} else {
    echo "<h1>Debug: Database Connection Failed</h1>";
    echo "<p>Could not connect to database. Please check:</p>";
    echo "<ul>";
    echo "<li>That XAMPP MySQL service is running</li>";
    echo "<li>That the database 'cbt_exam_system' exists</li>";
    echo "<li>That the database credentials in 'config/database.php' are correct</li>";
    echo "</ul>";
}
?>