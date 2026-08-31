# Option Expansion Changes

This document summarizes the changes made to expand the question options from 4 (A, B, C, D) to 6 (A, B, C, D, E, F).

## Database Changes

1. Added `option_e` and `option_f` columns to the `questions` table
2. Modified the `correct_answer` column in the `questions` table to support 'E' and 'F' values
3. Modified the `selected_answer` column in the `student_answers` table to support 'E' and 'F' values

## Backend Changes

1. Updated the teacher question management interface to:
   - Include input fields for options E and F
   - Update the correct answer dropdown to include E and F
   - Modify database queries to handle the new options
   - Update the display to show options E and F when they exist

2. Updated the student exam taking interface to:
   - Fetch options E and F from the database
   - Display options E and F when they exist
   - Allow students to select options E and F

## Files Modified

1. `database/cbt_exam_schema.sql` - Updated database schema
2. `database/update_questions_table_for_more_options.sql` - Migration script
3. `mysqli_setup.php` - Updated setup script
4. `manual_setup.php` - Updated setup script
5. `teacher/question_management.php` - Updated teacher interface
6. `student/take_exam.php` - Updated student interface
7. `apply_option_expansion.php` - Migration application script

## How to Apply Changes

1. Run the migration script:
   ```
   php apply_option_expansion.php
   ```

2. The system will now support up to 6 options (A, B, C, D, E, F) for questions.

## Notes

- Options E and F are optional - teachers can leave them blank if not needed
- Existing questions with only A-D options will continue to work as before
- The system maintains backward compatibility with existing data