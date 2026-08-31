<?php
require_once 'config/database.php';

echo "<h2>Arabic Encoding Test</h2>";

// Test Arabic text
$arabic_text = "سؤال اختبار باللغة العربية - ما هو ناتج 2+2؟";
$english_text = "Test question in English - What is 2+2?";

echo "<h3>Original Texts:</h3>";
echo "<p>Arabic: " . $arabic_text . "</p>";
echo "<p>English: " . $english_text . "</p>";

// Test database connection and charset
$mysqli = getDBConnection();
if ($mysqli) {
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Check charset
    $charset = $mysqli->character_set_name();
    echo "<p>Database charset: " . $charset . "</p>";
    
    if ($charset == 'utf8mb4') {
        echo "<p style='color: green;'>✓ Correct charset for Arabic text support</p>";
    } else {
        echo "<p style='color: red;'>✗ Incorrect charset for Arabic text support</p>";
    }
    
    // Test inserting Arabic text
    $stmt = $mysqli->prepare("INSERT INTO questions (exam_subject_id, question_text, option_a, option_b, option_c, option_d, correct_answer, created_by) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt) {
        $exam_subject_id = 1; // Assuming this exists
        $question_text = $arabic_text;
        $option_a = "1";
        $option_b = "2";
        $option_c = "4";
        $option_d = "5";
        $correct_answer = "C";
        $created_by = 1; // Assuming admin user exists
        
        $stmt->bind_param("issssssi", $exam_subject_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $created_by);
        
        if ($stmt->execute()) {
            $question_id = $mysqli->insert_id;
            echo "<p style='color: green;'>✓ Arabic text inserted successfully (ID: $question_id)</p>";
            
            // Retrieve and display the text
            $stmt2 = $mysqli->prepare("SELECT question_text FROM questions WHERE id = ?");
            $stmt2->bind_param("i", $question_id);
            $stmt2->execute();
            $result = $stmt2->get_result();
            $row = $result->fetch_assoc();
            
            echo "<h3>Retrieved Text:</h3>";
            echo "<p>Arabic: " . htmlspecialchars($row['question_text']) . "</p>";
            
            if ($row['question_text'] === $arabic_text) {
                echo "<p style='color: green;'>✓ Arabic text retrieved correctly</p>";
            } else {
                echo "<p style='color: red;'>✗ Arabic text retrieval failed</p>";
            }
            
            // Clean up
            $stmt2->close();
            $mysqli->query("DELETE FROM questions WHERE id = $question_id");
        } else {
            echo "<p style='color: red;'>✗ Failed to insert Arabic text: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color: red;'>✗ Failed to prepare statement: " . $mysqli->error . "</p>";
    }
    
    $mysqli->close();
} else {
    echo "<p style='color: red;'>✗ Database connection failed</p>";
}

echo "<h3>Character Encoding Functions Test:</h3>";

// Test encoding functions
function ensureUtf8Encoding($content) {
    if (mb_check_encoding($content, 'UTF-8')) {
        return $content;
    }
    
    $encodings = ['Windows-1252', 'ISO-8859-1'];
    foreach ($encodings as $encoding) {
        if (mb_check_encoding($content, $encoding)) {
            return mb_convert_encoding($content, 'UTF-8', $encoding);
        }
    }
    
    return mb_convert_encoding($content, 'UTF-8', 'auto');
}

$test_strings = [
    "English text",
    "النص العربي",
    "Français avec accents",
    "Español con ñ"
];

foreach ($test_strings as $str) {
    $encoded = ensureUtf8Encoding($str);
    echo "<p>" . htmlspecialchars($str) . " -> " . htmlspecialchars($encoded) . "</p>";
}

echo "<p><a href='index.php'>Back to Home</a></p>";
?>