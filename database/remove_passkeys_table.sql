-- Remove passkeys table and related constraints
-- Drop foreign key constraints first
ALTER TABLE `passkeys` DROP FOREIGN KEY `passkeys_ibfk_1`;
ALTER TABLE `passkeys` DROP FOREIGN KEY `passkeys_ibfk_2`;
ALTER TABLE `passkeys` DROP FOREIGN KEY `passkeys_ibfk_3`;

-- Drop indexes
DROP INDEX `exam_id` ON `passkeys`;
DROP INDEX `created_by` ON `passkeys`;
DROP INDEX `exam_subject_id` ON `passkeys`;
DROP INDEX `idx_passkeys_exam_subject` ON `passkeys`;

-- Drop the passkeys table
DROP TABLE IF EXISTS `passkeys`;