-- SQL script to add logo_path column to school_config table
-- Run this in PHPMyAdmin if the column doesn't exist

ALTER TABLE `school_config` 
ADD COLUMN `logo_path` VARCHAR(255) NULL DEFAULT NULL AFTER `default_time_limit`;

-- Verify the column was added
DESCRIBE `school_config`;