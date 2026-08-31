<?php
// Suppress any PHP warnings/notices that could break JSON
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
header('Content-Type: application/json');

// CONFIG
$uploadDir = __DIR__ . '/uploads/';
$maxFileSize = 2 * 1024 * 1024; // 2MB

// Allowed MIME types
$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp'
];

// Helper function to send JSON response
function jsonResponse($success, $data = [], $error = null) {
    echo json_encode(array_merge(['success' => $success], $data, $error ? ['error' => $error] : []));
    exit;
}

// Check if this is a CKEditor 5 simpleUpload request (no action parameter)
// CKEditor 5 sends file as 'upload' field, not 'image'
$isCKEditorRequest = !isset($_POST['action']);

if ($isCKEditorRequest) {
    // CKEditor 5 or TinyMCE upload request
    if (isset($_FILES['upload'])) {
        $file = $_FILES['upload']; // CKEditor
    } elseif (isset($_FILES['file'])) {
        $file = $_FILES['file']; // TinyMCE
    } else {
        echo json_encode(['error' => ['message' => 'No file uploaded']]);
        exit;
    }
} else {
    // Legacy manual upload request
    if ($_POST['action'] !== 'upload_image') {
        jsonResponse(false, [], 'Invalid action');
    }
    if (!isset($_FILES['image'])) {
        jsonResponse(false, [], 'No file uploaded');
    }
    $file = $_FILES['image'];
}

// Check upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $error_message = 'Upload failed';
    switch ($file['error']) {
        case UPLOAD_ERR_INI_SIZE:
            $error_message = 'File exceeds server limit';
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $error_message = 'File exceeds form limit';
            break;
        case UPLOAD_ERR_PARTIAL:
            $error_message = 'File partially uploaded';
            break;
        case UPLOAD_ERR_NO_FILE:
            $error_message = 'No file uploaded';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $error_message = 'Missing temporary folder';
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $error_message = 'Failed to write file to disk';
            break;
    }
    jsonResponse(false, [], $error_message);
}

// Check file size
if ($file['size'] > $maxFileSize) {
    jsonResponse(false, [], 'File too large (Max 2MB)');
}

// Validate MIME type (REAL check)
if (!function_exists('finfo_open')) {
    // Fallback if finfo extension not available
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($file_extension, $allowed_extensions)) {
        jsonResponse(false, [], 'Invalid image type. Allowed: JPG, PNG, GIF, WebP');
    }
    $mimeType = $file['type'];
} else {
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mimeType = @finfo_file($finfo, $file['tmp_name']);
        @finfo_close($finfo);
    } else {
        $mimeType = $file['type'];
    }
    
    // If mimeType is empty or not in allowed list, try extension-based validation
    if (empty($mimeType) || !array_key_exists($mimeType, $allowedTypes)) {
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $extensionMimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];
        
        if (!in_array($file_extension, array_keys($extensionMimeMap))) {
            jsonResponse(false, [], 'Invalid image type. Allowed: JPG, PNG, GIF, WebP');
        }
        
        $mimeType = $extensionMimeMap[$file_extension] ?? $file['type'];
    }
}

// Generate unique file name
$extension = isset($allowedTypes[$mimeType]) ? $allowedTypes[$mimeType] : strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$fileName = uniqid('img_', true) . '.' . $extension;
$targetPath = $uploadDir . $fileName;

// Ensure upload directory exists
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        jsonResponse(false, [], 'Upload directory does not exist and could not be created');
    }
} elseif (!is_writable($uploadDir)) {
    jsonResponse(false, [], 'Upload directory is not writable');
}

// IMAGE OPTIMIZATION - Compress and optimize images
function compressImage($source, $destination, $mimeType, $quality = 75) {
    try {
        // Check if GD is available
        if (!extension_loaded('gd') || !function_exists('imagecreatefromjpeg')) {
            return false; // GD not available, will use direct move
        }
        
        switch ($mimeType) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($source);
                if ($image === false) throw new Exception('Failed to load JPEG');
                imagejpeg($image, $destination, $quality);
                break;

            case 'image/png':
                $image = @imagecreatefrompng($source);
                if ($image === false) throw new Exception('Failed to load PNG');
                // Preserve transparency for PNG
                @imagealphablending($image, false);
                @imagesavealpha($image, true);
                imagepng($image, $destination, 6);
                break;

            case 'image/gif':
                $image = @imagecreatefromgif($source);
                if ($image === false) throw new Exception('Failed to load GIF');
                imagegif($image, $destination);
                break;

            case 'image/webp':
                $image = @imagecreatefromwebp($source);
                if ($image === false) throw new Exception('Failed to load WebP');
                imagewebp($image, $destination, $quality);
                break;

            default:
                return false;
        }
        @imagedestroy($image);
        return true;
    } catch (Exception $e) {
        error_log('Image compression error: ' . $e->getMessage());
        return false;
    }
}

// Move & compress image
if (extension_loaded('gd') && function_exists('compressImage') && compressImage($file['tmp_name'], $targetPath, $mimeType)) {
    // Successfully compressed
} else {
    // Fallback: Try direct move if GD compression fails or not available
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        jsonResponse(false, [], 'Failed to save image. Check directory permissions.');
    }
}

// Verify file was saved
if (!file_exists($targetPath)) {
    jsonResponse(false, [], 'Failed to save image file');
}

// Return URL (relative to site root)
$imageUrl = '/cbt_exam/uploads/' . $fileName;

// Return response based on request type
if ($isCKEditorRequest) {
    // CKEditor 5 and TinyMCE compatibility response
    header('Content-Type: application/json');
    echo json_encode([
        'default' => $imageUrl,
        'url' => $imageUrl,
        'location' => $imageUrl // Required by TinyMCE
    ]);
} else {
    // Legacy manual upload response
    jsonResponse(true, [
        'url' => $imageUrl,
        'mime_type' => $mimeType,
        'size' => filesize($targetPath)
    ]);
}
exit;
?>