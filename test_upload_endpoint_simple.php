<?php
// Simple test to check if upload endpoint is accessible
echo "Testing upload endpoint access...\n";

// Try to access the upload endpoint
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => 'action=test'
    ]
]);

$result = @file_get_contents('http://localhost:8000/upload_image.php', false, $context);

if ($result === false) {
    echo "Failed to access upload endpoint\n";
    $error = error_get_last();
    if ($error) {
        echo "Error: " . $error['message'] . "\n";
    }
} else {
    echo "Successfully accessed upload endpoint\n";
    echo "Response: " . $result . "\n";
}
?>