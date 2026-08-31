<?php
// Test script to verify image display in passages
require_once 'config/database.php';
require_once 'config/session.php';

echo "<h2>Passage Image Display Test</h2>";
echo "<hr>";

try {
    $mysqli = getDBConnection();
    
    // Get sample passage with images
    $stmt = $mysqli->prepare("SELECT id, passage_title, passage_content FROM comprehension_passages LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $passage = $result->fetch_assoc();
        
        echo "<h3>Test Passage: " . htmlspecialchars($passage['passage_title'] ?: 'Untitled') . "</h3>";
        
        echo "<h4>1. Raw Database Content (first 500 chars):</h4>";
        echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 4px;'>";
        echo htmlspecialchars(substr($passage['passage_content'], 0, 500));
        echo "...</pre>";
        
        echo "<h4>2. After HTML Entity Decoding:</h4>";
        function fullyDecodeHtml($content) {
            $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $content) {
                return $content;
            }
            $levels = 0;
            while ($decoded !== $content && $levels < 3) {
                $content = $decoded;
                $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $levels++;
            }
            return $decoded;
        }
        
        $decoded = fullyDecodeHtml($passage['passage_content']);
        echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 4px;'>";
        echo htmlspecialchars(substr($decoded, 0, 500));
        echo "...</pre>";
        
        echo "<h4>3. After Sanitization (Ready for Display):</h4>";
        function sanitizeHtml($text) {
            $allowed_tags = '<p><br><strong><em><img><span><div><ul><ol><li><h1><h2><h3><h4><h5><h6>';
            $text = strip_tags($text, $allowed_tags);
            $text = preg_replace('/(<[^>]+) on[a-zA-Z]*="[^"]*"/i', '$1', $text);
            $text = preg_replace("/(<[^>]+) on[a-zA-Z]*='[^']*'/i", '$1', $text);
            return $text;
        }
        
        $sanitized = sanitizeHtml($decoded);
        echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 4px;'>";
        echo $sanitized;
        echo "</div>";
        
        echo "<h4>4. Image Detection:</h4>";
        if (strpos($sanitized, '<img') !== false) {
            echo "<p style='color: green;'><strong>✓ Images detected!</strong></p>";
            
            // Extract and show image sources
            preg_match_all('/<img.*?src=[\"|\'](.+?)[\"|\']/i', $sanitized, $matches);
            if (!empty($matches[1])) {
                echo "<p><strong>Found " . count($matches[1]) . " image(s):</strong></p>";
                echo "<ul>";
                foreach ($matches[1] as $src) {
                    echo "<li>" . htmlspecialchars($src) . "</li>";
                }
                echo "</ul>";
                
                // Check if images exist
                echo "<h4>5. Image File Verification:</h4>";
                foreach ($matches[1] as $src) {
                    $full_path = __DIR__ . '/' . $src;
                    if (file_exists($full_path)) {
                        echo "<p style='color: green;'>✓ Image exists: " . htmlspecialchars($src) . "</p>";
                        echo "<img src='" . htmlspecialchars($src) . "' style='max-width: 200px; border: 2px solid green;'>";
                    } else {
                        echo "<p style='color: red;'>✗ Image NOT found: " . htmlspecialchars($src) . "</p>";
                    }
                }
            }
        } else {
            echo "<p style='color: orange;'>⚠ No images found in this passage</p>";
        }
        
        echo "<hr>";
        echo "<h3>Next Steps:</h3>";
        echo "<ol>";
        echo "<li>If images are showing above, the system is working correctly</li>";
        echo "<li>If you see broken image links, check that the image files exist in the uploads/ folder</li>";
        echo "<li>Test in student view by taking an exam that uses this passage</li>";
        echo "</ol>";
        
        echo "<p><a href='teacher/question_management.php?exam_id=2&class_id=1&subject_id=11' class='btn btn-primary'>Go to Question Management</a></p>";
        
    } else {
        echo "<p style='color: orange;'>No comprehension passages found in database. Please create one first.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
