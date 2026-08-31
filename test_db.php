<?php
require 'config/database.php';
$db = getDBConnection();
$res = $db->query('SHOW COLUMNS FROM users');
while($r = $res->fetch_assoc()){
    echo $r['Field'] . " ";
}
echo "\n";