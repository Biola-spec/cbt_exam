<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cbt_exam_system');

// Check if this script is being run directly or included
$running_directly = !isset($included_from_admin);

if ($running_directly) {
    echo "<h2>Sync Users from JSON to Database</h2>";
}

try {
    // Connect to database
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    if ($running_directly) {
        echo "<p style='color: green;'>✓ Database connection successful</p>";
    }
    
    // Load users from JSON file
    $users_file = 'users.json';
    
    if (!file_exists($users_file)) {
        throw new Exception("Users file not found: " . $users_file);
    }
    
    $users = json_decode(file_get_contents($users_file), true);
    
    if (!$users) {
        throw new Exception("Error parsing users file");
    }
    
    if ($running_directly) {
        echo "<p>Found " . count($users) . " users in JSON file</p>";
    }
    
    $synced_count = 0;
    $added_count = 0;
    $updated_count = 0;
    
    // Sync each user to database
    foreach ($users as $email => $user_data) {
        // Check if user already exists in database
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // User exists, update if needed
            $existing_user = $result->fetch_assoc();
            $user_id = $existing_user['id'];
            
            $stmt = $mysqli->prepare("UPDATE users SET password = ?, full_name = ?, role = ? WHERE id = ?");
            $stmt->bind_param("sssi", $user_data['password'], $user_data['full_name'], $user_data['role'], $user_id);
            
            if ($stmt->execute()) {
                if ($running_directly) {
                    echo "<p style='color: green;'>✓ Updated user: " . htmlspecialchars($email) . "</p>";
                }
                $updated_count++;
                $synced_count++;
            } else {
                if ($running_directly) {
                    echo "<p style='color: red;'>✗ Error updating user " . htmlspecialchars($email) . ": " . $stmt->error . "</p>";
                }
            }
        } else {
            // User doesn't exist, insert new user
            $stmt = $mysqli->prepare("INSERT INTO users (email, password, full_name, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $email, $user_data['password'], $user_data['full_name'], $user_data['role']);
            
            if ($stmt->execute()) {
                if ($running_directly) {
                    echo "<p style='color: green;'>✓ Added user: " . htmlspecialchars($email) . "</p>";
                }
                $added_count++;
                $synced_count++;
            } else {
                if ($running_directly) {
                    echo "<p style='color: red;'>✗ Error adding user " . htmlspecialchars($email) . ": " . $stmt->error . "</p>";
                }
            }
        }
        
        $stmt->close();
    }
    
    if ($running_directly) {
        echo "<p style='color: green;'><strong>Sync completed! " . $synced_count . " users processed (" . $added_count . " added, " . $updated_count . " updated).</strong></p>";
        
        // Verify teachers in database after sync
        echo "<h3>Teachers in Database After Sync:</h3>";
        $stmt = $mysqli->prepare("SELECT id, email, full_name, role FROM users WHERE role = 'teacher'");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Email</th><th>Full Name</th><th>Role</th></tr>";
            
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
            echo "<p>No teachers found in database.</p>";
        }
        
        $stmt->close();
    } else {
        // Return sync results when included
        $results = [
            'total_synced' => $synced_count,
            'added' => $added_count,
            'updated' => $updated_count
        ];
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    if ($running_directly) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    } else {
        throw $e;
    }
}

if ($running_directly) {
    echo "<p><a href='admin/dashboard.php?tab=sync'>Go to Sync Users Page</a> | <a href='admin/dashboard.php'>Admin Dashboard</a></p>";
} else {
    // Set a global variable to indicate the script was included
    $sync_results = $results ?? [];
}
?>