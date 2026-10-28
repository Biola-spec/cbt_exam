-- Add a separate audience for entrance examinations.
-- Run once after entrance_admissions.sql.
ALTER TABLE entrance_exams
    ADD COLUMN candidate_type ENUM('student','teacher') NOT NULL DEFAULT 'student' AFTER created_by;

ALTER TABLE entrance_applications
    ADD COLUMN candidate_type ENUM('student','teacher') NOT NULL DEFAULT 'student' AFTER status;

UPDATE entrance_applications ea
JOIN entrance_exams ee ON ee.id=ea.entrance_exam_id
SET ea.candidate_type=ee.candidate_type;
