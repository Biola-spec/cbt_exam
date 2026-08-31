<?php
require_once 'config/session.php';

// Function to format mathematical content including fractions using MathML
function formatMathematicalContent($content) {
    // First, preserve line breaks
    $content = nl2br(htmlspecialchars($content));
    
    // Handle mixed numbers like "2 1/2" - only simple cases with integer parts
    $content = preg_replace_callback(
        '/(?<!\d)(\d+)\s+(\d+)\/(\d+)(?!\d)/', 
        function($matches) {
            // Only convert if the fraction is simple (small numbers)
            if (strlen($matches[2]) <= 3 && strlen($matches[3]) <= 3) {
                return $matches[1] . '<math xmlns="http://www.w3.org/1998/Math/MathML" display="inline">' .
                       '<mfrac><mn>' . $matches[2] . '</mn><mn>' . $matches[3] . '</mn></mfrac>' .
                       '</math>';
            } else {
                // Keep original if numbers are too large (likely part of a larger expression)
                return $matches[0];
            }
        }, 
        $content
    );
    
    // Handle fractions in parentheses like "(a/b)" - only simple cases
    $content = preg_replace_callback(
        '/\((\d+(?:\.\d*)?)\/(\d+(?:\.\d*)?)\)/', 
        function($matches) {
            // Only convert if the fraction is simple (small numbers)
            if (strlen($matches[1]) <= 4 && strlen($matches[2]) <= 4) {
                return '(<math xmlns="http://www.w3.org/1998/Math/MathML" display="inline">' .
                       '<mfrac><mn>' . $matches[1] . '</mn><mn>' . $matches[2] . '</mn></mfrac>' .
                       '</math>)';
            } else {
                // Keep original if numbers are too large
                return $matches[0];
            }
        }, 
        $content
    );
    
    // Convert simple fractions in the format "a/b" to MathML fraction format
    // Only apply to simple fractions, not complex expressions
    $content = preg_replace_callback(
        '/(?<![\d\w\/\(\[\{\|\=\:])(\d+(?:\.\d*)?)\/(\d+(?:\.\d*)?)(?![\d\w\/\)\]\}\|\=\:])/', 
        function($matches) {
            // Only convert if the fraction is simple (small numbers)
            if (strlen($matches[1]) <= 4 && strlen($matches[2]) <= 4) {
                return '<math xmlns="http://www.w3.org/1998/Math/MathML" display="inline">' .
                       '<mfrac><mn>' . $matches[1] . '</mn><mn>' . $matches[2] . '</mn></mfrac>' .
                       '</math>';
            } else {
                // Keep original if numbers are too large (likely part of a larger expression)
                return $matches[0];
            }
        }, 
        $content
    );
    
    return $content;
}

echo "<h2>Matrix Display Test</h2>";

$matrix_content = "(2  1)   (1  3)\n(3  4) + (2  1)";

echo "<div class='container'>";
echo "<div class='card mb-3'>";
echo "<div class='card-body'>";
echo "<h5>Matrix Content Test:</h5>";
echo "<p><strong>Original:</strong></p>";
echo "<pre>" . htmlspecialchars($matrix_content) . "</pre>";
echo "<p><strong>Formatted:</strong></p>";
echo "<div class='bg-light p-3 rounded'>";
echo formatMathematicalContent($matrix_content);
echo "</div>";
echo "</div>";
echo "</div>";

// Test with the full question
$full_question = "1. Add the following matrices:\n(2  1)   (1  3)\n(3  4) + (2  1)";

echo "<div class='card mb-3'>";
echo "<div class='card-body'>";
echo "<h5>Full Question Test:</h5>";
echo "<p><strong>Original:</strong></p>";
echo "<pre>" . htmlspecialchars($full_question) . "</pre>";
echo "<p><strong>Formatted:</strong></p>";
echo "<div class='bg-light p-3 rounded'>";
echo formatMathematicalContent($full_question);
echo "</div>";
echo "</div>";
echo "</div>";

echo "</div>";
?>

<style>
.card {
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
}
.card-body {
    padding: 1rem;
}
</style>