<?php
header('Content-Type: application/json');

// Folder to store images
$uploadDir = __DIR__ . '/../uploads/questions/';

// Create folder if not exists
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if (isset($_FILES['upload'])) {

    $file = $_FILES['upload'];

    // Validate image
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode([
            'error' => ['message' => 'Invalid image type']
        ]);
        exit;
    }

    // Generate unique name
    $fileName = time() . '_' . basename($file['name']);
    $filePath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $filePath)) {

        // IMPORTANT: Correct URL path
        $url = 'uploads/questions/' . $fileName;

        echo json_encode([
            'url' => $url
        ]);
    } else {
        echo json_encode([
            'error' => ['message' => 'Upload failed']
        ]);
    }

} else {
    echo json_encode([
        'error' => ['message' => 'No file uploaded']
    ]);
}
