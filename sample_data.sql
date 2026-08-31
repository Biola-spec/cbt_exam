USE cbt_exam_system;

-- Insert sample teacher
INSERT INTO users (email, password, full_name, role) VALUES ('gb.to@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Gbenga Tosin', 'teacher');

-- Insert sample exam
INSERT INTO exams (title, description, is_published, created_by) VALUES ('FIRST TERM EXAMINATION', 'First term examination for all classes', 0, 1);

-- Get the IDs we just inserted
SET @teacher_id = (SELECT id FROM users WHERE email = 'gb.to@gmail.com');
SET @exam_id = (SELECT id FROM exams WHERE title = 'FIRST TERM EXAMINATION');

-- Insert teacher allocation (JSS 2 / English Language)
INSERT INTO teacher_allocations (teacher_id, class_id, subject_id) VALUES (@teacher_id, 2, 2);

-- Link exam to JSS 1 / Mathematics
INSERT INTO exam_subjects (exam_id, subject_id, class_id) VALUES (@exam_id, 1, 1);

-- Link exam to JSS 1 / English Language
INSERT INTO exam_subjects (exam_id, subject_id, class_id) VALUES (@exam_id, 2, 1);

-- Link exam to JSS 2 / English Language (this is the key missing link!)
INSERT INTO exam_subjects (exam_id, subject_id, class_id) VALUES (@exam_id, 2, 2);