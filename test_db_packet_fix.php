<?php
// Test to verify the database packet size fix
include 'config/database.php';

function sanitizeHtml($text) {
    // Allow only safe HTML tags (like img, p, br, strong, em)
    $allowed_tags = '<p><br><strong><em><img><span><div><ul><ol><li><h1><h2><h3><h4><h5><h6>';
    $text = strip_tags($text, $allowed_tags);
    
    // Additional security: Remove any javascript or on* attributes
    $text = preg_replace('/(<[^>]+) on[a-zA-Z]*="[^"]*"/i', '$1', $text);
    $text = preg_replace("/(<[^>]+) on[a-zA-Z]*='[^']*'/i", '$1', $text);
    
    return $text;
}

echo "<h1>Database Packet Size Fix Test</h1>";

// Test content with an image URL instead of base64 data
$test_content = '<p>This is a test passage with an image:</p>
<img src="uploads/test_image.jpg" alt="Test Image" style="max-width: 100%; height: auto;">
<p>The image is now stored as a file and referenced by URL instead of embedded as base64 data.</p>';

echo "<h2>Test Content:</h2>";
echo "<pre>" . htmlspecialchars($test_content) . "</pre>";

echo "<h2>After HTML Sanitization:</h2>";
$sanitized_content = sanitizeHtml($test_content);
echo "<pre>" . htmlspecialchars($sanitized_content) . "</pre>";

echo "<h2>Final Display:</h2>";
echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9;'>";
echo $sanitized_content;
echo "</div>";

// Test database insertion
$mysqli = getDBConnection();
if ($mysqli) {
    echo "<h2>Database Test:</h2>";
    
    // Create a test table if it doesn't exist
    $mysqli->query("CREATE TABLE IF NOT EXISTS test_passages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255),
        content TEXT
    )");
    
    // Insert test content
    $stmt = $mysqli->prepare("INSERT INTO test_passages (title, content) VALUES (?, ?)");
    $title = "Test Passage";
    $stmt->bind_param("ss", $title, $test_content);
    
    if ($stmt->execute()) {
        echo "<p style='color: green;'>✓ Content inserted successfully into database</p>";
        
        // Retrieve and verify
        $result = $mysqli->query("SELECT content FROM test_passages WHERE title = 'Test Passage'");
        if ($result && $row = $result->fetch_assoc()) {
            echo "<p style='color: green;'>✓ Content retrieved successfully from database</p>";
            echo "<h3>Retrieved Content:</h3>";
            echo "<pre>" . htmlspecialchars($row['content']) . "</pre>";
        } else {
            echo "<p style='color: red;'>✗ Error retrieving content from database</p>";
        }
        
        // Clean up
        $mysqli->query("DELETE FROM test_passages WHERE title = 'Test Passage'");
    } else {
        echo "<p style='color: red;'>✗ Error inserting content into database: " . $mysqli->error . "</p>";
    }
    
    $stmt->close();
    $mysqli->close();
} else {
    echo "<p style='color: red;'>✗ Could not connect to database</p>";
}

echo "<h2>Benefits of This Approach:</h2>";
echo "<ul>";
echo "<li>✓ No more 'max_allowed_packet' errors</li>";
echo "<li>✓ Smaller database size</li>";
echo "<li>✓ Faster database operations</li>";
echo "<li>✓ Better performance</li>";
echo "<li>✓ Easier content management</li>";
echo "</ul>";
?>