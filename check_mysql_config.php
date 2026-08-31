<?php
// Script to check current MySQL configuration
include 'config/database.php';

echo "<h1>MySQL Configuration Checker</h1>";

$mysqli = getDBConnection();

if ($mysqli) {
    echo "<p style='color: green;'>✓ Successfully connected to database</p>";
    
    // Check current configuration values
    $configs = [
        'max_allowed_packet' => 'Maximum packet size',
        'wait_timeout' => 'Wait timeout',
        'interactive_timeout' => 'Interactive timeout'
    ];
    
    echo "<h2>Current MySQL Configuration:</h2>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Variable</th><th>Description</th><th>Current Value</th><th>Recommended</th></tr>";
    
    foreach ($configs as $variable => $description) {
        $result = $mysqli->query("SHOW VARIABLES LIKE '$variable'");
        if ($result && $row = $result->fetch_assoc()) {
            $current_value = $row['Value'];
            $recommended = getRecommendedValue($variable);
            $status = isValueAdequate($variable, $current_value) ? 
                "<span style='color: green;'>✓ Adequate</span>" : 
                "<span style='color: orange;'>⚠ Low</span>";
            
            echo "<tr>";
            echo "<td><code>$variable</code></td>";
            echo "<td>$description</td>";
            echo "<td>$current_value $status</td>";
            echo "<td>$recommended</td>";
            echo "</tr>";
        }
    }
    
    echo "</table>";
    
    // Additional recommendations
    echo "<h2>Recommendations:</h2>";
    echo "<ul>";
    echo "<li>If values are marked as 'Low', consider updating your MySQL configuration file</li>";
    echo "<li>For XAMPP users, edit <code>C:\\xampp\\mysql\\bin\\my.ini</code></li>";
    echo "<li>Add the following to the <code>[mysqld]</code> section:<br>";
    echo "<pre>";
    echo "max_allowed_packet = 64M<br>";
    echo "wait_timeout = 600<br>";
    echo "interactive_timeout = 600";
    echo "</pre></li>";
    echo "<li>After editing, restart the MySQL service</li>";
    echo "</ul>";
    
    $mysqli->close();
} else {
    echo "<p style='color: red;'>✗ Failed to connect to database</p>";
    echo "<p>Please ensure MySQL is running and the database configuration is correct.</p>";
}

function getRecommendedValue($variable) {
    switch ($variable) {
        case 'max_allowed_packet':
            return '64M or higher';
        case 'wait_timeout':
            return '600 or higher';
        case 'interactive_timeout':
            return '600 or higher';
        default:
            return 'N/A';
    }
}

function isValueAdequate($variable, $value) {
    switch ($variable) {
        case 'max_allowed_packet':
            // Convert to bytes for comparison
            $bytes = convertToBytes($value);
            return $bytes >= 32 * 1024 * 1024; // At least 32MB
        case 'wait_timeout':
        case 'interactive_timeout':
            return $value >= 300; // At least 5 minutes
        default:
            return true;
    }
}

function convertToBytes($value) {
    $unit = strtolower(substr($value, -1));
    $number = substr($value, 0, -1);
    
    switch ($unit) {
        case 'g':
            return $number * 1024 * 1024 * 1024;
        case 'm':
            return $number * 1024 * 1024;
        case 'k':
            return $number * 1024;
        default:
            return $value;
    }
}
?>