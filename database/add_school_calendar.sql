-- Migration: Add school calendar functionality
-- Date: 2026-09-05

-- 1. Add calendar configuration columns to school_config
ALTER TABLE `school_config`
ADD COLUMN `calendar_start_month` DATE DEFAULT NULL COMMENT 'Start date of the school calendar (e.g., 2026-09-01 for September 2026)',
ADD COLUMN `calendar_months_count` INT(11) NOT NULL DEFAULT 4 COMMENT 'Number of months to display in school calendar (default: 4)';

-- 2. Create calendar_events table
CREATE TABLE IF NOT EXISTS `calendar_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_title` varchar(255) NOT NULL COMMENT 'Title of the event',
  `event_description` text DEFAULT NULL COMMENT 'Detailed description of the event',
  `event_date` date NOT NULL COMMENT 'Date of the event (for single-day events)',
  `end_date` date DEFAULT NULL COMMENT 'End date (for multi-day events)',
  `event_type` enum('exam','holiday','meeting','activity','other') NOT NULL DEFAULT 'other' COMMENT 'Type of event',
  `event_color` varchar(20) DEFAULT '#007bff' COMMENT 'Color code for event display',
  `class_id` int(11) DEFAULT NULL COMMENT 'Specific class (NULL = all classes)',
  `is_public` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = visible to all users, 0 = admin/teachers only',
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID of creator',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_event_date` (`event_date`),
  KEY `idx_class_id` (`class_id`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `calendar_events_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `calendar_events_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='School calendar events';
