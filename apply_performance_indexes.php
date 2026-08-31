<?php
// Script to apply performance indexes to the database
require_once 'config/database.php';

echo "Applying performance indexes...\n";

$mysqli = getDBConnection();
if (!$mysqli) {
    die("Database connection failed.\n");
}

// Read the SQL file
$sql_file = __DIR__ . '/database/add_performance_indexes.sql';
if (!file_exists($sql_file)) {
    die("SQL file not found: $sql_file\n");
}

$sql_content = file_get_contents($sql_file);
if (!$sql_content) {
    die("Failed to read SQL file.\n");
}

// Split the SQL content into individual statements
$statements = explode(';', $sql_content);
$applied = 0;
$errors = 0;               

foreach ($statements as $statement) {
    $statement = trim($statement);
    
    // Skip empty statements or comments
    if (empty($statement) || strpos($statement, '--') === 0) {
        continue;
    }
    
    // Skip commented out statements
    if (strpos($statement, '--') === 0 || strpos($statement, '/*') === 0) {
        continue;
    }
    
    echo "Executing: " . substr($statement, 0, 50) . (strlen($statement) > 50 ? '...' : '') . "\n";
    
    if ($mysqli->query($statement)) {
        echo "✓ Success\n";
        $applied++;
    } else {
        // Check if it's a duplicate key error (index already exists)
        if (strpos($mysqli->error, 'Duplicate key name') !== false) {
            echo "⚠ Index already exists (skipping)\n";
        } else {
            echo "✗ Error: " . $mysqli->error . "\n";
            $errors++;
        }
    }
}

echo "\nIndex application complete.\n";
echo "Applied: $applied\n";
echo "Errors: $errors\n";

$mysqli->close();
?>