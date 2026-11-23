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
        images_upload_url: '<?php echo $base_url; ?>/teacher/upload_image.php',
        document_base_url: '<?php echo $base_url; ?>/',
        relative_urls: false,
        automatic_uploads: true,
        images_upload_handler: function (blobInfo, success, failure, progress) {
            var request = new XMLHttpRequest();
            request.open('POST', '<?php echo $base_url; ?>/teacher/upload_image.php');
            request.upload.onprogress = function (event) {
                if (event.lengthComputable) progress(Math.round(event.loaded / event.total * 100));
            };
            request.onload = function () {
                var response;
                try {
                    response = JSON.parse(request.responseText);
                } catch (error) {
                    failure('The image server returned an invalid response.');
                    return;
                }
                if (request.status < 200 || request.status >= 300) {
                    failure(response.error && response.error.message ? response.error.message : 'Image upload failed.');
                    return;
                }
                if (!response.location) {
                    failure('The image server did not return an image URL.');
                    return;
                }
                success(response.location);
            };
            request.onerror = function () { failure('Could not connect to the image upload service.'); };
            var formData = new FormData();
            formData.append('file', blobInfo.blob(), blobInfo.filename());
            request.send(formData);
        },
        setup: function (editor) {
            editor.on('change', function () {
                editor.save();
            });
        }
    });
});
</script>
