<?php
require_once 'config/database.php';

echo "Populating sample data...\n";

try {
    $mysqli = getDBConnection();
    
    if ($mysqli) {
        echo "Database connection successful\n";
        
        // Insert sample classes
        $stmt = $mysqli->prepare("INSERT INTO classes (name, description) VALUES (?, ?)");
        $classes = [
            ['JSS 1', 'Junior Secondary School 1'],
            ['JSS 2', 'Junior Secondary School 2'],
            ['JSS 3', 'Junior Secondary School 3']
        ];
        
        foreach ($classes as $class) {
            $stmt->bind_param("ss", $class[0], $class[1]);
            $stmt->execute();
            echo "Inserted class: " . $class[0] . "\n";
        }
        
        // Insert sample subjects
        $stmt = $mysqli->prepare("INSERT INTO subjects (name, description) VALUES (?, ?)");
        $subjects = [
            ['Mathematics', 'Mathematics subject'],
            ['English Language', 'English Language subject'],
            ['Civic Education', 'Civic Education subject']
        ];
        
        foreach ($subjects as $subject) {
            $stmt->bind_param("ss", $subject[0], $subject[1]);
            $stmt->execute();
            echo "Inserted subject: " . $subject[0] . "\n";
        }
        
        // Insert sample teacher
        $stmt = $mysqli->prepare("INSERT INTO users (email, password, full_name, role) VALUES (?, ?, ?, ?)");
        $password_hash = password_hash('Teacher@2024', PASSWORD_DEFAULT);
        $stmt->bind_param("ssss", 'gb.to@gmail.com', $password_hash, 'Gbenga Tosin', 'teacher');
        $stmt->execute();
        $teacher_id = $mysqli->insert_id;
        echo "Inserted teacher: Gbenga Tosin (ID: " . $teacher_id . ")\n";
        
        // Insert sample exam
        $stmt = $mysqli->prepare("INSERT INTO exams (title, description, is_published, created_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssii", 'FIRST TERM EXAMINATION', 'First term examination for all classes', 0, 1);
        $stmt->execute();
        $exam_id = $mysqli->insert_id;
        echo "Inserted exam: FIRST TERM EXAMINATION (ID: " . $exam_id . ")\n";
        
        // Insert exam subjects (linking exams to classes and subjects)
        $stmt = $mysqli->prepare("INSERT INTO exam_subjects (exam_id, subject_id, class_id) VALUES (?, ?, ?)");
        // Link exam to JSS 1 / Mathematics
        $stmt->bind_param("iii", $exam_id, 1, 1);
        $stmt->execute();
        echo "Linked exam to JSS 1 / Mathematics\n";
        
        // Link exam to JSS 1 / English Language
        $stmt->bind_param("iii", $exam_id, 2, 1);
        $stmt->execute();
        echo "Linked exam to JSS 1 / English Language\n";
        
        // Insert teacher allocation (JSS 2 / English Language)
        $stmt = $mysqli->prepare("INSERT INTO teacher_allocations (teacher_id, class_id, subject_id) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $teacher_id, 3, 2); // teacher_id, JSS 2 (class_id=3), English Language (subject_id=2)
        $stmt->execute();
        echo "Allocated teacher to JSS 2 / English Language\n";
        
        // Now add the missing link - link exam to JSS 2 / English Language
        $stmt = $mysqli->prepare("INSERT INTO exam_subjects (exam_id, subject_id, class_id) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $exam_id, 2, 3); // exam_id, English Language (subject_id=2), JSS 2 (class_id=3)
        $stmt->execute();
        echo "Linked exam to JSS 2 / English Language (this was the missing link!)\n";
        
        echo "\nSample data populated successfully!\n";
        
    } else {
        echo "Database connection failed\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>