-- Update script to add missing columns to support new features
-- This script updates the existing database schema

-- Add columns to school_config table
ALTER TABLE `school_config` 
ADD COLUMN `default_time_limit` INT DEFAULT 60 AFTER `school_name`;

-- Add columns to exam_subjects table
ALTER TABLE `exam_subjects` 
ADD COLUMN `time_limit` INT DEFAULT 60 AFTER `class_id`,
ADD COLUMN `is_published` TINYINT(1) DEFAULT 0 AFTER `time_limit`,
ADD COLUMN `published_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_published`;

-- Add columns to passkeys table
ALTER TABLE `passkeys` 
ADD COLUMN `usage_limit` INT DEFAULT 1 AFTER `exam_id`,
ADD COLUMN `exam_subject_id` INT NULL DEFAULT NULL AFTER `usage_limit`;

-- Add foreign key constraint for exam_subject_id in passkeys table
ALTER TABLE `passkeys` 
ADD CONSTRAINT `passkeys_ibfk_3` 
FOREIGN KEY (`exam_subject_id`) REFERENCES `exam_subjects` (`id`) ON DELETE CASCADE;

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_passkeys_exam_subject` ON `passkeys` (`exam_subject_id`);
CREATE INDEX IF NOT EXISTS `idx_exam_subjects_published` ON `exam_subjects` (`is_published`);