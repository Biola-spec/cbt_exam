<?php
echo "<h2>Complete CBT Exam System Setup</h2>";

// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'cbt_exam_system';

try {
    // Create connection
    $mysqli = new mysqli($host, $user, $pass);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Create database
    if ($mysqli->query("CREATE DATABASE IF NOT EXISTS `$dbname`")) {
        echo "<p style='color: green;'>✓ Database '$dbname' created or already exists</p>";
    } else {
        throw new Exception("Error creating database: " . $mysqli->error);
    }
    
    // Select database
    $mysqli->select_db($dbname);
    
    // Read and execute SQL schema
    $sql = file_get_contents('database/cbt_exam_schema.sql');
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $success = 0;
    $errors = 0;
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            if ($mysqli->query($statement)) {
                $success++;
            } else {
                $errors++;
                echo "<p style='color: orange;'>⚠ Warning: " . $mysqli->error . "</p>";
            }
        }
    }
    
    echo "<p style='color: green;'>✓ Executed $success SQL statements</p>";
    if ($errors > 0) {
        echo "<p style='color: orange;'>⚠ Encountered $errors SQL warnings</p>";
    }
    
    // Create admin user
    echo "<p>Creating admin user...</p>";
    
    $email = 'admin@cbtexam.com';
    $password = password_hash('Admin@2024', PASSWORD_DEFAULT);
    $full_name = 'System Administrator';
    $role = 'admin';
    
    // Check if admin user already exists
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? AND role = ?");
    $stmt->bind_param("ss", $email, $role);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<p style='color: orange;'>⚠ Admin user already exists</p>";
    } else {
        $stmt = $mysqli->prepare("INSERT INTO users (email, password, full_name, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $email, $password, $full_name, $role);
        
        if ($stmt->execute()) {
            echo "<p style='color: green;'>✓ Admin user created successfully!</p>";
        } else {
            throw new Exception("Error creating admin user: " . $stmt->error);
        }
    }
    
    echo "<h3>Setup completed successfully!</h3>";
    echo "<p><strong>Login Credentials:</strong></p>";
    echo "<ul>";
    echo "<li><strong>Email:</strong> " . $email . "</li>";
    echo "<li><strong>Password:</strong> Admin@2024</li>";
    echo "</ul>";
    echo "<p>You can now <a href='login.php'>login to the system</a>.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please make sure:</p>";
    echo "<ul>";
    echo "<li>MySQL service is running</li>";
    echo "<li>Database credentials are correct</li>";
    echo "<li>PHP MySQL extensions are enabled</li>";
    echo "</ul>";
}

echo "<p><a href='login.php'>Go to Login Page</a></p>";
?>