<?php
// Test the cleanWpsContent function with base64 data
function cleanWpsContent($content) {
    // Remove null bytes and other control characters that might cause issues
    $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
    
    // Normalize line endings
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    
    // Remove any invisible Unicode characters that WPS might insert
    $content = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $content);
    
    // Decode any HTML entities that might have been inserted
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Trim whitespace but preserve line breaks
    $content = rtrim($content);
    
    return $content;
}

// Sample passage content with base64 image (similar to what was shown in the issue)
$original_content = '<p>This is a sample passage with an image:</p>
<img src="data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=" alt="Test Image">
<p>Images should display properly.</p>';

echo "<h2>Original Content:</h2>";
echo "<pre>" . htmlspecialchars($original_content) . "</pre>";

$processed_content = cleanWpsContent($original_content);

echo "<h2>Processed Content:</h2>";
echo "<pre>" . htmlspecialchars($processed_content) . "</pre>";

echo "<h2>Comparison:</h2>";
echo "<p>Original length: " . strlen($original_content) . "</p>";
echo "<p>Processed length: " . strlen($processed_content) . "</p>";

// Check if base64 data is still intact
if (strpos($processed_content, '/9j/4AAQSkZJRgABAQAAAQABAAD') !== false) {
    echo "<p style='color: green;'>Base64 data appears to be intact</p>";
} else {
    echo "<p style='color: red;'>Base64 data may have been corrupted</p>";
}

// Show differences
if ($original_content !== $processed_content) {
    echo "<h2>Differences:</h2>";
    echo "<p>Content was modified by cleanWpsContent function</p>";
    
    // Find first difference
    for ($i = 0; $i < min(strlen($original_content), strlen($processed_content)); $i++) {
        if ($original_content[$i] !== $processed_content[$i]) {
            echo "<p>First difference at position $i</p>";
            echo "<p>Original character: " . htmlspecialchars($original_content[$i]) . " (ASCII: " . ord($original_content[$i]) . ")</p>";
            echo "<p>Processed character: " . htmlspecialchars($processed_content[$i]) . " (ASCII: " . ord($processed_content[$i]) . ")</p>";
            break;
        }
    }
} else {
    echo "<p>No differences found</p>";
}
?>