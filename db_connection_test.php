<?php
// Test database connection
echo "Testing database connection...\n";

// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'cbt_exam_system';

echo "Attempting to connect to MySQL at $host with user $user\n";

// Try to connect
try {
    $mysqli = new mysqli($host, $user, $pass, $dbname);
    
    if ($mysqli->connect_error) {
        echo "Connection failed: " . $mysqli->connect_error . "\n";
        echo "Error code: " . $mysqli->connect_errno . "\n";
    } else {
        echo "Connected successfully!\n";
        echo "Server info: " . $mysqli->server_info . "\n";
        $mysqli->close();
    }
} catch (Exception $e) {
    echo "Exception caught: " . $e->getMessage() . "\n";
}

// Also test if MySQL is running on the standard ports
echo "\nChecking if MySQL service is listening on standard ports...\n";

$ports = [3306, 3307]; // Standard MySQL ports
foreach ($ports as $port) {
    $connection = @fsockopen($host, $port, $errno, $errstr, 5);
    if ($connection) {
        echo "Port $port is open\n";
        fclose($connection);
    } else {
        echo "Port $port is closed or blocked\n";
    }
}

echo "\nTest completed.\n";
?>