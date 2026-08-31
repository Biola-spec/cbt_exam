<!DOCTYPE html>
<html>
<head>
    <title>Test Teacher Exams</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .debug { background-color: #f0f0f0; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
    <h2>Test Teacher Exams Query</h2>
    
    <?php
    require_once 'config/database.php';
    
    echo "<p>Testing teacher exams query...</p>";
    
    try {
        $mysqli = getDBConnection();
        
        if ($mysqli) {
            echo "<p class='success'>Database connection successful</p>";
            
            // Simulate teacher ID (Gbenga Tosin)
            $teacher_id = 2;
            
            echo "<p>Testing with teacher ID: " . $teacher_id . "</p>";
            
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
            
            echo "<p>Query executed successfully</p>";
            echo "<p>Number of rows returned: " . $result->num_rows . "</p>";
            
            if ($result->num_rows > 0) {
                echo "<p class='success'>Exams found:</p>";
                echo "<ul>";
                while ($row = $result->fetch_assoc()) {
                    echo "<li>" . htmlspecialchars($row['title']) . " (ID: " . $row['id'] . ")</li>";
                }
                echo "</ul>";
            } else {
                echo "<p class='error'>No exams found for this teacher</p>";
                
                // Debug: Let's check each table separately
                echo "<div class='debug'>";
                echo "<h3>Debugging individual tables:</h3>";
                
                // Check teacher allocations
                $stmt = $mysqli->prepare("SELECT id, teacher_id, class_id, subject_id, allocated_at, allocated_by FROM teacher_allocations WHERE teacher_id = ?");
                $stmt->bind_param("i", $teacher_id);
                $stmt->execute();
                $result = $stmt->get_result();
                echo "<p>Teacher allocations for teacher ID " . $teacher_id . ": " . $result->num_rows . " rows</p>";
                echo "<ul>";
                while ($row = $result->fetch_assoc()) {
                    echo "<li>Allocation: class_id=" . $row['class_id'] . ", subject_id=" . $row['subject_id'] . "</li>";
                }
                echo "</ul>";
                
                // Check exam subjects
                $stmt = $mysqli->prepare("SELECT id, exam_id, subject_id, class_id, created_at FROM exam_subjects");
                $stmt->execute();
                $result = $stmt->get_result();
                echo "<p>Total exam subjects: " . $result->num_rows . " rows</p>";
                echo "<ul>";
                while ($row = $result->fetch_assoc()) {
                    echo "<li>Exam subject: exam_id=" . $row['exam_id'] . ", class_id=" . $row['class_id'] . ", subject_id=" . $row['subject_id'] . "</li>";
                }
                echo "</ul>";
                
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
                echo "<p>Matching allocations and exam subjects: " . $result->num_rows . " rows</p>";
                if ($result->num_rows > 0) {
                    echo "<p class='success'>Found matching records! The teacher should be able to see exams.</p>";
                } else {
                    echo "<p class='error'>No matching records found. There's still a data mismatch.</p>";
                }
                echo "</div>";
            }
            
        } else {
            echo "<p class='error'>Database connection failed</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    ?>
</body>
</html>