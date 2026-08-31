<?php
echo "Checking available database extensions...\n";

// Check available extensions
$extensions = get_loaded_extensions();
$database_extensions = array_filter($extensions, function($ext) {
    return stripos($ext, 'mysql') !== false || stripos($ext, 'pdo') !== false || stripos($ext, 'sqlite') !== false;
});

echo "Available database extensions:\n";
foreach ($database_extensions as $ext) {
    echo "  - $ext\n";
}

// Check specifically for MySQL extensions
echo "\nMySQL-specific checks:\n";
echo "mysqli extension: " . (extension_loaded('mysqli') ? "Available" : "Not available") . "\n";
echo "pdo_mysql extension: " . (extension_loaded('pdo_mysql') ? "Available" : "Not available") . "\n";
echo "mysql extension: " . (extension_loaded('mysql') ? "Available" : "Not available") . "\n";

// Check PHP info for database support
echo "\nChecking phpinfo for MySQL support...\n";
ob_start();
phpinfo(INFO_MODULES);
$phpinfo = ob_get_contents();
ob_end_clean();

if (strpos($phpinfo, 'mysql') !== false) {
    echo "MySQL support detected in phpinfo\n";
} else {
    echo "No MySQL support detected in phpinfo\n";
}
?>