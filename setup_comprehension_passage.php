<?php
require_once 'config/database.php';

try {
    $mysqli = getDBConnection();
    
    if (!$mysqli) {
        die("Failed to connect to database. Please check your database configuration.\n");
    }
    
    // Check if the comprehension_passages table already exists
    $result = $mysqli->query("SHOW TABLES LIKE 'comprehension_passages'");
    
    if ($result && $result->num_rows > 0) {
        echo "Comprehension passages table already exists.\n";
        
        // Check if the new columns exist
        $columns_result = $mysqli->query("SHOW COLUMNS FROM comprehension_passages LIKE 'start_question_number'");
        if ($columns_result && $columns_result->num_rows == 0) {
            // Add the new columns
            $alter_sql = "ALTER TABLE comprehension_passages 
                         ADD COLUMN start_question_number int(11) NOT NULL AFTER passage_content,
                         ADD COLUMN end_question_number int(11) NOT NULL AFTER start_question_number";
            
            if ($mysqli->query($alter_sql) === TRUE) {
                echo "Added start_question_number and end_question_number columns.\n";
            } else {
                echo "Error adding columns: " . $mysqli->error . "\n";
            }
            
            // Add indexes
            $index_sql = "CREATE INDEX idx_comprehension_passage_range ON comprehension_passages (exam_subject_id, start_question_number, end_question_number)";
            if ($mysqli->query($index_sql) === TRUE) {
                echo "Added index for comprehension passage ranges.\n";
            } else {
                echo "Note: Index may already exist or error: " . $mysqli->error . "\n";
            }
        } else {
            echo "Table already has the required columns.\n";
        }
    } else {
        // Create the comprehension_passages table with the new structure
        $sql = "CREATE TABLE `comprehension_passages` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `exam_subject_id` int(11) NOT NULL,
                  `passage_title` varchar(255) DEFAULT NULL,
                  `passage_content` text NOT NULL,
                  `start_question_number` int(11) NOT NULL,
                  `end_question_number` int(11) NOT NULL,
                  `created_by` int(11) DEFAULT NULL,
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id`),
                  KEY `exam_subject_id` (`exam_subject_id`),
                  KEY `created_by` (`created_by`),
                  CONSTRAINT `comprehension_passages_ibfk_1` FOREIGN KEY (`exam_subject_id`) REFERENCES `exam_subjects` (`id`) ON DELETE CASCADE,
                  CONSTRAINT `comprehension_passages_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if ($mysqli->query($sql) === TRUE) {
            echo "Comprehension passages table created successfully.\n";
        } else {
            echo "Error creating table: " . $mysqli->error . "\n";
        }
        
        // Add indexes
        $index_sql = "CREATE INDEX idx_comprehension_passage_range ON comprehension_passages (exam_subject_id, start_question_number, end_question_number)";
        if ($mysqli->query($index_sql) === TRUE) {
            echo "Added index for comprehension passage ranges.\n";
        } else {
            echo "Note: Index may already exist or error: " . $mysqli->error . "\n";
        }
    }
    
    $mysqli->close();
    echo "Setup completed successfully.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>