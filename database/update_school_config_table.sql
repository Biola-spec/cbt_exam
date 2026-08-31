-- Update script to add default_time_limit column to school_config table

-- Add default_time_limit column to school_config table
ALTER TABLE `school_config` 
ADD COLUMN `default_time_limit` INT DEFAULT 60 AFTER `school_name`;