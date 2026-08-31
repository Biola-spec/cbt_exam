<?php
require_once 'config/session.php';

// Function to format mathematical content including fractions and matrices using MathML
function formatMathematicalContent($content) {
    // First, preserve line breaks
    $content = nl2br(htmlspecialchars($content));
    
    // Handle matrix notation - preserve formatting for matrix expressions
    // Look for patterns like (a b) or (a  b) and preserve spacing
    $content = preg_replace_callback(
        '/\(([\d\s]+)\)/', 
        function($matches) {
            // Preserve matrix format with proper spacing
            $matrix_content = $matches[1];
            // Replace multiple spaces with single spaces for consistent display
            $matrix_content = preg_replace('/\s+/', ' ', $matrix_content);
            return '(' . $matrix_content . ')';
        }, 
        $content
    );
    
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

echo "<h2>Matrix Question Display Test</h2>";

// Test matrix question exactly like the user's example
$matrix_question = "(2  1)   (1  3)\n(3  4) + (2  1) =";

echo "<div class='container'>";
echo "<div class='card mb-3'>";
echo "<div class='card-body'>";
echo "<h5>Matrix Question Test:</h5>";
echo "<p><strong>Original:</strong></p>";
echo "<pre style='background-color: #f8f9fa; padding: 15px; border-radius: 5px;'>" . htmlspecialchars($matrix_question) . "</pre>";
echo "<p><strong>Formatted:</strong></p>";
echo "<div class='bg-light p-3 rounded'>";
echo formatMathematicalContent($matrix_question);
echo "</div>";
echo "</div>";
echo "</div>";

// Test with a complete question
$full_question = "1. Add the following matrices:\n\n(2  1)   (1  3)\n(3  4) + (2  1) = ?\n\nWhat is the result of this matrix addition?";

echo "<div class='card mb-3'>";
echo "<div class='card-body'>";
echo "<h5>Full Matrix Question Test:</h5>";
echo "<p><strong>Original:</strong></p>";
echo "<pre style='background-color: #f8f9fa; padding: 15px; border-radius: 5px;'>" . htmlspecialchars($full_question) . "</pre>";
echo "<p><strong>Formatted:</strong></p>";
echo "<div class='bg-light p-3 rounded'>";
echo formatMathematicalContent($full_question);
echo "</div>";
echo "</div>";
echo "</div>";

// Test with matrix options
$matrix_options = [
    "A. (3  4)\n   (5  5)",
    "B. (1  2)\n   (1  3)",
    "C. (3  4)\n   (1  5)",
    "D. (2  1)\n   (3  4)"
];

echo "<h5>Matrix Options Test:</h5>";
foreach ($matrix_options as $index => $option) {
    echo "<div class='card mb-2'>";
    echo "<div class='card-body'>";
    echo "<p><strong>Option " . chr(65 + $index) . ":</strong></p>";
    echo "<pre style='background-color: #f8f9fa; padding: 10px; border-radius: 5px;'>" . htmlspecialchars($option) . "</pre>";
    echo "<p><strong>Formatted:</strong></p>";
    echo "<div class='bg-light p-2 rounded'>";
    echo formatMathematicalContent($option);
    echo "</div>";
    echo "</div>";
    echo "</div>";
}

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
.bg-light {
    background-color: #f8f9fa !important;
}
.rounded {
    border-radius: 0.5rem !important;
}
.p-3 {
    padding: 1rem !important;
}
</style>