<?php
// Enhanced TinyMCE Initialization Script
// Replaces the legacy CKEditor integration
$base_url = '/cbt_exam'; 
?>
<!-- Load TinyMCE from the cloud -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/5.10.7/tinymce.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    tinymce.init({
        selector: '#passage_content, #question_text, #option_a, #option_b, #option_c, #option_d, #option_e, #option_f, textarea[data-cke-auto-init]',
        plugins: 'image',
        toolbar: 'bold italic | image',
        images_upload_url: '../upload_image.php',
        setup: function (editor) {
            editor.on('change', function () {
                editor.save();
            });
        }
    });
});
</script>
