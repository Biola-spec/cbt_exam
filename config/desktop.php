<?php
// Embedded desktop runtime overrides (loaded by desktop-bundle launcher)
define('DB_HOST', getenv('CBT_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('CBT_DB_PORT') ?: 3307));
define('DB_USER', getenv('CBT_DB_USER') ?: 'root');
define('DB_PASS', getenv('CBT_DB_PASS') ?: '');
define('DB_NAME', getenv('CBT_DB_NAME') ?: 'cbt_exam_system');
