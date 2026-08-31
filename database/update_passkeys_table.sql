-- Update script to add missing columns to passkeys table
-- Adding usage_limit and exam_subject_id columns

ALTER TABLE `passkeys` ADD COLUMN `usage_limit` INT DEFAULT 1 AFTER `exam_id`;
ALTER TABLE `passkeys` ADD COLUMN `exam_subject_id` INT NULL AFTER `exam_id`;

-- Add foreign key constraint for exam_subject_id
ALTER TABLE `passkeys` ADD CONSTRAINT `passkeys_ibfk_3` 
FOREIGN KEY (`exam_subject_id`) REFERENCES `exam_subjects` (`id`) ON DELETE CASCADE;

-- Add index for better performance
CREATE INDEX `idx_passkeys_exam_subject` ON `passkeys` (`exam_subject_id`);