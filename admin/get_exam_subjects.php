<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Handle AJAX request for exam subjects based on class
if (isset($_GET['class_id'])) {
    header('Content-Type: application/json');
    $class_id = (int)$_GET['class_id'];
    $examSubjects = [];
    
    if ($class_id > 0) {
        try {
            $mysqli = getDBConnection();
            if ($mysqli) {
                // Get published exam subjects for this class
                $stmt = $mysqli->prepare("SELECT es.id, e.title as exam_title, s.name as subject_name, c.name as class_name
                                         FROM exam_subjects es
                                         JOIN exams e ON es.exam_id = e.id
                                         JOIN subjects s ON es.subject_id = s.id
                                         JOIN classes c ON es.class_id = c.id
                                         WHERE es.class_id = ? AND e.is_published = 1 AND es.is_published = 1
                                         ORDER BY e.title, s.name");
                $stmt->bind_param("i", $class_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $row['display_name'] = $row['exam_title'] . ' - ' . $row['subject_name'];
                    $examSubjects[] = $row;
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            // Return empty array on error
        }
    }
    
    echo json_encode($examSubjects);
    exit();
}

// If not an AJAX request for exam subjects, return 404
http_response_code(404);
echo "Not Found";
?>