<?php
// Test file for direct image upload functionality
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test: Direct Image Upload</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4"><i class="fas fa-upload me-2"></i>Direct Image Upload Test</h2>
        
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Upload Images Directly</h5>
            </div>
            <div class="card-body">
                <p>This test demonstrates uploading images directly from your computer without needing a rich text editor.</p>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    Images are converted to data URLs and inserted directly into the textarea.
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Passage Content Editor</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Passage Content</label>
                    <div class="mb-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="uploadImageBtn" title="Upload image from your computer">
                            <i class="fas fa-upload me-1"></i> Upload Image
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm ms-2" id="insertImageUrlBtn" title="Insert image from URL">
                            <i class="fas fa-link me-1"></i> Insert Image URL
                        </button>
                        <input type="file" id="imageUploadInput" accept="image/*" class="d-none" />
                        <small class="text-muted ms-2">Supports PNG, JPG, GIF, and other image formats</small>
                    </div>
                    <textarea class="form-control" id="passage_content" name="passage_content" rows="8">
&lt;p&gt;This is a sample passage with an image:&lt;/p&gt;
&lt;img src="https://via.placeholder.com/300x200/17a2b8/ffffff?text=Upload+Test" alt="Upload Test" style="height:200px; width:300px"&gt;
&lt;p&gt;Try uploading your own images using the "Upload Image" button.&lt;/p&gt;
                    </textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success">
                        <i class="fas fa-save me-1"></i> Save Passage
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="clearContent">
                        <i class="fas fa-trash me-1"></i> Clear Content
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle the image upload button click
        const uploadImageBtn = document.getElementById('uploadImageBtn');
        if (uploadImageBtn) {
            uploadImageBtn.addEventListener('click', function() {
                const imageUploadInput = document.getElementById('imageUploadInput');
                if (imageUploadInput) {
                    imageUploadInput.click();
                }
            });
        }
        
        // Handle the image upload input change
        const imageUploadInput = document.getElementById('imageUploadInput');
        if (imageUploadInput) {
            imageUploadInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    // Check if file is an image
                    if (!file.type.match('image.*')) {
                        alert('Please select an image file (PNG, JPG, GIF, etc.)');
                        return;
                    }
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const imageHtml = `<img src="${e.target.result}" alt="Uploaded Image" style="max-width: 100%; height: auto;">`;
                        const passageContent = document.getElementById('passage_content');
                        
                        // Insert at cursor position or at the end
                        const cursorPos = passageContent.selectionStart || passageContent.value.length;
                        const textBefore = passageContent.value.substring(0, cursorPos);
                        const textAfter = passageContent.value.substring(cursorPos);
                        passageContent.value = textBefore + imageHtml + textAfter;
                        
                        // Move cursor to after the inserted image
                        const newCursorPos = cursorPos + imageHtml.length;
                        passageContent.setSelectionRange(newCursorPos, newCursorPos);
                        
                        // Clear the input to allow uploading the same file again
                        imageUploadInput.value = '';
                        
                        alert('Image uploaded successfully!');
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Handle insert image URL button
        const insertImageUrlBtn = document.getElementById('insertImageUrlBtn');
        if (insertImageUrlBtn) {
            insertImageUrlBtn.addEventListener('click', function() {
                const imageUrl = prompt('Enter the URL of the image you want to insert:');
                if (imageUrl) {
                    // Validate URL format (basic validation)
                    if (imageUrl.match(/^https?:\/\/.+\.(jpg|jpeg|png|gif|bmp|webp|svg)/i)) {
                        const imageHtml = `<img src="${imageUrl}" alt="Inserted Image" style="max-width: 100%; height: auto;">`;
                        const passageContent = document.getElementById('passage_content');
                        
                        // Insert at cursor position or at the end
                        const cursorPos = passageContent.selectionStart || passageContent.value.length;
                        const textBefore = passageContent.value.substring(0, cursorPos);
                        const textAfter = passageContent.value.substring(cursorPos);
                        passageContent.value = textBefore + imageHtml + textAfter;
                        
                        // Move cursor to after the inserted image
                        const newCursorPos = cursorPos + imageHtml.length;
                        passageContent.setSelectionRange(newCursorPos, newCursorPos);
                        
                        alert('Image inserted successfully!');
                    } else {
                        alert('Please enter a valid image URL (should end with .jpg, .png, .gif, etc.)');
                    }
                }
            });
        }
        
        // Handle clear content button
        const clearContentBtn = document.getElementById('clearContent');
        if (clearContentBtn) {
            clearContentBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to clear all content?')) {
                    const passageContent = document.getElementById('passage_content');
                    if (passageContent) {
                        passageContent.value = '';
                    }
                }
            });
        }
    });
    </script>
</body>
</html>