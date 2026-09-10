<?php
// Helper script to update existing database foreign keys for user references
require_once __DIR__ . '/config/database.php';

$mysqli = getDBConnection();
if (!$mysqli) {
    die("Error: Could not connect to database.\n");
}

echo "Applying foreign key fixes for user references...\n";

$fk_updates = [
    [
        'table' => 'comprehension_passages',
        'constraint' => 'comprehension_passages_ibfk_2',
        'column' => 'created_by'
    ],
    [
        'table' => 'exams',
        'constraint' => 'exams_ibfk_1',
        'column' => 'created_by'
    ],
    [
        'table' => 'questions',
        'constraint' => 'questions_ibfk_2',
        'column' => 'created_by'
    ],
    [
        'table' => 'teacher_allocations',
        'constraint' => 'teacher_allocations_ibfk_4',
        'column' => 'allocated_by'
    ],
    [
        'table' => 'exam_republish_log',
        'constraint' => 'exam_republish_log_ibfk_4',
        'column' => 'republished_by'
    ]
];

foreach ($fk_updates as $item) {
    $table = $item['table'];
    $constraint = $item['constraint'];
    $column = $item['column'];

    // Check if table exists
    $t_check = $mysqli->query("SHOW TABLES LIKE '$table'");
    if (!$t_check || $t_check->num_rows === 0) {
        continue;
    }

    // Try dropping constraint if it exists
    $mysqli->query("ALTER TABLE `$table` DROP FOREIGN KEY `$constraint`");

    // Add constraint with ON DELETE SET NULL
    $alter_sql = "ALTER TABLE `$table` ADD CONSTRAINT `$constraint` FOREIGN KEY (`$column`) REFERENCES `users` (`id`) ON DELETE SET NULL";
    if ($mysqli->query($alter_sql)) {
        echo "✓ Updated FK `$constraint` on `$table` (`$column`) -> ON DELETE SET NULL\n";
    } else {
        echo "⚠ Warning for `$table`: " . $mysqli->error . "\n";
    }
}

echo "Done!\n";
