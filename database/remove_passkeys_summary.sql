-- Summary of changes to remove passkeys functionality
-- 
-- To completely remove passkeys functionality from the database, run these commands:

-- 1. Drop foreign key constraints (if they exist)
ALTER TABLE `passkeys` DROP FOREIGN KEY IF EXISTS `passkeys_ibfk_1`;
ALTER TABLE `passkeys` DROP FOREIGN KEY IF EXISTS `passkeys_ibfk_2`;
ALTER TABLE `passkeys` DROP FOREIGN KEY IF EXISTS `passkeys_ibfk_3`;

-- 2. Drop indexes (if they exist)
DROP INDEX IF EXISTS `exam_id` ON `passkeys`;
DROP INDEX IF EXISTS `created_by` ON `passkeys`;
DROP INDEX IF EXISTS `exam_subject_id` ON `passkeys`;
DROP INDEX IF EXISTS `idx_passkeys_exam_subject` ON `passkeys`;

-- 3. Drop the passkeys table
DROP TABLE IF EXISTS `passkeys`;