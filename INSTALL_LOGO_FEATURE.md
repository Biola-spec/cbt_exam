# School Logo Feature Installation Guide

This guide explains how to install and configure the school logo feature for the CBT Exam System.

## Prerequisites

1. Ensure your MySQL database is running
2. Make sure the `cbt_exam_system` database exists
3. Verify that you can connect to the database with the credentials in `config/database.php`

## Installation Steps

### 1. Update Database Schema

Run the following SQL command to add the logo column to the school configuration table:

```sql
ALTER TABLE `school_config` 
ADD COLUMN `logo_path` VARCHAR(255) NULL DEFAULT NULL AFTER `default_time_limit`;
```

You can execute this command using:
- PHPMyAdmin
- MySQL Workbench
- Command line MySQL client
- Any other MySQL management tool

### 2. Create Uploads Directory

Create an `uploads` directory in the root of the project with write permissions:

```bash
mkdir uploads
chmod 755 uploads
```

On Windows, you can simply create the folder through File Explorer and ensure it has write permissions.

### 3. Verify Implementation

The implementation is already included in the following files:
- `admin/school_settings.php` - Added logo upload functionality
- `includes/header.php` - Displays logo in navigation bar
- `login.php` - Displays logo on login page
- `student/exam_completion.php` - Displays logo on exam completion page
- `student/print_exam_result.php` - Displays logo on printed exam results
- `config/session.php` - Added `getSchoolLogo()` function

### 4. Test the Feature

1. Log in as an administrator
2. Navigate to Admin Dashboard → School Settings
3. Select a logo image file (JPG, PNG, GIF, or WebP)
4. Click "Save Settings"
5. Verify the logo appears on:
   - Login page
   - Navigation bar after login
   - Exam completion page
   - Printed exam results

## Troubleshooting

### Database Connection Issues

If you encounter database connection issues:

1. Check that MySQL service is running
2. Verify database credentials in `config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'cbt_exam_system');
   ```

3. Ensure the database `cbt_exam_system` exists

### Logo Not Displaying

If the logo doesn't appear after uploading:

1. Check that the `uploads` directory exists and has write permissions
2. Verify that the `logo_path` column was added to the `school_config` table
3. Check the browser's developer console for any image loading errors
4. Ensure the uploaded image file is in a supported format (JPG, PNG, GIF, WebP)

### File Permissions

On Linux/Unix systems, ensure the web server has write permissions to the `uploads` directory:

```bash
chown -R www-data:www-data uploads
chmod -R 755 uploads
```

Replace `www-data` with your web server's user if different.

## Supported Image Formats

The system supports the following image formats:
- JPEG (.jpg, .jpeg)
- PNG (.png)
- GIF (.gif)
- WebP (.webp)

Recommended image size: 200x200 pixels for optimal display.

## Security Considerations

The implementation includes basic security measures:
- File type validation
- Sanitization of file paths
- Proper escaping of output

For enhanced security in production environments, consider:
- Implementing file size limits
- Adding additional malware scanning
- Using a CDN for serving images
- Restricting upload directory access