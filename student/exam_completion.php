<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/results_sheet.php';

// Allow a limited subject-code session to view only its own exam completion.
if ((!isLoggedIn() && !isPublicExamSession()) || getUserRole() !== 'student') {
    header('Location: ../login.php');
    exit();
}

$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
$time_up = isset($_GET['time_up']) ? (int)$_GET['time_up'] : 0;

if (!$attempt_id) {
    header('Location: dashboard.php');
    exit();
}

try {
    $mysqli = getDBConnection();
    ensureTheoryExamSchema($mysqli);
    
    // Get exam attempt details with class information
    $stmt = $mysqli->prepare("SELECT ea.*, e.title as exam_title, e.is_published AS exam_published, es.is_published AS subject_published,
                          c.name as class_name, s.name as subject_name, c.marks_per_question,
                          rr.penalty_percentage, rr.id AS resit_request_id
                          FROM exam_attempts ea
                          JOIN exams e ON ea.exam_id = e.id
                          JOIN exam_subjects es ON ea.exam_subject_id = es.id
                          LEFT JOIN resit_requests rr ON rr.id = es.resit_request_id AND rr.student_id = ea.student_id AND rr.status = 'approved'
                          JOIN classes c ON es.class_id = c.id
                          JOIN subjects s ON es.subject_id = s.id
                          WHERE ea.id = ? AND ea.student_id = ?");
    $stmt->bind_param("ii", $attempt_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $attempt = $result->fetch_assoc();

    if (!$attempt) {
        header('Location: dashboard.php');
        exit();
    }

    $entranceExamGroupId = 0;
    $entranceGroupStmt = $mysqli->prepare("SELECT root_es.exam_id FROM entrance_applications ea JOIN entrance_exams root_ee ON root_ee.id=ea.entrance_exam_id JOIN exam_subjects root_es ON root_es.id=root_ee.exam_subject_id WHERE ea.user_id=? AND ea.status IN ('pending','admitted') AND root_es.exam_id=? LIMIT 1");
    if ($entranceGroupStmt) {
        $entranceGroupStmt->bind_param('ii', $_SESSION['user_id'], $attempt['exam_id']);
        $entranceGroupStmt->execute();
        $entranceExamGroupId = (int)($entranceGroupStmt->get_result()->fetch_assoc()['exam_id'] ?? 0);
        $entranceGroupStmt->close();
    }

    // Get the actual number of questions and marks in this exam
    $stmt = $mysqli->prepare("SELECT COUNT(*) as total,
                          SUM(CASE WHEN COALESCE(question_type, 'objective') = 'objective' THEN 1 ELSE 0 END) as objective_total,
                          COALESCE(SUM(CASE WHEN question_type = 'theory' THEN theory_marks ELSE 0 END), 0) as theory_total_marks
                          FROM questions WHERE exam_subject_id = ?");
    $stmt->bind_param("i", $attempt['exam_subject_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $question_stats = $result->fetch_assoc();
    $total_questions = $question_stats['total'];
    $objective_questions = (int)($question_stats['objective_total'] ?? $total_questions);
    $theory_total_marks = (float)($question_stats['theory_total_marks'] ?? 0);

    // Get student answer statistics
    $stmt = $mysqli->prepare("SELECT COUNT(*) as answered,
                          SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct,
                          SUM(CASE WHEN q.question_type = 'theory' AND COALESCE(sa.answer_text, '') <> '' AND sa.marked_at IS NULL THEN 1 ELSE 0 END) as pending_theory
                          FROM student_answers sa
                          JOIN questions q ON sa.question_id = q.id
                          WHERE sa.attempt_id = ?");
    $stmt->bind_param("i", $attempt_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $answer_stats = $result->fetch_assoc();
    $answered_questions = $answer_stats['answered'] ?? 0;
    $correct_answers = $answer_stats['correct'] ?? 0;
    $pending_theory = (int)($answer_stats['pending_theory'] ?? 0);

    // Get marks per question for this class
    $marks_per_question = (float)$attempt['marks_per_question'];

    // Calculate score based on objective class marks plus teacher-awarded theory marks.
    $resit_score = (float)$attempt['score'];
    $penalty_percentage = $attempt['resit_request_id'] !== null ? (float)$attempt['penalty_percentage'] : 0.0;
    $deduction_score = $resit_score * ($penalty_percentage / 100);
    $total_score = $resit_score - $deduction_score;
    $max_possible_score = ($objective_questions * $marks_per_question) + $theory_total_marks;

    // Calculate percentage based on total questions (not just answered)
    $percentage = 0;
    $percentage_of_total = 0;
    if ($max_possible_score > 0) {
        $percentage_of_total = ($total_score / $max_possible_score) * 100;
    }
    // Keep percentage for backward compatibility but use percentage_of_total for display
    $percentage = $percentage_of_total;

    $result_is_released = $attempt['submitted_at'] !== null && $attempt['score'] !== null && $max_possible_score > 0;
    $result_grade = $result_is_released ? resultSheetGrade(resultSheetGetScale($mysqli), $percentage_of_total) : null;
    $passed = $result_is_released && $result_grade !== null && (int)$result_grade['is_passing'] === 1;

    // Get school name and logo
    $school_name = getSchoolName();
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
    
    $student_name = getUserName();
    
    // Get student passport
    $student_passport = null;
    $stmt = $mysqli->prepare("SELECT passport FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $passport_result = $stmt->get_result();
        if ($row = $passport_result->fetch_assoc()) {
            $student_passport = $row['passport'];
        }
        $stmt->close();
    }
} catch (Exception $e) {
    header('Location: dashboard.php');
    exit();
}
?>

<?php include '../includes/header.php'; ?>

<script>
    // Prevent going back to the exam after completion
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    
    // Add a dummy entry to history to "trap" the back button
    window.history.pushState(null, null, window.location.href);
    window.onpopstate = function() {
        window.history.pushState(null, null, window.location.href);
    };

    // Specifically block Backspace from acting as a back button
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace') {
            var activeElement = document.activeElement;
            var isInput = (activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA' || activeElement.isContentEditable);
            if (!isInput) {
                e.preventDefault();
            }
        }
    });
</script>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg border-0">
                <div class="card-body text-center p-5">
                    <?php if (!$result_is_released): ?>
                        <h1 class="display-5 text-primary fw-bold mb-3">Exam Completed</h1>
                        <p class="lead mb-4">Your result will appear in My Examination Results after publication and marking are complete.</p>
                    <?php elseif ($passed): ?>
                        <div class="exam-outcome-icon text-success mb-4" aria-hidden="true"><i class="fas fa-circle-check"></i></div>
                        
                        <h1 class="display-4 text-success fw-bold mb-3">Congratulations!</h1>
                        <p class="lead mb-4">You have successfully completed the exam.</p>
                        
                    <?php else: ?>
                        <div class="exam-outcome-icon text-primary mb-4" aria-hidden="true"><i class="fas fa-circle-info"></i></div>
                        
                        <h1 class="display-4 text-primary fw-bold mb-3">Exam Completed</h1>
                        <p class="lead mb-4">You have finished the exam. Better luck next time!</p>
                    <?php endif; ?>
                    
                    <div class="row justify-content-center mt-4">
                        <div class="col-md-10">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <?php if (!empty($school_logo) && file_exists($school_logo)): ?>
                                            <img src="<?php echo htmlspecialchars($school_logo); ?>" alt="School Logo" style="height: 50px; margin-bottom: 10px;">
                                        <?php endif; ?>
                                        <h4><?php echo htmlspecialchars($school_name); ?></h4>
                                    </div>
                                    
                                    <h4 class="card-title text-center"><?php echo htmlspecialchars($attempt['exam_title']); ?></h4>
                                    <p class="card-text text-center">
                                        <strong><?php echo htmlspecialchars($attempt['class_name']); ?> - <?php echo htmlspecialchars($attempt['subject_name']); ?></strong>
                                    </p>
                                    
                                    <hr>
                                    
                                    <?php if ($result_is_released): ?>
                                    <div class="row text-center">
                                        <div class="col-md-4 mb-3">
                                            <h5 class="text-primary"><?php echo number_format($total_score, 2); ?> / <?php echo number_format($max_possible_score, 2); ?></h5>
                                            <p class="mb-0">Final Score</p>
                                        </div>
                                        <?php if ($attempt['resit_request_id'] !== null): ?>
                                        <div class="col-md-4 mb-3">
                                            <h5 class="text-primary"><?php echo number_format($resit_score, 2); ?> / <?php echo number_format($max_possible_score, 2); ?></h5>
                                            <p class="mb-0">Resit Score</p>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <h5 class="text-danger">-<?php echo number_format($deduction_score, 2); ?> (<?php echo number_format($penalty_percentage, 2); ?>%)</h5>
                                            <p class="mb-0">Penalty Deduction</p>
                                        </div>
                                        <?php endif; ?>
                                        <div class="col-md-4 mb-3">
                                            <h5 class="text-primary"><?php echo $answered_questions; ?> / <?php echo $total_questions; ?></h5>
                                            <p class="mb-0">Answered / Total Questions</p>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <h5 class="<?php echo $passed ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo round($percentage_of_total, 2); ?>%
                                            </h5>
                                            <p class="mb-0">Percentage</p>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info mb-0">Your score is pending result release. Check My Examination Results later.</div>
                                    <?php endif; ?>

                                    <div class="mt-3">
                                        <div class="text-center mb-3">
                                            <?php 
                                            $passport_img = !empty($student_passport) ? '../' . $student_passport : '../assets/img/default-avatar.png'; 
                                            ?>
                                            <img src="<?php echo htmlspecialchars($passport_img); ?>" alt="Student Passport" 
                                                 style="width: 100px; height: 130px; object-fit: cover; border-radius: 50%; border: 3px solid #007bff; margin-bottom: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                                        </div>
                                        <p class="mb-1"><strong>Student:</strong> <?php echo htmlspecialchars($student_name); ?></p>
                                        <p class="mb-1"><strong>School:</strong> <?php echo htmlspecialchars($school_name); ?></p>
                                        <p class="mb-0"><strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($attempt['submitted_at'])); ?></p>
                                    </div>
                                    
                                    <?php if ($answered_questions < $total_questions): ?>
                                    <div class="alert alert-warning mt-3 mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Note:</strong> You answered <?php echo $answered_questions; ?> out of <?php echo $total_questions; ?> questions. 
                                        Unanswered questions were scored as incorrect.
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <?php if ($entranceExamGroupId > 0): ?>
                            <a href="take_entrance_exam.php?exam_id=<?php echo $entranceExamGroupId; ?>" class="btn btn-success btn-lg mb-3"><i class="fas fa-arrow-right me-2"></i>Continue entrance exam</a><br>
                        <?php endif; ?>
                        <?php if ($result_is_released): ?>
                            <a href="result_sheet.php?exam_id=<?php echo (int)$attempt['exam_id']; ?>" class="btn btn-primary btn-lg"><i class="fas fa-poll me-2"></i>View Results Sheet</a>
                            <a href="print_exam_result.php?attempt_id=<?php echo (int)$attempt_id; ?>" class="btn btn-outline-success btn-lg ms-2" target="_blank"><i class="fas fa-list-check me-2"></i>View Questions &amp; Answers</a>
                            <a href="print_exam_result.php?attempt_id=<?php echo (int)$attempt_id; ?>" class="btn btn-outline-primary btn-lg ms-2" target="_blank"><i class="fas fa-print me-2"></i>Print Subject Details</a>
                        <?php else: ?>
                            <a href="dashboard.php?tab=results" class="btn btn-primary btn-lg"><i class="fas fa-poll me-2"></i>My Examination Results</a>
                            <a href="dashboard.php?tab=history" class="btn btn-outline-secondary btn-lg ms-2"><i class="fas fa-history me-2"></i>Exam History</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<link href="../css/exam_completion.css" rel="stylesheet">

<?php include '../includes/footer.php'; ?>
