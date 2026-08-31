<?php
require_once 'config/database.php';

echo "<h2>Detailed Debug</h2>";

try {
    $mysqli = getDBConnection();
    
    if ($mysqli) {
        echo "<p style='color: green;'>✓ Database connection successful</p>";
        
        // Get detailed information about Gbenga Tosin's allocation
        echo "<h3>Gbenga Tosin's Allocation Details:</h3>";
        $stmt = $mysqli->prepare("SELECT ta.*, c.name as class_name, s.name as subject_name, c.id as class_id, s.id as subject_id
                              FROM teacher_allocations ta
                              JOIN classes c ON ta.class_id = c.id
                              JOIN subjects s ON ta.subject_id = s.id
                              JOIN users u ON ta.teacher_id = u.id
                              WHERE u.email = 'gb.to@gmail.com'");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo "<p>Allocation ID: " . $row['id'] . "</p>";
            echo "<p>Teacher ID: " . $row['teacher_id'] . "</p>";
            echo "<p>Class: " . htmlspecialchars($row['class_name']) . " (ID: " . $row['class_id'] . ")</p>";
            echo "<p>Subject: " . htmlspecialchars($row['subject_name']) . " (ID: " . $row['subject_id'] . ")</p>";
            
            $class_id = $row['class_id'];
            $subject_id = $row['subject_id'];
            
            // Check if there's an exam subject with these exact class_id and subject_id
            echo "<h3>Checking for Exact Exam Subject Match:</h3>";
            // Fixed ambiguous column references by specifying explicit column names
            $stmt = $mysqli->prepare("SELECT es.id as exam_subject_id, es.exam_id, es.subject_id, es.class_id, es.time_limit, es.created_at,
                                  e.title as exam_title
                                  FROM exam_subjects es
                                  JOIN exams e ON es.exam_id = e.id
                                  WHERE es.class_id = ? AND es.subject_id = ?");
            $stmt->bind_param("ii", $class_id, $subject_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo "<p style='color: green;'>✓ Found exact match:</p>";
                while ($exam_row = $result->fetch_assoc()) {
                    echo "<p>Exam Subject ID: " . $exam_row['exam_subject_id'] . " - Exam: " . htmlspecialchars($exam_row['exam_title']) . "</p>";
                }
            } else {
                echo "<p style='color: red;'>✗ No exact match found</p>";
                
                // Let's check all exam subjects to see what exists
                echo "<h3>All Exam Subjects in Database:</h3>";
                // Fixed ambiguous column references by specifying explicit column names
                $stmt = $mysqli->prepare("SELECT es.id as exam_subject_id, es.exam_id, es.subject_id, es.class_id, es.time_limit, es.created_at,
                                      e.title as exam_title, c.name as class_name, s.name as subject_name
                                      FROM exam_subjects es
                                      JOIN exams e ON es.exam_id = e.id
                                      JOIN classes c ON es.class_id = c.id
                                      JOIN subjects s ON es.subject_id = s.id
                                      ORDER BY e.title, c.name, s.name");
                $stmt->execute();
                $result = $stmt->get_result();
                
                echo "<table border='1' cellpadding='5'>";
                echo "<tr><th>Exam Subject ID</th><th>Exam</th><th>Class</th><th>Class ID</th><th>Subject</th><th>Subject ID</th></tr>";
                while ($exam_row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $exam_row['exam_subject_id'] . "</td>";
                    echo "<td>" . htmlspecialchars($exam_row['exam_title']) . "</td>";
                    echo "<td>" . htmlspecialchars($exam_row['class_name']) . "</td>";
                    echo "<td>" . $exam_row['class_id'] . "</td>";
                    echo "<td>" . htmlspecialchars($exam_row['subject_name']) . "</td>";
                    echo "<td>" . $exam_row['subject_id'] . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
                
                // Check all classes
                echo "<h3>All Classes:</h3>";
                $stmt = $mysqli->prepare("SELECT id, name FROM classes ORDER BY id");
                $stmt->execute();
                $result = $stmt->get_result();
                echo "<ul>";
                while ($class_row = $result->fetch_assoc()) {
                    echo "<li>ID: " . $class_row['id'] . " - " . htmlspecialchars($class_row['name']) . "</li>";
                }
                echo "</ul>";
                
                // Check all subjects
                echo "<h3>All Subjects:</h3>";
                $stmt = $mysqli->prepare("SELECT id, name FROM subjects ORDER BY id");
                $stmt->execute();
                $result = $stmt->get_result();
                echo "<ul>";
                while ($subject_row = $result->fetch_assoc()) {
                    echo "<li>ID: " . $subject_row['id'] . " - " . htmlspecialchars($subject_row['name']) . "</li>";
                }
                echo "</ul>";
            }
        } else {
            echo "<p style='color: red;'>✗ No allocation found for Gbenga Tosin</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Database connection failed</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>