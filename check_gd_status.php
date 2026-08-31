<?php
echo "<h2>GD Library Status</h2>";
echo "<hr>";

if (extension_loaded('gd')) {
    echo "<h3 style='color: green;'>✅ GD Library is ENABLED!</h3>";
    echo "<p>You can now upload images successfully.</p>";
    
    $gd_info = gd_info();
    echo "<h4>GD Version: " . $gd_info['GD Version'] . "</h4>";
    
    echo "<p><a href='teacher/question_management.php' class='btn btn-success'>Try Uploading Image Now</a></p>";
} else {
    echo "<h3 style='color: red;'>❌ GD Library is NOT Enabled</h3>";
    echo "<p>Please restart Apache to apply the php.ini changes.</p>";
    
    echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid orange; margin: 20px 0;'>";
    echo "<strong>To restart Apache:</strong><br>";
    echo "<ol>";
    echo "<li>Open <strong>XAMPP Control Panel</strong></li>";
    echo "<li>Click <strong>Stop</strong> button for Apache</li>";
    echo "<li>Wait 2 seconds</li>";
    echo "<li>Click <strong>Start</strong> button for Apache</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<p><button onclick='location.reload()' class='btn btn-primary'>Check Again After Restart</button></p>";
}

echo "<hr>";
echo "<p><small>PHP Version: " . phpversion() . "</small></p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #333; }
h3 { margin-top: 20px; }
.btn {
    display: inline-block;
    padding: 10px 20px;
    background: #007bff;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    border: none;
    cursor: pointer;
}
.btn:hover { background: #0056b3; }
.btn-success { background: #28a745; }
.btn-success:hover { background: #1e7e34; }
</style>
