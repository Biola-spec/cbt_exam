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

echo "<h2>Fraction Display Test</h2>";

$test_cases = [
    "What is 1/2 + 1/4?",
    "Solve: x = (3/4) ÷ (1/2)",
    "Convert 2 1/2 to an improper fraction",
    "Simplify: (2/3) × (3/4)",
    "Calculate 5/6 - 1/3",
    "What is 3 3/4 as a decimal?"
];

echo "<div class='container'>";
foreach ($test_cases as $index => $test_case) {
    echo "<div class='card mb-3'>";
    echo "<div class='card-body'>";
    echo "<h5>Test Case " . ($index + 1) . ":</h5>";
    echo "<p><strong>Original:</strong> " . htmlspecialchars($test_case) . "</p>";
    echo "<p><strong>Formatted:</strong> " . formatMathematicalContent($test_case) . "</p>";
    echo "</div>";
    echo "</div>";
}

// Test with options
$test_options = [
    "A. 3/4",
    "B. 2 1/2",
    "C. (5/6)",
    "D. 7/8 + 1/4"
];

echo "<h3>Option Formatting Test:</h3>";
foreach ($test_options as $index => $option) {
    echo "<div class='card mb-2'>";
    echo "<div class='card-body'>";
    echo "<p><strong>Option " . chr(65 + $index) . ":</strong> " . formatMathematicalContent($option) . "</p>";
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
</style>