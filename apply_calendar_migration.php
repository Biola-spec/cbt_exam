<?php
require_once 'config/database.php';

echo "Applying school calendar migration...\n";

try {
    $mysqli = getDBConnection();
    if (!$mysqli) {
        die("Failed to connect to database.\n");
    }

    $sql = file_get_contents(__DIR__ . '/database/add_school_calendar.sql');
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $stmt) {
        if (empty($stmt)) continue;
        if ($mysqli->query($stmt)) {
            echo "OK: Statement executed successfully.\n";
        } else {
            echo "WARN: " . $mysqli->error . "\n";
        }
    }

    echo "\nMigration completed. Inserting sample data...\n";

    $check = $mysqli->query("SELECT COUNT(*) as cnt FROM calendar_events");
    if ($check && $check->fetch_assoc()['cnt'] == 0) {
        $today = date('Y-m-d');
        $thisMonth = date('Y-m-01');
        $nextMonth = date('Y-m-01', strtotime('+1 month'));
        $monthAfter = date('Y-m-01', strtotime('+2 months'));

        $samples = [
            ['First Day of Term', 'Welcome back to school!', $thisMonth, null, 'activity', '#28a745', null, 1],
            ['Staff Meeting', 'Monthly staff briefing', date('Y-m-05', strtotime($thisMonth)), null, 'meeting', '#ffc107', null, 0],
            ['Midterm Exams', 'Midterm assessment period', date('Y-m-15', strtotime($nextMonth)), date('Y-m-20', strtotime($nextMonth)), 'exam', '#dc3545', null, 1],
            ['Public Holiday', 'Independence Day holiday', date('Y-m-10', strtotime($monthAfter)), null, 'holiday', '#6f42c1', null, 1],
            ['Parent-Teacher Conference', 'Meet with parents to discuss progress', date('Y-m-25', strtotime($monthAfter)), null, 'meeting', '#fd7e14', null, 1],
        ];

        $insert = $mysqli->prepare("INSERT INTO calendar_events (event_title, event_description, event_date, end_date, event_type, event_color, class_id, is_public, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $created_by = 1;
        $class_id = null;

        foreach ($samples as $s) {
            $insert->bind_param("ssssssiii", $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $class_id, $s[7], $created_by);
            $insert->execute();
        }
        echo "Sample calendar events inserted.\n";
    }

    $cfgCheck = $mysqli->query("SELECT COUNT(*) as cnt FROM school_config");
    if ($cfgCheck && $cfgCheck->fetch_assoc()['cnt'] > 0) {
        $thisMonthStart = date('Y-m-01');
        $monthsCount = 4;
        $update = $mysqli->prepare("UPDATE school_config SET calendar_start_month = ?, calendar_months_count = ? WHERE calendar_start_month IS NULL");
        $update->bind_param("si", $thisMonthStart, $monthsCount);
        $update->execute();
        echo "Default calendar configuration set (4 months starting from current month).\n";
    }

    echo "\nAll done!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
