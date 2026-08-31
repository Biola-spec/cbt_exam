# Deployment Instructions: Passkey Removal and Subject Code Implementation

## Overview

This document provides instructions for deploying the changes that remove the passkey system and replace it with a subject code-based authentication system.

## Files Changed

The following files have been modified or created:

### Modified Files:
- `admin/dashboard.php` - Removed passkey management navigation
- `login.php` - Replaced passkey authentication with subject code authentication
- `student/available_exams.php` - Updated to use subject codes for exam access
- `admin/student_management.php` - Removed passkey generation functionality
- `database/cbt_exam_schema.sql` - Removed passkeys table definition
- `README.md` - Updated documentation

### New Files:
- `database/remove_passkeys_table.sql` - Script to drop the passkeys table
- `database/remove_passkey_columns.sql` - Script for future reference
- `database/remove_passkeys_summary.sql` - Summary of SQL commands
- `CHANGES_SUMMARY.md` - Detailed summary of all changes
- `DEPLOYMENT_INSTRUCTIONS.md` - This file

### Deleted Files:
- `admin/passkey_management.php` - Passkey management page
- `check_passkeys_schema.php` - Renamed to `check_subjects_schema.php`

## Deployment Steps

### 1. Backup Your Database
Before making any changes, create a backup of your database:
```sql
mysqldump -u root -p cbt_exam_system > cbt_exam_system_backup.sql
```

### 2. Update Files
Replace the existing files with the updated versions:
- Copy all modified files to your server
- Remove the deleted files from your server
- Rename `check_passkeys_schema.php` to `check_subjects_schema.php`

### 3. Update Database Schema
Run the database migration script to remove the passkeys table:

```sql
-- Run these commands in your MySQL client
-- Drop foreign key constraints (if they exist)
ALTER TABLE `passkeys` DROP FOREIGN KEY IF EXISTS `passkeys_ibfk_1`;
ALTER TABLE `passkeys` DROP FOREIGN KEY IF EXISTS `passkeys_ibfk_2`;
ALTER TABLE `passkeys` DROP FOREIGN KEY IF EXISTS `passkeys_ibfk_3`;

-- Drop indexes (if they exist)
DROP INDEX IF EXISTS `exam_id` ON `passkeys`;
DROP INDEX IF EXISTS `created_by` ON `passkeys`;
DROP INDEX IF EXISTS `exam_subject_id` ON `passkeys`;
DROP INDEX IF EXISTS `idx_passkeys_exam_subject` ON `passkeys`;

-- Drop the passkeys table
DROP TABLE IF EXISTS `passkeys`;
```

Alternatively, you can run the provided SQL script:
```bash
mysql -u root -p cbt_exam_system < database/remove_passkeys_table.sql
```

### 4. Update Documentation
- Update any internal documentation to reflect the new subject code system
- Inform users about the change in authentication method

### 5. Test the Changes
1. Access the login page and verify the student login form shows "Subject Code" instead of "Passkey"
2. Log in as a student using a valid email and subject code
3. Verify that students can access exams associated with their subject code
4. Check that the admin dashboard no longer shows the "Passkeys" navigation option

## New Authentication Flow

### For Students:
1. Navigate to the login page
2. Select the "Student Login" tab
3. Enter your Gmail address
4. Enter a valid subject code (e.g., "Mathematics", "Physics", etc.)
5. Click "Sign In as Student"

### For Administrators:
1. Navigate to the login page
2. Select the "Staff Login" tab
3. Enter your email and password
4. Click "Sign In as Staff"

## Benefits of the New System

1. **Simplified Access**: Students no longer need to remember or receive passkeys
2. **Intuitive**: Subject codes are more familiar to students than random passkeys
3. **Reduced Administration**: No need to generate and manage passkeys
4. **Direct Association**: Students access exams directly through their enrolled subjects

## Troubleshooting

### Issue: Students cannot log in with subject codes
**Solution**: 
1. Verify that the subject code exists in the `subjects` table
2. Check that the student's email exists in the `users` table with role 'student'
3. Ensure the database connection is working properly

### Issue: Admin dashboard still shows passkey options
**Solution**:
1. Verify that `admin/dashboard.php` has been updated
2. Clear browser cache and refresh the page
3. Check that `admin/passkey_management.php` has been deleted

### Issue: Database migration fails
**Solution**:
1. Check that you have the necessary privileges to modify the database
2. Verify that the passkeys table exists before attempting to drop it
3. Run the commands one by one instead of as a batch

## Rollback Procedure

If you need to revert to the previous passkey system:

1. Restore the database from your backup
2. Restore the deleted files:
   - `admin/passkey_management.php`
   - `check_passkeys_schema.php`
3. Revert the modified files to their previous versions
4. Update any documentation to reflect the restored passkey system

## Support

For any issues with deployment or questions about the new system, please contact the development team.