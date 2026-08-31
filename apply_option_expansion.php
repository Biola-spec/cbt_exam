<?php
// Script to apply the option expansion migration to support more than 4 options
require_once 'config/database.php';

echo "Applying option expansion migration...\n";

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$mysqli = getDBConnection();
if (!$mysqli) {
    die("Database connection failed.\n");
}

echo "✓ Database connection successful\n";

// Read the SQL file
$sql_file = __DIR__ . '/database/update_questions_table_for_more_options.sql';
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
        // Check if it's a duplicate column error (columns already exist)
        if (strpos($mysqli->error, 'Duplicate column name') !== false) {
            echo "⚠ Column already exists (skipping)\n";
        } else if (strpos($mysqli->error, 'Duplicate entry') !== false) {
            echo "⚠ Entry already exists (skipping)\n";
        } else {
            echo "✗ Error: " . $mysqli->error . "\n";
            $errors++;
        }
    }
}

echo "\nMigration application complete.\n";
echo "Applied: $applied\n";
echo "Errors: $errors\n";

$mysqli->close();

if ($errors == 0) {
    echo "\n✓ Migration applied successfully!\n";
    echo "The system now supports up to 6 options (A, B, C, D, E, F) for questions.\n";
} else {
    echo "\n⚠ Some errors occurred during migration. Please check the output above.\n";
}
?>