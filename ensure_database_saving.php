<?php
echo "<h2>Ensure Database Saving</h2>";

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
    
    // Function to test adding a new class
    function testAddClass($mysqli) {
        echo "<h3>Testing Class Addition</h3>";
        $stmt = $mysqli->prepare("INSERT INTO classes (name, description) VALUES (?, ?)");
        if ($stmt) {
            $name = "Test Class " . date("Y-m-d H:i:s");
            $description = "Test class for database saving verification";
            $stmt->bind_param("ss", $name, $description);
            
            if ($stmt->execute()) {
                $class_id = $mysqli->insert_id;
                echo "<p style='color: green;'>✓ Class added successfully with ID: " . $class_id . "</p>";
                
                // Verify it was saved
                $verify_stmt = $mysqli->prepare("SELECT id, name, description FROM classes WHERE id = ?");
                $verify_stmt->bind_param("i", $class_id);
                $verify_stmt->execute();
                $result = $verify_stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    echo "<p style='color: green;'>✓ Class verified in database: " . htmlspecialchars($row['name']) . "</p>";
                } else {
                    echo "<p style='color: red;'>✗ Could not verify class in database</p>";
                }
                $verify_stmt->close();
                
                // Clean up - delete the test class
                $cleanup_stmt = $mysqli->prepare("DELETE FROM classes WHERE id = ?");
                $cleanup_stmt->bind_param("i", $class_id);
                $cleanup_stmt->execute();
                $cleanup_stmt->close();
                echo "<p style='color: green;'>✓ Test class cleaned up</p>";
            } else {
                echo "<p style='color: red;'>✗ Error adding class: " . $stmt->error . "</p>";
            }
            $stmt->close();
        } else {
            echo "<p style='color: red;'>✗ Error preparing statement: " . $mysqli->error . "</p>";
        }
    }
    
    // Function to test adding a new subject
    function testAddSubject($mysqli) {
        echo "<h3>Testing Subject Addition</h3>";
        $stmt = $mysqli->prepare("INSERT INTO subjects (name, description) VALUES (?, ?)");
        if ($stmt) {
            $name = "Test Subject " . date("Y-m-d H:i:s");
            $description = "Test subject for database saving verification";
            $stmt->bind_param("ss", $name, $description);
            
            if ($stmt->execute()) {
                $subject_id = $mysqli->insert_id;
                echo "<p style='color: green;'>✓ Subject added successfully with ID: " . $subject_id . "</p>";
                
                // Verify it was saved
                $verify_stmt = $mysqli->prepare("SELECT id, name, description FROM subjects WHERE id = ?");
                $verify_stmt->bind_param("i", $subject_id);
                $verify_stmt->execute();
                $result = $verify_stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    echo "<p style='color: green;'>✓ Subject verified in database: " . htmlspecialchars($row['name']) . "</p>";
                } else {
                    echo "<p style='color: red;'>✗ Could not verify subject in database</p>";
                }
                $verify_stmt->close();
                
                // Clean up - delete the test subject
                $cleanup_stmt = $mysqli->prepare("DELETE FROM subjects WHERE id = ?");
                $cleanup_stmt->bind_param("i", $subject_id);
                $cleanup_stmt->execute();
                $cleanup_stmt->close();
                echo "<p style='color: green;'>✓ Test subject cleaned up</p>";
            } else {
                echo "<p style='color: red;'>✗ Error adding subject: " . $stmt->error . "</p>";
            }
            $stmt->close();
        } else {
            echo "<p style='color: red;'>✗ Error preparing statement: " . $mysqli->error . "</p>";
        }
    }
    
    // Function to test adding a subject-class link
    function testAddSubjectClassLink($mysqli) {
        echo "<h3>Testing Subject-Class Link Addition</h3>";
        
        // First, let's check if we have at least one class and one subject
        $class_result = $mysqli->query("SELECT id FROM classes LIMIT 1");
        $subject_result = $mysqli->query("SELECT id FROM subjects LIMIT 1");
        
        if ($class_result->num_rows > 0 && $subject_result->num_rows > 0) {
            $class = $class_result->fetch_assoc();
            $subject = $subject_result->fetch_assoc();
            $class_id = $class['id'];
            $subject_id = $subject['id'];
            
            // Check if this link already exists
            $check_stmt = $mysqli->prepare("SELECT id FROM subject_class_link WHERE class_id = ? AND subject_id = ?");
            $check_stmt->bind_param("ii", $class_id, $subject_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                echo "<p>Link already exists, skipping test</p>";
                $check_stmt->close();
                return;
            }
            $check_stmt->close();
            
            // Add the link
            $stmt = $mysqli->prepare("INSERT INTO subject_class_link (class_id, subject_id) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param("ii", $class_id, $subject_id);
                
                if ($stmt->execute()) {
                    $link_id = $mysqli->insert_id;
                    echo "<p style='color: green;'>✓ Subject-Class link added successfully with ID: " . $link_id . "</p>";
                    
                    // Verify it was saved
                    $verify_stmt = $mysqli->prepare("SELECT id, class_id, subject_id FROM subject_class_link WHERE id = ?");
                    $verify_stmt->bind_param("i", $link_id);
                    $verify_stmt->execute();
                    $result = $verify_stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        echo "<p style='color: green;'>✓ Subject-Class link verified in database</p>";
                    } else {
                        echo "<p style='color: red;'>✗ Could not verify subject-class link in database</p>";
                    }
                    $verify_stmt->close();
                    
                    // Clean up - delete the test link
                    $cleanup_stmt = $mysqli->prepare("DELETE FROM subject_class_link WHERE id = ?");
                    $cleanup_stmt->bind_param("i", $link_id);
                    $cleanup_stmt->execute();
                    $cleanup_stmt->close();
                    echo "<p style='color: green;'>✓ Test subject-class link cleaned up</p>";
                } else {
                    echo "<p style='color: red;'>✗ Error adding subject-class link: " . $stmt->error . "</p>";
                }
                $stmt->close();
            } else {
                echo "<p style='color: red;'>✗ Error preparing statement: " . $mysqli->error . "</p>";
            }
        } else {
            echo "<p>Need at least one class and one subject to test subject-class linking</p>";
        }
    }
    
    // Run the tests
    testAddClass($mysqli);
    testAddSubject($mysqli);
    testAddSubjectClassLink($mysqli);
    
    $mysqli->close();
    
    echo "<h3>Conclusion</h3>";
    echo "<p>If all tests above show green checkmarks, your system is properly saving data to the database.</p>";
    echo "<p>Remember to sync users from users.json to the database whenever you add new users:</p>";
    echo "<p><code>php sync_users_to_db.php</code></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='admin/dashboard.php'>Go to Admin Dashboard</a></p>";
?>