<?php
// Diagnostic script to check upload configuration
echo "<h2>Image Upload Configuration Check</h2>";
echo "<hr>";

echo "<h3>1. PHP Configuration:</h3>";
echo "<ul>";
echo "<li><strong>GD Library:</strong> " . (extension_loaded('gd') ? '<span style="color:green;">✓ Enabled</span>' : '<span style="color:red;">✗ NOT Enabled</span>') . "</li>";
echo "<li><strong>Fileinfo Extension:</strong> " . (function_exists('finfo_open') ? '<span style="color:green;">✓ Enabled</span>' : '<span style="color:red;">✗ NOT Enabled</span>') . "</li>";
echo "<li><strong>Upload Max Filesize:</strong> " . ini_get('upload_max_filesize') . "</li>";
echo "<li><strong>Post Max Size:</strong> " . ini_get('post_max_size') . "</li>";
echo "<li><strong>Max File Uploads:</strong> " . ini_get('max_file_uploads') . "</li>";
echo "</ul>";

echo "<h3>2. Directory Check:</h3>";
$uploadDir = __DIR__ . '/uploads';
echo "<ul>";
echo "<li><strong>Upload Directory:</strong> " . $uploadDir . "</li>";
echo "<li><strong>Directory Exists:</strong> " . (is_dir($uploadDir) ? '<span style="color:green;">✓ Yes</span>' : '<span style="color:red;">✗ No</span>') . "</li>";

if (!is_dir($uploadDir)) {
    echo "<li><strong>Creating Directory:</strong> ";
    if (@mkdir($uploadDir, 0755, true)) {
        echo '<span style="color:green;">✓ Created Successfully</span></li>';
    } else {
        echo '<span style="color:red;">✗ Failed to Create</span></li>';
    }
} else {
    echo "<li><strong>Is Writable:</strong> " . (is_writable($uploadDir) ? '<span style="color:green;">✓ Yes</span>' : '<span style="color:red;">✗ No - CHMOD to 755 or higher</span>') . "</li>";
    
    // Try to write a test file
    $testFile = $uploadDir . '/test_' . uniqid() . '.txt';
    if (@file_put_contents($testFile, 'test')) {
        echo "<li><strong>Write Test:</strong> <span style=\"color:green;\">✓ Success</span></li>";
        @unlink($testFile);
    } else {
        echo "<li><strong>Write Test:</strong> <span style=\"color:red;\">✗ Failed</span></li>";
    }
}
echo "</ul>";

echo "<h3>3. Error Reporting Status:</h3>";
echo "<ul>";
echo "<li><strong>Display Errors:</strong> " . ini_get('display_errors') . "</li>";
echo "<li><strong>Error Reporting:</strong> " . error_reporting() . "</li>";
echo "</ul>";

echo "<h3>4. Test Upload Form:</h3>";
?>
<form method="POST" action="" enctype="multipart/form-data" class="mt-4">
    <input type="hidden" name="action" value="upload_image">
    <div class="mb-3">
        <label for="image" class="form-label">Select Image to Upload:</label>
        <input type="file" name="image" id="image" class="form-control" accept="image/*" required>
    </div>
    <button type="submit" class="btn btn-primary">Test Upload</button>
</form>

<?php
// Handle test upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_image') {
    echo "<hr>";
    echo "<h3>5. Upload Result:</h3>";
    
    if (!isset($_FILES['image'])) {
        echo "<p style='color:red;'>✗ No file received</p>";
    } else {
        $file = $_FILES['image'];
        
        echo "<pre>";
        print_r($file);
        echo "</pre>";
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo "<p style='color:red;'>✗ Upload Error: " . $file['error'] . "</p>";
        } else {
            echo "<p style='color:green;'>✓ File uploaded successfully!</p>";
            
            // Try to save it
            $testPath = $uploadDir . '/test_upload_' . uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            if (move_uploaded_file($file['tmp_name'], $testPath)) {
                echo "<p style='color:green;'>✓ File saved to: " . $testPath . "</p>";
                echo "<img src='" . str_replace(__DIR__, '', $testPath) . "' style='max-width: 200px; border: 2px solid green;'>";
                
                // Clean up
                @unlink($testPath);
            } else {
                echo "<p style='color:red;'>✗ Failed to save file</p>";
            }
        }
    }
}

echo "<hr>";
echo "<h3>6. Recommendations:</h3>";
echo "<ul>";

if (!extension_loaded('gd')) {
    echo "<li style='color:red;'><strong>CRITICAL:</strong> GD library is not enabled. Install it:<br>";
    echo "Windows XAMPP: Edit php.ini and uncomment <code>extension=gd</code><br>";
    echo "Linux: <code>sudo apt-get install php-gd</code> or <code>sudo yum install php-gd</code></li>";
}

if (!is_writable($uploadDir)) {
    echo "<li style='color:red;'><strong>CRITICAL:</strong> Upload directory is not writable. Fix permissions:<br>";
    echo "Windows: Right-click uploads folder → Properties → Security → Give Users full control<br>";
    echo "Linux: <code>chmod 755 uploads</code> or <code>chown www-data:www-data uploads</code></li>";
}

if (ini_get('display_errors')) {
    echo "<li style='color:orange;'><strong>Warning:</strong> Display errors is ON. This can break JSON responses. Turn it off in php.ini</li>";
}

echo "</ul>";

echo "<p><a href='teacher/question_management.php' class='btn btn-secondary'>Back to Question Management</a></p>";
?>
