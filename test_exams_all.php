<?php
require 'config/database.php';
$pdo = getPDOConnection();
$stmt = $pdo->query("SELECT * FROM exams");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('test_exams_all.json', json_encode($results, JSON_PRETTY_PRINT));
echo "Done";
?>
