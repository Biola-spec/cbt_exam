-- Update school_config table to add logo_path column
ALTER TABLE `school_config` 
ADD COLUMN `logo_path` VARCHAR(255) NULL DEFAULT NULL AFTER `default_time_limit`;