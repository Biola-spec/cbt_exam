<?php
require_once 'config/database.php';

echo "Debugging database content...\n";

$mysqli = getDBConnection();

if (!$mysqli) {
    die("Failed to connect to database\n");
}

echo "Connected to database successfully\n\n";

// Check users
echo "--- Users ---\n";
$stmt = $mysqli->prepare("SELECT id, email, full_name, role FROM users ORDER BY id");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Name: " . $row['full_name'] . " | Email: " . $row['email'] . " | Role: " . $row['role'] . "\n";
}

// Check classes
echo "\n--- Classes ---\n";
$stmt = $mysqli->prepare("SELECT id, name FROM classes ORDER BY id");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Name: " . $row['name'] . "\n";
}

// Check subjects
echo "\n--- Subjects ---\n";
$stmt = $mysqli->prepare("SELECT id, name FROM subjects ORDER BY id");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Name: " . $row['name'] . "\n";
}

// Check teacher allocations
echo "\n--- Teacher Allocations ---\n";
$stmt = $mysqli->prepare("SELECT ta.id, ta.teacher_id, u.full_name as teacher_name, ta.class_id, c.name as class_name, ta.subject_id, s.name as subject_name FROM teacher_allocations ta JOIN users u ON ta.teacher_id = u.id JOIN classes c ON ta.class_id = c.id JOIN subjects s ON ta.subject_id = s.id ORDER BY ta.teacher_id, ta.class_id, ta.subject_id");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    echo "Allocation ID: " . $row['id'] . " | Teacher: " . $row['teacher_name'] . " | Class: " . $row['class_name'] . " | Subject: " . $row['subject_name'] . "\n";
}

// Check exams
echo "\n--- Exams ---\n";
$stmt = $mysqli->prepare("SELECT id, title, is_published FROM exams ORDER BY id");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Title: " . $row['title'] . " | Published: " . ($row['is_published'] ? 'Yes' : 'No') . "\n";
}

// Check exam subjects
echo "\n--- Exam Subjects ---\n";
$stmt = $mysqli->prepare("SELECT es.id, es.exam_id, e.title as exam_title, es.class_id, c.name as class_name, es.subject_id, s.name as subject_name FROM exam_subjects es JOIN exams e ON es.exam_id = e.id JOIN classes c ON es.class_id = c.id JOIN subjects s ON es.subject_id = s.id ORDER BY es.exam_id, es.class_id, es.subject_id");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Exam: " . $row['exam_title'] . " | Class: " . $row['class_name'] . " | Subject: " . $row['subject_name'] . "\n";
}

echo "\nDone.\n";
?>