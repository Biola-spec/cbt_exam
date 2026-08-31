<?php
session_start();

echo "<h2>Test Teacher Allocation Fix (Corrected)</h2>";

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cbt_exam_system');

// Simulate login
$_SESSION['user_id'] = 1;  // Admin user ID
$_SESSION['email'] = 'admin@cbtexam.com';
$_SESSION['full_name'] = 'System Administrator';
$_SESSION['role'] = 'admin';

echo "<p>Simulated login as admin user (ID: " . $_SESSION['user_id'] . ")</p>";

try {
    // Connect to database
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Use correct IDs from database
    $teacher_id = 4;  // Ahmad Abiola
    $class_id = 1;    // JSS 1
    $subject_id = 2;  // Mathematics
    
    echo "<p>Attempting to allocate teacher ID: " . $teacher_id . " to class ID: " . $class_id . " for subject ID: " . $subject_id . "</p>";
    
    // Get the actual user ID from the database based on session email
    $session_email = $_SESSION['email'] ?? null;
    $allocated_by = null;
    
    if ($session_email) {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $session_email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($user = $result->fetch_assoc()) {
                $allocated_by = $user['id'];
                echo "<p style='color: green;'>✓ Found allocating user ID: " . $allocated_by . " for email: " . htmlspecialchars($session_email) . "</p>";
            } else {
                echo "<p style='color: orange;'>⚠ Could not find user in database, using fallback ID: 1</p>";
                $allocated_by = 1;
            }
            $stmt->close();
        }
    } else {
        echo "<p style='color: orange;'>⚠ No session email found, using fallback ID: 1</p>";
        $allocated_by = 1;
    }
    
    // Insert the allocation
    $stmt = $mysqli->prepare("INSERT INTO teacher_allocations (teacher_id, class_id, subject_id, allocated_by) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iiii", $teacher_id, $class_id, $subject_id, $allocated_by);
        
        if ($stmt->execute()) {
            $allocation_id = $mysqli->insert_id;
            echo "<p style='color: green;'>✓ Teacher allocation added successfully with ID: " . $allocation_id . "</p>";
            
            // Verify the allocation
            $stmt = $mysqli->prepare("SELECT ta.*, u.full_name as teacher_name, c.name as class_name, s.name as subject_name 
                                      FROM teacher_allocations ta
                                      JOIN users u ON ta.teacher_id = u.id
                                      JOIN classes c ON ta.class_id = c.id
                                      JOIN subjects s ON ta.subject_id = s.id
                                      WHERE ta.id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $allocation_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($allocation = $result->fetch_assoc()) {
                    echo "<p>Allocation details:</p>";
                    echo "<ul>";
                    echo "<li>Teacher: " . htmlspecialchars($allocation['teacher_name']) . "</li>";
                    echo "<li>Class: " . htmlspecialchars($allocation['class_name']) . "</li>";
                    echo "<li>Subject: " . htmlspecialchars($allocation['subject_name']) . "</li>";
                    echo "<li>Allocated by user ID: " . $allocation['allocated_by'] . "</li>";
                    echo "</ul>";
                }
                $stmt->close();
            }
            
            // Clean up - delete the test allocation
            $stmt = $mysqli->prepare("DELETE FROM teacher_allocations WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $allocation_id);
                $stmt->execute();
                $stmt->close();
                echo "<p style='color: green;'>✓ Cleaned up test allocation</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ Error adding allocation: " . $stmt->error . "</p>";
            
            // Check for specific foreign key constraint error
            if ($mysqli->errno == 1452) { // Foreign key constraint error
                echo "<p style='color: red;'>This is a foreign key constraint error. Check that all referenced IDs exist in their respective tables.</p>";
            }
        }
        $stmt->close();
    } else {
        echo "<p style='color: red;'>✗ Error preparing statement: " . $mysqli->error . "</p>";
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='admin/teacher_allocation.php'>Go to Teacher Allocation</a></p>";
?>