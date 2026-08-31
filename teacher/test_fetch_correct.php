<!DOCTYPE html>
<html>
<head>
    <title>Test Fetch Correct</title>
</head>
<body>
    <h1>Test Fetch Request (Correct)</h1>
    
    <input type="file" id="testFile" accept="image/*">
    <button id="testBtn">Test Fetch</button>
    <div id="result"></div>
    
    <script>
    document.getElementById('testBtn').addEventListener('click', function() {
        const fileInput = document.getElementById('testFile');
        const file = fileInput.files[0];
        
        if (!file) {
            document.getElementById('result').innerHTML = '<p style="color: red;">Please select a file</p>';
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'upload_image');
        formData.append('image', file);
        
        fetch('../upload_image.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', [...response.headers.entries()]);
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status + ' ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            console.log('Data:', data);
            if (data.success) {
                document.getElementById('result').innerHTML = '<p style="color: green;">Success: ' + JSON.stringify(data) + '</p>';
            } else {
                document.getElementById('result').innerHTML = '<p style="color: red;">Error: ' + data.error + '</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('result').innerHTML = '<p style="color: red;">Error: ' + error.message + '</p>';
        });
    });
    </script>
</body>
</html>