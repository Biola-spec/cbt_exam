<?php
require_once 'config/database.php';
require_once 'config/session.php';

echo "Debugging teacher allocations...\n";

$mysqli = getDBConnection();

// Check current user
$user_id = getUserId();
echo "Current user ID: " . $user_id . "\n";

$stmt = $mysqli->prepare("SELECT id, email, password, full_name, role, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
echo "Current user: " . ($user ? $user['full_name'] . " (" . $user['email'] . ")" : "Not found") . "\n";

// Check all teacher allocations
echo "\n--- All Teacher Allocations ---\n";
$stmt = $mysqli->prepare("SELECT ta.id, ta.teacher_id, ta.class_id, ta.subject_id, ta.allocated_at, ta.allocated_by, u.full_name as teacher_name, c.name as class_name, s.name as subject_name FROM teacher_allocations ta JOIN users u ON ta.teacher_id = u.id JOIN classes c ON ta.class_id = c.id JOIN subjects s ON ta.subject_id = s.id");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    echo "Teacher: " . $row['teacher_name'] . " | Class: " . $row['class_name'] . " | Subject: " . $row['subject_name'] . "\n";
}

// Check exams and their subjects
echo "\n--- All Exams ---\n";
$stmt = $mysqli->prepare("SELECT id, title, description, is_published, published_at, created_by, created_at FROM exams");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    echo "Exam ID: " . $row['id'] . " | Title: " . $row['title'] . " | Published: " . ($row['is_published'] ? 'Yes' : 'No') . "\n";
    
    // Check exam subjects for this exam
    echo "  Subjects for this exam:\n";
    $stmt2 = $mysqli->prepare("SELECT es.id, es.exam_id, es.subject_id, es.class_id, es.created_at, c.name as class_name, s.name as subject_name FROM exam_subjects es JOIN classes c ON es.class_id = c.id JOIN subjects s ON es.subject_id = s.id WHERE es.exam_id = ?");
    $stmt2->bind_param("i", $row['id']);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    while ($row2 = $result2->fetch_assoc()) {
        echo "    Class: " . $row2['class_name'] . " | Subject: " . $row2['subject_name'] . "\n";
    }
}

// Check the specific query from my_exams.php
echo "\n--- Teacher's Allocated Exams Query Result ---\n";
$stmt = $mysqli->prepare("SELECT DISTINCT e.id, e.title, e.description, e.is_published, e.published_at, e.created_by, e.created_at
                      FROM exams e
                      JOIN exam_subjects es ON e.id = es.exam_id
                      JOIN teacher_allocations ta ON es.class_id = ta.class_id AND es.subject_id = ta.subject_id
                      WHERE ta.teacher_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Exam: " . $row['title'] . " (ID: " . $row['id'] . ")\n";
    }
} else {
    echo "No exams found for this teacher\n";
}

echo "\nDone.\n";
?>