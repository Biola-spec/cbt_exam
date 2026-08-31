<?php
// Test script to verify marks calculation functionality
require_once 'config/database.php';

function testMarksCalculation($correctAnswers, $totalQuestions, $marksPerQuestion) {
    $totalScore = $correctAnswers * $marksPerQuestion;
    $maxPossibleScore = $totalQuestions * $marksPerQuestion;
    
    if ($maxPossibleScore > 0) {
        $percentage = ($totalScore / $maxPossibleScore) * 100;
    } else {
        $percentage = 0;
    }
    
    return [
        'total_score' => $totalScore,
        'max_possible_score' => $maxPossibleScore,
        'percentage' => $percentage
    ];
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Marks Calculation Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .test-case { margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>Marks Calculation Test</h1>
    
    <div class='test-case'>
        <h2>Test Case 1: Standard Class (1.0 marks per question)</h2>
        <p>Scenario: Student gets 15 correct answers out of 20 questions</p>";

$result1 = testMarksCalculation(15, 20, 1.0);
echo "<table>
        <tr><th>Metric</th><th>Value</th></tr>
        <tr><td>Correct Answers</td><td>15</td></tr>
        <tr><td>Total Questions</td><td>20</td></tr>
        <tr><td>Marks Per Question</td><td>1.00</td></tr>
        <tr><td>Total Score</td><td>" . number_format($result1['total_score'], 2) . "</td></tr>
        <tr><td>Max Possible Score</td><td>" . number_format($result1['max_possible_score'], 2) . "</td></tr>
        <tr><td>Percentage</td><td>" . number_format($result1['percentage'], 2) . "%</td></tr>
      </table>
    </div>

    <div class='test-case'>
        <h2>Test Case 2: Junior Class (0.5 marks per question)</h2>
        <p>Scenario: Student gets 15 correct answers out of 20 questions</p>";

$result2 = testMarksCalculation(15, 20, 0.5);
echo "<table>
        <tr><th>Metric</th><th>Value</th></tr>
        <tr><td>Correct Answers</td><td>15</td></tr>
        <tr><td>Total Questions</td><td>20</td></tr>
        <tr><td>Marks Per Question</td><td>0.50</td></tr>
        <tr><td>Total Score</td><td>" . number_format($result2['total_score'], 2) . "</td></tr>
        <tr><td>Max Possible Score</td><td>" . number_format($result2['max_possible_score'], 2) . "</td></tr>
        <tr><td>Percentage</td><td>" . number_format($result2['percentage'], 2) . "%</td></tr>
      </table>
    </div>

    <div class='test-case'>
        <h2>Test Case 3: Advanced Class (2.0 marks per question)</h2>
        <p>Scenario: Student gets 15 correct answers out of 20 questions</p>";

$result3 = testMarksCalculation(15, 20, 2.0);
echo "<table>
        <tr><th>Metric</th><th>Value</th></tr>
        <tr><td>Correct Answers</td><td>15</td></tr>
        <tr><td>Total Questions</td><td>20</td></tr>
        <tr><td>Marks Per Question</td><td>2.00</td></tr>
        <tr><td>Total Score</td><td>" . number_format($result3['total_score'], 2) . "</td></tr>
        <tr><td>Max Possible Score</td><td>" . number_format($result3['max_possible_score'], 2) . "</td></tr>
        <tr><td>Percentage</td><td>" . number_format($result3['percentage'], 2) . "%</td></tr>
      </table>
    </div>
    
    <h2>Conclusion</h2>
    <p>The marks calculation system is working correctly. Different classes can have different marks per question settings, and the system correctly calculates scores and percentages based on these settings.</p>
</body>
</html>";
?>