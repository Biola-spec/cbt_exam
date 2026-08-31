<?php
echo "<h2>Check Teacher Allocations in Database</h2>";

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cbt_exam_system');

try {
    // Connect to database
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Check allocations with explicit column names to avoid ambiguity
    echo "<h3>Teacher Allocations:</h3>";
    $stmt = $mysqli->prepare("SELECT ta.id, ta.teacher_id, ta.class_id, ta.subject_id, ta.allocated_at, ta.allocated_by,
                              u.full_name as teacher_name, c.name as class_name, s.name as subject_name, alloc.full_name as allocated_by_name
                              FROM teacher_allocations ta
                              JOIN users u ON ta.teacher_id = u.id
                              JOIN classes c ON ta.class_id = c.id
                              JOIN subjects s ON ta.subject_id = s.id
                              JOIN users alloc ON ta.allocated_by = alloc.id
                              ORDER BY ta.id");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Teacher</th><th>Class</th><th>Subject</th><th>Allocated By</th><th>Allocated At</th></tr>";
            
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . htmlspecialchars($row['teacher_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['class_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['subject_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['allocated_by_name']) . "</td>";
                echo "<td>" . date('M j, Y g:i A', strtotime($row['allocated_at'])) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No teacher allocations found in database.</p>";
        }
        $stmt->close();
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='admin/teacher_allocation.php'>Go to Teacher Allocation</a></p>";
?>