<!DOCTYPE html>
<html>
<head>
    <title>Test Image Upload</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Test Image Upload</h1>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Upload Image</h5>
            </div>
            <div class="card-body">
                <form id="uploadForm">
                    <div class="mb-3">
                        <label for="image" class="form-label">Select Image</label>
                        <input class="form-control" type="file" id="image" accept="image/*" required>
                        <div class="form-text">Supported formats: JPG, PNG, GIF, WebP (max 5MB)</div>
                    </div>
                    <button type="submit" class="btn btn-primary">Upload Image</button>
                </form>
                
                <div id="result" class="mt-3"></div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5>Uploaded Image</h5>
            </div>
            <div class="card-body">
                <div id="imagePreview" class="text-center">
                    <p class="text-muted">No image uploaded yet</p>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        const imageFile = document.getElementById('image').files[0];
        
        if (!imageFile) {
            document.getElementById('result').innerHTML = '<div class="alert alert-warning">Please select an image file</div>';
            return;
        }
        
        formData.append('image', imageFile);
        formData.append('action', 'upload_image');
        
        fetch('../upload_image.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status + ' ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                document.getElementById('result').innerHTML = '<div class="alert alert-success">Image uploaded successfully!</div>';
                document.getElementById('imagePreview').innerHTML = `<img src="../${data.url}" alt="Uploaded Image" class="img-fluid">`;
            } else {
                document.getElementById('result').innerHTML = `<div class="alert alert-danger">Error: ${data.error}</div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('result').innerHTML = `<div class="alert alert-danger">Error uploading image: ${error.message}</div>`;
        });
    });
    </script>
</body>
</html>