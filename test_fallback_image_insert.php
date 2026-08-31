<?php
// Test file for fallback image insertion method
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test: Fallback Image Insertion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.ckeditor.com/4.25.1-lts/full-all/ckeditor.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4"><i class="fas fa-image me-2"></i>Fallback Image Insertion Test</h2>
        
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Alternative Image Insertion Method</h5>
            </div>
            <div class="card-body">
                <p>This test demonstrates a fallback method for inserting images when the rich text editor fails to load.</p>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Use the "Insert Image URL" button to add images directly using a URL, even without the editor.
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
                        <button type="button" class="btn btn-outline-primary btn-sm ms-2" id="simpleImageInsertBtn" title="Simple image insertion (fallback method)">
                            <i class="fas fa-plus-circle me-1"></i> Insert Image URL
                        </button>
                        <small class="text-muted ms-2">Supports PNG, JPG, GIF, and other image formats</small>
                    </div>
                    <textarea class="form-control" id="passage_content" name="passage_content" rows="8">
&lt;p&gt;This is a sample passage with an image:&lt;/p&gt;
&lt;img src="https://via.placeholder.com/300x200/28a745/ffffff?text=Fallback+Test" alt="Fallback Test" style="height:200px; width:300px"&gt;
&lt;p&gt;Try inserting more images using the "Insert Image URL" button.&lt;/p&gt;
                    </textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success">
                        <i class="fas fa-save me-1"></i> Save Passage
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="resetEditor">
                        <i class="fas fa-redo me-1"></i> Reset Editor
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let editorInitializationAttempts = 0;
        const maxInitializationAttempts = 5;
        
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
                
                // Check if editor is already initialized
                if (!CKEDITOR.instances.passage_content) {
                    try {
                        CKEDITOR.replace('passage_content', ckConfig);
                        editorInitializationAttempts++;
                        console.log('CKEditor initialization attempt ' + editorInitializationAttempts);
                    } catch (error) {
                        console.error('CKEditor initialization error:', error);
                        editorInitializationAttempts++;
                    }
                } else {
                    console.log('CKEditor already initialized');
                    enableImportButton();
                }
            } else {
                console.log('CKEditor not available or passage_content element not found');
                editorInitializationAttempts++;
            }
        }
        
        // Enable the import button once CKEditor is ready
        function enableImportButton() {
            const importImageBtn = document.getElementById('importImageBtn');
            if (importImageBtn && CKEDITOR.instances.passage_content) {
                importImageBtn.disabled = false;
                importImageBtn.classList.remove('btn-outline-secondary');
                importImageBtn.classList.add('btn-outline-primary');
                importImageBtn.title = 'Click to import an image into the passage';
                console.log('Import button enabled');
            }
        }
        
        // Disable the import button
        function disableImportButton() {
            const importImageBtn = document.getElementById('importImageBtn');
            if (importImageBtn) {
                importImageBtn.disabled = true;
                importImageBtn.classList.remove('btn-outline-primary');
                importImageBtn.classList.add('btn-outline-secondary');
                importImageBtn.title = 'Editor is loading...';
            }
        }
        
        // Simple image insertion function (fallback method)
        function insertImageSimple() {
            const imageUrl = prompt('Enter the URL of the image you want to insert:');
            if (imageUrl) {
                // Validate URL format (basic validation)
                if (imageUrl.match(/^https?:\/\/.+\.(jpg|jpeg|png|gif|bmp|webp|svg)/i)) {
                    const imageHtml = `<img src="${imageUrl}" alt="Inserted Image" style="max-width: 100%; height: auto;">`;
                    const passageContent = document.getElementById('passage_content');
                    
                    // If CKEditor is available and initialized, use it
                    if (CKEDITOR.instances.passage_content) {
                        const editor = CKEDITOR.instances.passage_content;
                        editor.insertHtml(imageHtml);
                    } else {
                        // Fallback to direct textarea manipulation
                        const cursorPos = passageContent.selectionStart || 0;
                        const textBefore = passageContent.value.substring(0, cursorPos);
                        const textAfter = passageContent.value.substring(cursorPos);
                        passageContent.value = textBefore + imageHtml + textAfter;
                        
                        // Move cursor to after the inserted image
                        const newCursorPos = cursorPos + imageHtml.length;
                        passageContent.setSelectionRange(newCursorPos, newCursorPos);
                    }
                    
                    alert('Image inserted successfully!');
                } else {
                    alert('Please enter a valid image URL (should end with .jpg, .png, .gif, etc.)');
                }
            }
        }
        
        // Reset editor function
        function resetEditor() {
            // Destroy existing editor instance if it exists
            if (CKEDITOR.instances.passage_content) {
                CKEDITOR.instances.passage_content.destroy();
            }
            
            // Reset counters
            editorInitializationAttempts = 0;
            
            // Re-disable button
            disableImportButton();
            
            // Re-initialize
            initializePassageEditor();
            
            // Try to initialize multiple times with different intervals
            const initIntervals = [500, 1000, 2000, 3000, 5000];
            initIntervals.forEach((delay, index) => {
                setTimeout(() => {
                    if (!CKEDITOR.instances.passage_content && editorInitializationAttempts < maxInitializationAttempts) {
                        initializePassageEditor();
                    }
                }, delay);
            });
        }
        
        // Initialize editor with more aggressive attempts
        disableImportButton();
        initializePassageEditor();
        
        // Try to initialize multiple times with different intervals
        const initIntervals = [500, 1000, 2000, 3000, 5000];
        initIntervals.forEach((delay, index) => {
            setTimeout(() => {
                if (!CKEDITOR.instances.passage_content && editorInitializationAttempts < maxInitializationAttempts) {
                    initializePassageEditor();
                }
            }, delay);
        });
        
        // Check periodically if CKEditor is ready
        const editorCheckInterval = setInterval(function() {
            if (CKEDITOR.instances.passage_content) {
                enableImportButton();
                clearInterval(editorCheckInterval);
            } else if (editorInitializationAttempts >= maxInitializationAttempts) {
                // Show error message if too many attempts
                const importImageBtn = document.getElementById('importImageBtn');
                if (importImageBtn) {
                    importImageBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Editor Failed';
                    importImageBtn.classList.remove('btn-outline-secondary');
                    importImageBtn.classList.add('btn-outline-danger');
                    importImageBtn.title = 'Failed to load editor. Please refresh the page.';
                }
                clearInterval(editorCheckInterval);
            }
        }, 500);
        
        // Stop checking after 15 seconds to prevent infinite loop
        setTimeout(function() {
            clearInterval(editorCheckInterval);
            // If editor still not ready, enable button anyway but show warning
            if (!CKEDITOR.instances.passage_content) {
                const importImageBtn = document.getElementById('importImageBtn');
                if (importImageBtn) {
                    importImageBtn.disabled = false;
                    importImageBtn.classList.remove('btn-outline-secondary');
                    importImageBtn.classList.add('btn-outline-warning');
                    importImageBtn.title = 'Editor may not be fully loaded. Click to try manual initialization.';
                }
            }
        }, 15000);
        
        // Handle the import image button click
        const importImageBtn = document.getElementById('importImageBtn');
        if (importImageBtn) {
            importImageBtn.addEventListener('click', function() {
                // Check if CKEditor is initialized
                if (CKEDITOR.instances.passage_content) {
                    try {
                        // Trigger the image button in CKEditor toolbar
                        CKEDITOR.instances.passage_content.execCommand('image');
                    } catch (error) {
                        console.error('Error executing image command:', error);
                        alert('Failed to open image dialog. Please try again.');
                    }
                } else {
                    // If editor is not ready, try to initialize it
                    if (editorInitializationAttempts < maxInitializationAttempts) {
                        alert('Initializing editor... Please click the button again in a moment.');
                        initializePassageEditor();
                    } else {
                        // Offer manual refresh option
                        if (confirm('The editor failed to initialize. Would you like to refresh the page to try again?')) {
                            location.reload();
                        } else {
                            // Try one more time
                            editorInitializationAttempts = 0;
                            initializePassageEditor();
                            alert('Retrying editor initialization... Please wait a few seconds and try again.');
                        }
                    }
                }
            });
        }
        
        // Handle the simple image insertion button click (fallback method)
        const simpleImageInsertBtn = document.getElementById('simpleImageInsertBtn');
        if (simpleImageInsertBtn) {
            simpleImageInsertBtn.addEventListener('click', insertImageSimple);
        }
        
        // Handle reset button
        const resetBtn = document.getElementById('resetEditor');
        if (resetBtn) {
            resetBtn.addEventListener('click', resetEditor);
        }
    });
    </script>
</body>
</html>