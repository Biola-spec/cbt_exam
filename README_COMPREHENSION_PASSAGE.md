# Comprehension Passage Feature

This feature allows teachers to add a comprehension passage that students will read before answering questions in an exam.

## Database Setup

Since the database drivers are not available in your current PHP installation, you'll need to manually create the database table.

### Manual Database Setup

1. Open your MySQL client (phpMyAdmin, MySQL Workbench, or command line)
2. Execute the following SQL to create the comprehension_passages table:

```sql
CREATE TABLE `comprehension_passages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_subject_id` int(11) NOT NULL,
  `passage_title` varchar(255) DEFAULT NULL,
  `passage_content` text NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `exam_subject_id` (`exam_subject_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `comprehension_passages_ibfk_1` FOREIGN KEY (`exam_subject_id`) REFERENCES `exam_subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comprehension_passages_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add unique constraint to ensure only one passage per exam_subject
ALTER TABLE `comprehension_passages` ADD UNIQUE KEY `unique_exam_subject` (`exam_subject_id`);
```

## Feature Overview

The comprehension passage feature has been implemented in two main areas:

### 1. Teacher Question Management

Teachers can now add/edit a comprehension passage in the question management interface:
- Located in `teacher/question_management.php`
- New "Comprehension Passage (Optional)" section
- Teachers can save a passage title and content
- Passages are linked to specific exam subjects

### 2. Student Exam Interface

Students will see the comprehension passage before starting questions:
- Located in `student/take_exam.php`
- Students can view the passage by clicking "View Passage"
- Navigation between passage and questions is provided
- Time limit continues to run during passage viewing

## Usage Instructions

### For Teachers:
1. Navigate to Question Management for an exam subject
2. Scroll to the "Comprehension Passage (Optional)" section
3. Enter a title (optional) and the passage content
4. Click "Save Passage"
5. The passage will be displayed to students when they take the exam

### For Students:
1. When taking an exam with a comprehension passage, students will see a notification
2. Click "View Passage" to read the comprehension passage
3. After reading, click "Start Questions" to begin answering questions
4. Students can return to the passage at any time during the exam

## Files Modified

1. `teacher/question_management.php` - Added comprehension passage management
2. `student/take_exam.php` - Added comprehension passage display
3. `database/add_comprehension_passage_support.sql` - Database schema update script

## Testing

To test the feature:
1. Create a new exam with questions
2. Add a comprehension passage in the question management interface
3. Take the exam as a student to verify the passage displays correctly