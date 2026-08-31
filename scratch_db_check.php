<?php
$m = new mysqli('localhost', 'root', '', 'cbt_exam_system');
if ($m->connect_error) die("Connection failed: " . $m->connect_error);

foreach (['users', 'classes', 'subjects', 'teacher_allocations'] as $table) {
    echo "\n--- Schema for table: $table ---\n";
    $res = $m->query("DESCRIBE `$table`");
    while($row = $res->fetch_assoc()) {
        print_r($row);
    }
}
?>
