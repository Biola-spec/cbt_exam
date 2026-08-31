<?php
/**
 * CKEditor 5 Integration for CBT Exam System
 * Include this file in pages that need rich text editing with image upload
 */

// Determine the correct path to CKEditor based on current directory
$currentDir = dirname($_SERVER['PHP_SELF']);
if (strpos($currentDir, '/teacher') !== false || strpos($currentDir, '/admin') !== false || strpos($currentDir, '/student') !== false) {
    // We're in a subdirectory
    $ckeditorPath = '../assets/js/ckeditor.js';
} else {
    // We're in root directory
    $ckeditorPath = 'assets/js/ckeditor.js';
}
?>

<!-- CKEditor 5 Classic Editor (Local) -->
<script src="<?php echo $ckeditorPath; ?>"></script>
<script>
    // Verify CKEditor loaded
    window.addEventListener('load', function() {
        if (typeof ClassicEditor === 'undefined') {
            console.error('CKEditor failed to load from: <?php echo $ckeditorPath; ?>');
        } else {
            console.log('CKEditor loaded successfully from: <?php echo $ckeditorPath; ?>');
        }
    });
</script>

<!-- Custom CSS for CKEditor images -->
<style>
/* Responsive images in CKEditor content */
.ck-content img {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
    margin: 10px 0;
}

/* Image preview in editor */
.ck-content .image > figcaption {
    background: #f8f9fa;
    padding: 8px;
    font-size: 0.9em;
    border-top: 1px solid #dee2e6;
}

/* Loading state for uploads */
.ck-upload-placeholder-loader {
    position: relative;
}

/* Ensure images don't overflow containers */
.passage-content img,
.question-text img,
.option-text img {
    max-width: 100%;
    height: auto;
    display: block;
    margin: 15px 0;
}

/* Print-friendly images */
@media print {
    .ck-content img {
        max-width: 100% !important;
        page-break-inside: avoid;
    }
}
</style>

<!-- CKEditor Initialization Script -->
<script>
// Global CKEditor instances storage
window.ckEditors = window.ckEditors || {};

/**
 * Initialize CKEditor 5 on a textarea element
 * @param {string} elementId - The ID of the textarea to convert
 * @param {object} options - Additional configuration options
 */
function initCKEditor(elementId, options = {}) {
    const defaultConfig = {
        simpleUpload: {
            uploadUrl: '/cbt_exam/upload_image.php',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            withCredentials: false
        },
        toolbar: [
            'heading', '|',
            'bold', 'italic', 'link',
            'bulletedList', 'numberedList',
            '|',
            'insertTable', 'uploadImage',
            '|',
            'undo', 'redo'
        ],
        placeholder: 'Type your content here. You can paste from Word, drag & drop images, or use the toolbar...',
        language: 'en'
    };

    // Merge with custom options
    const config = { ...defaultConfig, ...options };

    // Remove source editing if disabled
    if (config.disableSourceEditing) {
        config.toolbar = config.toolbar.filter(item => item !== 'sourceEditing');
    }

    // Create editor
    if (typeof ClassicEditor !== 'undefined') {
        ClassicEditor
            .create(document.querySelector(elementId), config)
            .then(editor => {
                console.log('CKEditor initialized:', elementId, editor);
                window.ckEditors[elementId] = editor;
                
                if (elementId === '#passage_content' || elementId === 'passage_content') {
                    window.editor = editor; // Store in window.editor for easy access
                }
                
                // Handle form submission
                const form = document.querySelector(elementId)?.closest('form');
                if (form) {
                    form.addEventListener('submit', () => {
                        if (window.ckEditors[elementId]) {
                            // Update textarea with editor content before submit
                            const textarea = document.querySelector(elementId);
                            if (textarea) {
                                textarea.value = window.ckEditors[elementId].getData();
                            }
                        }
                    });
                }
            })
            .catch(error => {
                console.error('CKEditor initialization error:', error);
                console.warn('Falling back to plain textarea for:', elementId);
                
                // Show user-friendly message
                const textarea = document.querySelector(elementId);
                if (textarea && !textarea.nextElementSibling?.classList.contains('cke-error')) {
                    const errorMsg = document.createElement('div');
                    errorMsg.className = 'alert alert-warning cke-error';
                    errorMsg.innerHTML = '<i class="fas fa-info-circle"></i> Rich text editor failed to load. You can still use the plain textarea below.';
                    textarea.parentNode.insertBefore(errorMsg, textarea);
                }
            });
    }
}

/**
 * Destroy CKEditor instance
 * @param {string} elementId - The ID of the editor to destroy
 */
function destroyCKEditor(elementId) {
    if (window.ckEditors[elementId]) {
        window.ckEditors[elementId]
            .destroy()
            .then(() => {
                delete window.ckEditors[elementId];
                console.log('CKEditor destroyed:', elementId);
            });
    }
}

/**
 * Get content from CKEditor
 * @param {string} elementId - The ID of the editor
 * @returns {string} HTML content
 */
function getCKEditorContent(elementId) {
    return window.ckEditors[elementId]?.getData() || '';
}

/**
 * Set content in CKEditor
 * @param {string} elementId - The ID of the editor
 * @param {string} html - HTML content to set
 */
function setCKEditorContent(elementId, html) {
    if (window.ckEditors[elementId]) {
        window.ckEditors[elementId].setData(html);
    }
}

// Auto-initialize editors when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.ckEditors = window.ckEditors || {};
    
    // Initialize all elements with data-cke-auto-init
    const elementsToInit = document.querySelectorAll('textarea[data-cke-auto-init]');
    
    elementsToInit.forEach(element => {
        if (typeof ClassicEditor !== 'undefined' && element.id) {
            ClassicEditor
                .create(element, {
                    simpleUpload: {
                        uploadUrl: '/cbt_exam/upload_image.php',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        withCredentials: false
                    },
                    toolbar: [
                        'heading', '|',
                        'bold', 'italic', 'link',
                        'bulletedList', 'numberedList',
                        '|',
                        'insertTable', 'uploadImage',
                        '|',
                        'undo', 'redo'
                    ]
                })
                .then(editor => {
                    window.ckEditors[element.id] = editor;
                    
                    // Maintain backward compatibility for script expectations
                    if (element.id === 'passage_content') window.editor = editor;
                    if (element.id === 'question_text') window.questionEditor = editor;
                    
                    console.log('CKEditor initialized successfully on #' + element.id);
                })
                .catch(error => {
                    console.error('CKEditor initialization error on ' + element.id + ':', error);
                });
        }
    });

    // Listen to form submissions to sync data from all editors
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', () => {
             for (const id in window.ckEditors) {
                 const textarea = document.getElementById(id);
                 if (textarea && window.ckEditors[id]) {
                     textarea.value = window.ckEditors[id].getData();
                 }
             }
        });
    });
});
</script>
