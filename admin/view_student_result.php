<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is logged in and is an admin
requireLogin();
if (getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get the attempt ID from the query parameter
$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;

if (!$attempt_id) {
    header('Location: dashboard.php?tab=results');
    exit();
}

$mysqli = getDBConnection();

// Function to sanitize HTML content while allowing safe tags like images
function sanitizeHtml($text) {
    // Allow only safe HTML tags (like img, p, br, strong, em)
    $allowed_tags = '<p><br><strong><em><img><span><div><ul><ol><li><h1><h2><h3><h4><h5><h6>';
    $text = strip_tags($text, $allowed_tags);
    
    // Additional security: Remove any javascript or on* attributes
    $text = preg_replace('/(<[^>]+) on[a-zA-Z]*="[^"]*"/i', '$1', $text);
    $text = preg_replace("/(<[^>]+) on[a-zA-Z]*='[^']*'/i", '$1', $text);
    
    return $text;
}

// Function to get detailed exam results for viewing
function getExamDetails($mysqli, $attempt_id) {
    $stmt = $mysqli->prepare("SELECT ea.*, e.title as exam_title, c.name as class_name, s.name as subject_name,
                          es.time_limit, c.marks_per_question, u.full_name as student_name
                          FROM exam_attempts ea
                          JOIN exams e ON ea.exam_id = e.id
                          JOIN exam_subjects es ON ea.exam_subject_id = es.id
                          JOIN classes c ON es.class_id = c.id
                          JOIN subjects s ON es.subject_id = s.id
                          JOIN users u ON ea.student_id = u.id
                          WHERE ea.id = ?");
    $stmt->bind_param("i", $attempt_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to get question details with student answers
function getQuestionDetails($mysqli, $attempt_id) {
    $stmt = $mysqli->prepare("SELECT q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.option_e, q.option_f, 
                          q.correct_answer, sa.selected_answer,
                          CASE WHEN sa.selected_answer = q.correct_answer THEN 1 ELSE 0 END as is_correct
                          FROM student_answers sa
                          JOIN questions q ON sa.question_id = q.id
                          WHERE sa.attempt_id = ?
                          ORDER BY q.id");
    $stmt->bind_param("i", $attempt_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $questions = [];
    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }
    return $questions;
}

$exam_details = getExamDetails($mysqli, $attempt_id);
$question_details = getQuestionDetails($mysqli, $attempt_id);

if (!$exam_details) {
    header('Location: dashboard.php?tab=results');
    exit();
}

// Get school logo
$school_logo = null;
$stmt = $mysqli->prepare("SELECT logo_path FROM school_config LIMIT 1");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $school_logo = $row['logo_path'];
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Results - <?php echo htmlspecialchars($exam_details['exam_title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; }
            .card { border: 1px solid #000; box-shadow: none; }
            .page-break { page-break-before: always; }
        }
        .correct-answer { background-color: #d4edda; }
        .incorrect-answer { background-color: #f8d7da; }
        .answer-status { font-weight: bold; }
        .question-row { border-bottom: 1px solid #dee2e6; }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="mb-0">Exam Results</h3>
                            <div class="no-print">
                                <button class="btn btn-light me-2" onclick="window.print()">
                                    <i class="fas fa-print"></i> Print
                                </button>
                                <button type="button" class="btn btn-danger me-2" id="btn-delete-this">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                                <a href="dashboard.php?tab=results" class="btn btn-light">
                                    <i class="fas fa-arrow-left"></i> Back to Results
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="text-center mb-3">
                                    <?php if (!empty($school_logo) && file_exists($school_logo)): ?>
                                        <img src="<?php echo htmlspecialchars($school_logo); ?>" alt="School Logo" style="height: 50px; margin-bottom: 10px;">
                                    <?php endif; ?>
                                    <h4><?php echo htmlspecialchars(getSchoolName()); ?></h4>
                                </div>
                                <h4><?php echo htmlspecialchars($exam_details['exam_title']); ?></h4>
                                <p class="mb-1"><strong>Subject:</strong> <?php echo htmlspecialchars($exam_details['class_name']); ?> - <?php echo htmlspecialchars($exam_details['subject_name']); ?></p>
                                <p class="mb-1"><strong>Student:</strong> <?php echo htmlspecialchars($exam_details['student_name']); ?></p>
                                <p class="mb-1"><strong>School:</strong> <?php echo htmlspecialchars(getSchoolName()); ?></p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <p class="mb-1"><strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($exam_details['submitted_at'])); ?></p>
                                <p class="mb-1"><strong>Time Limit:</strong> <?php echo $exam_details['time_limit']; ?> minutes</p>
                                <p class="mb-1">
                                    <strong>Score:</strong> 
                                    <span class="fs-4"><?php echo number_format($exam_details['score'], 2); ?>/<?php echo number_format($exam_details['total_questions'] * $exam_details['marks_per_question'], 2); ?></span>
                                </p>
                                <?php 
                                $percentage = 0;
                                $max_possible_score = $exam_details['total_questions'] * $exam_details['marks_per_question'];
                                if ($max_possible_score > 0) {
                                    $percentage = ($exam_details['score'] / $max_possible_score) * 100;
                                }
                                ?>
                                <p class="mb-0">
                                    <strong>Percentage:</strong> 
                                    <span class="fs-4 <?php echo $percentage >= 50 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo round($percentage, 2); ?>%
                                    </span>
                                </p>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h4 class="mt-4">Question Details</h4>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th width="5%">#</th>
                                        <th>Question</th>
                                        <th width="15%">Student's Answer</th>
                                        <th width="15%">Correct Answer</th>
                                        <th width="10%">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($question_details as $index => $question): ?>
                                        <tr class="<?php echo $question['is_correct'] ? 'correct-answer' : 'incorrect-answer'; ?>">
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo sanitizeHtml($question['question_text']); ?></td>
                                            <td><?php echo htmlspecialchars($question['selected_answer']); ?></td>
                                            <td><?php echo htmlspecialchars($question['correct_answer']); ?></td>
                                            <td>
                                                <?php if ($question['is_correct']): ?>
                                                    <span class="answer-status text-success">Correct</span>
                                                <?php else: ?>
                                                    <span class="answer-status text-danger">Incorrect</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr class="<?php echo $question['is_correct'] ? 'correct-answer' : 'incorrect-answer'; ?>">
                                            <td colspan="5">
                                                <div class="row">
                                                    <div class="col-md-2">
                                                        <strong>A:</strong> <?php echo sanitizeHtml($question['option_a']); ?>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <strong>B:</strong> <?php echo sanitizeHtml($question['option_b']); ?>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <strong>C:</strong> <?php echo sanitizeHtml($question['option_c']); ?>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <strong>D:</strong> <?php echo sanitizeHtml($question['option_d']); ?>
                                                    </div>
                                                    <?php if (!empty($question['option_e'])): ?>
                                                    <div class="col-md-2">
                                                        <strong>E:</strong> <?php echo sanitizeHtml($question['option_e']); ?>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($question['option_f'])): ?>
                                                    <div class="col-md-2">
                                                        <strong>F:</strong> <?php echo sanitizeHtml($question['option_f']); ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <form id="delete-form" action="delete_result.php" method="POST" style="display: none;">
        <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
        <input type="hidden" name="redirect" value="view_all_results.php?tab=results">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('btn-delete-this').addEventListener('click', function() {
            if (confirm('Are you sure you want to delete this exam result? This action cannot be undone.')) {
                document.getElementById('delete-form').submit();
            }
        });
    </script>
</body>
</html>