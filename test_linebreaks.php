<?php
require_once 'config/session.php';
require_once 'config/database.php';

echo "<h2>Line Break Preservation Test</h2>";

// Test content with line breaks
$test_content = "(2  1)   (1  3)\n(3  4) + (2  1)\n\nThis is a multi-line matrix question.";

echo "<h3>Original Content:</h3>";
echo "<pre>" . htmlspecialchars($test_content) . "</pre>";
echo "<p>Length: " . strlen($test_content) . " characters</p>";
echo "<p>Line breaks: " . substr_count($test_content, "\n") . "</p>";

// Test with htmlspecialchars only
echo "<h3>With htmlspecialchars only:</h3>";
echo "<div class='bg-light p-3 rounded'>";
echo nl2br(htmlspecialchars($test_content));
echo "</div>";

// Test saving to and retrieving from database
echo "<h3>Database Test:</h3>";

try {
    // Create a temporary table for testing
    $mysqli->query("CREATE TEMPORARY TABLE test_content (id INT AUTO_INCREMENT PRIMARY KEY, content TEXT)");
    
    // Insert test content
    $stmt = $mysqli->prepare("INSERT INTO test_content (content) VALUES (?)");
    $stmt->bind_param("s", $test_content);
    $stmt->execute();
    $stmt->close();
    
    // Retrieve content
    $result = $mysqli->query("SELECT content FROM test_content WHERE id = 1");
    $row = $result->fetch_assoc();
    $retrieved_content = $row['content'];
    
    echo "<p><strong>Retrieved from database:</strong></p>";
    echo "<pre>" . htmlspecialchars($retrieved_content) . "</pre>";
    echo "<p>Length: " . strlen($retrieved_content) . " characters</p>";
    echo "<p>Line breaks: " . substr_count($retrieved_content, "\n") . "</p>";
    
    echo "<p><strong>Display with nl2br:</strong></p>";
    echo "<div class='bg-light p-3 rounded'>";
    echo nl2br(htmlspecialchars($retrieved_content));
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

echo "<style>
.bg-light {
    background-color: #f8f9fa !important;
}
.rounded {
    border-radius: 0.5rem !important;
}
.p-3 {
    padding: 1rem !important;
}
</style>";
?>