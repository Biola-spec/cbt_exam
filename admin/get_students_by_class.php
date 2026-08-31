<?php
require_once '../config/database.php';
header('Content-Type: application/json');

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$students = [];

if ($class_id > 0) {
    $mysqli = getDBConnection();
    if ($mysqli) {
        $stmt = $mysqli->prepare("SELECT id, full_name FROM users WHERE role = 'student' AND class_id = ? ORDER BY full_name");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        
        $stmt->close();
        $mysqli->close();
    }
}

echo json_encode($students);
?>
