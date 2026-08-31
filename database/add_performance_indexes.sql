-- Add performance indexes for frequently queried columns
-- These indexes will improve query performance for common operations

-- Index on users table for email lookups (already exists as UNIQUE KEY)
-- ALTER TABLE `users` ADD INDEX `idx_users_email` (`email`);

-- Add index on users table for role-based queries
ALTER TABLE `users` ADD INDEX `idx_users_role` (`role`);

-- Add index on exam_subjects for exam_id queries
ALTER TABLE `exam_subjects` ADD INDEX `idx_exam_subjects_exam_id` (`exam_id`);

-- Add index on questions for exam_subject_id queries (already exists as KEY)
-- ALTER TABLE `questions` ADD INDEX `idx_questions_exam_subject_id` (`exam_subject_id`);

-- Add index on exam_attempts for student_id queries (already exists as KEY)
-- ALTER TABLE `exam_attempts` ADD INDEX `idx_exam_attempts_student_id` (`student_id`);

-- Add index on exam_attempts for exam_subject_id queries (already exists as KEY)
-- ALTER TABLE `exam_attempts` ADD INDEX `idx_exam_attempts_exam_subject_id` (`exam_subject_id`);

-- Add index on student_answers for attempt_id queries (already exists as KEY)
-- ALTER TABLE `student_answers` ADD INDEX `idx_student_answers_attempt_id` (`attempt_id`);

-- Add composite index for faster exam access by student and subject
ALTER TABLE `exam_attempts` ADD INDEX `idx_exam_attempts_student_subject` (`student_id`, `exam_subject_id`);

-- Add index on subjects for subject_code queries
ALTER TABLE `subjects` ADD INDEX `idx_subjects_subject_code` (`subject_code`);

-- Add index on classes for name queries
ALTER TABLE `classes` ADD INDEX `idx_classes_name` (`name`);

-- Add index on exams for title queries
ALTER TABLE `exams` ADD INDEX `idx_exams_title` (`title`);

-- Add index on teacher_allocations for teacher_id queries
ALTER TABLE `teacher_allocations` ADD INDEX `idx_teacher_allocations_teacher_id` (`teacher_id`);

-- Add index on teacher_allocations for class_id queries
ALTER TABLE `teacher_allocations` ADD INDEX `idx_teacher_allocations_class_id` (`class_id`);

-- Add index on teacher_allocations for subject_id queries
ALTER TABLE `teacher_allocations` ADD INDEX `idx_teacher_allocations_subject_id` (`subject_id`);

-- Composite index for faster allocation lookups
ALTER TABLE `teacher_allocations` ADD INDEX `idx_teacher_allocations_teacher_class` (`teacher_id`, `class_id`);