<?php
// Test to see the actual database content
include 'config/database.php';

$mysqli = getDBConnection();
if ($mysqli) {
    // Get the latest passage with base64 content
    $result = $mysqli->query("SELECT id, passage_title, passage_content FROM comprehension_passages WHERE passage_content LIKE '%data:image%' ORDER BY id DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        echo "<h1>Actual Database Content Test</h1>";
        
        echo "<h2>Passage Information:</h2>";
        echo "<p><strong>ID:</strong> " . $row['id'] . "</p>";
        echo "<p><strong>Title:</strong> " . htmlspecialchars($row['passage_title']) . "</p>";
        
        echo "<h2>Raw Content from Database:</h2>";
        echo "<pre>" . htmlspecialchars($row['passage_content']) . "</pre>";
        
        echo "<h2>Content Length:</h2>";
        echo "<p>" . strlen($row['passage_content']) . " characters</p>";
        
        // Check if base64 data is intact
        echo "<h2>Base64 Data Check:</h2>";
        if (strpos($row['passage_content'], 'data:image') !== false) {
            echo "<p style='color: green;'>✓ Base64 data detected</p>";
            
            // Extract a portion of the base64 data to check integrity
            $start_pos = strpos($row['passage_content'], 'data:image');
            $end_pos = strpos($row['passage_content'], '"', $start_pos);
            if ($end_pos === false) {
                $end_pos = strlen($row['passage_content']);
            }
            
            $preview = substr($row['passage_content'], $start_pos, min(100, $end_pos - $start_pos));
            echo "<p><strong>Base64 preview:</strong> " . htmlspecialchars($preview) . "</p>";
        } else {
            echo "<p style='color: red;'>✗ Base64 data not detected</p>";
        }
        
        // Simulate the display pipeline
        echo "<h2>Simulated Display Pipeline:</h2>";
        
        // Sanitize HTML
        $allowed_tags = '<p><br><strong><em><img><span><div><ul><ol><li><h1><h2><h3><h4><h5><h6>';
        $sanitized_content = strip_tags($row['passage_content'], $allowed_tags);
        
        // Remove javascript attributes
        $sanitized_content = preg_replace('/(<[^>]+) on[a-zA-Z]*="[^"]*"/i', '$1', $sanitized_content);
        $sanitized_content = preg_replace("/(<[^>]+) on[a-zA-Z]*='[^']*'/i", '$1', $sanitized_content);
        
        echo "<p><strong>After sanitization:</strong></p>";
        echo "<pre>" . htmlspecialchars($sanitized_content) . "</pre>";
        
        // Final display
        echo "<h2>Final Display:</h2>";
        echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9;'>";
        if (strpos($sanitized_content, 'data:image') !== false) {
            // Content contains base64 images, output directly without nl2br
            echo $sanitized_content;
        } else {
            // Content doesn't contain base64 images, safe to use nl2br
            echo nl2br($sanitized_content);
        }
        echo "</div>";
        
    } else {
        echo "<p>No passages with base64 content found in database</p>";
        
        // Let's check if there are any passages at all
        $result = $mysqli->query("SELECT id, passage_title, passage_content FROM comprehension_passages ORDER BY id DESC LIMIT 5");
        if ($result && $result->num_rows > 0) {
            echo "<h2>Recent Passages (no base64 content):</h2>";
            while ($row = $result->fetch_assoc()) {
                echo "<p><strong>ID:</strong> " . $row['id'] . " <strong>Title:</strong> " . htmlspecialchars($row['passage_title']) . "</p>";
                echo "<pre>" . htmlspecialchars(substr($row['passage_content'], 0, 100)) . "...</pre>";
            }
        } else {
            echo "<p>No passages found in database</p>";
        }
    }
    $mysqli->close();
} else {
    echo "Could not connect to database";
}
?>