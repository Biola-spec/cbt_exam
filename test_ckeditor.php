<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CKEditor Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>🧪 CKEditor Test Page</h1>
        
        <div class="alert alert-info">
            <strong>Status:</strong> <span id="status">Checking...</span>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5>Test Editor</h5>
            </div>
            <div class="card-body">
                <label for="test_editor"><strong>Passage Content:</strong></label>
                <textarea id="test_editor" name="test_editor" rows="6"></textarea>
            </div>
        </div>
        
        <div class="mt-3">
            <button class="btn btn-primary" onclick="getContent()">Get Content</button>
            <button class="btn btn-secondary" onclick="setContent()">Set Content</button>
        </div>
        
        <div class="mt-3">
            <h5>Console Output:</h5>
            <pre id="output" class="bg-light p-3" style="min-height: 100px;"></pre>
        </div>
    </div>
    
    <!-- Load CKEditor -->
    <script src="https://cdn.ckeditor.com/ckeditor5/41.0.1/classic/ckeditor.js"></script>
    
    <script>
        let editorInstance = null;
        
        // Initialize CKEditor
        ClassicEditor
            .create(document.querySelector('#test_editor'), {
                simpleUpload: {
                    uploadUrl: 'teacher/upload_image.php'
                },
                toolbar: [
                    'heading', '|',
                    'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|',
                    'blockQuote', 'insertTable', '|',
                    'imageUpload', 'mediaEmbed', '|',
                    'undo', 'redo'
                ]
            })
            .then(editor => {
                console.log('✅ CKEditor initialized successfully:', editor);
                document.getElementById('status').innerHTML = '<strong style="color: green;">✅ CKEditor Loaded Successfully!</strong>';
                document.getElementById('status').className = 'text-success';
                editorInstance = editor;
                
                // Store instance
                window.testEditor = editor;
                
                log('CKEditor Version: ' + (editor.constructor.version || 'Latest'));
                log('Toolbar items: ' + editor.ui.componentFactory._definitions.size);
            })
            .catch(error => {
                console.error('❌ CKEditor initialization error:', error);
                document.getElementById('status').innerHTML = '<strong style="color: red;">❌ Failed to load CKEditor</strong>';
                document.getElementById('status').className = 'text-danger';
                log('Error: ' + error.message);
            });
        
        function getContent() {
            if (editorInstance) {
                const content = editorInstance.getData();
                log('Content:\n' + content);
            } else {
                log('Editor not initialized!');
            }
        }
        
        function setContent() {
            if (editorInstance) {
                const sampleContent = '<p>This is a <strong>test</strong> passage with an image:</p><img src="uploads/test.jpg" alt="Test image">';
                editorInstance.setData(sampleContent);
                log('Content set:\n' + sampleContent);
            } else {
                log('Editor not initialized!');
            }
        }
        
        function log(message) {
            const output = document.getElementById('output');
            output.textContent += message + '\n\n';
            output.scrollTop = output.scrollHeight;
        }
    </script>
</body>
</html>
