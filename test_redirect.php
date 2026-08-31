<?php
// Test redirect functionality
echo "<h2>Redirect Test</h2>";

// Test 1: Direct redirect
echo "<p>Test 1: Direct redirect to login.php</p>";
echo "<a href='login.php' class='btn btn-primary'>Go to Login</a>";

// Test 2: Redirect with parameter
echo "<p>Test 2: Redirect with parameter</p>";
echo "<a href='login.php?test=redirect' class='btn btn-secondary'>Go to Login with Test Parameter</a>";

// Test 3: Manual redirect
echo "<p>Test 3: Manual redirect</p>";
echo "<button class='btn btn-info' onclick='window.location.href=\"login.php\"'>JavaScript Redirect</button>";

// Test 4: Meta refresh
echo "<p>Test 4: Meta refresh (5 seconds)</p>";
echo "<button class='btn btn-warning' onclick='document.querySelector(\"meta[http-equiv=refresh]\").content=\"0;url=login.php\"'>Trigger Meta Refresh</button>";
echo "<meta http-equiv='refresh' content='5;url=login.php' style='display:none;'>";

echo "<hr>";
echo "<p><strong>If none of these work, there may be an issue with your web server configuration.</strong></p>";
?>