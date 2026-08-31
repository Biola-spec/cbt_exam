<?php
require_once 'config/database.php';
require_once 'config/session.php';

echo "<h2>Comprehensive Debug for Teacher Exams</h2>";

try {
    $mysqli = getDBConnection();
    
    if ($mysqli) {
        echo "<p style='color: green;'>✓ Database connection successful</p>";
        
        // Simulate what happens when Gbenga Tosin logs in
        echo "<h3>Simulating Gbenga Tosin Login:</h3>";
        
        // 1. Find Gbenga Tosin in database
        $email = 'gb.to@gmail.com';
        $stmt = $mysqli->prepare("SELECT id, email, full_name, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo "<p>✓ Found user in database:</p>";
            echo "<ul>";
            echo "<li>ID: " . $row['id'] . "</li>";
            echo "<li>Email: " . htmlspecialchars($row['email']) . "</li>";
            echo "<li>Name: " . htmlspecialchars($row['full_name']) . "</li>";
            echo "<li>Role: " . $row['role'] . "</li>";
            echo "</ul>";
            
            $user_id = $row['id'];
            $full_name = $row['full_name'];
            
            // 2. Check allocations for this user
            echo "<h3>Checking Allocations:</h3>";
            $stmt = $mysqli->prepare("SELECT ta.*, c.name as class_name, s.name as subject_name 
                                  FROM teacher_allocations ta
                                  JOIN classes c ON ta.class_id = c.id
                                  JOIN subjects s ON ta.subject_id = s.id
                                  WHERE ta.teacher_id = ?
                                  ORDER BY c.name, s.name");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo "<p style='color: green;'>✓ Found " . $result->num_rows . " allocations:</p>";
                echo "<table border='1' cellpadding='5'>";
                echo "<tr><th>ID</th><th>Class</th><th>Subject</th></tr>";
                while ($alloc_row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $alloc_row['id'] . "</td>";
                    echo "<td>" . htmlspecialchars($alloc_row['class_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($alloc_row['subject_name']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
                
                // 3. Check if there are matching exam subjects
                echo "<h3>Checking Exam Subjects:</h3>";
                // Fixed ambiguous column references by specifying explicit column names
                $stmt = $mysqli->prepare("SELECT es.id as exam_subject_id, es.exam_id, es.subject_id, es.class_id, es.time_limit, es.created_at,
                                      e.title as exam_title, e.is_published, c.name as class_name, s.name as subject_name
                                      FROM exam_subjects es
                                      JOIN exams e ON es.exam_id = e.id
                                      JOIN classes c ON es.class_id = c.id
                                      JOIN subjects s ON es.subject_id = s.id
                                      WHERE es.class_id = ? AND es.subject_id = ?");
                
                // Get the class_id and subject_id from the first allocation
                $stmt2 = $mysqli->prepare("SELECT class_id, subject_id FROM teacher_allocations WHERE teacher_id = ? LIMIT 1");
                $stmt2->bind_param("i", $user_id);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                
                if ($alloc_data = $result2->fetch_assoc()) {
                    $class_id = $alloc_data['class_id'];
                    $subject_id = $alloc_data['subject_id'];
                    
                    $stmt->bind_param("ii", $class_id, $subject_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        echo "<p style='color: green;'>✓ Found matching exam subjects:</p>";
                        echo "<table border='1' cellpadding='5'>";
                        echo "<tr><th>Exam</th><th>Class</th><th>Subject</th><th>Published</th></tr>";
                        while ($exam_row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($exam_row['exam_title']) . "</td>";
                            echo "<td>" . htmlspecialchars($exam_row['class_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($exam_row['subject_name']) . "</td>";
                            echo "<td>" . ($exam_row['is_published'] ? 'Yes' : 'No') . "</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                        
                        // 4. Test the exact query used in my_exams.php
                        echo "<h3>Testing Teacher's Exam Query:</h3>";
                        $stmt = $mysqli->prepare("SELECT DISTINCT e.id, e.title, e.description, e.is_published, e.published_at, e.created_by, e.created_at
                                              FROM exams e
                                              JOIN exam_subjects es ON e.id = es.exam_id
                                              JOIN teacher_allocations ta ON es.class_id = ta.class_id AND es.subject_id = ta.subject_id
                                              WHERE ta.teacher_id = ?
                                              ORDER BY e.created_at DESC");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        echo "<p>Query returned " . $result->num_rows . " rows</p>";
                        
                        if ($result->num_rows > 0) {
                            echo "<p style='color: green;'>✓ Query successful, found exams:</p>";
                            echo "<table border='1' cellpadding='5'>";
                            echo "<tr><th>ID</th><th>Title</th><th>Published</th></tr>";
                            while ($exam_row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . $exam_row['id'] . "</td>";
                                echo "<td>" . htmlspecialchars($exam_row['title']) . "</td>";
                                echo "<td>" . ($exam_row['is_published'] ? 'Yes' : 'No') . "</td>";
                                echo "</tr>";
                            }
                            echo "</table>";
                        } else {
                            echo "<p style='color: red;'>✗ Query returned no results</p>";
                            
                            // Let's debug the joins step by step
                            echo "<h4>Debugging Joins:</h4>";
                            
                            // Check exams
                            echo "<p>1. All exams:</p>";
                            $stmt = $mysqli->prepare("SELECT id, title FROM exams");
                            $stmt->execute();
                            $result = $stmt->get_result();
                            echo "<ul>";
                            while ($row = $result->fetch_assoc()) {
                                echo "<li>" . $row['id'] . " - " . htmlspecialchars($row['title']) . "</li>";
                            }
                            echo "</ul>";
                            
                            // Check exam_subjects
                            echo "<p>2. All exam_subjects:</p>";
                            $stmt = $mysqli->prepare("SELECT id, exam_id, class_id, subject_id FROM exam_subjects");
                            $stmt->execute();
                            $result = $stmt->get_result();
                            echo "<ul>";
                            while ($row = $result->fetch_assoc()) {
                                echo "<li>" . $row['id'] . " - Exam:" . $row['exam_id'] . " Class:" . $row['class_id'] . " Subject:" . $row['subject_id'] . "</li>";
                            }
                            echo "</ul>";
                            
                            // Check teacher_allocations
                            echo "<p>3. All teacher_allocations:</p>";
                            $stmt = $mysqli->prepare("SELECT id, teacher_id, class_id, subject_id FROM teacher_allocations");
                            $stmt->execute();
                            $result = $stmt->get_result();
                            echo "<ul>";
                            while ($row = $result->fetch_assoc()) {
                                echo "<li>" . $row['id'] . " - Teacher:" . $row['teacher_id'] . " Class:" . $row['class_id'] . " Subject:" . $row['subject_id'] . "</li>";
                            }
                            echo "</ul>";
                        }
                    } else {
                        echo "<p style='color: red;'>✗ No matching exam subjects found</p>";
                    }
                } else {
                    echo "<p style='color: red;'>✗ Could not get allocation data</p>";
                }
            } else {
                echo "<p style='color: orange;'>⚠ No allocations found for this user</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ User not found in database</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Database connection failed</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='login.php'>Go to Login</a></p>";
?>