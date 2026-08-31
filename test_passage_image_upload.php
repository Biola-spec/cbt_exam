<?php
// Test page for passage image upload functionality
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Passage Image Upload</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.ckeditor.com/4.25.1-lts/full-all/ckeditor.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4"><i class="fas fa-image me-2"></i>Test Passage Image Upload</h2>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Passage Image Upload Test</h5>
            </div>
            <div class="card-body">
                <p>This page demonstrates the improved passage image upload functionality that shows images immediately after upload, similar to the school logo feature.</p>
                
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
                                <input type="file" id="imageUploadInput" accept="image/*" class="d-none" />
                                <small class="text-muted ms-2">Supports PNG, JPG, GIF, and other image formats</small>
                            </div>
                            <textarea class="form-control" id="passage_content" name="passage_content" rows="8">
&lt;p&gt;This is a sample passage with an image:&lt;/p&gt;
&lt;img src="https://via.placeholder.com/300x200/007bff/ffffff?text=Sample+Image" alt="Sample Image" style="height:200px; width:300px"&gt;
&lt;p&gt;Try uploading your own images using the "Upload Image" button above.&lt;/p&gt;
                            </textarea>
                        </div>
                        <button type="button" class="btn btn-success">
                            <i class="fas fa-save me-1"></i> Save Passage
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize CKEditor for passage content
        function initializePassageEditor() {
            if (typeof CKEDITOR !== 'undefined' && document.getElementById('passage_content')) {
                // Configuration for CKEditor with enhanced image support
                const ckConfig = {
                    height: 250,
                    toolbar: [
                        { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike'] },
                        { name: 'paragraph', items: ['NumberedList', 'BulletedList', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock'] },
                        { name: 'links', items: ['Link', 'Unlink'] },
                        { name: 'insert', items: ['Image', 'Table', 'HorizontalRule'] },
                        { name: 'clipboard', items: ['Undo', 'Redo'] },
                        { name: 'editing', items: ['Find', 'Replace'] },
                        '/',
                        { name: 'styles', items: ['Format', 'FontSize'] },
                        { name: 'colors', items: ['TextColor', 'BGColor'] },
                        { name: 'tools', items: ['Maximize'] }
                    ],
                    // Enhanced image handling
                    filebrowserUploadUrl: '#',
                    filebrowserImageUploadUrl: '#',
                    // Allow all image attributes and styles
                    extraAllowedContent: 'img[*]{*}(*)',
                    allowedContent: true,
                    // Image plugin configuration
                    image_previewText: ' ',
                    // Remove element path to save space
                    removePlugins: 'elementspath'
                };
                
                // Initialize CKEditor for passage content
                if (!CKEDITOR.instances.passage_content) {
                    CKEDITOR.replace('passage_content', ckConfig);
                }
            }
        }
        
        // Initialize editor after a delay to ensure CKEditor is loaded
        setTimeout(initializePassageEditor, 1000);
        
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
                    
                    // Show uploading indicator
                    const uploadImageBtn = document.getElementById('uploadImageBtn');
                    const originalButtonText = uploadImageBtn.innerHTML;
                    uploadImageBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Uploading...';
                    uploadImageBtn.disabled = true;
                    
                    // For large images, we'll upload directly to server instead of using base64
                    const formData = new FormData();
                    formData.append('image', file);
                    formData.append('action', 'upload_image');
                    
                    fetch('upload_image.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        // Check if response is OK
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.status + ' ' + response.statusText);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Insert image URL into passage content
                            const imageHtml = `<img src="${data.url}" alt="Uploaded Image" style="max-width: 100%; height: auto; border: 2px dashed #007bff; padding: 5px; margin: 5px;">`;
                            const passageContent = document.getElementById('passage_content');
                            
                            // For CKEditor, we need to use its API
                            if (CKEDITOR.instances.passage_content) {
                                CKEDITOR.instances.passage_content.insertHtml(imageHtml);
                            } else {
                                // For regular textarea, insert at cursor position
                                const cursorPos = passageContent.selectionStart || passageContent.value.length;
                                const textBefore = passageContent.value.substring(0, cursorPos);
                                const textAfter = passageContent.value.substring(cursorPos);
                                passageContent.value = textBefore + imageHtml + textAfter;
                                
                                // Move cursor to after the inserted image
                                const newCursorPos = cursorPos + imageHtml.length;
                                passageContent.setSelectionRange(newCursorPos, newCursorPos);
                            }
                            
                            // Clear the input to allow uploading the same file again
                            imageUploadInput.value = '';
                            
                            // Show success message
                            alert('Image uploaded and inserted successfully!');
                        } else {
                            alert('Error uploading image: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error uploading image: ' + error.message + '. Please try again.');
                    })
                    .finally(() => {
                        // Restore button state
                        uploadImageBtn.innerHTML = originalButtonText;
                        uploadImageBtn.disabled = false;
                    });
                }
            });
        }
    });
    </script>
</body>
</html>