<?php
// Test to see what's actually being saved and retrieved from the database
include 'config/database.php';

$mysqli = getDBConnection();
if ($mysqli) {
    // Get the latest passage with base64 content
    $result = $mysqli->query("SELECT id, passage_title, passage_content FROM comprehension_passages WHERE passage_content LIKE '%data:image%' ORDER BY id DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "<h2>Passage from Database:</h2>";
        echo "<p><strong>ID:</strong> " . $row['id'] . "</p>";
        echo "<p><strong>Title:</strong> " . htmlspecialchars($row['passage_title']) . "</p>";
        echo "<h3>Content:</h3>";
        echo "<pre>" . htmlspecialchars(substr($row['passage_content'], 0, 500)) . "...</pre>";
        echo "<h3>Full Content Length:</h3>";
        echo "<p>" . strlen($row['passage_content']) . " characters</p>";
        
        // Check if it contains intact base64 data
        if (strpos($row['passage_content'], '/9j/4AAUSkZJRg') !== false) {
            echo "<p style='color: green;'>Base64 data appears to be intact</p>";
        } else {
            echo "<p style='color: red;'>Base64 data may be corrupted</p>";
        }
        
        // Show how it would be displayed
        echo "<h2>How it would be displayed:</h2>";
        echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9;'>";
        // Simulate the sanitizeHtml function
        $allowed_tags = '<p><br><strong><em><img><span><div><ul><ol><li><h1><h2><h3><h4><h5><h6>';
        $sanitized_content = strip_tags($row['passage_content'], $allowed_tags);
        echo $sanitized_content;
        echo "</div>";
    } else {
        echo "<p>No passages with base64 content found in database</p>";
    }
    $mysqli->close();
} else {
    echo "Could not connect to database";
}
?>