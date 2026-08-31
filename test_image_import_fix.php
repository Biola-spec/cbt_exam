<?php
// Test file for improved image import functionality
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test: Improved Image Import</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.ckeditor.com/4.25.1-lts/full-all/ckeditor.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4"><i class="fas fa-image me-2"></i>Improved Image Import Test</h2>
        
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Testing Enhanced Image Import</h5>
            </div>
            <div class="card-body">
                <p>This test demonstrates the improved image import functionality with better editor initialization handling.</p>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle me-2"></i>
                    The "Import Image" button will be enabled once the editor is fully loaded.
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
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="importImageBtn" disabled>
                            <i class="fas fa-image me-1"></i> Import Image
                        </button>
                        <small class="text-muted ms-2">Supports PNG, JPG, GIF, and other image formats</small>
                    </div>
                    <textarea class="form-control" id="passage_content" name="passage_content" rows="8">
&lt;p&gt;This is a sample passage with an image:&lt;/p&gt;
&lt;img src="https://via.placeholder.com/300x200/28a745/ffffff?text=Test+Image" alt="Test Image" style="height:200px; width:300px"&gt;
&lt;p&gt;Click the "Import Image" button above to add more images.&lt;/p&gt;
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
        
        // Initialize editor immediately and then again after a delay to ensure CKEditor is loaded
        initializePassageEditor();
        setTimeout(initializePassageEditor, 1000);
        setTimeout(initializePassageEditor, 3000); // Additional attempt after 3 seconds
        
        // Handle the import image button click
        const importImageBtn = document.getElementById('importImageBtn');
        if (importImageBtn) {
            importImageBtn.addEventListener('click', function() {
                // Check if CKEditor is initialized
                if (CKEDITOR.instances.passage_content) {
                    // Trigger the image button in CKEditor toolbar
                    CKEDITOR.instances.passage_content.execCommand('image');
                } else {
                    // Try to initialize CKEditor one more time
                    initializePassageEditor();
                    
                    // Wait a bit and try again
                    setTimeout(function() {
                        if (CKEDITOR.instances.passage_content) {
                            CKEDITOR.instances.passage_content.execCommand('image');
                        } else {
                            // Show a more helpful message with retry option
                            if (confirm('Editor is still loading. Would you like to try again now?')) {
                                // Try one final initialization
                                initializePassageEditor();
                                
                                // Give it a moment and then try to open the dialog
                                setTimeout(function() {
                                    if (CKEDITOR.instances.passage_content) {
                                        CKEDITOR.instances.passage_content.execCommand('image');
                                    } else {
                                        alert('The editor is still initializing. Please wait a few seconds and try again.');
                                    }
                                }, 500);
                            }
                        }
                    }, 500);
                }
            });
        }
        
        // Enable the import button once CKEditor is ready
        function enableImportButton() {
            if (importImageBtn && CKEDITOR.instances.passage_content) {
                importImageBtn.disabled = false;
                importImageBtn.classList.remove('btn-outline-secondary');
                importImageBtn.classList.add('btn-outline-primary');
            }
        }
        
        // Check periodically if CKEditor is ready
        const editorCheckInterval = setInterval(function() {
            if (CKEDITOR.instances.passage_content) {
                enableImportButton();
                clearInterval(editorCheckInterval);
            }
        }, 500);
        
        // Stop checking after 10 seconds to prevent infinite loop
        setTimeout(function() {
            clearInterval(editorCheckInterval);
        }, 10000);
    });
    </script>
</body>
</html>