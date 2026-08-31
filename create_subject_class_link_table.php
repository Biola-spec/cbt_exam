<?php
require_once 'config/database.php';

try {
    $mysqli = getDBConnection();
    
    if ($mysqli) {
        $sql = "CREATE TABLE IF NOT EXISTS subject_class_link (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subject_id INT NOT NULL,
            class_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            UNIQUE KEY unique_subject_class (subject_id, class_id)
        )";
        
        if ($mysqli->query($sql)) {
            echo "Table 'subject_class_link' created successfully or already exists.";
        } else {
            echo "Error creating table: " . $mysqli->error;
        }
        
        $mysqli->close();
    } else {
        echo "Database connection failed.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>