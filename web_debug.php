<!DOCTYPE html>
<html>
<head>
    <title>CBT Exam System - Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <h2>CBT Exam System - Database Debug</h2>
    
    <?php
    require_once 'config/database.php';
    
    try {
        $mysqli = getDBConnection();
        
        if ($mysqli) {
            echo "<p class='success'>✓ Database connection successful</p>";
            
            // Check database content
            echo "<h3>Database Content</h3>";
            
            // Users
            echo "<h4>Users</h4>";
            $stmt = $mysqli->prepare("SELECT id, email, full_name, role FROM users ORDER BY id");
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                echo "<table>";
                echo "<tr><th>ID</th><th>Email</th><th>Name</th><th>Role</th></tr>";
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
                    echo "<td>" . $row['role'] . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No users found</p>";
            }
            
            // Classes
            echo "<h4>Classes</h4>";
            $stmt = $mysqli->prepare("SELECT id, name FROM classes ORDER BY id");
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                echo "<table>";
                echo "<tr><th>ID</th><th>Name</th></tr>";
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No classes found</p>";
            }
            
            // Subjects
            echo "<h4>Subjects</h4>";
            $stmt = $mysqli->prepare("SELECT id, name FROM subjects ORDER BY id");
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                echo "<table>";
                echo "<tr><th>ID</th><th>Name</th></tr>";
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No subjects found</p>";
            }
            
            // Teacher Allocations
            echo "<h4>Teacher Allocations</h4>";
            $stmt = $mysqli->prepare("SELECT ta.id, ta.teacher_id, u.full_name as teacher_name, ta.class_id, c.name as class_name, ta.subject_id, s.name as subject_name FROM teacher_allocations ta LEFT JOIN users u ON ta.teacher_id = u.id LEFT JOIN classes c ON ta.class_id = c.id LEFT JOIN subjects s ON ta.subject_id = s.id ORDER BY ta.teacher_id, ta.class_id, ta.subject_id");
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                echo "<table>";
                echo "<tr><th>ID</th><th>Teacher ID</th><th>Teacher Name</th><th>Class ID</th><th>Class Name</th><th>Subject ID</th><th>Subject Name</th></tr>";
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td>" . $row['teacher_id'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['teacher_name'] ?? 'Unknown') . "</td>";
                    echo "<td>" . $row['class_id'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['class_name'] ?? 'Unknown') . "</td>";
                    echo "<td>" . $row['subject_id'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['subject_name'] ?? 'Unknown') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No teacher allocations found</p>";
            }
            
            // Exams
            echo "<h4>Exams</h4>";
            $stmt = $mysqli->prepare("SELECT id, title, is_published FROM exams ORDER BY id");
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                echo "<table>";
                echo "<tr><th>ID</th><th>Title</th><th>Published</th></tr>";
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['title']) . "</td>";
                    echo "<td>" . ($row['is_published'] ? 'Yes' : 'No') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No exams found</p>";
            }
            
            // Exam Subjects
            echo "<h4>Exam Subjects</h4>";
            $stmt = $mysqli->prepare("SELECT es.id, es.exam_id, e.title as exam_title, es.class_id, c.name as class_name, es.subject_id, s.name as subject_name FROM exam_subjects es LEFT JOIN exams e ON es.exam_id = e.id LEFT JOIN classes c ON es.class_id = c.id LEFT JOIN subjects s ON es.subject_id = s.id ORDER BY es.exam_id, es.class_id, es.subject_id");
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                echo "<table>";
                echo "<tr><th>ID</th><th>Exam ID</th><th>Exam Title</th><th>Class ID</th><th>Class Name</th><th>Subject ID</th><th>Subject Name</th></tr>";
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td>" . $row['exam_id'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['exam_title'] ?? 'Unknown') . "</td>";
                    echo "<td>" . $row['class_id'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['class_name'] ?? 'Unknown') . "</td>";
                    echo "<td>" . $row['subject_id'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['subject_name'] ?? 'Unknown') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No exam subjects found</p>";
            }
            
        } else {
            echo "<p class='error'>✗ Database connection failed</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    ?>
</body>
</html>