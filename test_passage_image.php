<?php
// Test file to demonstrate image handling in passage content
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Passage with Image</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.ckeditor.com/4.25.1-lts/full-all/ckeditor.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h2>Test Passage with Image</h2>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Passage Editor with Image Support</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="passage_content" class="form-label">Passage Content</label>
                        <textarea class="form-control" id="passage_content" name="passage_content" rows="10" placeholder="Enter passage content here...">
&lt;p&gt;This is a sample passage with an image:&lt;/p&gt;
&lt;img src="https://via.placeholder.com/300x200" alt="Sample Image" width="300" height="200"&gt;
&lt;p&gt;Images can be inserted using the image button in the toolbar.&lt;/p&gt;
                        </textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Passage</button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5>Preview</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5 class="alert-heading">Comprehension Passage</h5>
                    <div class="bg-light p-3 rounded">
                        <?php 
                        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['passage_content'])) {
                            // Simulate the sanitizeHtml function from take_exam.php
                            function sanitizeHtml($text) {
                                // Allow only safe HTML tags (like img, p, br, strong, em)
                                $allowed_tags = '<p><br><strong><em><img><span><div><ul><ol><li><h1><h2><h3><h4><h5><h6>';
                                $text = strip_tags($text, $allowed_tags);
                                
                                // Additional security: Remove any javascript or on* attributes
                                $text = preg_replace('/(<[^>]+) on[a-zA-Z]*="[^"]*"/i', '$1', $text);
                                $text = preg_replace("/(<[^>]+) on[a-zA-Z]*='[^']*'/i", '$1', $text);
                                
                                return $text;
                            }
                            
                            echo sanitizeHtml($_POST['passage_content']);
                        } else {
                            echo '<p>This is a sample passage with an image:</p>';
                            echo '<img src="https://via.placeholder.com/300x200" alt="Sample Image" width="300" height="200">';
                            echo '<p>Images can be inserted using the image button in the toolbar.</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize CKEditor for passage content
        if (typeof CKEDITOR !== 'undefined' && document.getElementById('passage_content')) {
            // Configuration for CKEditor with image support
            const ckConfig = {
                height: 300,
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
                // Allow image uploads
                filebrowserUploadUrl: '#',
                filebrowserImageUploadUrl: '#',
                // Extra allowed content for images
                extraAllowedContent: 'img[*]{*}(*)',
                // Ensure proper cleanup
                allowedContent: true
            };
            
            // Initialize CKEditor for passage content
            if (!CKEDITOR.instances.passage_content) {
                CKEDITOR.replace('passage_content', ckConfig);
            }
        }
    });
    </script>
</body>
</html>