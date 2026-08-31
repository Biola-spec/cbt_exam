<?php
require 'config/database.php';
$mysqli = getDBConnection();
$result = $mysqli->query("SELECT id, title, created_at FROM exams WHERE title LIKE '%Interview%'");
while($row = $result->fetch_assoc()) {
    print_r($row);
}
?>
