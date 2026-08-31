# MySQL Configuration Guide for CBT Exam System

This guide explains how to properly configure MySQL to avoid "max_allowed_packet" and "MySQL server has gone away" errors.

## Issue Explanation

The CBT Exam System may encounter these database errors when saving large content (especially with images):

1. `Error loading data: SESSION variable 'max_allowed_packet' is read-only`
2. `Error saving comprehension passage: MySQL server has gone away`
3. `Got a packet bigger than 'max_allowed_packet' bytes`

These errors occur because:
- MySQL has limits on packet sizes and connection timeouts
- Large content (especially base64 images) can exceed these limits
- Session variables like `max_allowed_packet` cannot be changed at runtime

## Solution: Configure MySQL Settings

To permanently fix these issues, you need to modify your MySQL configuration file.

### Step 1: Locate MySQL Configuration File

Depending on your system, the configuration file may be named:
- `my.ini` (Windows with XAMPP)
- `my.cnf` (Linux/Mac)

Common locations:
- Windows (XAMPP): `C:\xampp\mysql\bin\my.ini`
- Windows: `C:\ProgramData\MySQL\MySQL Server X.X\my.ini`
- Linux: `/etc/mysql/my.cnf` or `/etc/my.cnf`
- Mac: `/usr/local/mysql/my.cnf`

### Step 2: Edit Configuration File

Add or modify the following settings in the `[mysqld]` section:

```ini
[mysqld]
# Increase max allowed packet size
max_allowed_packet = 64M

# Increase connection timeout values
wait_timeout = 600
interactive_timeout = 600

# Optional: Increase other limits for large data
innodb_log_file_size = 64M
innodb_log_buffer_size = 16M
```

### Step 3: Restart MySQL Service

After saving the configuration file, restart your MySQL service:
- For XAMPP: Stop and start MySQL through the XAMPP Control Panel
- For Windows services: `services.msc` → MySQL → Restart
- For Linux/Mac: `sudo service mysql restart` or `sudo systemctl restart mysql`

## Alternative Solutions

If you cannot modify the MySQL configuration:

1. **Continue using the file upload approach** (recommended):
   - Images are uploaded to the server and referenced by URL
   - This keeps database content small and avoids packet size issues

2. **Optimize content size**:
   - Compress images before uploading
   - Use appropriate image dimensions for display
   - Limit the amount of content per passage

## Verification

After applying the configuration changes, you can verify the settings by running:

```sql
SHOW VARIABLES LIKE 'max_allowed_packet';
SHOW VARIABLES LIKE 'wait_timeout';
SHOW VARIABLES LIKE 'interactive_timeout';
```

These should show your new values.

## Troubleshooting

If you still encounter issues:

1. Check MySQL error logs for specific error messages
2. Ensure your MySQL user has appropriate permissions
3. Consider upgrading MySQL if you're using an older version
4. Monitor database performance to ensure changes don't impact other applications