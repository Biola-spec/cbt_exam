<?php
require_once '../config/database.php';
require_once '../config/session.php'; // Added this to ensure session functions are available

$public_exam_access = isPublicExamSession();
if ((!$public_exam_access && !isLoggedIn()) || getUserRole() !== 'student') {
    header('Location: ../login.php');
    exit();
}

if (!$public_exam_access) {
    $db = getDBConnection();
    $candidateId = (int)getUserId();
    $appStmt = $db->prepare("SELECT ea.id,ea.status,e.id AS exam_id,e.title,e.description,c.name AS class_name,COUNT(DISTINCT ee.exam_subject_id) AS subject_count,(SELECT COUNT(DISTINCT a.exam_subject_id) FROM exam_attempts a JOIN exam_subjects done_es ON done_es.id=a.exam_subject_id JOIN entrance_exams done_ee ON done_ee.exam_subject_id=done_es.id WHERE a.student_id=? AND done_es.exam_id=e.id AND a.submitted_at IS NOT NULL) AS completed_subjects,(SELECT COUNT(DISTINCT a.exam_subject_id) FROM exam_attempts a JOIN exam_subjects active_es ON active_es.id=a.exam_subject_id JOIN entrance_exams active_ee ON active_ee.exam_subject_id=active_es.id WHERE a.student_id=? AND active_es.exam_id=e.id AND a.submitted_at IS NULL) AS in_progress_subjects FROM entrance_applications ea JOIN entrance_exams root_ee ON root_ee.id=ea.entrance_exam_id JOIN exam_subjects root_es ON root_es.id=root_ee.exam_subject_id JOIN exams e ON e.id=root_es.exam_id JOIN classes c ON c.id=ea.target_class_id JOIN entrance_exams ee ON ee.exam_subject_id IN (SELECT x.exam_subject_id FROM entrance_exams x JOIN exam_subjects xs ON xs.id=x.exam_subject_id WHERE xs.exam_id=e.id) JOIN exam_subjects es ON es.id=ee.exam_subject_id WHERE ea.user_id=? AND ea.status IN ('pending','admitted') AND e.is_published=1 GROUP BY ea.id,ea.status,e.id,e.title,e.description,c.name HAVING subject_count=SUM(es.is_published)");
    $appStmt->bind_param('iii',$candidateId,$candidateId,$candidateId); $appStmt->execute(); $candidateExams=$appStmt->get_result()->fetch_all(MYSQLI_ASSOC); $appStmt->close();
    $statusStmt=$db->prepare('SELECT status FROM entrance_applications WHERE user_id=?'); $statusStmt->bind_param('i',$candidateId); $statusStmt->execute(); $candidateStatus=$statusStmt->get_result()->fetch_assoc()['status']??null; $statusStmt->close();
    if ($candidateStatus !== null) {
        if ($candidateStatus === 'rejected') {
            echo '<div class="alert alert-danger">Your entrance application was not admitted. You can still view any results already released below.</div>';
        } else {
            echo '<h5>Entrance Examination</h5><p class="text-muted">This exam is for admission consideration. Your account will join the class only after the admin admits you.</p>';
            if (!$candidateExams) echo '<div class="alert alert-info">Your entrance exam is not fully published yet. Check back later.</div>';
            foreach ($candidateExams as $exam) {
                $completed=(int)$exam['completed_subjects']; $total=(int)$exam['subject_count']; $done=$completed >= $total; $label=$done?'View Entrance Results':((int)$exam['in_progress_subjects']>0?'Continue Entrance Exam':'Start Entrance Exam');
                $url='take_entrance_exam.php?exam_id='.(int)$exam['exam_id'];
                echo '<div class="card mb-3"><div class="card-body"><h5 class="card-title">'.htmlspecialchars($exam['title']).'</h5><p class="mb-2">'.htmlspecialchars($exam['class_name']).' · '.(int)$exam['subject_count'].' subjects in one sitting</p><p class="small text-muted">'.(int)$exam['completed_subjects'].' of '.(int)$exam['subject_count'].' subject sections completed</p><a class="btn btn-primary" href="'.htmlspecialchars($url).'">'.htmlspecialchars($label).'</a></div></div>';
            }
            if ($candidateStatus === 'admitted') echo '<div class="alert alert-success">You have been admitted. Your class access is now active. <a href="../login.php?exam_access=1" class="alert-link">Open a regular published exam</a>.</div>';
        }
        echo '<a class="btn btn-outline-primary" href="dashboard.php?tab=results">View examination results</a>';
        return;
    }
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
               AND NOT EXISTS (SELECT 1 FROM entrance_exams entrance_filter WHERE entrance_filter.exam_subject_id=es.id)
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
