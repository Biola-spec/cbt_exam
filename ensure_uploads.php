<?php
// Ensure uploads directory exists with proper permissions
echo "<h2>Setting Up Uploads Directory</h2>";
echo "<hr>";

$uploadDir = __DIR__ . '/uploads';

echo "<h3>Step 1: Checking Directory...</h3>";

if (!is_dir($uploadDir)) {
    echo "<p>Creating uploads directory...</p>";
    if (@mkdir($uploadDir, 0755, true)) {
        echo "<p style='color: green;'>✓ Directory created successfully</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to create directory. Manual creation required.</p>";
        echo "<p>Path needed: <code>" . $uploadDir . "</code></p>";
    }
} else {
    echo "<p style='color: green;'>✓ Directory already exists</p>";
}

echo "<h3>Step 2: Checking Permissions...</h3>";

if (is_writable($uploadDir)) {
    echo "<p style='color: green;'>✓ Directory is writable</p>";
} else {
    echo "<p style='color: orange;'>⚠ Directory is not writable</p>";
    echo "<p><strong>Windows Solution:</strong></p>";
    echo "<ol>";
    echo "<li>Navigate to: <code>C:\\xampp\\htdocs\\cbt_exam\\</code></li>";
    echo "<li>Right-click on <strong>uploads</strong> folder</li>";
    echo "<li>Select <strong>Properties</strong></li>";
    echo "<li>Go to <strong>Security</strong> tab</li>";
    echo "<li>Click <strong>Edit</strong></li>";
    echo "<li>Select <strong>Users</strong> group</li>";
    echo "<li>Check <strong>Full control</strong></li>";
    echo "<li>Click <strong>OK</strong></li>";
    echo "</ol>";
    
    echo "<p><strong>Or run this PowerShell command as Administrator:</strong></p>";
    echo "<pre style='background: #f0f0f0; padding: 10px; border-left: 4px solid orange;'>icacls \"" . $uploadDir . "\" /grant Users:F /T</pre>";
}

echo "<h3>Step 3: Testing File Creation...</h3>";

$testFile = $uploadDir . '/test_' . uniqid() . '.txt';
if (@file_put_contents($testFile, 'Test file')) {
    echo "<p style='color: green;'>✓ Can write files to directory</p>";
    @unlink($testFile);
} else {
    echo "<p style='color: red;'>✗ Cannot write files. Fix permissions as shown above.</p>";
}

echo "<h3>Step 4: PHP Configuration Check...</h3>";
echo "<ul>";
echo "<li><strong>GD Library:</strong> " . (extension_loaded('gd') ? '<span style="color:green;">✓ Enabled</span>' : '<span style="color:red;">✗ NOT Enabled - Edit php.ini and uncomment extension=gd</span>') . "</li>";
echo "<li><strong>Fileinfo Extension:</strong> " . (function_exists('finfo_open') ? '<span style="color:green;">✓ Enabled</span>' : '<span style="color:orange;">⚠ Not available (will use fallback)</span>') . "</li>";
echo "<li><strong>Upload Max Filesize:</strong> " . ini_get('upload_max_filesize') . "</li>";
echo "<li><strong>Post Max Size:</strong> " . ini_get('post_max_size') . "</li>";
echo "</ul>";

echo "<hr>";
echo "<h3>Summary:</h3>";
echo "<p>If all checks passed, your upload should work now!</p>";
echo "<p><a href='teacher/question_management.php' class='btn btn-primary'>Try Uploading Again</a></p>";

echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #333; }
h3 { color: #666; margin-top: 20px; }
code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style>";
?>
