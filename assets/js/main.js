// Main JavaScript file for CBT Exam System

// Add fade-in effect to cards when they come into view
document.addEventListener('DOMContentLoaded', function() {
    console.log('Main.js loaded and DOM ready');
    
    // Add fade-in animation to cards
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        // Add delay for staggered animation
        card.style.animationDelay = (index * 0.1) + 's';
        card.classList.add('fade-in');
    });
    
    // Add slide-in animation to list items
    const listItems = document.querySelectorAll('.list-group-item');
    listItems.forEach((item, index) => {
        item.style.animationDelay = (index * 0.05) + 's';
        item.classList.add('slide-in-left');
    });
    
    // Add confirmation to delete forms
    const deleteForms = document.querySelectorAll('form[onsubmit*="confirm"]');
    deleteForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const message = this.getAttribute('onsubmit').replace('return confirm(\'', '').replace('\')', '');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // Add focus effect to form controls
    const formControls = document.querySelectorAll('.form-control, .form-select');
    formControls.forEach(control => {
        control.addEventListener('focus', function() {
            this.parentElement.classList.add('shadow');
        });
        
        control.addEventListener('blur', function() {
            this.parentElement.classList.remove('shadow');
        });
    });
    
    // Add hover effect to buttons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.classList.add('shadow');
        });
        
        button.addEventListener('mouseleave', function() {
            this.classList.remove('shadow');
        });
    });
    
    // Initialize CKEditor for question management forms if present
    // Multiple attempts with different delays to ensure CKEditor loads
    setTimeout(initializeCKEditor, 100);
    setTimeout(initializeCKEditor, 500);
    setTimeout(initializeCKEditor, 1000);
    setTimeout(initializeCKEditor, 2000);
});

// Function to initialize CKEditor with Math support
function initializeCKEditor() {
    console.log('Main.js: Attempting to initialize CKEditor...');
    
    // Check if CKEditor is available
    if (typeof CKEDITOR === 'undefined') {
        console.log('Main.js: CKEditor not found');
        return;
    }
    
    console.log('Main.js: CKEditor found, version:', CKEDITOR.version);
    doInitializeCKEditor();
}

function doInitializeCKEditor() {
    // Configuration for CKEditor with Math support
    const ckConfig = {
        height: 150,
        toolbar: [
            { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript'] },
            { name: 'paragraph', items: ['NumberedList', 'BulletedList', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock'] },
            { name: 'links', items: ['Link', 'Unlink'] },
            { name: 'insert', items: ['Image', 'Table', 'HorizontalRule', 'SpecialChar', 'Mathjax'] },
            { name: 'clipboard', items: ['Undo', 'Redo'] },
            { name: 'editing', items: ['Find', 'Replace', 'SelectAll'] },
            '/',
            { name: 'styles', items: ['Styles', 'Format', 'Font', 'FontSize'] },
            { name: 'colors', items: ['TextColor', 'BGColor'] },
            { name: 'tools', items: ['Maximize'] },
            { name: 'about', items: ['About'] }
        ],
        // Math plugin configuration with fallback
        mathJaxLib: 'https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.4/MathJax.js?config=TeX-AMS_HTML',
        extraPlugins: 'mathjax',
        // Ensure MathJax plugin is loaded
        on: {
            instanceReady: function() {
                console.log('Main.js: CKEditor with MathJax plugin ready for:', this.name);
            }
        }
    };

    // Initialize CKEditor instances
    if (typeof CKEDITOR !== 'undefined') {
        console.log('Main.js: CKEditor found, initializing editors...');
        
        // Initialize for question text
        if (document.getElementById('question_text')) {
            console.log('Main.js: Found question_text element');
            // Check if already initialized to prevent duplicates
            if (!CKEDITOR.instances.question_text) {
                console.log('Main.js: Initializing question_text editor');
                try {
                    CKEDITOR.replace('question_text', ckConfig);
                    console.log('Main.js: Successfully initialized question_text editor');
                } catch (error) {
                    console.error('Main.js: Error initializing question_text editor:', error);
                }
            } else {
                console.log('Main.js: question_text editor already initialized');
            }
        }
        
        // Initialize for options
        ['option_a', 'option_b', 'option_c', 'option_d'].forEach(function(optionId) {
            if (document.getElementById(optionId)) {
                console.log('Main.js: Found ' + optionId + ' element');
                // Check if already initialized to prevent duplicates
                if (!CKEDITOR.instances[optionId]) {
                    console.log('Main.js: Initializing ' + optionId + ' editor');
                    try {
                        CKEDITOR.replace(optionId, ckConfig);
                        console.log('Main.js: Successfully initialized ' + optionId + ' editor');
                    } catch (error) {
                        console.error('Main.js: Error initializing ' + optionId + ' editor:', error);
                    }
                } else {
                    console.log('Main.js: ' + optionId + ' editor already initialized');
                }
            }
        });
    } else {
        console.log('Main.js: CKEditor not found - library may not be loaded properly');
    }
}

// Function to get CKEditor content
function getCKEditorContent(elementId) {
    if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances[elementId]) {
        return CKEDITOR.instances[elementId].getData();
    }
    return document.getElementById(elementId) ? document.getElementById(elementId).value : '';
}

// Function to show alert messages
function showAlert(message, type = 'info') {
    // Create alert element
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.role = 'alert';
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Insert alert at the top of the container
    const container = document.querySelector('.container');
    if (container) {
        container.insertBefore(alert, container.firstChild);
    }
}

// Function to toggle password visibility
function togglePasswordVisibility(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = field.nextElementSibling.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}