<?php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/cbt_exam/upload_image.php');
curl_setopt($ch, CURLOPT_POST, 1);

// Create a dummy image file for testing
$dummyImagePath = 'dummy_test.png';
$img = imagecreatetruecolor(10, 10);
imagepng($img, $dummyImagePath);
imagedestroy($img);

$cfile = new CURLFile($dummyImagePath, 'image/png', 'dummy_test.png');
$data = array('upload' => $cfile);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if(curl_errno($ch)){
    echo 'Curl error: ' . curl_error($ch);
}
echo "HTTP Code: $http_code\n";
echo "Response: \n$response\n";
curl_close($ch);
?>
