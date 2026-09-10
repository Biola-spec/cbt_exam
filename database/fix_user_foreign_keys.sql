-- Migration Script: Fix User Foreign Key Constraints
-- This script updates foreign keys referencing `users`(`id`) to use `ON DELETE SET NULL`
-- so that deleting a user or teacher does not fail with a foreign key constraint violation.

USE `cbt_exam_system`;

-- 1. Comprehension Passages
ALTER TABLE `comprehension_passages` DROP FOREIGN KEY IF EXISTS `comprehension_passages_ibfk_2`;
ALTER TABLE `comprehension_passages` ADD CONSTRAINT `comprehension_passages_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- 2. Exams
ALTER TABLE `exams` DROP FOREIGN KEY IF EXISTS `exams_ibfk_1`;
ALTER TABLE `exams` ADD CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- 3. Questions
ALTER TABLE `questions` DROP FOREIGN KEY IF EXISTS `questions_ibfk_2`;
ALTER TABLE `questions` ADD CONSTRAINT `questions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- 4. Teacher Allocations
ALTER TABLE `teacher_allocations` DROP FOREIGN KEY IF EXISTS `teacher_allocations_ibfk_4`;
ALTER TABLE `teacher_allocations` ADD CONSTRAINT `teacher_allocations_ibfk_4` FOREIGN KEY (`allocated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- 5. Exam Republish Log
ALTER TABLE `exam_republish_log` DROP FOREIGN KEY IF EXISTS `exam_republish_log_ibfk_4`;
ALTER TABLE `exam_republish_log` ADD CONSTRAINT `exam_republish_log_ibfk_4` FOREIGN KEY (`republished_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
