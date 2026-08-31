<?php
// Check the maximum length of the passage_content column
include 'config/database.php';

$mysqli = getDBConnection();
if ($mysqli) {
    $result = $mysqli->query("SHOW CREATE TABLE comprehension_passages");
    if ($result) {
        $row = $result->fetch_row();
        echo "<h2>CREATE TABLE statement for comprehension_passages:</h2>";
        echo "<pre>" . htmlspecialchars($row[1]) . "</pre>";
    } else {
        echo "Error: " . $mysqli->error;
    }
    $mysqli->close();
} else {
    echo "Could not connect to database";
}
?>