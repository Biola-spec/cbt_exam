<?php
require_once 'config/database.php';
require_once 'config/session.php';

// Check if user is logged in and is teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    die('Access denied');
}

echo "<h2>Image Upload Diagnostic Test</h2>";
echo "<hr>";

// Test 1: Check uploads directory
$uploadDir = __DIR__ . '/uploads/';
echo "<h3>Test 1: Uploads Directory</h3>";
if (is_dir($uploadDir)) {
    echo "✓ Uploads directory exists: $uploadDir<br>";
    if (is_writable($uploadDir)) {
        echo "✓ Uploads directory is writable<br>";
    } else {
        echo "✗ Uploads directory is NOT writable<br>";
    }
} else {
    echo "✗ Uploads directory does NOT exist<br>";
    if (mkdir($uploadDir, 0755, true)) {
        echo "✓ Created uploads directory<br>";
    } else {
        echo "✗ Failed to create uploads directory<br>";
    }
}

// Test 2: Check GD library
echo "<h3>Test 2: GD Library</h3>";
if (extension_loaded('gd')) {
    echo "✓ GD extension is loaded<br>";
} else {
    echo "✗ GD extension is NOT loaded<br>";
}

// Test 3: Check fileinfo
echo "<h3>Test 3: FileInfo Extension</h3>";
if (function_exists('finfo_open')) {
    echo "✓ FileInfo extension is available<br>";
} else {
    echo "✗ FileInfo extension is NOT available<br>";
}

// Test 4: Check PHP upload settings
echo "<h3>Test 4: PHP Upload Settings</h3>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";
echo "file_uploads: " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "<br>";

// Test 5: Check upload_image.php accessibility
echo "<h3>Test 5: Upload Endpoint</h3>";
$uploadEndpoint = 'http://' . $_SERVER['HTTP_HOST'] . '/cbt_exam/teacher/upload_image.php';
echo "Upload endpoint URL: <a href='$uploadEndpoint' target='_blank'>$uploadEndpoint</a><br>";

// Test 6: Simulate upload
echo "<h3>Test 6: Manual Upload Test</h3>";
?>

<form method="POST" enctype="multipart/form-data">
    <div class="mb-3">
        <label>Select Image:</label>
        <input type="file" name="test_image" accept="image/*" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary">Test Upload</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_image'])) {
    echo "<hr><h3>Upload Result:</h3>";
    
    $file = $_FILES['test_image'];
    
    echo "<pre>";
    print_r($file);
    echo "</pre>";
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        echo "✓ File uploaded successfully<br>";
        echo "File size: " . number_format($file['size']) . " bytes<br>";
        echo "Temp location: " . $file['tmp_name'] . "<br>";
        
        // Try to move it
        $fileName = 'test_' . uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $target = $uploadDir . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $target)) {
            echo "✓ File moved to: $target<br>";
            echo "File URL: /cbt_exam/uploads/$fileName<br>";
            
            // Show image
            echo "<img src='/cbt_exam/uploads/$fileName' style='max-width: 300px; border: 2px solid green;'>";
        } else {
            echo "✗ Failed to move file<br>";
        }
    } else {
        echo "✗ Upload error code: " . $file['error'] . "<br>";
    }
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #333; }
h3 { color: #666; margin-top: 20px; }
pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
</style>
