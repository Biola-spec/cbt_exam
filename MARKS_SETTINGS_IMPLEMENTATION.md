# Mark Settings Implementation for Classes

## Overview
This implementation adds the ability to configure different mark settings for each class. For example, junior classes can have 0.5 marks per question instead of the default 1.0 mark per question.

## Changes Made

### 1. Database Schema Update
- Added `marks_per_question` column to the `classes` table
- Column type: `DECIMAL(5,2)` with default value of `1.00`
- Positioned after the `description` column

### 2. Class Management Interface
- Updated the class management page to include a "Marks Per Question" field
- Added input validation to ensure positive values
- Modified the class listing to display the marks per question setting

### 3. Exam Scoring System
- Modified the exam scoring logic to use class-specific marks per question
- Updated all score calculations to multiply correct answers by the class's marks per question value
- Updated score display in:
  - Exam completion page
  - Exam history page
  - Print exam results page

## Files Modified

1. `admin/class_management.php` - Added marks per question field to forms and displays
2. `student/take_exam.php` - Updated scoring logic to use class-specific marks
3. `student/exam_completion.php` - Updated score display to show calculated scores
4. `student/exam_history.php` - Updated percentage calculations based on maximum possible score
5. `student/print_exam_result.php` - Updated score display and percentage calculations

## Database Update Scripts
- `database/update_classes_table_add_marks.sql` - SQL script to add the column
- `add_marks_column.php` - PHP script to add the column
- `check_column.php` - PHP script to check if column exists
- `db_test.php` - Comprehensive test script

## Usage Instructions

1. Go to Admin Dashboard → Class Management
2. When adding or editing a class, set the "Marks Per Question" value (e.g., 0.50 for junior classes)
3. When students take exams in that class, their scores will be calculated based on this setting
4. All score displays will show the correctly calculated scores

## Example
- For a class with 0.5 marks per question:
  - 10 correct answers = 5.00 points
  - 20 total questions = 10.00 maximum points
  - Score display: 5.00 / 10.00 (50%)
- For a class with 1.0 marks per question (default):
  - 10 correct answers = 10.00 points
  - 20 total questions = 20.00 maximum points
  - Score display: 10.00 / 20.00 (50%)

## Implementation Notes
- The system maintains backward compatibility with existing classes
- Default value of 1.00 ensures existing classes continue to work as before
- All score calculations now use floating-point arithmetic for precision
- Percentage calculations are based on the maximum possible score for the exam