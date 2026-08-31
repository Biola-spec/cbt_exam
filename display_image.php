<?php
// Display image directly
$image_path = 'uploads/img_6926edd8df5153.81050369.JPG';

if (file_exists($image_path)) {
    // Get the image info
    $image_info = getimagesize($image_path);
    
    if ($image_info) {
        // Set the correct content type
        header('Content-Type: ' . $image_info['mime']);
        
        // Output the image
        readfile($image_path);
    } else {
        echo "File exists but is not a valid image.";
    }
} else {
    echo "Image file does not exist at: " . $image_path;
}
?>