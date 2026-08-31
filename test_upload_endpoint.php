<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Upload Endpoint</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test Upload Endpoint</h1>
        
        <div class="info">
            <strong>Testing:</strong> <code>teacher/upload_image.php</code>
        </div>
        
        <?php
        // Test if file exists
        $uploadFile = __DIR__ . '/teacher/upload_image.php';
        if (file_exists($uploadFile)) {
            echo "<div class='success'>✅ Upload script exists</div>";
        } else {
            echo "<div class='error'>❌ Upload script NOT found!</div>";
        }
        
        // Test uploads directory
        $uploadsDir = __DIR__ . '/uploads';
        if (is_dir($uploadsDir)) {
            echo "<div class='success'>✅ Uploads directory exists</div>";
            
            if (is_writable($uploadsDir)) {
                echo "<div class='success'>✅ Uploads directory is writable</div>";
            } else {
                echo "<div class='error'>❌ Uploads directory is NOT writable</div>";
            }
        } else {
            echo "<div class='error'>❌ Uploads directory does NOT exist</div>";
            echo "<div class='info'>Creating directory... ";
            if (@mkdir($uploadsDir, 0755, true)) {
                echo "✅ Created successfully!</div>";
            } else {
                echo "❌ Failed to create</div>";
            }
        }
        
        // Test GD library
        if (extension_loaded('gd')) {
            echo "<div class='success'>✅ GD library is enabled</div>";
        } else {
            echo "<div class='error'>❌ GD library is NOT enabled</div>";
        }
        
        // Test Fileinfo extension
        if (function_exists('finfo_open')) {
            echo "<div class='success'>✅ Fileinfo extension is available</div>";
        } else {
            echo "<div class='info'>⚠️ Fileinfo extension not available (will use fallback)</div>";
        }
        ?>
        
        <h2>Manual Upload Test</h2>
        <form id="uploadForm" enctype="multipart/form-data">
            <div style="margin: 20px 0;">
                <label>Select an image:</label><br>
                <input type="file" name="upload" id="upload" accept="image/*" required>
            </div>
            <button type="submit">Test Upload</button>
        </form>
        
        <div id="result"></div>
        
        <hr style="margin: 30px 0;">
        
        <h2>Direct Endpoint Test</h2>
        <p>Click this button to test if the endpoint responds:</p>
        <button onclick="testEndpoint()">Test Endpoint Response</button>
        <pre id="endpointResult"></pre>
    </div>
    
    <script>
        // Test form upload
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const resultDiv = document.getElementById('result');
            
            resultDiv.innerHTML = '<div class="info">Uploading...</div>';
            
            fetch('teacher/upload_image.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Upload response:', data);
                
                if (data.url) {
                    resultDiv.innerHTML = `
                        <div class="success">
                            <strong>✅ Upload Successful!</strong><br>
                            URL: <code>${data.url}</code><br>
                            <img src="${data.url}" style="max-width: 300px; margin-top: 10px; border: 2px solid green;">
                        </div>
                    `;
                } else if (data.error && data.error.message) {
                    resultDiv.innerHTML = `<div class="error">❌ Upload Failed: ${data.error.message}</div>`;
                } else {
                    resultDiv.innerHTML = `<div class="error">❌ Unexpected response format</div><pre>${JSON.stringify(data, null, 2)}</pre>`;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `<div class="error">❌ Error: ${error.message}</div>`;
                console.error('Upload error:', error);
            });
        });
        
        // Test endpoint directly
        function testEndpoint() {
            const resultPre = document.getElementById('endpointResult');
            resultPre.textContent = 'Testing...';
            
            // Send empty POST request to see what happens
            fetch('teacher/upload_image.php', {
                method: 'POST'
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    resultPre.textContent = JSON.stringify(data, null, 2);
                } catch (e) {
                    resultPre.textContent = 'Response:\n' + text;
                }
            })
            .catch(error => {
                resultPre.textContent = 'Error: ' + error.message;
            });
        }
    </script>
</body>
</html>
