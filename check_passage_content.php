<?php
// Check what's actually stored in the passage content
try {
    $pdo = new PDO("mysql:host=localhost;dbname=cbt_exam_system", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>Passage Content Check</h1>";
    
    // Get all passages to see what's stored
    $stmt = $pdo->query("SELECT id, passage_title, passage_content FROM comprehension_passages LIMIT 5");
    $passages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($passages) > 0) {
        foreach ($passages as $row) {
            echo "<h3>Passage ID: " . $row['id'] . " - " . htmlspecialchars($row['passage_title']) . "</h3>";
            echo "<h4>Raw Content:</h4>";
            echo "<pre>" . htmlspecialchars($row['passage_content']) . "</pre>";
            
            echo "<h4>Rendered Content:</h4>";
            echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
            // Allow img tags for display
            $allowed_tags = '<p><br><strong><em><img><span><div><ul><ol><li><h1><h2><h3><h4><h5><h6>';
            $sanitized = strip_tags($row['passage_content'], $allowed_tags);
            echo $sanitized;
            echo "</div>";
            echo "<hr>";
        }
    } else {
        echo "<p>No passages found.</p>";
    }
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>