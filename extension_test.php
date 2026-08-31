<?php
echo "<h2>PHP Extension Test</h2>";

// Check extension directory
echo "<p>Extension directory: " . ini_get('extension_dir') . "</p>";

// Try to load extensions manually
echo "<p>Attempting to load MySQL extensions...</p>";

// Check if extensions are already loaded
$extensions = get_loaded_extensions();
sort($extensions);

echo "<p>Currently loaded extensions:</p>";
echo "<ul style='columns: 3;'>";
foreach($extensions as $extension) {
    echo "<li>" . $extension . "</li>";
}
echo "</ul>";

// Check specifically for MySQL-related extensions
$mysql_extensions = ['mysqli', 'pdo', 'pdo_mysql', 'mysqlnd'];
echo "<p>MySQL-related extensions status:</p>";
echo "<ul>";
foreach($mysql_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<li style='color: green;'>✓ " . $ext . " (loaded)</li>";
    } else {
        echo "<li style='color: red;'>✗ " . $ext . " (not loaded)</li>";
    }
}
echo "</ul>";

// Try to manually load extensions (this might not work in all environments)
if (!extension_loaded('mysqli')) {
    echo "<p>Attempting to load mysqli extension...</p>";
    // Note: dl() function is disabled in many environments for security reasons
    // This is just for testing purposes
    if (function_exists('dl')) {
        if (dl('php_mysqli.dll')) {
            echo "<p style='color: green;'>✓ mysqli extension loaded successfully</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to load mysqli extension</p>";
        }
    } else {
        echo "<p>dl() function not available - cannot dynamically load extensions</p>";
    }
}

echo "<p>PHP info for debugging:</p>";
echo "<pre>";
print_r([
    'PHP Version' => phpversion(),
    'SAPI' => php_sapi_name(),
    'OS' => php_uname(),
    'Loaded Configuration File' => php_ini_loaded_file(),
    'Scan this dir for additional .ini files' => php_ini_scanned_files()
]);
echo "</pre>";
?>