-- Add index to improve performance of duplicate checking
ALTER TABLE `questions` 
ADD INDEX `idx_questions_duplicate_check` (`exam_subject_id`, `question_text`(255), `option_a`(100), `option_b`(100), `option_c`(100), `option_d`(100), `correct_answer`);