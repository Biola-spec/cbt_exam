<?php
echo "<h2>MySQLi CBT Exam System Setup</h2>";

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cbt_exam_system');

echo "<p>Creating database and tables using MySQLi...</p>";

try {
    // Connect without specifying database
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "<p style='color: green;'>✓ Basic connection successful</p>";
    
    // Create database
    if ($mysqli->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
        echo "<p style='color: green;'>✓ Database created successfully</p>";
    } else {
        throw new Exception("Error creating database: " . $mysqli->error);
    }
    
    // Select database
    if (!$mysqli->select_db(DB_NAME)) {
        throw new Exception("Error selecting database: " . $mysqli->error);
    }
    
    echo "<p style='color: green;'>✓ Database selected</p>";
    
    // Create tables from schema
    echo "<p>Creating tables...</p>";
    
    // Create users table
    $sql = "
        CREATE TABLE IF NOT EXISTS `users` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `email` varchar(255) NOT NULL,
          `password` varchar(255) NOT NULL,
          `full_name` varchar(255) NOT NULL,
          `role` enum('admin','teacher','student') NOT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    if ($mysqli->query($sql)) {
        echo "<p style='color: green;'>✓ Users table created</p>";
    } else {
        throw new Exception("Error creating users table: " . $mysqli->error);
    }
    
    // Create school_config table
    $sql = "
        CREATE TABLE IF NOT EXISTS `school_config` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `school_name` varchar(255) NOT NULL,
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          `updated_by` int(11) DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    if ($mysqli->query($sql)) {
        echo "<p style='color: green;'>✓ School config table created</p>";
    } else {
        throw new Exception("Error creating school_config table: " . $mysqli->error);
    }
    
    // Create classes table
    $sql = "
        CREATE TABLE IF NOT EXISTS `classes` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          `description` text DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    if ($mysqli->query($sql)) {
        echo "<p style='color: green;'>✓ Classes table created</p>";
    } else {
        throw new Exception("Error creating classes table: " . $mysqli->error);
    }
    
    // Create subjects table
    $sql = "
        CREATE TABLE IF NOT EXISTS `subjects` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          `description` text DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    if ($mysqli->query($sql)) {
        echo "<p style='color: green;'>✓ Subjects table created</p>";
    } else {
        throw new Exception("Error creating subjects table: " . $mysqli->error);
    }
    
    // Create teacher_allocations table
    $sql = "
        CREATE TABLE IF NOT EXISTS `teacher_allocations` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `teacher_id` int(11) NOT NULL,
          `class_id` int(11) NOT NULL,
          `subject_id` int(11) NOT NULL,
          `allocated_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `allocated_by` int(11) DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `teacher_class_subject` (`teacher_id`,`class_id`,`subject_id`),
          KEY `class_id` (`class_id`),
          KEY `subject_id` (`subject_id`),
          KEY `allocated_by` (`allocated_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    if ($mysqli->query($sql)) {
        echo "<p style='color: green;'>✓ Teacher allocations table created</p>";
    } else {
        throw new Exception("Error creating teacher_allocations table: " . $mysqli->error);
    }
    
    // Create exams table
    $sql = "
        CREATE TABLE IF NOT EXISTS `exams` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `title` varchar(255) NOT NULL,
          `description` text DEFAULT NULL,
          `is_published` tinyint(1) NOT NULL DEFAULT 0,
          `published_at` timestamp NULL DEFAULT NULL,
          `created_by` int(11) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `created_by` (`created_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    if ($mysqli->query($sql)) {
        echo "<p style='color: green;'>✓ Exams table created</p>";
    } else {
        throw new Exception("Error creating exams table: " . $mysqli->error);
    }
    
    // Create exam_subjects table
    $sql = "
        CREATE TABLE IF NOT EXISTS `exam_subjects` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `exam_id` int(11) NOT NULL,
          `subject_id` int(11) NOT NULL,
          `class_id` int(11) NOT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `exam_subject_class` (`exam_id`,`subject_id`,`class_id`),
          KEY `subject_id` (`subject_id`),
          KEY `class_id` (`class_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    if ($mysqli->query($sql)) {
        echo "<p style='color: green;'>✓ Exam subjects table created</p>";
    } else {
        throw new Exception("Error creating exam_subjects table: " . $mysqli->error);
    }
    
    // Create questions table
    $sql = "
        CREATE TABLE IF NOT EXISTS `questions` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `exam_subject_id` int(11) NOT NULL,
          `question_text` text NOT NULL,
          `option_a` text NOT NULL,
          `option_b` text NOT NULL,
          `option_c` text NOT NULL,
          `option_d` text NOT NULL,
          `option_e` text,
          `option_f` text,
          `correct_answer` enum('A','B','C','D','E','F') NOT NULL,
          `created_by` int(11) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `exam_subject_id` (`exam_subject_id`),
          KEY `created_by` (`created_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    if ($mysqli->query($sql)) {
        echo "<p style='color: green;'>✓ Questions table created</p>";
    } else {
        throw new Exception("Error creating questions table: " . $mysqli->error);
    }
    
    // Note: Passkeys table has been removed in favor of subject code authentication
    
    // Create exam_attempts table
    $sql = "
        CREATE TABLE IF NOT EXISTS `exam_attempts` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `student_id` int(11) NOT NULL,
          `exam_id` int(11) NOT NULL,
          `exam_subject_id` int(11) NOT NULL,
          `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `submitted_at` timestamp NULL DEFAULT NULL,
          `score` int(11) DEFAULT NULL,
          `total_questions` int(11) NOT NULL,
          PRIMARY KEY (`id`),
          KEY `student_id` (`student_id`),
          KEY `exam_id` (`exam_id`),
          KEY `exam_subject_id` (`exam_subject_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    if ($mysqli->query($sql)) {
        echo "<p style='color: green;'>✓ Exam attempts table created</p>";
    } else {
        throw new Exception("Error creating exam_attempts table: " . $mysqli->error);
    }
    
    // Create student_answers table
    $sql = "
        CREATE TABLE IF NOT EXISTS `student_answers` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `attempt_id` int(11) NOT NULL,
          `question_id` int(11) NOT NULL,
          `selected_answer` enum('A','B','C','D','E','F') DEFAULT NULL,
          `is_correct` tinyint(1) DEFAULT NULL,
          `answered_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `attempt_question` (`attempt_id`,`question_id`),
          KEY `question_id` (`question_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    if ($mysqli->query($sql)) {
        echo "<p style='color: green;'>✓ Student answers table created</p>";
    } else {
        throw new Exception("Error creating student_answers table: " . $mysqli->error);
    }
    
    // Create exam_republish_log table
    $sql = "
        CREATE TABLE IF NOT EXISTS `exam_republish_log` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `student_id` int(11) NOT NULL,
          `exam_id` int(11) NOT NULL,
          `exam_subject_id` int(11) DEFAULT NULL,
          `republished_by` int(11) DEFAULT NULL,
          `republished_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `reason` text DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `student_id` (`student_id`),
          KEY `exam_id` (`exam_id`),
          KEY `exam_subject_id` (`exam_subject_id`),
          KEY `republished_by` (`republished_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    if ($mysqli->query($sql)) {
        echo "<p style='color: green;'>✓ Exam republish log table created</p>";
    } else {
        throw new Exception("Error creating exam_republish_log table: " . $mysqli->error);
    }
    
    // Create foreign key constraints
    echo "<p>Adding foreign key constraints...</p>";
    
    $constraints = [
        "ALTER TABLE `teacher_allocations` ADD CONSTRAINT `teacher_allocations_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `teacher_allocations` ADD CONSTRAINT `teacher_allocations_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `teacher_allocations` ADD CONSTRAINT `teacher_allocations_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `teacher_allocations` ADD CONSTRAINT `teacher_allocations_ibfk_4` FOREIGN KEY (`allocated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL",
        "ALTER TABLE `exams` ADD CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL",
        "ALTER TABLE `exam_subjects` ADD CONSTRAINT `exam_subjects_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `exam_subjects` ADD CONSTRAINT `exam_subjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `exam_subjects` ADD CONSTRAINT `exam_subjects_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `questions` ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`exam_subject_id`) REFERENCES `exam_subjects` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `questions` ADD CONSTRAINT `questions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL",
        "ALTER TABLE `passkeys` ADD CONSTRAINT `passkeys_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `passkeys` ADD CONSTRAINT `passkeys_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL",
        "ALTER TABLE `exam_attempts` ADD CONSTRAINT `exam_attempts_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `exam_attempts` ADD CONSTRAINT `exam_attempts_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `exam_attempts` ADD CONSTRAINT `exam_attempts_ibfk_3` FOREIGN KEY (`exam_subject_id`) REFERENCES `exam_subjects` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `student_answers` ADD CONSTRAINT `student_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `student_answers` ADD CONSTRAINT `student_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `exam_republish_log` ADD CONSTRAINT `exam_republish_log_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `exam_republish_log` ADD CONSTRAINT `exam_republish_log_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `exam_republish_log` ADD CONSTRAINT `exam_republish_log_ibfk_3` FOREIGN KEY (`exam_subject_id`) REFERENCES `exam_subjects` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `exam_republish_log` ADD CONSTRAINT `exam_republish_log_ibfk_4` FOREIGN KEY (`republished_by`) REFERENCES `users` (`id`) ON DELETE SET NULL"
    ];
    
    foreach ($constraints as $constraint) {
        if (!$mysqli->query($constraint)) {
            echo "<p style='color: orange;'>⚠ Warning: " . $mysqli->error . "</p>";
        }
    }
    
    echo "<p style='color: green;'>✓ Foreign key constraints processed</p>";
    
    // Create admin user
    echo "<p>Creating admin user...</p>";
    
    $email = 'admin@cbtexam.com';
    $password = password_hash('Admin@2024', PASSWORD_DEFAULT);
    $full_name = 'System Administrator';
    $role = 'admin';
    
    $stmt = $mysqli->prepare("INSERT IGNORE INTO users (email, password, full_name, role) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssss", $email, $password, $full_name, $role);
        if ($stmt->execute()) {
            echo "<p style='color: green;'>✓ Admin user created successfully!</p>";
        } else {
            throw new Exception("Error creating admin user: " . $stmt->error);
        }
        $stmt->close();
    } else {
        throw new Exception("Error preparing admin user statement: " . $mysqli->error);
    }
    
    echo "<p><strong>Login Credentials:</strong></p>";
    echo "<ul>";
    echo "<li><strong>Email:</strong> " . $email . "</li>";
    echo "<li><strong>Password:</strong> Admin@2024</li>";
    echo "</ul>";
    echo "<p>You can now <a href='login.php'>login to the system</a>.</p>";
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p>MySQLi setup completed!</p>";
?>