<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is logged in and is an admin
requireLogin();
if (getUserRole() !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

try {
    $mysqli = getDBConnection();
    
    if ($class_id > 0) {
        // Get students from the selected class
        // Assuming there's a student_classes table or similar relationship
        // If not, we'll get all students (fallback)
        $stmt = $mysqli->prepare("SELECT DISTINCT u.id, u.full_name 
                                 FROM users u 
                                 JOIN exam_attempts ea ON u.id = ea.student_id
                                 JOIN exam_subjects es ON ea.exam_subject_id = es.id
                                 WHERE u.role = 'student' AND es.class_id = ?
                                 ORDER BY u.full_name");
        $stmt->bind_param("i", $class_id);
    } else {
        // Get all students
        $stmt = $mysqli->prepare("SELECT id, full_name FROM users WHERE role = 'student' ORDER BY full_name");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'students' => $students
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>