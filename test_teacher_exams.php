<?php
require_once 'config/database.php';

echo "Testing teacher exams query...\n";

try {
    $mysqli = getDBConnection();
    
    if ($mysqli) {
        echo "Database connection successful\n";
        
        // Simulate teacher ID (Gbenga Tosin)
        $teacher_id = 2;
        
        echo "Testing with teacher ID: " . $teacher_id . "\n";
        
        // Run the exact query from my_exams.php
        $stmt = $mysqli->prepare("SELECT DISTINCT e.id, e.title, e.description, e.is_published, e.published_at, e.created_by, e.created_at
                              FROM exams e
                              JOIN exam_subjects es ON e.id = es.exam_id
                              JOIN teacher_allocations ta ON es.class_id = ta.class_id AND es.subject_id = ta.subject_id
                              WHERE ta.teacher_id = ?
                              ORDER BY e.created_at DESC");
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo "Query executed successfully\n";
        echo "Number of rows returned: " . $result->num_rows . "\n";
        
        if ($result->num_rows > 0) {
            echo "Exams found:\n";
            while ($row = $result->fetch_assoc()) {
                echo "- " . $row['title'] . " (ID: " . $row['id'] . ")\n";
            }
        } else {
            echo "No exams found for this teacher\n";
            
            // Debug: Let's check each table separately
            echo "\nDebugging individual tables:\n";
            
            // Check teacher allocations
            $stmt = $mysqli->prepare("SELECT id, teacher_id, class_id, subject_id, allocated_at, allocated_by FROM teacher_allocations WHERE teacher_id = ?");
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo "Teacher allocations for teacher ID " . $teacher_id . ": " . $result->num_rows . " rows\n";
            while ($row = $result->fetch_assoc()) {
                echo "  Allocation: class_id=" . $row['class_id'] . ", subject_id=" . $row['subject_id'] . "\n";
            }
            
            // Check exam subjects
            $stmt = $mysqli->prepare("SELECT id, exam_id, subject_id, class_id, created_at FROM exam_subjects");
            $stmt->execute();
            $result = $stmt->get_result();
            echo "Total exam subjects: " . $result->num_rows . " rows\n";
            while ($row = $result->fetch_assoc()) {
                echo "  Exam subject: exam_id=" . $row['exam_id'] . ", class_id=" . $row['class_id'] . ", subject_id=" . $row['subject_id'] . "\n";
            }
            
            // Check if there's a match
            $stmt = $mysqli->prepare("SELECT ta.id as ta_id, ta.teacher_id, ta.class_id as ta_class_id, ta.subject_id as ta_subject_id, 
                                  es.id as es_id, es.exam_id, es.class_id as es_class_id, es.subject_id as es_subject_id
                                  FROM teacher_allocations ta
                                  JOIN exam_subjects es 
                                  ON ta.class_id = es.class_id AND ta.subject_id = es.subject_id
                                  WHERE ta.teacher_id = ?");
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo "Matching allocations and exam subjects: " . $result->num_rows . " rows\n";
        }
        
    } else {
        echo "Database connection failed\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>