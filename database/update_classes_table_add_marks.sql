-- Add marks_per_question column to classes table
ALTER TABLE `classes` ADD COLUMN `marks_per_question` DECIMAL(5,2) DEFAULT 1.00 NOT NULL AFTER `description`;