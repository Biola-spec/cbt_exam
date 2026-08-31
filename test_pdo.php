<?php
echo "<h2>PDO MySQL Test</h2>";

// Check if PDO is available
if (!extension_loaded('pdo')) {
    echo "<p style='color: red;'>✗ PDO is not available</p>";
} else {
    echo "<p style='color: green;'>✓ PDO is available</p>";
}

// Check if PDO MySQL driver is available
if (!extension_loaded('pdo_mysql')) {
    echo "<p style='color: red;'>✗ PDO MySQL driver is not available</p>";
} else {
    echo "<p style='color: green;'>✓ PDO MySQL driver is available</p>";
}

// List all available PDO drivers
echo "<p>Available PDO drivers:</p>";
echo "<ul>";
foreach(PDO::getAvailableDrivers() as $driver) {
    echo "<li>" . $driver . "</li>";
}
echo "</ul>";

echo "<p>PHP Extensions:</p>";
echo "<ul>";
$extensions = get_loaded_extensions();
sort($extensions);
foreach($extensions as $extension) {
    echo "<li>" . $extension . "</li>";
}
echo "</ul>";
?>