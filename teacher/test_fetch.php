<!DOCTYPE html>
<html>
<head>
    <title>Test Fetch</title>
</head>
<body>
    <h1>Test Fetch Request</h1>
    
    <button id="testBtn">Test Fetch</button>
    <div id="result"></div>
    
    <script>
    document.getElementById('testBtn').addEventListener('click', function() {
        fetch('../upload_image.php', {
            method: 'POST',
            body: new FormData()
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', [...response.headers.entries()]);
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status + ' ' + response.statusText);
            }
            return response.text();
        })
        .then(data => {
            document.getElementById('result').innerHTML = '<p style="color: green;">Success: ' + data + '</p>';
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('result').innerHTML = '<p style="color: red;">Error: ' + error.message + '</p>';
        });
    });
    </script>
</body>
</html>