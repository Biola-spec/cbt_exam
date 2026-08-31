<?php
// Check the actual data in the comprehension_passages table
include 'config/database.php';

$mysqli = getDBConnection();
if ($mysqli) {
    $result = $mysqli->query("SELECT id, passage_title, passage_content, start_question_number, end_question_number FROM comprehension_passages LIMIT 5");
    if ($result) {
        echo "<h2>Sample comprehension_passages data:</h2>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Title</th><th>Content</th><th>Start Q</th><th>End Q</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['passage_title']) . "</td>";
            echo "<td><pre>" . htmlspecialchars(substr($row['passage_content'], 0, 200)) . (strlen($row['passage_content']) > 200 ? "..." : "") . "</pre></td>";
            echo "<td>" . $row['start_question_number'] . "</td>";
            echo "<td>" . $row['end_question_number'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check if any passage contains base64 data
        $result->data_seek(0); // Reset to beginning
        echo "<h2>Passages with base64 images:</h2>";
        $found_base64 = false;
        while ($row = $result->fetch_assoc()) {
            if (strpos($row['passage_content'], 'data:image') !== false) {
                echo "<h3>Passage ID: " . $row['id'] . "</h3>";
                echo "<p>Title: " . htmlspecialchars($row['passage_title']) . "</p>";
                echo "<p>Content preview: " . htmlspecialchars(substr($row['passage_content'], 0, 300)) . "...</p>";
                $found_base64 = true;
            }
        }
        if (!$found_base64) {
            echo "<p>No passages with base64 images found.</p>";
        }
    } else {
        echo "Error: " . $mysqli->error;
    }
    $mysqli->close();
} else {
    echo "Could not connect to database";
}
?>