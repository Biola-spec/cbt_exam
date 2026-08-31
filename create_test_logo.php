<?php
// Create a simple test logo image

// Create a blank image
$im = imagecreate(100, 100);

// Set background to white
$white = imagecolorallocate($im, 255, 255, 255);
imagefilledrectangle($im, 0, 0, 99, 99, $white);

// Draw a simple logo (blue square with text)
$blue = imagecolorallocate($im, 0, 0, 255);
imagefilledrectangle($im, 20, 20, 80, 80, $blue);

// Add text
$black = imagecolorallocate($im, 0, 0, 0);
imagestring($im, 5, 25, 40, 'TEST', $black);

// Save the image
if (!is_dir('uploads')) {
    mkdir('uploads', 0755, true);
}

$filename = 'uploads/test_logo.png';
if (imagepng($im, $filename)) {
    echo "Test logo created successfully: " . $filename . "\n";
    echo "File size: " . filesize($filename) . " bytes\n";
} else {
    echo "Failed to create test logo\n";
}

// Free memory
imagedestroy($im);
?>