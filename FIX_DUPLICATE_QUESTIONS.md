# Fix for Question Duplication Issue

## Problem
When importing questions, the system was creating duplicate entries. For example, importing 80 questions would sometimes result in 160 or more questions in the database.

## Solution Implemented

### 1. Duplicate Prevention Feature
Added a duplicate checking mechanism that compares imported questions with existing ones based on:
- Exam subject ID
- Question text
- All option texts (A, B, C, D)
- Correct answer

### 2. Clear Existing Questions Option
Added an option to delete all existing questions before importing new ones.

### 3. Database Index for Performance
Created an index to improve the performance of duplicate checking queries.

## Changes Made

### Modified Files
1. `teacher/question_management.php` - Main implementation
2. `database/add_questions_unique_index.sql` - Performance index

### Features Added
1. **Prevent Duplicate Questions Checkbox** (enabled by default):
   - Checks if a question with the same content already exists
   - Skips importing duplicate questions
   - Shows a message indicating how many duplicates were skipped

2. **Clear Existing Questions Checkbox** (disabled by default):
   - Deletes all existing questions for the exam before importing
   - Useful when you want to replace all questions with a new set
   - Shows a confirmation message when used

## How to Use

### Preventing Duplicates (Default Behavior)
1. Go to Question Management page
2. Ensure "Prevent duplicate questions" checkbox is checked
3. Select your question file
4. Click "Import Questions"
5. The system will automatically skip any questions that already exist

### Replacing All Questions
1. Go to Question Management page
2. Check "Clear all existing questions before import"
3. Select your question file
4. Click "Import Questions"
5. All existing questions will be deleted before importing the new ones

## Technical Details

### Duplicate Detection Logic
The system uses the following query to detect duplicates:
```sql
SELECT COUNT(*) as count FROM questions 
WHERE exam_subject_id = ? 
  AND question_text = ? 
  AND option_a = ? 
  AND option_b = ? 
  AND option_c = ? 
  AND option_d = ? 
  AND correct_answer = ?
```

### Performance Optimization
Added index `idx_questions_duplicate_check` on the questions table to speed up duplicate checking:
```sql
ALTER TABLE `questions` 
ADD INDEX `idx_questions_duplicate_check` (
  `exam_subject_id`, 
  `question_text`(255), 
  `option_a`(100), 
  `option_b`(100), 
  `option_c`(100), 
  `option_d`(100), 
  `correct_answer`
);
```

## Benefits
1. Prevents accidental duplication of questions
2. Provides flexibility to either skip duplicates or replace all questions
3. Maintains data integrity
4. Improves performance with database indexing
5. Gives clear feedback on import operations

## Testing
A test script `test_duplicate_prevention.php` was created to verify the duplicate checking functionality.