<?php
require 'config/database.php';
$pdo = getPDOConnection();
if (!$pdo) {
    echo "No PDO";
} else {
    $stmt = $pdo->query("SHOW CREATE TABLE exam_subjects");
    echo $stmt->fetchColumn(1) . "\n\n";
    $stmt = $pdo->query("SHOW CREATE TABLE exam_attempts");
    echo $stmt->fetchColumn(1) . "\n\n";
    $stmt = $pdo->query("SHOW CREATE TABLE comprehension_passages");
    echo $stmt->fetchColumn(1) . "\n\n";
}
