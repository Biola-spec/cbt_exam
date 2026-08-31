<?php
// Test file to verify the upload endpoint is working
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Upload Endpoint</title>
</head>
<body>
    <h1>Test Upload Endpoint</h1>
    
    <form id="testForm">
        <input type="file" id="testImage" accept="image/*" required>
        <button type="submit">Test Upload</button>
    </form>
    
    <div id="result"></div>
    
    <script>
    document.getElementById('testForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const fileInput = document.getElementById('testImage');
        const file = fileInput.files[0];
        
        if (!file) {
            document.getElementById('result').innerHTML = '<p style="color: red;">Please select a file</p>';
            return;
        }
        
        const formData = new FormData();
        formData.append('image', file);
        formData.append('action', 'upload_image');
        
        fetch('../upload_image.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', [...response.headers.entries()]);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                document.getElementById('result').innerHTML = `
                    <p style="color: green;">Upload successful!</p>
                    <p>URL: ${data.url}</p>
                    <img src="${data.url}" style="max-width: 300px;">
                `;
            } else {
                document.getElementById('result').innerHTML = `<p style="color: red;">Upload failed: ${data.error}</p>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('result').innerHTML = `<p style="color: red;">Error: ${error.message}</p>`;
        });
    });
    </script>
</body>
</html>