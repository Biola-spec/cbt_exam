<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/results_sheet.php';

// Public subject-code access may print only the result belonging to its own student.
if (!isLoggedIn() && !isPublicExamSession()) {
    header('Location: ../login.php');
    exit();
}
$user_role = getUserRole();
if (!in_array($user_role, ['student', 'teacher', 'admin'])) {
    header('Location: ../login.php');
    exit();
}

// Get the attempt ID from the query parameter
$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;

if (!$attempt_id) {
    header('Location: dashboard.php?tab=history');
    exit();
}

$mysqli = getDBConnection();
ensureTheoryExamSchema($mysqli);
$current_user_id = getUserId();

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

// Function to get detailed exam results for printing
function getExamDetails($mysqli, $attempt_id, $user_id, $user_role) {
    if ($user_role === 'student') {
        $stmt = $mysqli->prepare("SELECT ea.score, ea.total_questions, ea.submitted_at, ea.exam_subject_id,
                              e.title as exam_title, e.is_published AS exam_published, es.is_published AS subject_published,
                              c.name as class_name, s.name as subject_name,
                              es.time_limit, c.marks_per_question, u.passport, u.full_name as student_name,
                              es.class_id, es.subject_id
                              FROM exam_attempts ea
                              JOIN exams e ON ea.exam_id = e.id
                              JOIN exam_subjects es ON ea.exam_subject_id = es.id
                              JOIN classes c ON es.class_id = c.id
                              JOIN subjects s ON es.subject_id = s.id
                              JOIN users u ON ea.student_id = u.id
                              WHERE ea.id = ? AND ea.student_id = ?");
        $stmt->bind_param("ii", $attempt_id, $user_id);
    } else {
        $stmt = $mysqli->prepare("SELECT ea.score, ea.total_questions, ea.submitted_at, ea.exam_subject_id,
                              e.title as exam_title, e.is_published AS exam_published, es.is_published AS subject_published,
                              c.name as class_name, s.name as subject_name,
                              es.time_limit, c.marks_per_question, u.passport, u.full_name as student_name,
                              es.class_id, es.subject_id
                              FROM exam_attempts ea
                              JOIN exams e ON ea.exam_id = e.id
                              JOIN exam_subjects es ON ea.exam_subject_id = es.id
                              JOIN classes c ON es.class_id = c.id
                              JOIN subjects s ON es.subject_id = s.id
                              JOIN users u ON ea.student_id = u.id
                              WHERE ea.id = ?");
        $stmt->bind_param("i", $attempt_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to get question details with student answers
function getQuestionDetails($mysqli, $attempt_id) {
    $stmt = $mysqli->prepare("SELECT q.question_text, q.question_type, q.option_a, q.option_b, q.option_c, q.option_d, q.option_e, q.option_f, 
                          q.correct_answer, q.theory_marks, sa.selected_answer, sa.answer_text, sa.awarded_marks, sa.teacher_comment, sa.marked_at,
                          CASE WHEN q.question_type = 'objective' AND sa.selected_answer = q.correct_answer THEN 1 ELSE 0 END as is_correct
                          FROM student_answers sa
                          JOIN questions q ON sa.question_id = q.id
                          WHERE sa.attempt_id = ?
                          ORDER BY COALESCE(q.order_position, q.id), q.id");
    $stmt->bind_param("i", $attempt_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $questions = [];
    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }
    return $questions;
}

$exam_details = getExamDetails($mysqli, $attempt_id, $current_user_id, $user_role);
if (!$exam_details) {
    header('Location: dashboard.php?tab=history');
    exit();
}

if ($user_role === 'student') {
    if ($exam_details['submitted_at'] === null || $exam_details['score'] === null) {
        http_response_code(403);
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Result unavailable</title><body style="font:16px Arial,sans-serif;max-width:640px;margin:12vh auto;padding:24px;color:#243447"><h1>Result not available yet</h1><p>This exam has not been submitted or has no calculated score.</p><p><a href="dashboard.php?tab=results">Return to My Examination Results</a></p></body></html>';
        exit();
    }
}
$question_details = getQuestionDetails($mysqli, $attempt_id);

$stmt = $mysqli->prepare("SELECT SUM(CASE WHEN COALESCE(question_type, 'objective') = 'objective' THEN 1 ELSE 0 END) AS objective_questions,
                      COALESCE(SUM(CASE WHEN question_type = 'theory' THEN theory_marks ELSE 0 END), 0) AS theory_total_marks
                      FROM questions
                      WHERE exam_subject_id = ?");
$stmt->bind_param("i", $exam_details['exam_subject_id']);
$stmt->execute();
$marks_summary = $stmt->get_result()->fetch_assoc();
$objective_questions = (int)($marks_summary['objective_questions'] ?? $exam_details['total_questions']);
$theory_total_marks = (float)($marks_summary['theory_total_marks'] ?? 0);
$max_possible_score = ($objective_questions * (float)$exam_details['marks_per_question']) + $theory_total_marks;

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
    <link href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>" rel="stylesheet">
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
                                <a href="dashboard.php?tab=history" class="btn btn-light">
                                    <i class="fas fa-arrow-left"></i> Back to History
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
                                
                                <div class="d-flex align-items-center mt-3 mb-2">
                                    <?php 
                                    $passport_img = !empty($exam_details['passport']) ? '../' . $exam_details['passport'] : '../assets/img/default-avatar.png'; 
                                    ?>
                                    <img src="<?php echo htmlspecialchars($passport_img); ?>" alt="Student Passport" 
                                         style="width: 80px; height: 104px; object-fit: cover; border-radius: 50%; border: 2px solid #007bff; margin-right: 15px;">
                                    <div>
                                        <p class="mb-1"><strong>Student:</strong> <?php echo htmlspecialchars(getUserName()); ?></p>
                                        <p class="mb-1"><strong>School:</strong> <?php echo htmlspecialchars(getSchoolName()); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <p class="mb-1"><strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($exam_details['submitted_at'])); ?></p>
                                <p class="mb-1"><strong>Time Limit:</strong> <?php echo $exam_details['time_limit']; ?> minutes</p>
                                <p class="mb-1">
                                    <strong>Score:</strong> 
                                    <span class="fs-4"><?php echo number_format($exam_details['score'], 2); ?>/<?php echo number_format($max_possible_score, 2); ?></span>
                                </p>
                                <?php 
$percentage = 0;
if ($max_possible_score > 0) {
    $percentage = ($exam_details['score'] / $max_possible_score) * 100;
}
$grade_result = resultSheetGrade(resultSheetGetScale($mysqli), $percentage);
                                ?>
                                <p class="mb-0">
                                    <strong>Percentage:</strong> 
                                    <span class="fs-4 <?php echo $grade_result && $grade_result['is_passing'] ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo round($percentage, 2); ?>%
                                    </span>
                                </p>
                                <?php if ($grade_result): ?>
                                <p class="mb-0"><strong>Grade:</strong> <?php echo htmlspecialchars($grade_result['grade_letter']); ?> · <?php echo htmlspecialchars($grade_result['remark']); ?></p>
                                <?php endif; ?>
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
                                        <th width="15%">Your Answer</th>
                                        <th width="15%">Correct Answer</th>
                                        <th width="10%">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($question_details as $index => $question): 
                                        $is_theory = ($question['question_type'] ?? 'objective') === 'theory';
                                        $row_class = $is_theory ? '' : ($question['is_correct'] ? 'correct-answer' : 'incorrect-answer');
                                    ?>
                                        <tr class="<?php echo $row_class; ?>">
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo sanitizeHtml($question['question_text']); ?></td>
                                            <td><?php echo $is_theory ? nl2br(htmlspecialchars($question['answer_text'] ?? '')) : htmlspecialchars($question['selected_answer']); ?></td>
                                            <td><?php echo $is_theory ? number_format((float)($question['awarded_marks'] ?? 0), 2) . ' / ' . number_format((float)$question['theory_marks'], 2) : htmlspecialchars($question['correct_answer']); ?></td>
                                            <td>
                                                <?php if ($is_theory): ?>
                                                    <?php if (!empty($question['marked_at'])): ?>
                                                        <span class="answer-status text-success">Marked</span>
                                                    <?php else: ?>
                                                        <span class="answer-status text-warning">Pending</span>
                                                    <?php endif; ?>
                                                <?php elseif ($question['is_correct']): ?>
                                                    <span class="answer-status text-success">Correct</span>
                                                <?php else: ?>
                                                    <span class="answer-status text-danger">Incorrect</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php if ($is_theory): ?>
                                        <tr>
                                            <td colspan="5">
                                                <strong>Teacher Comment:</strong>
                                                <?php echo !empty($question['teacher_comment']) ? nl2br(htmlspecialchars($question['teacher_comment'])) : '<span class="text-muted">No comment yet.</span>'; ?>
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                        <tr class="<?php echo $row_class; ?>">
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
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-print when page loads
        window.onload = function() {
            // Only auto-print if not already printing
            if (typeof window.matchMedia !== 'undefined' && 
                window.matchMedia('print').matches === false) {
                window.print();
            }
        };
    </script>
</body>
</html>
