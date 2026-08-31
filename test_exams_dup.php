<?php
require 'config/database.php';
$pdo = getPDOConnection();
if (!$pdo) {
    die("PDO failed");
}
$stmt = $pdo->prepare("SELECT DISTINCT e.id, e.title, e.description, e.is_published, e.published_at, e.created_by, e.created_at
                      FROM exams e
                      JOIN exam_subjects es ON e.id = es.exam_id
                      JOIN teacher_allocations ta ON es.class_id = ta.class_id AND es.subject_id = ta.subject_id
                      ORDER BY e.created_at DESC");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

file_put_contents('test_exams_dup.json', json_encode($results, JSON_PRETTY_PRINT));
echo "Done";
?>
