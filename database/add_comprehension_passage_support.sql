-- Update script to add comprehension passage support with question ranges
-- This script adds a new table to store comprehension passages for specific question ranges

-- Create comprehension_passages table
CREATE TABLE `comprehension_passages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_subject_id` int(11) NOT NULL,
  `passage_title` varchar(255) DEFAULT NULL,
  `passage_content` text NOT NULL,
  `start_question_number` int(11) NOT NULL,
  `end_question_number` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `exam_subject_id` (`exam_subject_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `comprehension_passages_ibfk_1` FOREIGN KEY (`exam_subject_id`) REFERENCES `exam_subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comprehension_passages_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add indexes for better performance
CREATE INDEX `idx_comprehension_passage_range` ON `comprehension_passages` (`exam_subject_id`, `start_question_number`, `end_question_number`);