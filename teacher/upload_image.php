<?php
header('Content-Type: application/json; charset=utf-8');

function respondImageUploadError($message, $status = 400) {
    http_response_code($status);
    echo json_encode(['error' => ['message' => $message]]);
    exit;
}

// TinyMCE 5 sends `file`; CKEditor uses `upload`.
$imageField = isset($_FILES['file']) ? 'file' : (isset($_FILES['upload']) ? 'upload' : null);
if ($imageField === null || !is_array($_FILES[$imageField])) {
    respondImageUploadError('No image was uploaded.');
}

$file = $_FILES[$imageField];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    respondImageUploadError('The image upload did not complete.');
}
if (!is_uploaded_file($file['tmp_name'] ?? '')) {
    respondImageUploadError('The uploaded image could not be verified.');
}

$maxSize = 5 * 1024 * 1024;
if ((int)$file['size'] > $maxSize) {
    respondImageUploadError('Images must be 5 MB or smaller.');
}

$mimeToExtension = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp'
];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
if (!isset($mimeToExtension[$mimeType])) {
    respondImageUploadError('Use a JPG, PNG, GIF, or WebP image.');
}

$uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'questions' . DIRECTORY_SEPARATOR;
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    respondImageUploadError('The image folder could not be created.', 500);
}

$fileName = 'passage_' . bin2hex(random_bytes(12)) . '.' . $mimeToExtension[$mimeType];
if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
    respondImageUploadError('The image could not be saved.', 500);
}

// TinyMCE requires the uploaded image URL in the `location` response field.
echo json_encode(['location' => '/cbt_exam/uploads/questions/' . $fileName]);
