<!DOCTYPE html>
<html>
<head>
    <title>Simple Image Upload Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Simple Image Upload Test</h1>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Upload Image</h5>
            </div>
            <div class="card-body">
                <form id="uploadForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="image" class="form-label">Select Image</label>
                        <input class="form-control" type="file" id="image" name="image" accept="image/*" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Upload Image</button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5>Result</h5>
            </div>
            <div class="card-body">
                <div id="result"></div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            const fileInput = document.getElementById('image');
            const file = fileInput.files[0];
            
            if (!file) {
                document.getElementById('result').innerHTML = '<div class="alert alert-danger">Please select a file</div>';
                return;
            }
            
            formData.append('image', file);
            formData.append('action', 'upload_image');
            
            fetch('upload_image.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const resultHtml = `
                        <div class="alert alert-success">
                            <h5>Image uploaded successfully!</h5>
                            <p>Image URL: ${data.url}</p>
                            <img src="${data.url}" alt="Uploaded Image" class="img-fluid" style="max-width: 300px;">
                        </div>
                    `;
                    document.getElementById('result').innerHTML = resultHtml;
                } else {
                    document.getElementById('result').innerHTML = `<div class="alert alert-danger">Error: ${data.error}</div>`;
                }
            })
            .catch(error => {
                document.getElementById('result').innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
            });
        });
    </script>
</body>
</html>