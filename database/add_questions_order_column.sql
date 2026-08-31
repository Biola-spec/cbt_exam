-- Add order_position column to questions table to maintain question sequence
ALTER TABLE `questions` 
ADD COLUMN `order_position` INT(11) DEFAULT NULL AFTER `exam_subject_id`;

-- Add index for better performance when ordering by position
ALTER TABLE `questions` 
ADD INDEX `idx_questions_order_position` (`exam_subject_id`, `order_position`);