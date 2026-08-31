<?php
require_once 'config/database.php';

echo "<h2>CBT Exam System Setup</h2>";

try {
    // Create database and tables
    echo "<p>Creating database and tables...</p>";
    
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    echo "<p>Database created successfully</p>";
    
    // Select database
    $pdo->exec("USE " . DB_NAME);
    
    // Read and execute SQL schema
    $sql = file_get_contents('database/cbt_exam_schema.sql');
    $pdo->exec($sql);
    echo "<p>Tables created successfully</p>";
    
    // Create admin user
    echo "<p>Creating admin user...</p>";
    
    $email = 'admin@cbtexam.com';
    $password = password_hash('Admin@2024', PASSWORD_DEFAULT);
    $full_name = 'System Administrator';
    $role = 'admin';
    
    $stmt = $pdo->prepare("INSERT INTO users (email, password, full_name, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$email, $password, $full_name, $role]);
    
    echo "<p>Admin user created successfully!</p>";
    echo "<p><strong>Login Credentials:</strong></p>";
    echo "<ul>";
    echo "<li><strong>Email:</strong> " . $email . "</li>";
    echo "<li><strong>Password:</strong> Admin@2024</li>";
    echo "</ul>";
    echo "<p>You can now <a href='login.php'>login to the system</a>.</p>";
    
} catch (PDOException $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

echo "<p>Setup completed!</p>";
?>