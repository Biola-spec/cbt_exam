# CBT Exam System (Fama CBT Examination)

A comprehensive Computer-Based Testing (CBT) system with both React (TypeScript) and PHP implementations.

## Features

### Admin Features
- **School Settings**: Configure school name and default exam time limits
- **Class Management**: Create, edit, and delete classes with search functionality
- **Subject Management**: Create, edit, and delete subjects with search functionality
- **Teacher Allocation**: Assign teachers to specific classes and subjects
- **Exam Management**: Create exams, add subjects with time limits, and publish/unpublish exams and subjects
- **Subject Management**: Manage subjects for student access

### Teacher Features
- **Question Management**: Add, edit, and delete multiple-choice questions for allocated exams

### Student Features
- **Exam Access**: Access exams using subject codes
- **Exam Taking**: Take exams with timer functionality
- **Results Viewing**: View exam results and scores

## New Features Added

### Enhanced Subject System
- **Subject-Specific Access**: Students access exams using subject codes
- **Improved Filtering**: Filter exams by student class and subject

### Enhanced Exam Management
- **Subject-Level Publishing**: Publish/unpublish individual subjects within an exam
- **Time Limits**: Set time limits for each exam subject
- **Improved Workflow**: Better organization and management of exams and subjects

### Enhanced Class and Subject Management
- **Search Functionality**: Search classes and subjects by name or description
- **Duplicate Prevention**: Prevent creation of duplicate classes and subjects
- **Better UI**: Improved user interface with search bars and filtered results

### Enhanced School Settings
- **Default Time Limit**: Set a default time limit for new exam subjects

## Database Schema Updates

The system now includes several new columns to support the enhanced features:

1. **school_config table**:
   - `default_time_limit`: Default time limit for new exam subjects

2. **exam_subjects table**:
   - `time_limit`: Time limit for the exam subject
   - `is_published`: Publish status for individual exam subjects
   - `published_at`: Timestamp when the exam subject was published

3. **subjects table**:
   - Contains subject information for student access

## Installation

1. Clone the repository
2. Set up the database using the schema in `database/cbt_exam_schema.sql`
3. Configure database connection in `config/database.php`
4. For the React version, set up environment variables in `project/.env`
5. Run the application

## Usage

### Admin Login
- Email: admin@cbtexam.com
- Password: Admin@2024

### Teacher Login
- Email: teacher@cbtexam.com
- Password: Teacher@2024

### Student Login
- Email: wasiugold@gmail.com
- Subject Code: MATH001 (Mathematics)

## Technologies Used

### PHP Version
- PHP 7.4+
- MySQL
- Bootstrap 5
- JavaScript

### React Version
- React with TypeScript
- Supabase (backend)
- Tailwind CSS
- Vite

## License

This project is licensed under the MIT License.
