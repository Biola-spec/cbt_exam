<?php
require 'config/database.php';
$m = getDBConnection();

$r = $m->query('SELECT NOW() as db_time');
$db = $r->fetch_assoc()['db_time'];
$php = date('Y-m-d H:i:s');

echo "MySQL Time: $db\n";
echo "PHP Time: $php\n";

// check the exam_attempts table structure
function describe($m, $table) {
    if (!$m) return;
    $res = $m->query("SHOW CREATE TABLE $table");
    if ($row = $res->fetch_array()) {
        echo "<pre>{$row[1]}</pre>\n";
    }
}
describe($m, 'exam_attempts');
describe($m, 'exam_subjects');
