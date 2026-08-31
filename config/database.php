<?php
// Database configuration
if (getenv('CBT_DESKTOP_MODE') === '1' && file_exists(__DIR__ . '/desktop.php')) {
    require_once __DIR__ . '/desktop.php';
} else {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'cbt_exam_system');
}

// Simple connection pool to reuse connections within a single request
static $db_connection_pool = [];

// Simple logging function for debugging
function logMessage($message, $level = 'INFO') {
    $log_file = __DIR__ . '/../logs/app.log';
    
    // Create logs directory if it doesn't exist
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
    
    // Append to log file
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Create connection using MySQLi with persistent connections and connection pooling
function getDBConnection() {
    global $db_connection_pool;
    
    // Create a unique key for this connection
    $connection_key = DB_HOST . ':' . DB_USER . ':' . DB_NAME;
    
    // Check if we already have a connection in our pool
    if (isset($db_connection_pool[$connection_key])) {
        $mysqli = $db_connection_pool[$connection_key];
        
        // Check if connection is still alive
        try {
            if ($mysqli && $mysqli->ping()) {
                logMessage("Reusing existing database connection", "DEBUG");
                return $mysqli;
            }
        } catch (mysqli_sql_exception $e) {
            logMessage("Database connection lost (ping failed): " . $e->getMessage(), "DEBUG");
            // Fall through to remove from pool and create new connection
        }
        
        // If connection is dead, remove it from pool
        logMessage("Removing dead database connection from pool", "DEBUG");
        unset($db_connection_pool[$connection_key]);
    }
    
    // Check if MySQLi is available
    if (class_exists('mysqli')) {
        // Use persistent connection to reduce connection overhead
        $dbPort = defined('DB_PORT') ? DB_PORT : 3306;
        $mysqli = new mysqli('p:' . DB_HOST, DB_USER, DB_PASS, DB_NAME, $dbPort);
        
        if ($mysqli->connect_error) {
            logMessage("Database connection failed: " . $mysqli->connect_error, "ERROR");
            // If connection fails, return false to indicate database is not available
            return false;
        }
        
        logMessage("Created new database connection", "DEBUG");
        
        // Set charset to utf8mb4 to support all Unicode characters including Arabic
        $mysqli->set_charset("utf8mb4");
        
        // Increase timeout limits to handle larger content
        $mysqli->query("SET SESSION wait_timeout = 600");
        $mysqli->query("SET SESSION interactive_timeout = 600");
        $mysqli->query("SET SESSION net_read_timeout = 600");
        $mysqli->query("SET SESSION net_write_timeout = 600");
        
        // Set autocommit to true to ensure transactions are handled properly
        $mysqli->autocommit(true);
        
        // Ping the connection to ensure it's alive
        try {
            if (!$mysqli->ping()) {
                logMessage("Database connection ping failed", "ERROR");
                return false;
            }
        } catch (mysqli_sql_exception $e) {
            logMessage("Database connection ping failed with exception: " . $e->getMessage(), "ERROR");
            return false;
        }
        
        // Store connection in pool for reuse
        $db_connection_pool[$connection_key] = $mysqli;
        
        return $mysqli;
    }
    
    // If MySQLi is not available, return false to indicate database is not available
    logMessage("MySQLi extension not available", "ERROR");
    return false;
}

// Helper function to check if database connection is alive and reconnect if needed
function ensureDBConnection(&$mysqli) {
    // Check if connection is still alive
    try {
        if ($mysqli && !$mysqli->ping()) {
            logMessage("Database connection lost, attempting to reconnect", "WARN");
            
            // Close the dead connection
            $mysqli->close();
            
            // Try to reconnect
            $mysqli = getDBConnection();
            
            if ($mysqli !== false) {
                logMessage("Database reconnection successful", "INFO");
            } else {
                logMessage("Database reconnection failed", "ERROR");
            }
            
            return $mysqli !== false;
        }
    } catch (mysqli_sql_exception $e) {
        logMessage("Database connection lost (exception on ping): " . $e->getMessage(), "WARN");
        
        // Try to reconnect
        $mysqli = getDBConnection();
        return $mysqli !== false;
    }
    return true;
}

// Helper function to execute queries with automatic reconnection
function executeQueryWithReconnect($mysqli, $query, $params = []) {
    logMessage("Executing query: " . substr($query, 0, 100) . (strlen($query) > 100 ? '...' : ''), "DEBUG");
    
    // Ensure connection is alive
    if (!ensureDBConnection($mysqli)) {
        logMessage("Database connection lost and reconnection failed", "ERROR");
        throw new Exception("Database connection lost and reconnection failed");
    }
    
    // Prepare statement
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        logMessage("Failed to prepare statement: " . $mysqli->error, "ERROR");
        throw new Exception("Failed to prepare statement: " . $mysqli->error);
    }
    
    // Bind parameters if provided
    if (!empty($params)) {
        $types = str_repeat('s', count($params)); // Assume all parameters are strings for simplicity
        $stmt->bind_param($types, ...$params);
    }
    
    // Execute statement
    if (!$stmt->execute()) {
        logMessage("Query execution failed: " . $stmt->error, "ERROR");
        
        // Check if it's a connection error
        if (strpos($stmt->error, 'server has gone away') !== false || 
            strpos($stmt->error, 'Lost connection') !== false) {
            
            logMessage("Attempting to reconnect to database", "WARN");
            
            // Try to reconnect
            $mysqli = getDBConnection();
            if ($mysqli) {
                logMessage("Reconnected to database, retrying query", "INFO");
                
                // Retry the query
                $stmt = $mysqli->prepare($query);
                if ($stmt) {
                    if (!empty($params)) {
                        $types = str_repeat('s', count($params));
                        $stmt->bind_param($types, ...$params);
                    }
                    if (!$stmt->execute()) {
                        logMessage("Query failed after reconnection: " . $stmt->error, "ERROR");
                        throw new Exception("Query failed after reconnection: " . $stmt->error);
                    }
                } else {
                    logMessage("Failed to prepare statement after reconnection: " . $mysqli->error, "ERROR");
                    throw new Exception("Failed to prepare statement after reconnection: " . $mysqli->error);
                }
            } else {
                logMessage("Reconnection failed: " . $stmt->error, "ERROR");
                throw new Exception("Reconnection failed: " . $stmt->error);
            }
        } else {
            throw new Exception("Query failed: " . $stmt->error);
        }
    }
    
    return $stmt;
}

function dbColumnExists($mysqli, $table, $column) {
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return !empty($row['count']);
}

function ensureTheoryExamSchema($mysqli) {
    if (!$mysqli) {
        return;
    }

    if (!dbColumnExists($mysqli, 'questions', 'order_position')) {
        $mysqli->query("ALTER TABLE questions ADD COLUMN order_position INT(11) DEFAULT NULL AFTER exam_subject_id");
    }
    if (!dbColumnExists($mysqli, 'questions', 'question_type')) {
        $mysqli->query("ALTER TABLE questions ADD COLUMN question_type ENUM('objective','theory') NOT NULL DEFAULT 'objective' AFTER order_position");
    }
    if (!dbColumnExists($mysqli, 'questions', 'theory_marks')) {
        $mysqli->query("ALTER TABLE questions ADD COLUMN theory_marks DECIMAL(6,2) DEFAULT NULL AFTER correct_answer");
    }
    if (!dbColumnExists($mysqli, 'questions', 'marking_scheme')) {
        $mysqli->query("ALTER TABLE questions ADD COLUMN marking_scheme TEXT DEFAULT NULL AFTER theory_marks");
    }

    // Theory questions do not have options or one fixed correct option.
    $mysqli->query("ALTER TABLE questions MODIFY option_a TEXT NULL");
    $mysqli->query("ALTER TABLE questions MODIFY option_b TEXT NULL");
    $mysqli->query("ALTER TABLE questions MODIFY option_c TEXT NULL");
    $mysqli->query("ALTER TABLE questions MODIFY option_d TEXT NULL");
    $mysqli->query("ALTER TABLE questions MODIFY correct_answer ENUM('A','B','C','D','E','F') NULL");

    if (!dbColumnExists($mysqli, 'student_answers', 'answer_text')) {
        $mysqli->query("ALTER TABLE student_answers ADD COLUMN answer_text LONGTEXT DEFAULT NULL AFTER selected_answer");
    }
    if (!dbColumnExists($mysqli, 'student_answers', 'awarded_marks')) {
        $mysqli->query("ALTER TABLE student_answers ADD COLUMN awarded_marks DECIMAL(6,2) DEFAULT NULL AFTER is_correct");
    }
    if (!dbColumnExists($mysqli, 'student_answers', 'teacher_comment')) {
        $mysqli->query("ALTER TABLE student_answers ADD COLUMN teacher_comment TEXT DEFAULT NULL AFTER awarded_marks");
    }
    if (!dbColumnExists($mysqli, 'student_answers', 'marked_by')) {
        $mysqli->query("ALTER TABLE student_answers ADD COLUMN marked_by INT(11) DEFAULT NULL AFTER teacher_comment");
    }
    if (!dbColumnExists($mysqli, 'student_answers', 'marked_at')) {
        $mysqli->query("ALTER TABLE student_answers ADD COLUMN marked_at TIMESTAMP NULL DEFAULT NULL AFTER marked_by");
    }

    $mysqli->query("ALTER TABLE student_answers MODIFY selected_answer ENUM('A','B','C','D','E','F') NULL");
    $mysqli->query("ALTER TABLE exam_attempts MODIFY score DECIMAL(8,2) DEFAULT NULL");
}

// Get default time limit from school configuration
function getDefaultTimeLimit() {
    $mysqli = getDBConnection();
    if ($mysqli) {
        $stmt = $mysqli->prepare("SELECT default_time_limit FROM school_config LIMIT 1");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                $mysqli->close();
                return (int)$row['default_time_limit'];
            }
            $stmt->close();
        }
        $mysqli->close();
    }
    // Return default value of 60 minutes if not found
    return 60;
}

// Check if database is available
function isDatabaseAvailable() {
    $mysqli = getDBConnection();
    if ($mysqli) {
        $mysqli->close();
        return true;
    }
    return false;
}

// Mock database functions for when database is not available
class MockMySQLi {
    public function prepare($sql) {
        return new MockMySQLiStmt();
    }
    
    public function query($sql) {
        return new MockMySQLiResult();
    }
    
    public function close() {
        return true;
    }
    
    public function __get($name) {
        if ($name === 'connect_error') {
            return null;
        }
        return null;
    }
}

class MockMySQLiStmt {
    public function bind_param($types, ...$vars) {
        return true;
    }
    
    public function execute() {
        return true;
    }
    
    public function get_result() {
        return new MockMySQLiResult();
    }
    
    public function close() {
        return true;
    }
}

class MockMySQLiResult {
    public function fetch_assoc() {
        return null;
    }
    
    public function fetch_all($resulttype = null) {
        return [];
    }
    
    public function __get($name) {
        if ($name === 'num_rows') {
            return 0;
        }
        return 0;
    }
}

// Alternative PDO connection (if PDO MySQL driver is available)
function getPDOConnection() {
    try {
        $dbPort = defined('DB_PORT') ? DB_PORT : 3306;
        $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . $dbPort . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        // If PDO fails, return false
        return false;
    }
}
?>
