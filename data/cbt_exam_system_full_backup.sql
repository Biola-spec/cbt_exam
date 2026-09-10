SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `academic_records`;
CREATE TABLE `academic_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `term` enum('1st','2nd','3rd') NOT NULL,
  `session` varchar(20) NOT NULL,
  `ca_score` decimal(5,2) DEFAULT 0.00,
  `exam_score` decimal(5,2) DEFAULT 0.00,
  `total_score` decimal(5,2) DEFAULT 0.00,
  `grade` varchar(5) DEFAULT NULL,
  `remark` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_subject_term_session` (`student_id`,`subject_id`,`term`,`session`),
  KEY `class_id` (`class_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `academic_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_records_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_records_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `academic_records` (`id`, `student_id`, `class_id`, `subject_id`, `term`, `session`, `ca_score`, `exam_score`, `total_score`, `grade`, `remark`, `created_at`) VALUES ('1', '7', '1', '11', '1st', '2026/2027', '0.00', '0.00', '0.00', 'F', 'Fail', '2026-02-25 11:21:43');

DROP TABLE IF EXISTS `classes`;
CREATE TABLE `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `marks_per_question` decimal(5,2) NOT NULL DEFAULT 1.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_classes_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `classes` (`id`, `name`, `description`, `marks_per_question`, `created_at`) VALUES ('1', 'JSS 1', 'Junior Secondary School 1', '0.50', '2025-10-28 10:09:12');
INSERT INTO `classes` (`id`, `name`, `description`, `marks_per_question`, `created_at`) VALUES ('2', 'JSS 2', 'Junior Secondary School 2', '0.50', '2025-10-28 10:09:12');
INSERT INTO `classes` (`id`, `name`, `description`, `marks_per_question`, `created_at`) VALUES ('3', 'SSS 1', 'Senior Secondary School 1', '1.00', '2025-10-28 10:09:12');
INSERT INTO `classes` (`id`, `name`, `description`, `marks_per_question`, `created_at`) VALUES ('4', 'JSS 3', 'Junior Secondary School 3', '0.50', '2025-11-04 16:46:14');
INSERT INTO `classes` (`id`, `name`, `description`, `marks_per_question`, `created_at`) VALUES ('5', 'SSS 3', 'Senior Secondary School 3', '1.00', '2025-11-04 16:48:13');
INSERT INTO `classes` (`id`, `name`, `description`, `marks_per_question`, `created_at`) VALUES ('6', 'SSS 2', 'Senior Secondary School 2', '1.00', '2025-11-04 16:48:42');
INSERT INTO `classes` (`id`, `name`, `description`, `marks_per_question`, `created_at`) VALUES ('7', '2026/2026 Teacher Interview', '2026/2026 Teacher Interview', '1.00', '2026-07-26 20:05:11');

DROP TABLE IF EXISTS `cognitive_scores`;
CREATE TABLE `cognitive_scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `trait_id` int(11) NOT NULL,
  `rating` varchar(5) NOT NULL,
  `term` enum('1st','2nd','3rd') NOT NULL,
  `session` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_trait_term_session` (`student_id`,`trait_id`,`term`,`session`),
  KEY `trait_id` (`trait_id`),
  CONSTRAINT `cognitive_scores_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cognitive_scores_ibfk_2` FOREIGN KEY (`trait_id`) REFERENCES `cognitive_traits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `cognitive_traits`;
CREATE TABLE `cognitive_traits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trait_name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('1', 'Punctuality');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('2', 'Neatness');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('3', 'Honesty');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('4', 'Leadership');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('5', 'Team Work');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('6', 'Reliability');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('7', 'Politeness');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('8', 'Attentiveness');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('9', 'Punctuality');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('10', 'Neatness');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('11', 'Honesty');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('12', 'Leadership');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('13', 'Team Work');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('14', 'Reliability');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('15', 'Politeness');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('16', 'Attentiveness');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('17', 'Punctuality');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('18', 'Neatness');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('19', 'Honesty');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('20', 'Leadership');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('21', 'Team Work');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('22', 'Reliability');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('23', 'Politeness');
INSERT INTO `cognitive_traits` (`id`, `trait_name`) VALUES ('24', 'Attentiveness');

DROP TABLE IF EXISTS `comprehension_passages`;
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
  KEY `idx_comprehension_passage_range` (`exam_subject_id`,`start_question_number`,`end_question_number`),
  CONSTRAINT `comprehension_passages_ibfk_1` FOREIGN KEY (`exam_subject_id`) REFERENCES `exam_subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comprehension_passages_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `comprehension_passages` (`id`, `exam_subject_id`, `passage_title`, `passage_content`, `start_question_number`, `end_question_number`, `created_by`, `created_at`, `updated_at`) VALUES ('8', '7', NULL, 'Ussu', '9', '10', '2', '2025-12-03 16:44:38', '2025-12-03 16:44:38');
INSERT INTO `comprehension_passages` (`id`, `exam_subject_id`, `passage_title`, `passage_content`, `start_question_number`, `end_question_number`, `created_by`, `created_at`, `updated_at`) VALUES ('15', '10', NULL, '<p>The name of the person working</p>', '1', '1', '1', '2026-03-24 10:04:00', '2026-03-24 10:04:00');
INSERT INTO `comprehension_passages` (`id`, `exam_subject_id`, `passage_title`, `passage_content`, `start_question_number`, `end_question_number`, `created_by`, `created_at`, `updated_at`) VALUES ('17', '10', 'Table rows', '<figure class=\"table\"><table><tbody><tr><td>3</td><td>4</td><td>2</td><td>1</td></tr><tr><td>8</td><td>5</td><td>6</td><td>7</td></tr><tr><td>9</td><td>8</td><td>4</td><td>0</td></tr><tr><td>7</td><td>6</td><td>9</td><td>3</td></tr></tbody></table></figure>', '2', '2', '1', '2026-03-24 10:06:46', '2026-03-24 10:06:46');

DROP TABLE IF EXISTS `exam_access_codes`;
CREATE TABLE `exam_access_codes` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `access_code` varchar(255) NOT NULL,
  `usage_limit` int(11) DEFAULT 1,
  `used_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  UNIQUE KEY `access_code` (`access_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `exam_access_codes` (`id`, `exam_id`, `access_code`, `usage_limit`, `used_count`, `created_at`, `expires_at`) VALUES ('0', '2', '8KKWAB4G', '3', '0', '2026-01-22 19:13:31', '0000-00-00 00:00:00');
INSERT INTO `exam_access_codes` (`id`, `exam_id`, `access_code`, `usage_limit`, `used_count`, `created_at`, `expires_at`) VALUES ('0', '2', 'YLYZKJUC', '1', '0', '2026-01-20 21:37:43', '0000-00-00 00:00:00');

DROP TABLE IF EXISTS `exam_attempts`;
CREATE TABLE `exam_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `exam_subject_id` int(11) NOT NULL,
  `current_subject_id` int(11) DEFAULT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `submitted_at` timestamp NULL DEFAULT NULL,
  `score` decimal(8,2) DEFAULT NULL,
  `total_questions` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `exam_id` (`exam_id`),
  KEY `exam_subject_id` (`exam_subject_id`),
  KEY `idx_exam_attempts_student_subject` (`student_id`,`exam_subject_id`),
  CONSTRAINT `exam_attempts_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_attempts_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_attempts_ibfk_3` FOREIGN KEY (`exam_subject_id`) REFERENCES `exam_subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('3', '4', '1', '4', NULL, '2025-10-29 17:10:07', '2025-10-29 18:58:47', '0.00', '2');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('4', '4', '1', '4', NULL, '2025-10-29 18:58:52', '2025-11-04 13:40:13', '0.00', '3');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('5', '4', '1', '4', NULL, '2025-11-04 13:40:19', '2025-11-04 14:08:16', '2.00', '4');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('6', '4', '1', '4', NULL, '2025-11-04 14:09:00', '2025-11-04 21:50:50', '0.00', '4');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('12', '4', '1', '4', NULL, '2025-11-04 21:51:09', '2025-11-04 21:51:31', '2.00', '4');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('17', '4', '1', '4', NULL, '2025-11-10 15:27:14', '2025-11-10 15:27:37', '2.00', '4');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('18', '4', '1', '4', NULL, '2025-11-10 15:31:45', '2025-11-10 15:32:06', '2.00', '4');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('19', '4', '1', '4', NULL, '2025-11-10 15:38:07', '2025-11-10 15:38:33', '4.00', '4');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('20', '4', '1', '4', NULL, '2025-11-10 15:38:46', '2025-11-10 15:41:14', '1.00', '4');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('28', '8', '1', '4', NULL, '2025-11-24 10:46:24', '2025-11-24 10:47:59', '0.00', '25');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('30', '10', '1', '4', NULL, '2025-11-25 11:43:45', '2025-11-25 11:48:33', '4.00', '16');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('31', '11', '1', '4', NULL, '2025-11-25 12:13:32', '2025-11-25 12:17:36', '4.00', '16');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('32', '13', '1', '4', NULL, '2025-11-25 12:23:26', NULL, NULL, '16');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('34', '14', '1', '4', NULL, '2025-11-25 13:00:14', '2025-11-25 13:03:04', '5.00', '16');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('35', '15', '1', '4', NULL, '2025-11-25 17:22:54', '2025-11-30 21:58:38', '0.00', '16');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('36', '16', '1', '4', NULL, '2025-11-26 13:10:14', NULL, NULL, '16');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('37', '16', '1', '7', NULL, '2025-11-26 13:11:47', NULL, NULL, '10');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('38', '15', '1', '7', NULL, '2025-11-26 14:45:38', '2025-11-26 15:47:34', '0.00', '10');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('39', '17', '1', '7', NULL, '2025-11-26 15:48:43', NULL, NULL, '10');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('40', '18', '1', '4', NULL, '2025-12-01 10:11:10', '2025-12-01 10:13:31', '6.00', '16');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('41', '19', '1', '4', NULL, '2025-12-02 15:36:59', '2025-12-02 15:41:53', '4.00', '16');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('42', '20', '1', '4', NULL, '2025-12-02 22:07:44', '2025-12-02 22:08:44', '0.00', '16');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('43', '21', '1', '4', NULL, '2025-12-03 10:00:22', '2025-12-03 10:01:22', '2.00', '16');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('44', '10', '1', '7', NULL, '2025-12-03 22:29:48', '2025-12-04 07:52:45', '0.00', '10');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('47', '10', '1', '7', NULL, '2025-12-04 14:24:18', NULL, NULL, '10');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('49', '7', '2', '10', NULL, '2026-03-24 07:46:58', '2026-03-24 07:50:21', '0.00', '1');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('50', '7', '2', '10', NULL, '2026-03-24 08:00:22', '2026-03-24 08:19:37', '0.00', '1');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('53', '7', '2', '10', NULL, '2026-03-24 08:19:58', '2026-03-24 08:21:59', '0.00', '1');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('54', '7', '2', '10', NULL, '2026-03-24 10:09:29', '2026-03-24 10:09:58', '2.00', '3');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('55', '7', '2', '10', NULL, '2026-04-09 14:19:21', '2026-04-09 14:19:21', '0.00', '3');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('56', '7', '2', '10', NULL, '2026-04-09 14:19:32', '2026-04-09 14:19:32', '0.00', '3');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('58', '7', '2', '10', NULL, '2026-04-09 14:19:51', '2026-04-09 14:19:51', '0.00', '3');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('59', '7', '2', '10', NULL, '2026-04-09 14:20:50', '2026-04-09 14:20:50', '0.00', '3');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('61', '7', '2', '10', NULL, '2026-04-09 14:21:21', '2026-04-09 14:21:21', '0.00', '3');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('64', '7', '2', '10', NULL, '2026-04-09 14:22:05', '2026-04-09 14:22:05', '0.00', '3');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('65', '10', '1', '4', NULL, '2026-04-09 19:33:23', '2026-04-09 19:33:23', '0.00', '8');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('66', '10', '1', '4', NULL, '2026-04-09 21:06:44', '2026-04-09 21:07:53', '0.00', '8');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('67', '10', '1', '4', NULL, '2026-04-09 21:29:07', NULL, NULL, '8');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('68', '7', '1', '4', NULL, '2026-04-17 05:55:12', '2026-04-30 14:57:51', '0.00', '0');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('69', '23', '1', '11', NULL, '2026-04-17 06:31:00', '2026-04-17 06:46:02', '0.00', '5');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('70', '23', '1', '11', NULL, '2026-04-17 07:19:06', '2026-04-17 07:19:33', '2.00', '5');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('71', '7', '1', '4', NULL, '2026-04-30 14:58:01', '2026-04-30 14:58:28', '2.00', '3');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('85', '23', '2', '10', NULL, '2026-05-07 17:22:08', '2026-05-07 17:30:32', NULL, '3');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('97', '28', '4', '12', NULL, '2026-07-24 09:43:06', '2026-07-24 09:57:34', '25.00', '50');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('98', '7', '1', '7', NULL, '2026-07-24 12:53:35', '2026-07-24 12:54:30', '10.50', '2');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('99', '29', '5', '14', NULL, '2026-07-27 09:46:49', '2026-07-27 10:01:23', '24.00', '30');
INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `current_subject_id`, `started_at`, `submitted_at`, `score`, `total_questions`) VALUES ('100', '30', '5', '14', NULL, '2026-07-27 10:43:34', '2026-07-27 10:59:02', '21.00', '30');

DROP TABLE IF EXISTS `exam_republish_log`;
CREATE TABLE `exam_republish_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `exam_subject_id` int(11) DEFAULT NULL,
  `republished_by` int(11) DEFAULT NULL,
  `republished_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `exam_id` (`exam_id`),
  KEY `exam_subject_id` (`exam_subject_id`),
  KEY `republished_by` (`republished_by`),
  CONSTRAINT `exam_republish_log_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_republish_log_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_republish_log_ibfk_3` FOREIGN KEY (`exam_subject_id`) REFERENCES `exam_subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_republish_log_ibfk_4` FOREIGN KEY (`republished_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('1', '14', '1', '7', '1', '2025-12-03 22:28:00', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('2', '10', '1', '7', '1', '2025-12-03 22:28:32', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('4', '10', '1', '7', '1', '2025-12-04 07:52:05', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('5', '10', '1', '7', '1', '2025-12-04 08:00:09', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('6', '10', '1', '7', '1', '2025-12-04 08:01:13', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('7', '7', '1', '6', '1', '2026-01-22 20:49:38', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('8', '7', '1', '6', '22', '2026-03-24 07:29:48', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('9', '7', '1', '6', '1', '2026-03-24 07:59:23', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('10', '7', '2', '10', '1', '2026-03-24 07:59:40', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('11', '7', '1', '6', '1', '2026-03-24 08:19:02', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('12', '7', '2', '10', '1', '2026-03-24 08:19:16', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('13', '7', '1', '6', '1', '2026-03-24 10:08:29', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('14', '7', '2', '10', '1', '2026-03-24 10:08:43', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('15', '7', '1', '6', '1', '2026-04-09 14:18:17', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('16', '7', '1', '6', '1', '2026-04-09 14:20:35', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('17', '7', '1', '4', '1', '2026-04-09 19:29:54', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('18', '10', '1', '4', '1', '2026-04-09 19:31:31', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('19', '7', '1', '4', '1', '2026-04-17 05:54:07', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('20', '23', '1', '11', '1', '2026-04-17 07:18:51', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('21', '23', '1', '6', '1', '2026-05-07 12:59:15', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('22', '23', '1', '6', '1', '2026-05-07 13:08:19', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('23', '23', '1', '6', '1', '2026-05-07 15:40:46', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('24', '23', '1', '6', '1', '2026-05-07 16:01:19', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('25', '23', '1', '6', '1', '2026-05-07 16:05:35', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('26', '23', '1', '6', '1', '2026-05-07 16:10:43', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('27', '23', '1', '6', '1', '2026-05-07 16:19:26', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('28', '23', '1', '6', '1', '2026-05-07 16:48:47', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('29', '23', '1', '6', '1', '2026-05-07 17:30:56', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('30', '23', '1', '6', '1', '2026-05-07 17:32:23', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('31', '23', '1', '6', '1', '2026-05-07 17:40:43', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('32', '23', '1', '6', '1', '2026-05-07 17:49:29', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('33', '23', '1', '6', '1', '2026-05-12 15:49:02', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('34', '23', '1', '6', '1', '2026-05-13 09:18:09', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('35', '23', '1', '6', '1', '2026-05-13 09:28:06', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('36', '23', '1', '6', '1', '2026-05-13 10:01:19', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('37', '23', '1', '6', '1', '2026-05-14 09:36:45', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('38', '23', '1', '6', '1', '2026-05-14 09:44:24', '0');
INSERT INTO `exam_republish_log` (`id`, `student_id`, `exam_id`, `exam_subject_id`, `republished_by`, `republished_at`, `reason`) VALUES ('39', '7', '1', '7', '22', '2026-07-24 12:53:21', '0');

DROP TABLE IF EXISTS `exam_subjects`;
CREATE TABLE `exam_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `time_limit` int(11) DEFAULT 60,
  `show_calculator` tinyint(1) DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exam_subject_class` (`exam_id`,`subject_id`,`class_id`),
  KEY `subject_id` (`subject_id`),
  KEY `class_id` (`class_id`),
  KEY `idx_exam_subjects_exam_id` (`exam_id`),
  CONSTRAINT `exam_subjects_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_subjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_subjects_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `exam_subjects` (`id`, `exam_id`, `subject_id`, `class_id`, `created_at`, `time_limit`, `show_calculator`, `is_published`, `published_at`) VALUES ('4', '1', '6', '2', '2025-10-28 10:45:49', '1', '0', '1', '2025-11-20 18:06:04');
INSERT INTO `exam_subjects` (`id`, `exam_id`, `subject_id`, `class_id`, `created_at`, `time_limit`, `show_calculator`, `is_published`, `published_at`) VALUES ('6', '1', '11', '1', '2025-11-04 17:22:45', '50', '1', '1', '2025-11-20 18:06:01');
INSERT INTO `exam_subjects` (`id`, `exam_id`, `subject_id`, `class_id`, `created_at`, `time_limit`, `show_calculator`, `is_published`, `published_at`) VALUES ('7', '1', '10', '2', '2025-11-24 10:25:01', '1', '0', '1', '2025-11-24 10:25:07');
INSERT INTO `exam_subjects` (`id`, `exam_id`, `subject_id`, `class_id`, `created_at`, `time_limit`, `show_calculator`, `is_published`, `published_at`) VALUES ('8', '2', '5', '1', '2026-01-22 14:47:39', '60', '0', '0', NULL);
INSERT INTO `exam_subjects` (`id`, `exam_id`, `subject_id`, `class_id`, `created_at`, `time_limit`, `show_calculator`, `is_published`, `published_at`) VALUES ('9', '2', '7', '1', '2026-01-22 14:53:18', '60', '0', '0', NULL);
INSERT INTO `exam_subjects` (`id`, `exam_id`, `subject_id`, `class_id`, `created_at`, `time_limit`, `show_calculator`, `is_published`, `published_at`) VALUES ('10', '2', '11', '1', '2026-03-24 07:37:53', '60', '0', '1', '2026-03-24 07:38:01');
INSERT INTO `exam_subjects` (`id`, `exam_id`, `subject_id`, `class_id`, `created_at`, `time_limit`, `show_calculator`, `is_published`, `published_at`) VALUES ('11', '1', '7', '1', '2026-04-17 06:27:21', '3', '0', '1', '2026-04-17 06:28:10');
INSERT INTO `exam_subjects` (`id`, `exam_id`, `subject_id`, `class_id`, `created_at`, `time_limit`, `show_calculator`, `is_published`, `published_at`) VALUES ('12', '4', '15', '3', '2026-07-24 09:22:00', '60', '1', '1', '2026-07-24 09:22:09');
INSERT INTO `exam_subjects` (`id`, `exam_id`, `subject_id`, `class_id`, `created_at`, `time_limit`, `show_calculator`, `is_published`, `published_at`) VALUES ('13', '5', '16', '7', '2026-07-26 20:07:23', '20', '0', '1', '2026-07-26 20:07:41');
INSERT INTO `exam_subjects` (`id`, `exam_id`, `subject_id`, `class_id`, `created_at`, `time_limit`, `show_calculator`, `is_published`, `published_at`) VALUES ('14', '5', '17', '7', '2026-07-26 20:07:33', '20', '0', '1', '2026-07-26 20:07:46');

DROP TABLE IF EXISTS `exam_subjects_multimap`;
CREATE TABLE `exam_subjects_multimap` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `time_limit` int(11) DEFAULT 60,
  `question_count` int(11) DEFAULT NULL,
  `order_position` int(11) DEFAULT 0,
  `is_published` tinyint(1) DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_exam_subject_class` (`exam_id`,`subject_id`,`class_id`),
  KEY `exam_id` (`exam_id`),
  KEY `subject_id` (`subject_id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `exam_subjects_multimap_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_subjects_multimap_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_subjects_multimap_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `exam_subjects_multimap` (`id`, `exam_id`, `subject_id`, `class_id`, `time_limit`, `question_count`, `order_position`, `is_published`, `published_at`, `created_at`) VALUES ('1', '2', '7', '1', '60', '20', '1', '1', NULL, '2026-01-20 21:18:47');
INSERT INTO `exam_subjects_multimap` (`id`, `exam_id`, `subject_id`, `class_id`, `time_limit`, `question_count`, `order_position`, `is_published`, `published_at`, `created_at`) VALUES ('2', '2', '9', '1', '60', '40', '0', '1', NULL, '2026-01-20 21:19:10');
INSERT INTO `exam_subjects_multimap` (`id`, `exam_id`, `subject_id`, `class_id`, `time_limit`, `question_count`, `order_position`, `is_published`, `published_at`, `created_at`) VALUES ('3', '2', '5', '1', '60', '20', '3', '1', NULL, '2026-01-20 21:20:14');

DROP TABLE IF EXISTS `exams`;
CREATE TABLE `exams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `class_id` int(11) DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_multi_subject` tinyint(1) DEFAULT 0,
  `total_time_limit` int(11) DEFAULT NULL,
  `uses_access_code` tinyint(1) DEFAULT 0,
  `show_calculator` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `fk_exams_class` (`class_id`),
  KEY `idx_exams_title` (`title`),
  CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_exams_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `exams` (`id`, `title`, `description`, `is_published`, `class_id`, `published_at`, `created_by`, `created_at`, `is_multi_subject`, `total_time_limit`, `uses_access_code`, `show_calculator`) VALUES ('1', 'FIRST TERM EXAMINATION', 'First term examination for all classes', '1', NULL, '2025-10-28 14:36:40', '1', '2025-10-28 10:13:05', '0', NULL, '0', '1');
INSERT INTO `exams` (`id`, `title`, `description`, `is_published`, `class_id`, `published_at`, `created_by`, `created_at`, `is_multi_subject`, `total_time_limit`, `uses_access_code`, `show_calculator`) VALUES ('2', 'SS3 MOCK EXAM', 'WEEKLY MOCK EXAM FOR SS3', '1', NULL, '2026-01-22 20:44:34', '1', '2026-01-20 21:17:46', '1', '20', '1', '0');
INSERT INTO `exams` (`id`, `title`, `description`, `is_published`, `class_id`, `published_at`, `created_by`, `created_at`, `is_multi_subject`, `total_time_limit`, `uses_access_code`, `show_calculator`) VALUES ('4', 'ENTRANCE EXAMINATION', 'School Entrance Examination', '1', NULL, '2026-07-24 09:42:42', '1', '2026-07-24 09:21:43', '0', NULL, '0', '0');
INSERT INTO `exams` (`id`, `title`, `description`, `is_published`, `class_id`, `published_at`, `created_by`, `created_at`, `is_multi_subject`, `total_time_limit`, `uses_access_code`, `show_calculator`) VALUES ('5', '2026/2027 Teacher Interview', '2026/2027 Teacher Interview', '1', NULL, '2026-07-26 20:07:37', '22', '2026-07-26 20:07:09', '0', NULL, '0', '0');

DROP TABLE IF EXISTS `grading_scale`;
CREATE TABLE `grading_scale` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `min_score` int(11) NOT NULL,
  `max_score` int(11) NOT NULL,
  `grade` varchar(5) NOT NULL,
  `remark` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `grading_scale` (`id`, `min_score`, `max_score`, `grade`, `remark`) VALUES ('1', '70', '100', 'A', 'Excellent');
INSERT INTO `grading_scale` (`id`, `min_score`, `max_score`, `grade`, `remark`) VALUES ('2', '60', '69', 'B', 'Very Good');
INSERT INTO `grading_scale` (`id`, `min_score`, `max_score`, `grade`, `remark`) VALUES ('3', '50', '59', 'C', 'Good');
INSERT INTO `grading_scale` (`id`, `min_score`, `max_score`, `grade`, `remark`) VALUES ('4', '45', '49', 'D', 'Fair');
INSERT INTO `grading_scale` (`id`, `min_score`, `max_score`, `grade`, `remark`) VALUES ('5', '40', '44', 'E', 'Pass');
INSERT INTO `grading_scale` (`id`, `min_score`, `max_score`, `grade`, `remark`) VALUES ('6', '0', '39', 'F', 'Fail');
INSERT INTO `grading_scale` (`id`, `min_score`, `max_score`, `grade`, `remark`) VALUES ('7', '70', '100', 'A', 'Excellent');
INSERT INTO `grading_scale` (`id`, `min_score`, `max_score`, `grade`, `remark`) VALUES ('8', '60', '69', 'B', 'Very Good');
INSERT INTO `grading_scale` (`id`, `min_score`, `max_score`, `grade`, `remark`) VALUES ('9', '50', '59', 'C', 'Good');
INSERT INTO `grading_scale` (`id`, `min_score`, `max_score`, `grade`, `remark`) VALUES ('10', '45', '49', 'D', 'Fair');
INSERT INTO `grading_scale` (`id`, `min_score`, `max_score`, `grade`, `remark`) VALUES ('11', '40', '44', 'E', 'Pass');
INSERT INTO `grading_scale` (`id`, `min_score`, `max_score`, `grade`, `remark`) VALUES ('12', '0', '39', 'F', 'Fail');
INSERT INTO `grading_scale` (`id`, `min_score`, `max_score`, `grade`, `remark`) VALUES ('13', '70', '100', 'A', 'Excellent');
INSERT INTO `grading_scale` (`id`, `min_score`, `max_score`, `grade`, `remark`) VALUES ('14', '60', '69', 'B', 'Very Good');
INSERT INTO `grading_scale` (`id`, `min_score`, `max_score`, `grade`, `remark`) VALUES ('15', '50', '59', 'C', 'Good');
INSERT INTO `grading_scale` (`id`, `min_score`, `max_score`, `grade`, `remark`) VALUES ('16', '45', '49', 'D', 'Fair');
INSERT INTO `grading_scale` (`id`, `min_score`, `max_score`, `grade`, `remark`) VALUES ('17', '40', '44', 'E', 'Pass');
INSERT INTO `grading_scale` (`id`, `min_score`, `max_score`, `grade`, `remark`) VALUES ('18', '0', '39', 'F', 'Fail');

DROP TABLE IF EXISTS `multi_subject_exam_results`;
CREATE TABLE `multi_subject_exam_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `score` int(11) DEFAULT NULL,
  `total_questions` int(11) NOT NULL,
  `correct_answers` int(11) DEFAULT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `time_spent` int(11) DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attempt_subject` (`attempt_id`,`subject_id`),
  KEY `exam_id` (`exam_id`),
  KEY `student_id` (`student_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `multi_subject_results_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `multi_subject_results_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `multi_subject_results_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `multi_subject_results_ibfk_4` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `passkeys`;
CREATE TABLE `passkeys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `passkey` varchar(255) NOT NULL,
  `student_email` varchar(255) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `exam_subject_id` int(11) DEFAULT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `usage_limit` int(11) NOT NULL DEFAULT 1,
  `usage_count` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `passkey` (`passkey`),
  KEY `exam_id` (`exam_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_exam_subject_id` (`exam_subject_id`),
  CONSTRAINT `fk_passkey_exam_subject` FOREIGN KEY (`exam_subject_id`) REFERENCES `exam_subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `passkeys_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `passkeys_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `passkeys` (`id`, `passkey`, `student_email`, `exam_id`, `exam_subject_id`, `is_used`, `expires_at`, `usage_limit`, `usage_count`, `created_by`, `created_at`, `used_at`) VALUES ('18', 'WBXEDVAL', 'abdulrahman@gmail.com', '1', '6', '0', NULL, '1', '0', '1', '2025-11-04 19:57:21', NULL);
INSERT INTO `passkeys` (`id`, `passkey`, `student_email`, `exam_id`, `exam_subject_id`, `is_used`, `expires_at`, `usage_limit`, `usage_count`, `created_by`, `created_at`, `used_at`) VALUES ('19', '2KNR9G57', 'wasiugold@gmail.com', '1', '4', '0', NULL, '2', '2', '1', '2025-11-04 20:12:09', NULL);
INSERT INTO `passkeys` (`id`, `passkey`, `student_email`, `exam_id`, `exam_subject_id`, `is_used`, `expires_at`, `usage_limit`, `usage_count`, `created_by`, `created_at`, `used_at`) VALUES ('20', '1MKZ3YHO', 'wasiugold@gmail.com', '1', '4', '0', '0000-00-00 00:00:00', '2', '1', '1', '2025-11-05 16:57:35', NULL);

DROP TABLE IF EXISTS `questions`;
CREATE TABLE `questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_subject_id` int(11) NOT NULL,
  `order_position` int(11) DEFAULT NULL,
  `question_type` enum('objective','theory') NOT NULL DEFAULT 'objective',
  `question_text` text NOT NULL,
  `option_a` text DEFAULT NULL,
  `option_b` text DEFAULT NULL,
  `option_c` text DEFAULT NULL,
  `option_d` text DEFAULT NULL,
  `option_e` text DEFAULT NULL,
  `option_f` text DEFAULT NULL,
  `correct_answer` enum('A','B','C','D','E','F') DEFAULT NULL,
  `theory_marks` decimal(6,2) DEFAULT NULL,
  `marking_scheme` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `exam_subject_id` (`exam_subject_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_questions_order_position` (`exam_subject_id`,`order_position`),
  CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`exam_subject_id`) REFERENCES `exam_subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `questions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('454', '8', NULL, 'objective', 'What is the powerhouse of the cell?', 'Nucleus', 'Mitochondria', 'Ribosome', 'Endoplasmic reticulum', NULL, NULL, 'B', NULL, NULL, '2', '2026-01-22 14:47:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('455', '8', NULL, 'objective', 'Who was the first President of the United States?', 'Thomas Jefferson', 'Abraham Lincoln', 'George Washington', 'John Adams', NULL, NULL, 'C', NULL, NULL, '2', '2026-01-22 14:47:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('456', '8', NULL, 'objective', 'What is the capital of Japan?', 'Seoul', 'Beijing', 'Tokyo', 'Bangkok', NULL, NULL, 'C', NULL, NULL, '2', '2026-01-22 14:47:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('457', '8', NULL, 'objective', 'Who wrote \"Pride and Prejudice\"?', 'Charlotte Brontë', 'Jane Austen', 'Emily Brontë', 'Charles Dickens', NULL, NULL, 'B', NULL, NULL, '2', '2026-01-22 14:47:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('458', '8', NULL, 'objective', 'What is the value of π (pi) approximately?', '3.14', '2.71', '1.62', '3.16', NULL, NULL, 'A', NULL, NULL, '2', '2026-01-22 14:47:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('460', '9', NULL, 'objective', 'What is the powerhouse of the cell?', 'Nucleus', 'Mitochondria', 'Ribosome', 'Endoplasmic reticulum', NULL, NULL, 'B', NULL, NULL, '2', '2026-01-22 14:53:18');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('461', '9', NULL, 'objective', 'Who was the first President of the United States?', 'Thomas Jefferson', 'Abraham Lincoln', 'George Washington', 'John Adams', NULL, NULL, 'C', NULL, NULL, '2', '2026-01-22 14:53:18');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('462', '9', NULL, 'objective', 'What is the capital of Japan?', 'Seoul', 'Beijing', 'Tokyo', 'Bangkok', NULL, NULL, 'C', NULL, NULL, '2', '2026-01-22 14:53:18');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('463', '9', NULL, 'objective', 'Who wrote \"Pride and Prejudice\"?', 'Charlotte Brontë', 'Jane Austen', 'Emily Brontë', 'Charles Dickens', NULL, NULL, 'B', NULL, NULL, '2', '2026-01-22 14:53:18');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('464', '9', NULL, 'objective', 'What is the value of π (pi) approximately?', '3.14', '2.71', '1.62', '3.16', NULL, NULL, 'A', NULL, NULL, '2', '2026-01-22 14:53:18');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('465', '10', '1', 'objective', 'What is your name?', 'Ahmad', 'Me', 'Man', 'Heel', NULL, NULL, 'A', NULL, NULL, '6', '2026-03-24 07:45:44');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('466', '10', '2', 'objective', 'What is in column B row 2', '5', '6', '7', '8', '9', '10', 'A', NULL, NULL, '6', '2026-03-24 10:05:48');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('467', '10', '3', 'objective', 'What is in column B row 2', '5', '6', '7', '8', '9', '10', 'A', NULL, NULL, '6', '2026-03-24 10:06:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('471', '11', '0', 'objective', 'What is the primary purpose of government in Nigeria?', 'To make profit', 'To provide services and ensure security', 'To promote only one religion', 'To control only the economy', NULL, NULL, 'B', NULL, NULL, '2', '2026-04-17 06:30:27');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('472', '11', '1', 'objective', 'Which of the following is a fundamental human right?', 'Right to fight', 'Right to vote', 'Right to cheat', 'Right to disobey laws', NULL, NULL, 'B', NULL, NULL, '2', '2026-04-17 06:30:27');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('473', '11', '2', 'objective', 'Who is the head of the executive arm of government in Nigeria?', 'Chief Justice', 'Senate President', 'President', 'Speaker of the House', NULL, NULL, 'C', NULL, NULL, '2', '2026-04-17 06:30:27');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('474', '11', '3', 'objective', 'What does the rule of law mean?', 'Leaders are above the law', 'Only the rich obey the law', 'Everyone is subject to the law', 'Laws are not important', NULL, NULL, 'C', NULL, NULL, '2', '2026-04-17 06:30:27');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('475', '11', '4', 'objective', 'Which of the following is a civic responsibility of a citizen?', 'Destroying public property', 'Paying taxes', 'Fighting in public places', 'Ignoring national symbols', NULL, NULL, 'B', NULL, NULL, '2', '2026-04-17 06:30:27');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('476', '4', '0', 'objective', 'What is your name?', 'Abiola', 'Me', 'You', 'I', NULL, NULL, 'A', NULL, NULL, '2', '2026-04-30 14:55:52');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('477', '4', '1', 'objective', 'How old are you?', '12', '13', '14', '15', NULL, NULL, 'C', NULL, NULL, '2', '2026-04-30 14:55:52');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('478', '4', '2', 'objective', 'What is the name of your schoool?', 'FARM', 'FAMAIIS', 'IBM', 'MBI', NULL, NULL, 'B', NULL, NULL, '2', '2026-04-30 14:55:52');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('882', '6', '0', 'objective', '<math xmlns=\"http://www.w3.org/1998/Math/MathML\" display=\"inline\"><msup><mrow><mrow><mo>(</mo><mrow><mrow><mtext>x+a</mtext></mrow></mrow><mo>)</mo></mrow></mrow><mrow><mrow><mi>n</mi></mrow></mrow></msup><mrow><mo>=</mo></mrow><munderover><mo>∑</mo><mrow><mrow><mtext>k=0</mtext></mrow></mrow><mrow><mrow><mi>n</mi></mrow></mrow></munderover><mrow><mrow><mo>(</mo><mrow><mfrac><mrow><mrow><mi>n</mi></mrow></mrow><mrow><mrow><mi>k</mi></mrow></mrow></mfrac></mrow><mo>)</mo></mrow><msup><mrow><mrow><mi>x</mi></mrow></mrow><mrow><mrow><mi>k</mi></mrow></mrow></msup><msup><mrow><mrow><mi>a</mi></mrow></mrow><mrow><mrow><mtext>n-k</mtext></mrow></mrow></msup></mrow></math>', '<math xmlns=\"http://www.w3.org/1998/Math/MathML\" display=\"inline\"><mfrac><mrow><mrow><mn>1</mn></mrow></mrow><mrow><mrow><mn>4</mn></mrow></mrow></mfrac></math>', '<math xmlns=\"http://www.w3.org/1998/Math/MathML\" display=\"inline\"><msup><mrow><mrow><mn>4</mn></mrow></mrow><mrow><mrow><mn>2</mn></mrow></mrow></msup></math>', '<math xmlns=\"http://www.w3.org/1998/Math/MathML\" display=\"inline\"><msqrt><mrow><mrow><mn>4</mn></mrow></mrow></msqrt></math>', '<math xmlns=\"http://www.w3.org/1998/Math/MathML\" display=\"inline\"><munderover><mo>∑</mo><mrow></mrow><mrow></mrow></munderover><mrow><mrow><mn>5</mn></mrow></mrow></math>', NULL, NULL, 'D', NULL, NULL, '6', '2026-06-18 11:50:36');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('883', '12', '0', 'objective', 'Which of the following words contains the /θ/ sound?', 'Then', 'Those', 'Think', 'This', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('884', '12', '1', 'objective', 'Which of the following words contains the /ð/ sound?', 'Thank', 'Bath', 'Thin', 'Those', NULL, NULL, 'D', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('885', '12', '2', 'objective', 'Choose the correct pair of words that contrasts the /s/ and /ʃ/ sounds.', 'Save – Shave', 'Pin – Bin', 'Think – Then', 'Purse – Bus', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('886', '12', '3', 'objective', 'Which sentence expresses permission?', 'You may enter the classroom.', 'You must finish your work.', 'You should study harder.', 'You can swim very well.', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('887', '12', '4', 'objective', 'Which modal verb expresses obligation?', 'Can', 'Must', 'May', 'Could', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('888', '12', '5', 'objective', 'Which modal verb expresses possibility?', 'Might', 'Must', 'Shall', 'Will', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('889', '12', '6', 'objective', 'Which modal verb expresses ability?', 'May', 'Can', 'Must', 'Ought to', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('890', '12', '7', 'objective', 'Identify the active voice sentence.', '', 'The letter was written by Amin', 'The teacher praised the students.', 'The food was cooked by my mother.', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('891', '12', '8', 'objective', 'Identify the passive voice sentence.', '', 'The room was cleaned by Fatim', 'Ali kicked the ball.', 'Musa opened the gat', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('892', '12', '9', 'objective', 'Which of the following is a fact?', '', 'Mathematics is the easiest subject.', 'Football is the best gam', 'Rice tastes better than yam.', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('893', '12', '10', 'objective', 'Which of the following is an opinion?', '', 'Lagos is in Nigeri', 'English is the most interesting subject.', 'The earth revolves round the sun.', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('894', '12', '11', 'objective', 'A descriptive essay mainly describes ________.', 'a person, place or thing', 'a mathematical formula', 'a play only', 'an examination', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('895', '12', '12', 'objective', 'Which of the following is an expository essay topic?', 'How to Prepare Garri', 'My Favourite Friend', 'My Birthday Party', 'My School Uniform', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('896', '12', '13', 'objective', 'A farewell speech is delivered ________.', 'to welcome visitors', 'at the end of a programme or departure', 'during an examination', 'before a debate', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('897', '12', '14', 'objective', 'Summary writing requires the writer to ________.', 'copy the whole passage', 'shorten the passage using the main points', 'write another story', 'add personal opinions', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('898', '12', '15', 'objective', 'A diary is mainly used for ________.', 'recording daily events', 'writing poems only', 'writing letters', 'answering examination questions', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('899', '12', '16', 'objective', 'Which of the following is an adverbial of restriction?', 'Only', 'Quickly', 'Yesterday', 'Outside', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('900', '12', '17', 'objective', 'Which punctuation mark ends an interrogative sentence?', '.', '!', '?', ',', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('901', '12', '18', 'objective', 'A poem is usually written in ________.', 'paragraphs only', 'verses and stanzas', 'chapters only', 'tables', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('902', '12', '19', 'objective', 'The setting of a drama refers to ________.', 'the title of the play', 'where and when the story takes place', 'the writer\'s name', 'the ending only', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('903', '12', '20', 'objective', 'The plot of a drama is ________.', 'the sequence of events', 'the list of characters', 'the cover page', 'the language used', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('904', '12', '21', 'objective', 'The main people in a play are called ________.', 'chapters', 'characters', 'paragraphs', 'themes', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('905', '12', '22', 'objective', 'Rising intonation is commonly used in ________.', 'statements', 'questions requiring yes or no answers', 'commands', 'announcements', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('906', '12', '23', 'objective', 'Which pair of words contrasts the /p/ and /b/ sounds?', 'Peace – Beast', 'Purse – Bus', 'Thin – Then', 'Save – Shave', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('907', '12', '24', 'objective', 'Which sentence correctly uses a modal of ability?', 'She can solve difficult mathematics problems.', 'She must solve difficult mathematics problems.', 'She may solve difficult mathematics problems.', 'She ought to solve difficult mathematics problems.', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('908', '12', '25', 'objective', 'Find the mean of the data: 8, 10, 12, 14, 16.', '10', '11', '12', '13', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('909', '12', '26', 'objective', 'Find the median of the data: 5, 9, 3, 7, 1.', '3', '5', '7', '9', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('910', '12', '27', 'objective', 'Find the mode of the data: 4, 5, 4, 6, 7, 4, 8.', '5', '6', '4', '8', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('911', '12', '28', 'objective', 'Find the range of the data: 12, 18, 25, 10, 30.', '18', '20', '25', '30', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('912', '12', '29', 'objective', 'The table below shows the scores of students.\nScore   Frequency\n2       3\n4       5\n6       2\nFind the total frequency.', '8', '9', '10', '11', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('913', '12', '30', 'objective', 'If y varies directly as x and y = 24 when x = 6, find y when x = 10.', '36', '38', '40', '42', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('914', '12', '31', 'objective', 'If y varies inversely as x and y = 8 when x = 3, find y when x = 6.', '2', '3', '4', '5', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('915', '12', '32', 'objective', 'If P varies jointly as m and n, and P = 24 when m = 2 and n = 3, find P when m = 4 and n = 5.', '60', '70', '80', '90', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('916', '12', '33', 'objective', 'Round 17.8465 correct to 2 decimal places.', '17.84', '17.85', '17.86', '17.87', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('917', '12', '34', 'objective', 'Round 0.006784 to 2 significant figures.', '0.0067', '0.0068', '0.0069', '0.0070', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('918', '12', '35', 'objective', 'Evaluate (2/3) + (5/6).', '7/6', '3/2', '5/4', '13/6', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('919', '12', '36', 'objective', 'Evaluate (7/8) - (1/4).', '3/8', '5/8', '7/8', '1/2', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('920', '12', '37', 'objective', 'Evaluate (3/5) × (10/9).', '2/3', '3/2', '5/3', '10/15', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('921', '12', '38', 'objective', 'Evaluate (4/7) ÷ (2/3).', '6/7', '7/6', '8/21', '12/14', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('922', '12', '39', 'objective', 'Evaluate 1/2 + (3/4 × 2/3).', '3/4', '1', '5/4', '3/2', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('923', '12', '40', 'objective', 'Simplify 12x/3.', '3x', '4x', '6x', '9x', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('924', '12', '41', 'objective', 'A right-angled triangle has sides 6 cm, 8 cm and x cm. Find x.', '9', '10', '11', '12', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('925', '12', '42', 'objective', 'Find the hypotenuse of a right-angled triangle whose other sides are 5 cm and 12 cm.', '11', '12', '13', '15', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('926', '12', '43', 'objective', 'Which is a Pythagorean triple?', '2,3,4', '5,12,13', '4,5,8', '7,8,10', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('927', '12', '44', 'objective', 'Opposite = 3 cm, Hypotenuse = 5 cm. Find sin θ.', '3/5', '4/5', '5/3', '3/4', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('928', '12', '45', 'objective', 'Adjacent = 4 cm, Hypotenuse = 5 cm. Find cos θ.', '3/5', '4/5', '5/4', '4/3', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('929', '12', '46', 'objective', 'Opposite = 12 cm, Adjacent = 5 cm. Find tan θ.', '5/12', '12/5', '13/5', '5/13', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('930', '12', '47', 'objective', 'If the frequencies are 5, 8, 10 and 7, find the total frequency.', '28', '29', '30', '31', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('931', '12', '48', 'objective', 'Score/Frequency: (2,1), (4,2), (6,3). Find the mean.', '4.00', '4.33', '4.50', '4.67', NULL, NULL, 'D', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('932', '12', '49', 'objective', 'A pie chart is 360°. A sector is 90°. What fraction is it?', '1/4', '1/3', '1/2', '3/4', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-24 09:39:51');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('933', '7', '1', 'theory', '<p>Write about yourself?</p>', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '10.00', '<p>Write about yourself</p>', '22', '2026-07-24 12:51:17');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('934', '7', '2', 'objective', '<p>What is your name</p>', '<p>Ahmad</p>', '<p>Abiola</p>', '<p>Fatai</p>', '<p>Fat</p>', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-24 12:52:07');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('935', '13', '0', 'objective', 'كم عدد سور القرآن الكريم؟', '100', '114', '120', '99', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('936', '13', '1', 'objective', 'ما هي أول سورة نزلت من القرآن الكريم؟', 'سورة الفاتحة', 'سورة العلق', 'سورة البقرة', 'سورة المدثر', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('937', '13', '2', 'objective', 'ما هي أطول سورة في القرآن الكريم؟', 'سورة آل عمران', 'سورة النساء', 'سورة البقرة', 'سورة يونس', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('938', '13', '3', 'objective', 'ما هي أقصر سورة في القرآن الكريم؟', 'سورة الكوثر', 'سورة الإخلاص', 'سورة العصر', 'سورة الناس', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('939', '13', '4', 'objective', 'كم عدد أجزاء القرآن الكريم؟', '20', '25', '30', '35', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('940', '13', '5', 'objective', 'في أي شهر بدأ نزول القرآن الكريم؟', 'شعبان', 'رمضان', 'شوال', 'رجب', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('941', '13', '6', 'objective', 'ما هي السورة الوحيدة التي لا تبدأ بالبسملة؟', 'سورة التوبة', 'سورة يس', 'سورة الملك', 'سورة الرحمن', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('942', '13', '7', 'objective', 'أين نزل القرآن الكريم لأول مرة على النبي صلى الله عليه وسلم؟', 'المسجد الأقصى', 'غار حراء', 'غار ثور', 'المسجد الحرام', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('943', '13', '8', 'objective', 'كم عدد آيات سورة الفاتحة؟', '5', '6', '7', '8', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('944', '13', '9', 'objective', 'ما اسم السورة التي تسمى \'قلب القرآن\'؟', 'سورة الملك', 'سورة يس', 'سورة الرحمن', 'سورة الكهف', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('945', '13', '10', 'objective', 'من هو مؤلف كتاب \'صحيح البخاري\'؟', 'الإمام مسلم', 'الإمام البخاري', 'الإمام مالك', 'الإمام أحمد', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('946', '13', '11', 'objective', 'ما هو أصح كتاب بعد القرآن الكريم عند أهل السنة؟', 'سنن الترمذي', 'صحيح مسلم', 'صحيح البخاري', 'سنن أبي داود', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('947', '13', '12', 'objective', 'من هو مؤلف كتاب \'صحيح مسلم\'؟', 'مسلم بن الحجاج', 'البخاري', 'الترمذي', 'النسائي', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('948', '13', '13', 'objective', 'من هو راوي حديث \'إنما الأعمال بالنيات\'؟', 'أبو هريرة', 'عمر بن الخطاب', 'علي بن أبي طالب', 'عبد الله بن عمر', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('949', '13', '14', 'objective', 'كم عدد كتب الحديث المعروفة بـ \'الكتب الستة\'؟', 'أربعة', 'خمسة', 'ستة', 'سبعة', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('950', '13', '15', 'objective', 'من هو الصحابي الذي اشتهر بكثرة رواية الحديث عن النبي صلى الله عليه وسلم؟', 'أبو هريرة', 'أبو بكر الصديق', 'بلال بن رباح', 'سعد بن أبي وقاص', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('951', '13', '16', 'objective', 'من هو مؤلف كتاب \'الموطأ\' وهو من أوائل كتب الحديث؟', 'الشافعي', 'مالك بن أنس', 'أحمد بن حنبل', 'ابن ماجه', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('952', '13', '17', 'objective', 'من هو مؤلف كتاب \'سنن أبي داود\'؟', 'أبو داود السجستاني', 'الترمذي', 'النسائي', 'ابن ماجه', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('953', '13', '18', 'objective', 'الحديث القدسي هو كلام:', 'من قول الصحابة', 'من الله تعالى بلفظ ومعنى من النبي صلى الله عليه وسلم', 'من التابعين', 'من علماء الحديث', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('954', '13', '19', 'objective', 'ما معنى \'الحديث الصحيح\'؟', 'حديث ضعيف السند', 'حديث موضوع ومكذوب', 'حديث اتصل سنده بنقل عدل ضابط من غير شذوذ ولا علة', 'حديث لا يُعرف راويه', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('955', '13', '20', 'objective', 'من هو أول الأنبياء والرسل؟', 'نوح عليه السلام', 'آدم عليه السلام', 'إبراهيم عليه السلام', 'إدريس عليه السلام', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('956', '13', '21', 'objective', 'من هو النبي الذي التقمه الحوت؟', 'يونس عليه السلام', 'يوسف عليه السلام', 'أيوب عليه السلام', 'صالح عليه السلام', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('957', '13', '22', 'objective', 'من هو النبي الذي أمره الله ببناء السفينة؟', 'هود عليه السلام', 'نوح عليه السلام', 'لوط عليه السلام', 'شعيب عليه السلام', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('958', '13', '23', 'objective', 'من هو النبي الذي كلّمه الله تعالى مباشرة (تكليماً)؟', 'موسى عليه السلام', 'عيسى عليه السلام', 'داود عليه السلام', 'سليمان عليه السلام', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('959', '13', '24', 'objective', 'من هو النبي الذي وُلد من غير أب؟', 'إسماعيل عليه السلام', 'عيسى عليه السلام', 'إسحاق عليه السلام', 'يحيى عليه السلام', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('960', '13', '25', 'objective', 'من هما النبيّان اللذان بنيا الكعبة المشرفة؟', 'موسى وهارون عليهما السلام', 'نوح وابنه عليهما السلام', 'إبراهيم وإسماعيل عليهما السلام', 'داود وسليمان عليهما السلام', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('961', '13', '26', 'objective', 'من هو النبي الذي أُلقي في النار فجعلها الله عليه برداً وسلاماً؟', 'إبراهيم عليه السلام', 'موسى عليه السلام', 'لوط عليه السلام', 'يوسف عليه السلام', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('962', '13', '27', 'objective', 'من هو النبي الذي علّمه الله صنعة الدروع؟', 'سليمان عليه السلام', 'داود عليه السلام', 'زكريا عليه السلام', 'أيوب عليه السلام', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('963', '13', '28', 'objective', 'من هو النبي الذي ابتُلي بفقد البصر والأبناء والمال فصبر حتى شفاه الله؟', 'أيوب عليه السلام', 'يعقوب عليه السلام', 'زكريا عليه السلام', 'يونس عليه السلام', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('964', '13', '29', 'objective', 'في أي مدينة وُلد النبي محمد صلى الله عليه وسلم؟', 'المدينة المنورة', 'مكة المكرمة', 'الطائف', 'القدس', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:08:39');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('965', '14', '0', 'objective', 'Simplify: 7 + 3 × (5 − 2)', '24', '30', '16', '21', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('966', '14', '1', 'objective', 'Find the Lowest Common Multiple (LCM) of 12 and 18.', '24', '36', '54', '72', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('967', '14', '2', 'objective', 'What is 0.75 expressed as a fraction in its lowest term?', '3/4', '7/10', '3/5', '5/8', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('968', '14', '3', 'objective', 'Find the average of 4, 8, 15, and 16.', '10.75', '11', '10.5', '12', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('969', '14', '4', 'objective', 'Solve for x: 2x + 5 = 17', '5', '6', '7', '8', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('970', '14', '5', 'objective', 'What is the next number in the sequence: 2, 4, 8, 16, ___?', '18', '24', '32', '20', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('971', '14', '6', 'objective', 'A trader bought an item for ₦2,000 and sold it for ₦2,500. What is the percentage profit?', '20%', '25%', '30%', '15%', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('972', '14', '7', 'objective', 'What is the area of a rectangle with length 8cm and breadth 5cm?', '13 cm²', '40 cm²', '45 cm²', '30 cm²', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('973', '14', '8', 'objective', 'Choose the word that is the correct synonym of \'ABUNDANT\'.', 'Scarce', 'Plentiful', 'Limited', 'Rare', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('974', '14', '9', 'objective', 'Choose the word that is the correct antonym of \'GENEROUS\'.', 'Kind', 'Selfish', 'Wealthy', 'Caring', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('975', '14', '10', 'objective', 'Identify the correctly spelt word.', 'Necessery', 'Neccessary', 'Necessary', 'Nessecary', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('976', '14', '11', 'objective', 'Choose the option that best completes the sentence: “Neither the teacher nor the students ___      ready for the test.”', 'was', 'is', 'were', 'am', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('977', '14', '12', 'objective', 'In the sentence \'She sings beautifully\', the word \'beautifully\' is a/an ___', 'Noun', 'Adjective', 'Adverb', 'Pronoun', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('978', '14', '13', 'objective', 'Which punctuation mark is used to separate items in a list?', 'Semi-colon', 'Comma', 'Colon', 'Apostrophe', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('979', '14', '14', 'objective', '\'The classroom was as silent as a graveyard\' is an example of a ___.', 'Metaphor', 'Simile', 'Personification', 'Hyperbole', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('980', '14', '15', 'objective', 'In what year did Nigeria gain independence from British colonial rule?', '1957', '1963', '1960', '1966', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('981', '14', '16', 'objective', 'Who was Nigeria\'s first Prime Minister?', 'Nnamdi Azikiwe', 'Abubakar Tafawa Balewa', 'Obafemi Awolowo', 'Ahmadu Bello', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('982', '14', '17', 'objective', 'The amalgamation of the Northern and Southern Protectorates of Nigeria took place in what year?', '1900', '1914', '1922', '1960', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('983', '14', '18', 'objective', 'Who was Nigeria\'s first President?', 'Yakubu Gowon', 'Nnamdi Azikiwe', 'Shehu Shagari', 'Olusegun Obasanjo', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('984', '14', '19', 'objective', 'The Nigerian Civil War lasted from which years?', '1966–1969', '1967–1970', '1970–1973', '1965–1968', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('985', '14', '20', 'objective', 'How many states currently make up the Federal Republic of Nigeria?', '30', '33', '36', '37', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('986', '14', '21', 'objective', 'Nigeria\'s first military coup took place in what year?', '1963', '1966', '1970', '1975', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('987', '14', '22', 'objective', 'What is the current capital city of Nigeria?', 'Lagos', 'Kano', 'Abuja', 'Port Harcourt', NULL, NULL, 'C', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('988', '14', '23', 'objective', 'Nigeria\'s National Policy on Education was first published in what year?', '1969', '1977', '1981', '1999', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('989', '14', '24', 'objective', 'What does the acronym \'UBE\' stand for in the Nigerian education system?', 'Universal Basic Education', 'Uniform Basic Examination', 'Universal Board of Education', 'Unified Basic Enrolment', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('990', '14', '25', 'objective', 'Which of the following is NOT one of the domains of Bloom\'s Taxonomy?', 'Cognitive', 'Affective', 'Psychomotor', 'Behavioural', NULL, NULL, 'D', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('991', '14', '26', 'objective', 'Which of the following is an essential component of a good lesson plan?', 'Learning objectives', 'Teacher\'s salary', 'School location', 'Staff attendance record', NULL, NULL, 'A', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('992', '14', '27', 'objective', 'Continuous Assessment (CA) in schools is primarily used to ___.', 'Punish weak students', 'Monitor students\' progress over time', 'Replace final examinations completely', 'Determine teachers\' salaries', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('993', '14', '28', 'objective', 'The curriculum can best be defined as ___.', 'The total number of students in a school', 'A planned set of learning experiences designed to achieve educational goals', 'The school\'s physical infrastructure', 'A list of teachers employed in a school', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:09:33');
INSERT INTO `questions` (`id`, `exam_subject_id`, `order_position`, `question_type`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_answer`, `theory_marks`, `marking_scheme`, `created_by`, `created_at`) VALUES ('994', '14', '29', 'objective', 'Which teaching method involves students actively working together in small groups to solve a problem?', 'Lecture method', 'Cooperative/Group learning method', 'Demonstration method', 'Rote learning method', NULL, NULL, 'B', NULL, NULL, '22', '2026-07-26 20:09:33');

DROP TABLE IF EXISTS `report_comments`;
CREATE TABLE `report_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `term` enum('1st','2nd','3rd') NOT NULL,
  `session` varchar(20) NOT NULL,
  `teacher_comment` text DEFAULT NULL,
  `admin_comment` text DEFAULT NULL,
  `attendance_present` int(11) DEFAULT 0,
  `attendance_total` int(11) DEFAULT 0,
  `next_term_start` date DEFAULT NULL,
  `promotion_status` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_report_term_session` (`student_id`,`term`,`session`),
  CONSTRAINT `report_comments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `school_config`;
CREATE TABLE `school_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_name` varchar(255) NOT NULL DEFAULT 'CBT Exam System',
  `default_time_limit` int(11) DEFAULT 60,
  `logo_path` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `school_config` (`id`, `school_name`, `default_time_limit`, `logo_path`, `updated_at`, `updated_by`) VALUES ('1', 'CBT Exam System', '60', '../uploads/school_logo_1788260922.png', '2026-09-01 12:08:42', '1');

DROP TABLE IF EXISTS `student_answers`;
CREATE TABLE `student_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_answer` enum('A','B','C','D','E','F') DEFAULT NULL,
  `answer_text` longtext DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `awarded_marks` decimal(6,2) DEFAULT NULL,
  `teacher_comment` text DEFAULT NULL,
  `marked_by` int(11) DEFAULT NULL,
  `marked_at` timestamp NULL DEFAULT NULL,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `attempt_question` (`attempt_id`,`question_id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `student_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('203', '49', '465', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-03-24 07:50:21');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('206', '54', '465', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-03-24 10:09:39');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('207', '54', '466', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-03-24 10:09:47');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('208', '54', '467', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-03-24 10:09:58');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('209', '70', '471', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-04-17 07:19:11');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('210', '70', '472', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-04-17 07:19:17');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('211', '70', '473', 'C', NULL, '1', NULL, NULL, NULL, NULL, '2026-04-17 07:19:22');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('212', '70', '474', 'C', NULL, '1', NULL, NULL, NULL, NULL, '2026-04-17 07:19:26');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('213', '70', '475', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-04-17 07:19:33');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('214', '71', '476', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-04-30 14:58:16');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('215', '71', '477', 'C', NULL, '1', NULL, NULL, NULL, NULL, '2026-04-30 14:58:22');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('216', '71', '478', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-04-30 14:58:28');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('288', '97', '884', 'D', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:43:20');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('289', '97', '914', 'B', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:43:52');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('290', '97', '913', 'A', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:44:21');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('291', '97', '887', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:44:41');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('292', '97', '889', 'D', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:44:59');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('293', '97', '890', 'C', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:45:24');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('294', '97', '900', 'B', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:45:50');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('295', '97', '926', 'A', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:46:02');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('296', '97', '891', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:46:21');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('297', '97', '916', 'C', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:46:28');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('298', '97', '908', 'A', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:46:39');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('299', '97', '886', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:46:49');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('300', '97', '904', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:47:02');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('301', '97', '885', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:47:19');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('302', '97', '888', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:47:35');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('303', '97', '915', 'B', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:47:55');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('304', '97', '883', 'B', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:48:07');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('305', '97', '924', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:48:22');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('306', '97', '929', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:48:33');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('307', '97', '898', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:48:46');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('308', '97', '917', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:49:24');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('309', '97', '899', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:49:41');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('310', '97', '921', 'C', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:50:00');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('311', '97', '922', 'C', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:50:17');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('312', '97', '895', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:50:36');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('313', '97', '896', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:51:02');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('314', '97', '894', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:51:31');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('315', '97', '911', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:51:41');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('316', '97', '897', 'A', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:52:17');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('317', '97', '927', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:52:22');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('318', '97', '919', 'A', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:52:31');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('319', '97', '903', 'B', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:52:48');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('320', '97', '910', 'B', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:53:12');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('321', '97', '930', 'A', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:53:16');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('322', '97', '920', 'C', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:53:21');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('323', '97', '918', 'A', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:53:25');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('324', '97', '893', 'C', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:53:47');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('325', '97', '931', 'D', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:53:58');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('326', '97', '932', 'B', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:54:04');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('327', '97', '907', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:54:32');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('328', '97', '902', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:54:52');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('329', '97', '912', 'C', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:55:14');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('330', '97', '909', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:55:23');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('331', '97', '923', 'A', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:55:28');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('332', '97', '901', 'A', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:55:50');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('333', '97', '928', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 09:55:56');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('334', '97', '905', 'D', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:56:17');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('335', '97', '892', 'C', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:56:44');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('336', '97', '906', 'A', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:57:01');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('337', '97', '925', 'B', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-24 09:57:24');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('338', '98', '934', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-24 12:53:39');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('339', '98', '933', NULL, 'My name is Ahmad Abiola. I am 10 years old. I am in Nursery 2.', NULL, '10.00', 'Good Question', '22', '2026-07-24 12:55:33', '2026-07-24 12:54:24');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('340', '99', '992', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:47:03');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('341', '99', '976', 'B', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-27 09:47:20');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('342', '99', '980', 'C', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:47:29');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('343', '99', '987', 'C', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:47:37');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('344', '99', '990', 'D', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:48:23');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('345', '99', '977', 'B', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-27 09:48:51');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('346', '99', '982', 'D', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-27 09:49:03');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('347', '99', '973', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:49:18');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('348', '99', '983', 'C', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-27 09:49:50');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('349', '99', '975', 'C', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:50:14');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('350', '99', '981', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:50:20');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('351', '99', '989', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:50:31');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('352', '99', '994', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:50:56');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('353', '99', '970', 'C', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:51:06');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('354', '99', '965', 'B', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-27 09:51:18');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('355', '99', '979', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:51:38');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('356', '99', '985', 'C', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:51:47');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('357', '99', '974', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:52:02');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('358', '99', '972', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:52:25');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('359', '99', '984', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:52:35');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('360', '99', '967', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:52:55');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('361', '99', '978', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:53:25');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('362', '99', '988', 'D', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-27 09:53:39');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('363', '99', '991', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:53:53');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('364', '99', '993', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:54:10');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('365', '99', '986', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:54:30');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('366', '99', '971', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:56:38');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('367', '99', '969', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:57:10');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('368', '99', '966', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:58:04');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('369', '99', '968', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 09:59:05');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('370', '100', '968', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:44:39');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('371', '100', '967', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:45:31');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('372', '100', '976', 'C', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:45:58');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('373', '100', '978', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:46:10');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('374', '100', '989', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:46:24');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('375', '100', '972', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:46:32');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('376', '100', '971', 'D', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-27 10:47:03');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('377', '100', '980', 'C', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:47:30');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('378', '100', '970', 'C', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:47:43');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('379', '100', '985', 'C', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:47:57');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('380', '100', '966', 'A', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-27 10:48:23');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('381', '100', '992', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:48:49');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('382', '100', '994', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:49:22');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('383', '100', '981', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:49:45');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('384', '100', '969', 'A', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-27 10:50:14');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('385', '100', '990', 'C', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-27 10:50:43');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('386', '100', '983', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:50:56');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('387', '100', '973', 'C', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-27 10:51:50');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('388', '100', '993', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:52:13');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('389', '100', '979', 'D', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-27 10:52:33');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('390', '100', '987', 'C', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:52:48');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('391', '100', '977', 'C', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:53:15');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('392', '100', '982', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:53:28');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('393', '100', '965', 'B', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-27 10:53:50');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('394', '100', '986', 'D', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-27 10:54:23');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('395', '100', '984', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:54:42');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('396', '100', '991', 'A', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:54:57');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('397', '100', '988', 'B', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:55:12');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('398', '100', '974', 'A', NULL, '0', NULL, NULL, NULL, NULL, '2026-07-27 10:55:26');
INSERT INTO `student_answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `answer_text`, `is_correct`, `awarded_marks`, `teacher_comment`, `marked_by`, `marked_at`, `answered_at`) VALUES ('399', '100', '975', 'C', NULL, '1', NULL, NULL, NULL, NULL, '2026-07-27 10:56:02');

DROP TABLE IF EXISTS `subject_class_link`;
CREATE TABLE `subject_class_link` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_subject_class` (`subject_id`,`class_id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `subject_class_link_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subject_class_link_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `subject_class_link` (`id`, `subject_id`, `class_id`, `created_at`) VALUES ('11', '5', '1', '2025-10-28 10:33:39');
INSERT INTO `subject_class_link` (`id`, `subject_id`, `class_id`, `created_at`) VALUES ('12', '8', '2', '2025-11-04 16:53:18');
INSERT INTO `subject_class_link` (`id`, `subject_id`, `class_id`, `created_at`) VALUES ('13', '9', '1', '2025-11-04 16:57:36');
INSERT INTO `subject_class_link` (`id`, `subject_id`, `class_id`, `created_at`) VALUES ('14', '10', '2', '2025-11-04 17:03:15');
INSERT INTO `subject_class_link` (`id`, `subject_id`, `class_id`, `created_at`) VALUES ('15', '11', '1', '2025-11-04 17:07:53');
INSERT INTO `subject_class_link` (`id`, `subject_id`, `class_id`, `created_at`) VALUES ('16', '12', '2', '2025-11-04 17:08:23');
INSERT INTO `subject_class_link` (`id`, `subject_id`, `class_id`, `created_at`) VALUES ('17', '13', '3', '2025-11-09 22:08:21');
INSERT INTO `subject_class_link` (`id`, `subject_id`, `class_id`, `created_at`) VALUES ('19', '14', '6', '2025-11-21 08:57:24');
INSERT INTO `subject_class_link` (`id`, `subject_id`, `class_id`, `created_at`) VALUES ('22', '6', '2', '2025-12-01 10:07:01');
INSERT INTO `subject_class_link` (`id`, `subject_id`, `class_id`, `created_at`) VALUES ('29', '7', '1', '2026-04-17 06:23:04');
INSERT INTO `subject_class_link` (`id`, `subject_id`, `class_id`, `created_at`) VALUES ('30', '7', '2', '2026-04-17 06:23:04');
INSERT INTO `subject_class_link` (`id`, `subject_id`, `class_id`, `created_at`) VALUES ('31', '7', '4', '2026-04-17 06:23:04');
INSERT INTO `subject_class_link` (`id`, `subject_id`, `class_id`, `created_at`) VALUES ('32', '7', '3', '2026-04-17 06:23:04');
INSERT INTO `subject_class_link` (`id`, `subject_id`, `class_id`, `created_at`) VALUES ('33', '7', '6', '2026-04-17 06:23:04');
INSERT INTO `subject_class_link` (`id`, `subject_id`, `class_id`, `created_at`) VALUES ('34', '7', '5', '2026-04-17 06:23:04');
INSERT INTO `subject_class_link` (`id`, `subject_id`, `class_id`, `created_at`) VALUES ('35', '15', '3', '2026-07-24 09:18:28');
INSERT INTO `subject_class_link` (`id`, `subject_id`, `class_id`, `created_at`) VALUES ('36', '16', '7', '2026-07-26 20:06:29');
INSERT INTO `subject_class_link` (`id`, `subject_id`, `class_id`, `created_at`) VALUES ('37', '17', '7', '2026-07-26 20:06:40');

DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `subject_code` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `subject_code` (`subject_code`),
  KEY `idx_subjects_subject_code` (`subject_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `subjects` (`id`, `name`, `subject_code`, `description`, `created_at`) VALUES ('5', 'Mathematics', 'MATH001', 'Mathematics subject', '2025-10-28 10:23:10');
INSERT INTO `subjects` (`id`, `name`, `subject_code`, `description`, `created_at`) VALUES ('6', 'English Language', 'ENG001', 'English Language subject', '2025-10-28 10:23:10');
INSERT INTO `subjects` (`id`, `name`, `subject_code`, `description`, `created_at`) VALUES ('7', 'Civic Education', 'CIV001', 'Civic Education subject', '2025-10-28 10:23:10');
INSERT INTO `subjects` (`id`, `name`, `subject_code`, `description`, `created_at`) VALUES ('8', 'Mathematics', 'MATH002', 'Mathematics for JSS2', '2025-11-04 16:53:18');
INSERT INTO `subjects` (`id`, `name`, `subject_code`, `description`, `created_at`) VALUES ('9', 'English Language', 'ENG002', 'English Language JSS 1', '2025-11-04 16:57:36');
INSERT INTO `subjects` (`id`, `name`, `subject_code`, `description`, `created_at`) VALUES ('10', 'Civic Education', 'CIV002', 'Civic Education JSS2', '2025-11-04 17:03:15');
INSERT INTO `subjects` (`id`, `name`, `subject_code`, `description`, `created_at`) VALUES ('11', 'IRS', 'IRS001', 'Islamic Religious Studies 1', '2025-11-04 17:07:53');
INSERT INTO `subjects` (`id`, `name`, `subject_code`, `description`, `created_at`) VALUES ('12', 'IRS', 'IRS002', 'Islamic Religious Studies 2', '2025-11-04 17:08:23');
INSERT INTO `subjects` (`id`, `name`, `subject_code`, `description`, `created_at`) VALUES ('13', 'Computer Studies', 'COMP001', 'Computer Studies SS1', '2025-11-09 22:08:21');
INSERT INTO `subjects` (`id`, `name`, `subject_code`, `description`, `created_at`) VALUES ('14', 'Computer Studies', 'COMP002', '', '2025-11-21 08:51:36');
INSERT INTO `subjects` (`id`, `name`, `subject_code`, `description`, `created_at`) VALUES ('15', 'General Student Questions', 'GNS 101', 'Entrance Questions for SS1 Student', '2026-07-24 09:17:38');
INSERT INTO `subjects` (`id`, `name`, `subject_code`, `description`, `created_at`) VALUES ('16', 'Arabic Teacher Interview', 'INT102', 'Arabic Section', '2026-07-26 20:05:50');
INSERT INTO `subjects` (`id`, `name`, `subject_code`, `description`, `created_at`) VALUES ('17', 'Conventional Teacher Interview', 'INT103', 'Conventional Teacher Lesson', '2026-07-26 20:06:16');

DROP TABLE IF EXISTS `teacher_allocations`;
CREATE TABLE `teacher_allocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `allocated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `allocated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teacher_class_subject` (`teacher_id`,`class_id`,`subject_id`),
  KEY `class_id` (`class_id`),
  KEY `subject_id` (`subject_id`),
  KEY `allocated_by` (`allocated_by`),
  KEY `idx_teacher_allocations_teacher_id` (`teacher_id`),
  KEY `idx_teacher_allocations_class_id` (`class_id`),
  KEY `idx_teacher_allocations_subject_id` (`subject_id`),
  KEY `idx_teacher_allocations_teacher_class` (`teacher_id`,`class_id`),
  CONSTRAINT `teacher_allocations_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teacher_allocations_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teacher_allocations_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teacher_allocations_ibfk_4` FOREIGN KEY (`allocated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `teacher_allocations` (`id`, `teacher_id`, `class_id`, `subject_id`, `allocated_at`, `allocated_by`) VALUES ('2', '2', '2', '6', '2025-10-28 10:39:29', '1');
INSERT INTO `teacher_allocations` (`id`, `teacher_id`, `class_id`, `subject_id`, `allocated_at`, `allocated_by`) VALUES ('3', '6', '1', '5', '2025-10-28 10:41:27', '1');
INSERT INTO `teacher_allocations` (`id`, `teacher_id`, `class_id`, `subject_id`, `allocated_at`, `allocated_by`) VALUES ('4', '6', '2', '12', '2025-11-04 17:17:33', '1');
INSERT INTO `teacher_allocations` (`id`, `teacher_id`, `class_id`, `subject_id`, `allocated_at`, `allocated_by`) VALUES ('6', '6', '1', '11', '2025-11-04 17:35:50', '1');
INSERT INTO `teacher_allocations` (`id`, `teacher_id`, `class_id`, `subject_id`, `allocated_at`, `allocated_by`) VALUES ('7', '2', '1', '9', '2025-11-25 12:53:08', '1');
INSERT INTO `teacher_allocations` (`id`, `teacher_id`, `class_id`, `subject_id`, `allocated_at`, `allocated_by`) VALUES ('8', '2', '1', '7', '2026-04-17 06:25:54', '1');
INSERT INTO `teacher_allocations` (`id`, `teacher_id`, `class_id`, `subject_id`, `allocated_at`, `allocated_by`) VALUES ('9', '22', '3', '15', '2026-07-24 09:18:45', '1');
INSERT INTO `teacher_allocations` (`id`, `teacher_id`, `class_id`, `subject_id`, `allocated_at`, `allocated_by`) VALUES ('10', '22', '2', '10', '2026-07-24 12:50:24', '22');
INSERT INTO `teacher_allocations` (`id`, `teacher_id`, `class_id`, `subject_id`, `allocated_at`, `allocated_by`) VALUES ('11', '22', '7', '16', '2026-07-26 20:08:05', '22');
INSERT INTO `teacher_allocations` (`id`, `teacher_id`, `class_id`, `subject_id`, `allocated_at`, `allocated_by`) VALUES ('12', '22', '7', '17', '2026-07-26 20:08:16', '22');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `passport` varchar(255) DEFAULT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `role` enum('admin','teacher','student') NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_class_gender` (`class_id`,`gender`),
  CONSTRAINT `fk_users_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('1', 'admin@cbtexam.com', '$2y$12$38WGwGlFhLMryhFpaTLAHOoTK2cImZcIEVGwxbYZYVfKv/J.OvusC', 'System Administrator', NULL, NULL, 'admin', NULL, '2025-10-28 10:03:38');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('2', 'gb.to@gmail.com', '$2y$10$CQdAdLXMwBx5ihYO0nCXX.IQxR2bjGqdb3twVV1hR/bzjZi4PjzLi', 'Gbenga Tosin', NULL, NULL, 'teacher', NULL, '2025-10-28 10:09:36');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('4', 'wasiugold@gmail.com', '$2y$10$JqT/FQIbTxBVkRnP6nC3.Or9OLV6Mj8sATloULFAMXmnaZiX/WbCW', 'Wasiu Gold', NULL, NULL, 'student', '2', '2025-10-28 12:57:43');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('6', 'ash.raf@gmail.com', '$2y$10$11NqB7wbZj0e6uPdr6dgQ.iuena7JMtsZAPLPZRY7MXpgnQvMTcJ.', 'Ashraf Ahmad', NULL, NULL, 'admin', NULL, '2025-11-04 17:09:34');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('7', 'abdulrahman@gmail.com', '$2y$10$4uq8u8hai8oHC5sVIokcNe9EPZFRHbD3XZ6hUpcYeiSw3GyhBUb8u', 'AbdulRahman Ahmad', 'uploads/passports/69e1cf310e240.jpg', NULL, 'student', '2', '2025-11-04 17:21:31');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('8', 'muhammad@gmail.com', '$2y$10$c7sxq8iG7s.jM8CzTOQrQOb6ypX4dCkPyYCppKrYI4wL924Kc2CUi', 'Muhammad', NULL, NULL, 'student', '2', '2025-11-24 10:03:28');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('10', 'student1@gmail.com', '$2y$10$0wU3iefgCAWtBTo0dpzMYOuoluF1wERsYsKV2rNQycqzX7ivNHBRe', 'Student 1', NULL, NULL, 'student', '2', '2025-11-25 11:26:21');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('11', 'student2@gmail.com', '$2y$10$lB2kj2sLZULj3tWcqFVpLOpRp30jP39az9BENx.2evLLaTULS9Q3q', 'Student 2', NULL, NULL, 'student', '2', '2025-11-25 11:56:27');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('13', 'student4@gmail.com', '$2y$10$bNQbCuSRt2OMVeQTU5AaTO/lyw9JfeT5PCU75z3.UKr24/J7Zrrbi', 'Student 4', NULL, NULL, 'student', '2', '2025-11-25 12:23:06');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('14', 'student5@gmail.com', '$2y$10$BgUrRUxAjEmVlqGKONwMmekh2m5FsLBYA9M0GRDrfw0YQX8gn7THK', 'student5', NULL, NULL, 'student', '2', '2025-11-25 12:51:59');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('15', 'student6@gmail.com', '$2y$10$HNbL3H/llqNHEA3ZFjZZkuBv0nk5/xNVQ5ohgBv0HG3KWBTzQCNrq', 'student 6', NULL, NULL, 'student', '2', '2025-11-25 17:08:10');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('16', 'student7@gmail.com', '$2y$10$2bnwk2j1PqMvN.AkguhCWOi5n7Unekyg1GhCI81xd7Jlub8A31Muq', 'student 7', NULL, NULL, 'student', '2', '2025-11-26 13:09:59');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('17', 'student9@gmail.com', '$2y$10$DP7D9lmh91ZAUNwspyGQiOOh3KFXuJdFFt2/qjycL.S/mRRnSEVoO', 'Student 9', NULL, NULL, 'student', '2', '2025-11-26 15:48:27');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('18', 'student10@gmail.com', '$2y$10$/rCXIwcSpiet9f5rOxIfLekTMhAL9L39H3gmtgUuErPoJ1xbZEthi', 'student 10', NULL, NULL, 'student', '2', '2025-12-01 10:06:08');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('19', 'student12@gmail.com', '$2y$10$Pg.marvgu7xguvupHPYth.Dabv9zd.Fz9X/54RJB7agmK0.wcvVX.', 'student 12', NULL, NULL, 'student', '2', '2025-12-02 15:36:44');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('20', 'student14@gmail.com', '$2y$10$9ngNlZaTr2/0Rc7hbkMQquQNY8VQEazJ6r5Yj/hf8xZUY17g4xvMS', 'student 14', NULL, NULL, 'student', '2', '2025-12-02 22:07:16');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('21', 'student15@gmail.com', '$2y$10$FhaUSbYQAP33q1/BWqjMYuAsxo7mGUUSrMo/C9lwF9Y.43wWkLSki', 'student 15', NULL, NULL, 'student', '2', '2025-12-03 10:00:11');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('22', 'adminahmad@gmail.com', '$2y$10$w/k7863/9bQ/Tw/HJJUckuouIwTZLCB81Q2/GP1BIVtsbMPdCGRdW', 'Admin Ahmad', NULL, NULL, 'admin', NULL, '2026-03-24 07:27:22');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('23', 'sherifayomide@gmail.com', '$2y$10$k6ej5IV1FBeB7JinYgi6PeHA5u.Kq00QaSIDx9i9q2SKUbjoXR/pK', 'Sherif Ayomide', 'uploads/passports/69e1c4108191f.jpeg', NULL, 'student', '1', '2026-04-17 06:24:32');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('24', 'teacher@cbtexam.com', '$2y$12$9PnFrihFqW5zIiyPjB8b/.xKVUW66LWpUZUdBBnvG3g7Kev7rK0SG', 'Teacher User', NULL, NULL, 'teacher', NULL, '2026-05-19 07:35:10');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('25', 'student@cbtexam.com', '$2y$12$eVaR7KAyYd7dqTu8oUbPOOW31tyLjQ9NI5dKOYO.vjAAS5hJQiPfG', 'Student User', NULL, NULL, 'student', NULL, '2026-05-19 07:35:10');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('26', 'ahmbio4real@gmail.com', '$2y$10$F9E95ky.n9Gyzf8n.cAY7e71NdXe5pj6u8Yv6CoNM9QvkRAwvyoM2', 'Ahmad Abiola', NULL, NULL, 'teacher', NULL, '2026-05-19 07:35:10');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('27', 'ahmad.abiola@famaiis.sch.ng', '$2y$10$5EZBMTZYiswkyNW8pjCIJuBsTZDi9GY.rRBg5ET0PTbNem3cEBgHa', 'Ahmad Abiola Ogunmeru', NULL, NULL, 'teacher', NULL, '2026-05-19 07:35:10');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('28', 'abdulrahmanmalami@gmail.com', '$2y$10$xSGsPePWSV6SOiv.mhHh/ubA4b8WQxB8B5BPp4iuPC4QnrVh9NrsW', 'Abdulrahman Malami Wala', 'uploads/passports/6a63206a4feb7.png', NULL, 'student', '3', '2026-07-24 09:20:58');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('29', 'muhammadabdulkadir884@gmail.com', '$2y$10$6O7OC0YATVOxFB/LzeK0V.1h27aEupUcwgBarPzbiDOzFVCd0URwi', 'MUHAMMAD ABDULKADIR', 'uploads/passports/6a6715262bb4e.png', NULL, 'student', '7', '2026-07-27 09:21:58');
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `passport`, `gender`, `role`, `class_id`, `created_at`) VALUES ('30', 'fridayauta27@gmail.com', '$2y$10$qvt2kCqCrD3krEbDWUVUKu8XcxmLi1PDISVuYvb/UwWo.GnsG82ci', 'Auta Friday Amos', 'uploads/passports/6a6726c552e72.png', NULL, 'student', '7', '2026-07-27 10:37:09');

SET FOREIGN_KEY_CHECKS=1;
