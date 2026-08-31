<?php
// Demo file for passage image import functionality
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo: Passage Image Import</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.ckeditor.com/4.25.1-lts/full-all/ckeditor.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4"><i class="fas fa-image me-2"></i>Passage Image Import Demo</h2>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">How to Import Images into Passage Content</h5>
            </div>
            <div class="card-body">
                <ol>
                    <li>Click the "Import Image" button below to open the image dialog</li>
                    <li>Enter an image URL or browse to upload an image file</li>
                    <li>Adjust image properties (size, alignment, etc.) as needed</li>
                    <li>Click "OK" to insert the image into your passage</li>
                    <li>Save the passage when finished</li>
                </ol>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Supported formats:</strong> PNG, JPG, JPEG, GIF, SVG, and other common image formats
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
                        <button type="button" class="btn btn-outline-primary btn-sm" id="importImageBtn">
                            <i class="fas fa-image me-1"></i> Import Image
                        </button>
                        <small class="text-muted ms-2">Click to add images to your passage</small>
                    </div>
                    <textarea class="form-control" id="passage_content" name="passage_content" rows="8">
&lt;p&gt;This is a sample passage with an image:&lt;/p&gt;
&lt;img src="https://via.placeholder.com/300x200/007bff/ffffff?text=Sample+Image" alt="Sample Image" style="height:200px; width:300px"&gt;
&lt;p&gt;You can add more images using the "Import Image" button above.&lt;/p&gt;
                    </textarea>
                </div>
                <button type="button" class="btn btn-success">
                    <i class="fas fa-save me-1"></i> Save Passage
                </button>
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
        
        // Handle the import image button click
        const importImageBtn = document.getElementById('importImageBtn');
        if (importImageBtn) {
            importImageBtn.addEventListener('click', function() {
                // Check if CKEditor is initialized
                if (CKEDITOR.instances.passage_content) {
                    // Trigger the image button in CKEditor toolbar
                    CKEDITOR.instances.passage_content.execCommand('image');
                } else {
                    // Fallback: show alert if CKEditor is not ready
                    alert('Editor is still loading. Please try again in a moment.');
                }
            });
        }
    });
    </script>
</body>
</html>