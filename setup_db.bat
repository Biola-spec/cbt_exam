@echo off
echo Creating CBT Exam System Database...
echo.

REM Drop database if it exists
"C:\xampp\mysql\bin\mysql.exe" -u root -e "DROP DATABASE IF EXISTS cbt_exam_system;"
if %errorlevel% neq 0 (
    echo Error dropping database
    exit /b %errorlevel%
)
echo Existing database dropped

REM Create database
"C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE cbt_exam_system;"
if %errorlevel% neq 0 (
    echo Error creating database
    exit /b %errorlevel%
)
echo Database created successfully

REM Import schema
"C:\xampp\mysql\bin\mysql.exe" -u root cbt_exam_system < database\cbt_exam_schema.sql
if %errorlevel% neq 0 (
    echo Error importing schema
    exit /b %errorlevel%
)
echo Schema imported successfully

echo.
echo Database setup completed!
echo.
echo Now creating admin user...
echo.

REM Create admin user
"C:\xampp\mysql\bin\mysql.exe" -u root -e "USE cbt_exam_system; INSERT INTO users (email, password, full_name, role) VALUES ('admin@cbtexam.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin');"
if %errorlevel% neq 0 (
    echo Error creating admin user
    exit /b %errorlevel%
)
echo Admin user created successfully

echo.
echo Setup completed successfully!
echo.
echo Login credentials:
echo Email: admin@cbtexam.com
echo Password: Admin@2024
echo.
pause