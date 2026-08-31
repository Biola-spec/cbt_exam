<?php
// Test accessing the upload endpoint directly
echo "<h1>Testing Upload Endpoint Direct Access</h1>";

// Test accessing the endpoint with a GET request
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Accept: application/json'
    ]
]);

$result = @file_get_contents('http://localhost:8000/upload_image.php', false, $context);

if ($result === false) {
    echo "<p style='color: red;'>Failed to access upload endpoint</p>";
    $error = error_get_last();
    if ($error) {
        echo "<p>Error: " . htmlspecialchars($error['message']) . "</p>";
    }
} else {
    echo "<p style='color: green;'>Successfully accessed upload endpoint</p>";
    echo "<p>Response: " . htmlspecialchars($result) . "</p>";
    
    // Check if it's valid JSON
    $json = json_decode($result, true);
    if ($json !== null) {
        echo "<p style='color: green;'>Valid JSON response</p>";
    } else {
        echo "<p style='color: orange;'>Not valid JSON</p>";
    }
}

echo "<h2>Server Status</h2>";
echo "<p>Trying to access server root...</p>";

$root_result = @file_get_contents('http://localhost:8000/');

if ($root_result === false) {
    echo "<p style='color: red;'>Failed to access server root</p>";
} else {
    echo "<p style='color: green;'>Successfully accessed server root</p>";
}
?>