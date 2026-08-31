<?php
// Include session management to check for getSchoolLogo function
require_once 'config/session.php';

echo "<h1>School Logo Feature Implementation Verification</h1>\n";

// Check 1: Required files existence
echo "<h2>1. Required Files Check</h2>\n";
$required_files = [
    'admin/school_settings.php',
    'config/session.php',
    'includes/header.php',
    'login.php',
    'student/exam_completion.php',
    'student/print_exam_result.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✓ $file exists</p>\n";
    } else {
        echo "<p style='color: red;'>✗ $file not found</p>\n";
    }
}

// Check 2: Required functions
echo "<h2>2. Required Functions Check</h2>\n";
if (function_exists('getSchoolLogo')) {
    echo "<p style='color: green;'>✓ getSchoolLogo() function exists</p>\n";
} else {
    echo "<p style='color: red;'>✗ getSchoolLogo() function not found</p>\n";
}

// Check 3: Upload directory
echo "<h2>3. Upload Directory Check</h2>\n";
$upload_dir = 'uploads/';
if (is_dir($upload_dir)) {
    echo "<p style='color: green;'>✓ Uploads directory exists</p>\n";
    if (is_writable($upload_dir)) {
        echo "<p style='color: green;'>✓ Uploads directory is writable</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Uploads directory is not writable</p>\n";
    }
} else {
    echo "<p style='color: red;'>✗ Uploads directory does not exist</p>\n";
}

// Check 4: SQL migration files
echo "<h2>4. SQL Migration Files Check</h2>\n";
$sql_files = [
    'database/add_school_logo_column.sql',
    'database/update_school_config_table_add_logo.sql'
];

foreach ($sql_files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✓ $file exists</p>\n";
    } else {
        echo "<p style='color: red;'>✗ $file not found</p>\n";
    }
}

// Check 5: Script files
echo "<h2>5. Script Files Check</h2>\n";
$script_files = [
    'update_database_for_logo.php',
    'test_logo_functionality.php',
    'test_db_connection.php',
    'INSTALL_LOGO_FEATURE.md',
    'LOGO_FEATURE_SUMMARY.md'
];

foreach ($script_files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✓ $file exists</p>\n";
    } else {
        echo "<p style='color: red;'>✗ $file not found</p>\n";
    }
}

// Check 6: Form enctype in school settings
echo "<h2>6. Form Configuration Check</h2>\n";
$school_settings_content = file_get_contents('admin/school_settings.php');
if (strpos($school_settings_content, 'enctype="multipart/form-data"') !== false) {
    echo "<p style='color: green;'>✓ School settings form has multipart encoding for file uploads</p>\n";
} else {
    echo "<p style='color: red;'>✗ School settings form missing multipart encoding</p>\n";
}

// Check 7: Logo input field
if (strpos($school_settings_content, 'input type="file"') !== false) {
    echo "<p style='color: green;'>✓ Logo file input field found in school settings</p>\n";
} else {
    echo "<p style='color: red;'>✗ Logo file input field not found in school settings</p>\n";
}

echo "<h2>Implementation Status</h2>\n";
echo "<p>All necessary code changes have been implemented. To complete the installation:</p>\n";
echo "<ol>\n";
echo "<li>Run the database update to add the logo_path column to school_config table</li>\n";
echo "<li>Ensure the uploads directory exists and is writable</li>\n";
echo "<li>Log in as admin and upload a school logo through School Settings</li>\n";
echo "</ol>\n";

echo "<p>Refer to INSTALL_LOGO_FEATURE.md for detailed installation instructions.</p>\n";
?>