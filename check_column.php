<?php
require_once 'config/database.php';

$mysqli = getDBConnection();

if ($mysqli) {
    $result = $mysqli->query("SHOW COLUMNS FROM classes LIKE 'marks_per_question'");
    if ($result->num_rows > 0) {
        echo "Column 'marks_per_question' exists in 'classes' table.";
    } else {
        echo "Column 'marks_per_question' does not exist in 'classes' table.";
    }
    $mysqli->close();
} else {
    echo "Database connection failed.";
}
?>