<?php
require_once '../config/database.php';
require_once '../config/session.php'; // Added this to ensure session functions are available

$public_exam_access = isPublicExamSession();
if ((!$public_exam_access && !isLoggedIn()) || getUserRole() !== 'student') {
    header('Location: ../login.php');
    exit();
}

if (!$public_exam_access) {
    ?>
    <div class="alert alert-primary border-0 shadow-sm p-4">
        <h5 class="alert-heading"><i class="fas fa-lock me-2"></i>No subject exam selected</h5>
        <p class="mb-3">Your dashboard is open. To start an exam, enter the subject code provided for that published subject.</p>
        <a class="btn btn-primary" href="../login.php?exam_access=1"><i class="fas fa-key me-2"></i>Enter Subject Code</a>
    </div>
    <?php
    return;
}

$message = '';
$available_exams = [];

try {
    $mysqli = getDBConnection();
    
    // Get current user ID and store in a variable to avoid pass by reference error
    $current_user_id = getUserId();
    
    // Get the student's class_id
    $stmt = $mysqli->prepare("SELECT class_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $student_class_id = $student['class_id'];
    
    $resit_schema_available = false;
    $table_check = $mysqli->query("SHOW TABLES LIKE 'resit_requests'");
    $column_check = $mysqli->query("SHOW COLUMNS FROM exam_subjects LIKE 'resit_request_id'");
    $resit_schema_available = $table_check && $table_check->num_rows > 0 && $column_check && $column_check->num_rows > 0;
    $student_subject_filter = $public_exam_access ? ($resit_schema_available ? "
               AND (
                   (es.resit_request_id IS NULL AND s.subject_code = ?)
                   OR (
                       es.resit_request_id IS NOT NULL
                       AND EXISTS (
                           SELECT 1 FROM resit_requests rr
                           WHERE rr.id = es.resit_request_id
                             AND rr.student_id = ?
                             AND rr.status = 'approved'
                       )
                   )
               )" : "AND s.subject_code = ?") : ($resit_schema_available ? "
               AND (es.resit_request_id IS NULL OR EXISTS (
                   SELECT 1 FROM resit_requests rr
                   WHERE rr.id = es.resit_request_id AND rr.student_id = ? AND rr.status = 'approved'
               ))" : "");
    $exam_visibility_filter = $resit_schema_available ? "
               AND (
                   (e.is_published = 1 AND es.is_published = 1)
                   OR (
                       es.resit_request_id IS NOT NULL
                       AND EXISTS (
                           SELECT 1 FROM questions rq
                           WHERE rq.exam_subject_id = es.id
                       )
                   )
               )" : "
               AND e.is_published = 1
               AND es.is_published = 1";

    // NEW ROBUST QUERY: Get exams with their latest attempt status and retake permission comparison
    $query = "SELECT 
                es.id as exam_subject_id, es.time_limit, 
                e.title, e.description, e.published_at, 
                c.name as class_name, s.name as subject_name,
                (SELECT id FROM exam_attempts 
                 WHERE student_id = ? AND exam_subject_id = es.id AND submitted_at IS NOT NULL 
                 ORDER BY submitted_at DESC LIMIT 1) as submitted_attempt_id,
                (SELECT submitted_at FROM exam_attempts 
                 WHERE student_id = ? AND exam_subject_id = es.id AND submitted_at IS NOT NULL 
                 ORDER BY submitted_at DESC LIMIT 1) as last_submitted_at,
                (SELECT id FROM exam_attempts 
                 WHERE student_id = ? AND exam_subject_id = es.id AND submitted_at IS NULL 
                 ORDER BY started_at DESC LIMIT 1) as in_progress_attempt_id,
                 (SELECT republished_at FROM exam_republish_log
                  WHERE student_id = ? AND exam_subject_id = es.id
                  ORDER BY republished_at DESC LIMIT 1) as last_retake_at
              FROM exam_subjects es
              JOIN exams e ON es.exam_id = e.id
              JOIN classes c ON es.class_id = c.id
              JOIN subjects s ON es.subject_id = s.id
               WHERE 1 = 1" . $exam_visibility_filter . "
               AND es.class_id = ?
               " . $student_subject_filter . "
               ORDER BY e.published_at DESC";

    $stmt = $mysqli->prepare($query);
    if ($public_exam_access && $resit_schema_available) {
        $stmt->bind_param("iiiiisi", $current_user_id, $current_user_id, $current_user_id, $current_user_id, $student_class_id, $_SESSION['subject_code'], $current_user_id);
    } elseif ($public_exam_access) {
        $stmt->bind_param("iiiiis", $current_user_id, $current_user_id, $current_user_id, $current_user_id, $student_class_id, $_SESSION['subject_code']);
    } elseif ($resit_schema_available) {
        $stmt->bind_param("iiiiii", $current_user_id, $current_user_id, $current_user_id, $current_user_id, $student_class_id, $current_user_id);
    } else {
        $stmt->bind_param("iiiii", $current_user_id, $current_user_id, $current_user_id, $current_user_id, $student_class_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $has_submitted = !empty($row['submitted_attempt_id']);
        $has_retake_permission = false;
        
        // Retake permission is only valid if it was granted AFTER the last submission
        if (!empty($row['last_retake_at'])) {
            if (!$has_submitted || strtotime($row['last_retake_at']) > strtotime($row['last_submitted_at'])) {
                $has_retake_permission = true;
            }
        }
        
        // Determine status
        if ($has_submitted && !$has_retake_permission) {
            $row['status'] = 'completed';
            $row['attempt_id'] = $row['submitted_attempt_id'];
        } elseif ($row['in_progress_attempt_id']) {
            $row['status'] = 'in_progress';
        } else {
            $row['status'] = 'available';
        }
        $available_exams[] = $row;
    }
} catch (Exception $e) {
    $message = 'Error loading exams: ' . $e->getMessage();
}
?>

<h5>Available Exams</h5>

<?php if ($message): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if (count($available_exams) > 0): ?>
    <div class="row">
        <?php foreach ($available_exams as $exam): ?>
            <div class="col-md-6 mb-4">
                <div class="card <?php echo $exam['status'] === 'completed' ? 'bg-light' : ''; ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($exam['title']); ?></h5>
                            <?php if ($exam['status'] === 'completed'): ?>
                                <span class="badge bg-success">Completed</span>
                            <?php elseif ($exam['status'] === 'in_progress'): ?>
                                <span class="badge bg-warning text-dark">In Progress</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Available</span>
                            <?php endif; ?>
                        </div>
                        
                        <p class="card-text mb-1"><?php echo htmlspecialchars($exam['class_name']); ?> - <?php echo htmlspecialchars($exam['subject_name']); ?></p>
                        
                        <p class="card-text mb-1">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i> <?php echo $exam['time_limit']; ?> minutes
                            </small>
                        </p>
                        
                        <div class="mt-3">
                            <?php if ($exam['status'] === 'completed'): ?>
                                <a href="exam_completion.php?attempt_id=<?php echo $exam['attempt_id']; ?>" 
                                   class="btn btn-outline-success w-100">
                                    <i class="fas fa-poll me-1"></i> View Result
                                </a>
                            <?php elseif ($exam['status'] === 'in_progress'): ?>
                                <a href="take_exam.php?exam_subject_id=<?php echo $exam['exam_subject_id']; ?>" 
                                   class="btn btn-warning w-100">
                                    <i class="fas fa-play me-1"></i> Continue Exam
                                </a>
                            <?php else: ?>
                                <a href="take_exam.php?exam_subject_id=<?php echo $exam['exam_subject_id']; ?>" 
                                   class="btn btn-primary w-100">
                                    <i class="fas fa-play me-1"></i> Start Exam
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <p>You don't have any available exams at the moment.</p>
        <p>Please contact your administrator or teacher for access to exams.</p>
    </div>
<?php endif; ?>
