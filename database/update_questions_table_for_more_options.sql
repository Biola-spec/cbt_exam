-- Migration script to add support for more than 4 options (A, B, C, D) in questions table
-- This script adds option_e and option_f columns and updates the correct_answer enum

-- Add option_e and option_f columns to questions table
ALTER TABLE `questions` 
ADD COLUMN `option_e` TEXT NULL AFTER `option_d`,
ADD COLUMN `option_f` TEXT NULL AFTER `option_e`;

-- Modify correct_answer column to include E and F options
ALTER TABLE `questions` 
MODIFY COLUMN `correct_answer` ENUM('A','B','C','D','E','F') NOT NULL;

-- Also update student_answers table to support E and F options
ALTER TABLE `student_answers` 
MODIFY COLUMN `selected_answer` ENUM('A','B','C','D','E','F') NULL;