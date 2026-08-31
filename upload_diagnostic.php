<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Diagnostic Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .status { padding: 15px; margin: 15px 0; border-radius: 5px; }
        .success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .check { color: #28a745; font-weight: bold; }
        .cross { color: #dc3545; font-weight: bold; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #1e7e34; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Image Upload Diagnostic Tool</h1>
        
        <?php
        $allChecksPassed = true;
        
        // Check 1: GD Library
        echo "<h2>1. GD Library Status</h2>";
        if (extension_loaded('gd')) {
            echo "<div class='status success'>";
            echo "<strong>✅ GD Library is ENABLED</strong><br>";
            $gd_info = gd_info();
            echo "Version: " . $gd_info['GD Version'];
            echo "</div>";
        } else {
            echo "<div class='status error'>";
            echo "<strong>❌ GD Library is NOT Enabled</strong><br>";
            echo "This is likely why your image upload is failing.<br><br>";
            echo "<strong>Action Required:</strong><br>";
            echo "1. Open XAMPP Control Panel<br>";
            echo "2. Click 'Stop' for Apache<br>";
            echo "3. Wait 2 seconds<br>";
            echo "4. Click 'Start' for Apache<br>";
            echo "5. Refresh this page<br>";
            echo "</div>";
            $allChecksPassed = false;
        }
        
        // Check 2: Uploads Directory
        echo "<h2>2. Uploads Directory</h2>";
        $uploadDir = __DIR__ . '/uploads';
        if (is_dir($uploadDir)) {
            echo "<div class='status success'>";
            echo "<strong>✅ Directory exists:</strong> " . realpath($uploadDir) . "<br>";
            
            if (is_writable($uploadDir)) {
                echo "<strong>✅ Directory is writable</strong><br>";
                
                // Count existing files
                $files = glob($uploadDir . '/*');
                echo "<strong>📁 Files in uploads:</strong> " . count($files) . " files";
            } else {
                echo "<div class='status error'>";
                echo "<strong>❌ Directory is NOT writable</strong><br>";
                echo "Fix: Run this in PowerShell as Administrator:<br>";
                echo "<code>icacls \"" . $uploadDir . "\" /grant Users:F /T</code>";
                echo "</div>";
                $allChecksPassed = false;
            }
            echo "</div>";
        } else {
            echo "<div class='status error'>";
            echo "<strong>❌ Directory does not exist</strong><br>";
            echo "Creating directory... ";
            if (@mkdir($uploadDir, 0755, true)) {
                echo "<strong style='color:green;'>✅ Created successfully!</strong>";
            } else {
                echo "<strong style='color:red;'>❌ Failed to create</strong>";
                $allChecksPassed = false;
            }
            echo "</div>";
        }
        
        // Check 3: PHP Configuration
        echo "<h2>3. PHP Configuration</h2>";
        echo "<table>";
        echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";
        
        $uploadMax = ini_get('upload_max_filesize');
        $postMax = ini_get('post_max_size');
        $displayErrors = ini_get('display_errors');
        $maxExecutionTime = ini_get('max_execution_time');
        $memoryLimit = ini_get('memory_limit');
        
        echo "<tr>";
        echo "<td>Upload Max Filesize</td>";
        echo "<td>" . ($uploadMax ?: 'Not set (uses php.ini default)') . "</td>";
        echo "<td>" . (version_compare($uploadMax, '2M', '>=') ? "<span class='check'>✅ OK</span>" : "<span class='warning'>⚠️ May be too small</span>") . "</td>";
        echo "</tr>";
        
        echo "<tr>";
        echo "<td>Post Max Size</td>";
        echo "<td>" . ($postMax ?: 'Not set (uses php.ini default)') . "</td>";
        echo "<td>" . (version_compare($postMax, '8M', '>=') ? "<span class='check'>✅ OK</span>" : "<span class='warning'>⚠️ May be too small</span>") . "</td>";
        echo "</tr>";
        
        echo "<tr>";
        echo "<td>Display Errors</td>";
        echo "<td>" . ($displayErrors ? 'ON' : 'OFF') . "</td>";
        echo "<td>" . (!$displayErrors ? "<span class='check'>✅ Good (prevents JSON corruption)</span>" : "<span class='warning'>⚠️ Should be OFF for production</span>") . "</td>";
        echo "</tr>";
        
        echo "<tr>";
        echo "<td>Max Execution Time</td>";
        echo "<td>" . $maxExecutionTime . " seconds</td>";
        echo "<td>" . ($maxExecutionTime >= 30 ? "<span class='check'>✅ OK</span>" : "<span class='warning'>⚠️ Consider increasing</span>") . "</td>";
        echo "</tr>";
        
        echo "<tr>";
        echo "<td>Memory Limit</td>";
        echo "<td>" . $memoryLimit . "</td>";
        echo "<td>" . (version_compare($memoryLimit, '128M', '>=') ? "<span class='check'>✅ OK</span>" : "<span class='warning'>⚠️ May need more for image processing</span>") . "</td>";
        echo "</tr>";
        
        echo "</table>";
        
        // Check 4: Fileinfo Extension
        echo "<h2>4. Fileinfo Extension</h2>";
        if (function_exists('finfo_open')) {
            echo "<div class='status success'>";
            echo "<strong>✅ Fileinfo is enabled</strong><br>";
            echo "This allows proper MIME type detection for uploaded files.";
            echo "</div>";
        } else {
            echo "<div class='status warning'>";
            echo "<strong>⚠️ Fileinfo is NOT available</strong><br>";
            echo "The upload script will use extension-based fallback validation.";
            echo "</div>";
        }
        
        // Check 5: Test Upload Form
        echo "<h2>5. Test File Upload</h2>";
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_upload'])) {
            $file = $_FILES['test_upload'];
            
            echo "<div class='status info'>";
            echo "<strong>Upload Attempt Details:</strong><br>";
            echo "File Name: " . htmlspecialchars($file['name']) . "<br>";
            echo "File Type: " . htmlspecialchars($file['type']) . "<br>";
            echo "File Size: " . number_format($file['size']) . " bytes<br>";
            echo "Temp Name: " . htmlspecialchars($file['tmp_name']) . "<br>";
            echo "Upload Error Code: " . $file['error'] . "<br>";
            echo "</div>";
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                echo "<div class='status error'>";
                echo "<strong>❌ Upload Failed!</strong><br>";
                
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize limit',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE form limit',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'PHP extension blocked the upload'
                ];
                
                if (isset($errorMessages[$file['error']])) {
                    echo "<strong>Error:</strong> " . $errorMessages[$file['error']];
                }
                echo "</div>";
                $allChecksPassed = false;
            } else {
                // Try to save the file
                $targetName = 'test_' . time() . '_' . basename($file['name']);
                $targetPath = $uploadDir . '/' . $targetName;
                
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    echo "<div class='status success'>";
                    echo "<strong>✅ Upload Successful!</strong><br>";
                    echo "File saved as: <code>" . htmlspecialchars($targetName) . "</code><br>";
                    echo "File size: " . number_format(filesize($targetPath)) . " bytes<br>";
                    
                    // Show image preview if it's an image
                    $imageInfo = @getimagesize($targetPath);
                    if ($imageInfo) {
                        echo "<br><img src='uploads/" . htmlspecialchars($targetName) . "' style='max-width: 300px; border: 2px solid green;'><br>";
                    }
                    
                    // Clean up
                    @unlink($targetPath);
                    echo "<br><em>Test file deleted after verification.</em>";
                    echo "</div>";
                } else {
                    echo "<div class='status error'>";
                    echo "<strong>❌ Failed to Save File</strong><br>";
                    echo "Check directory permissions and error logs.";
                    echo "</div>";
                    $allChecksPassed = false;
                }
            }
        } else {
            echo "<form method='POST' enctype='multipart/form-data' style='background: #f8f9fa; padding: 20px; border-radius: 5px;'>";
            echo "<label><strong>Select an image to test upload:</strong></label><br>";
            echo "<input type='file' name='test_upload' accept='image/*' required style='margin: 10px 0;'><br>";
            echo "<button type='submit' class='btn btn-success'>Test Upload Now</button>";
            echo "</form>";
        }
        
        // Final Summary
        echo "<hr style='margin: 30px 0;'>";
        echo "<h2>📊 Summary</h2>";
        
        if ($allChecksPassed) {
            echo "<div class='status success'>";
            echo "<h3>✅ All Checks Passed!</h3>";
            echo "<p>Your system is properly configured for image uploads.</p>";
            echo "<p><strong>Next Steps:</strong></p>";
            echo "<ol>";
            echo "<li>Go to Question Management page</li>";
            echo "<li>Try uploading MARYAM.jpg using CKEditor</li>";
            echo "<li>If it still fails, check browser console (F12) for JavaScript errors</li>";
            echo "</ol>";
            echo "</div>";
        } else {
            echo "<div class='status error'>";
            echo "<h3>❌ Some Checks Failed</h3>";
            echo "<p>Please address the issues highlighted above before testing uploads.</p>";
            echo "<p><strong>Most Common Issue:</strong></p>";
            echo "<p>If GD library shows as NOT ENABLED, you need to restart Apache:</p>";
            echo "<ol>";
            echo "<li>Open XAMPP Control Panel</li>";
            echo "<li>Click 'Stop' for Apache</li>";
            echo "<li>Wait 2 seconds</li>";
            echo "<li>Click 'Start' for Apache</li>";
            echo "<li>Refresh this page</li>";
            echo "</ol>";
            echo "</div>";
        }
        
        echo "<div style='margin-top: 30px;'>";
        echo "<a href='teacher/question_management.php' class='btn btn-success'>Go to Question Management</a>";
        echo "<a href='QUICK_START_CKEDITOR.md' class='btn'>View Quick Start Guide</a>";
        echo "</div>";
        ?>
    </div>
</body>
</html>
