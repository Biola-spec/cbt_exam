-- Add logo column to school_config table
ALTER TABLE `school_config` 
ADD COLUMN `logo_path` VARCHAR(255) NULL DEFAULT NULL AFTER `default_time_limit`;