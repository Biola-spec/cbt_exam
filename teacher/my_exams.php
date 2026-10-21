<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is logged in and is a teacher or admin
requireLogin();
if (!in_array(getUserRole(), ['teacher', 'admin'])) {
    header('Location: ../login.php');
    exit();
}

$message = '';
$allocated_exams = [];

try {
    $mysqli = getDBConnection();
    
    // Get the current user ID and store in a variable to avoid pass by reference error
    $current_user_id = getUserId();
    
    $exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
    
    // Get distinct exams allocated to this teacher
    // We need to group by exam ID to prevent duplicates
    // Show both published and unpublished exams so teachers can work on them
    $query = "SELECT DISTINCT e.id, e.title, e.description, e.is_published, e.published_at, e.created_by, e.created_at
              FROM exams e
              JOIN exam_subjects es ON e.id = es.exam_id
              JOIN teacher_allocations ta ON es.class_id = ta.class_id AND es.subject_id = ta.subject_id
              WHERE ta.teacher_id = ?";
    
    if ($exam_id > 0) {
        $query .= " AND e.id = ?";
    }
    
    $query .= " ORDER BY e.created_at DESC";
    
    $stmt = $mysqli->prepare($query);
    if ($exam_id > 0) {
        $stmt->bind_param("ii", $current_user_id, $exam_id);
    } else {
        $stmt->bind_param("i", $current_user_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $allocated_exams[] = $row;
    }
    
    // For each exam, get the associated classes and subjects
    foreach ($allocated_exams as &$exam) {
        $stmt = $mysqli->prepare("SELECT DISTINCT es.id as exam_subject_id, c.id as class_id, c.name as class_name, s.id as subject_id, s.name as subject_name,
                              es.is_published as subject_published, es.published_at as subject_published_at, es.show_calculator
                              FROM exam_subjects es
                              JOIN classes c ON es.class_id = c.id
                              JOIN subjects s ON es.subject_id = s.id
                              JOIN teacher_allocations ta ON es.class_id = ta.class_id AND es.subject_id = ta.subject_id
                              WHERE es.exam_id = ? AND ta.teacher_id = ?
                              ORDER BY c.name, s.name");
        $stmt->bind_param("ii", $exam['id'], $current_user_id); // Use the variable instead of function call
        $stmt->execute();
        $result = $stmt->get_result();
        $exam['subjects'] = [];
        while ($row = $result->fetch_assoc()) {
            $exam['subjects'][] = $row;
        }
    }
    unset($exam); // Break the reference with the last element
} catch (Exception $e) {
    $message = 'Error loading exams: ' . $e->getMessage();
}

// Handle calculator toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_calculator'])) {
    try {
        $exam_subject_id = intval($_POST['exam_subject_id']);
        $show_calc = intval($_POST['show_calculator']);
        
        $stmt = $mysqli->prepare("UPDATE exam_subjects SET show_calculator = ? WHERE id = ?");
        $stmt->bind_param("ii", $show_calc, $exam_subject_id);
        
        if ($stmt->execute()) {
            $message = "Scientific calculator " . ($show_calc ? "enabled" : "disabled") . " successfully.";
            // Refresh the exam list to reflect changes, keeping the exam_id filter if present
            $redirect_url = "my_exams.php?message=" . urlencode($message);
            if ($exam_id > 0) {
                $redirect_url .= "&exam_id=" . $exam_id;
            }
            header("Location: " . $redirect_url);
            exit();
        }
        $stmt->close();
    } catch (Exception $e) {
        $message = "Error updating calculator setting: " . $e->getMessage();
    }
}

if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

// Function to get question count for an exam subject
function getQuestionCount($exam_subject_id) {
    global $mysqli;
    try {
        $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM questions WHERE exam_subject_id = ?");
        $stmt->bind_param("i", $exam_subject_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'];
    } catch (Exception $e) {
        return 0;
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="dashboard-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="dashboard-title"><i class="fas fa-file-alt me-2"></i> My Exams</h1>
                <p class="mb-0">Manage your assigned exams and questions</p>
            </div>
            <div class="col-md-6 text-md-end">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">My Exams</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-cogs me-2"></i> Navigation</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="dashboard.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
                <a href="dashboard.php?tab=results" class="list-group-item list-group-item-action">
                    <i class="fas fa-chart-bar me-2"></i> Exam Results
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="card shadow-sm fade-in">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0"><i class="fas fa-file-alt me-2"></i> My Exams</h4>
                    <?php if ($exam_id > 0): ?>
                        <a href="my_exams.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-list me-1"></i> View All Exams
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (count($allocated_exams) > 0): ?>
                    <div class="row">
                        <?php foreach ($allocated_exams as $exam): ?>
                            <div class="col-md-12 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <?php echo htmlspecialchars($exam['title']); ?>
                                            <?php if (!$exam['is_published']): ?>
                                                <span class="badge bg-warning">Draft</span>
                                            <?php endif; ?>
                                        </h5>
                                        
                                        <?php if (!empty($exam['subjects'])): ?>
                                            <div class="mb-3">
                                                <h6>Classes and Subjects:</h6>
                                                <ul class="list-group">
                                                    <?php foreach ($exam['subjects'] as $subject): ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($subject['class_name']); ?></strong> - 
                                                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                                                                 <?php if (!$subject['subject_published']): ?>
                                                                    <span class="badge bg-secondary">Not Published</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="d-flex align-items-center gap-3">
                                                                <form method="POST" class="m-0 d-flex align-items-center">
                                                                    <input type="hidden" name="exam_subject_id" value="<?php echo $subject['exam_subject_id']; ?>">
                                                                    <input type="hidden" name="show_calculator" value="<?php echo $subject['show_calculator'] ? '0' : '1'; ?>">
                                                                    <button type="submit" name="toggle_calculator" class="btn btn-sm p-0 border-0" title="<?php echo $subject['show_calculator'] ? 'Disable' : 'Enable'; ?> Calculator">
                                                                        <?php if ($subject['show_calculator']): ?>
                                                                            <i class="fas fa-toggle-on text-success fs-5"></i>
                                                                        <?php else: ?>
                                                                            <i class="fas fa-toggle-off text-muted fs-5"></i>
                                                                        <?php endif; ?>
                                                                        <small class="ms-1 <?php echo $subject['show_calculator'] ? 'text-success' : 'text-muted'; ?>">Calc</small>
                                                                    </button>
                                                                </form>

                                                                <?php if ($subject['subject_published']): ?>
                                                                    <a href="question_management.php?exam_id=<?php echo $exam['id']; ?>&class_id=<?php echo $subject['class_id']; ?>&subject_id=<?php echo $subject['subject_id']; ?>" 
                                                                       class="btn btn-primary btn-sm">
                                                                        <i class="fas fa-question-circle"></i> Manage
                                                                    </a>
                                                                <?php else: ?>
                                                                    <span class="text-muted small">Not published</span>
                                                                <?php endif; ?>
                                                                 <a href="export_class_excel.php?exam_id=<?php echo $exam['id']; ?>&class_id=<?php echo $subject['class_id']; ?>"
                                                                    class="btn btn-outline-success btn-sm" title="Download Excel Broadsheet Results for <?php echo htmlspecialchars($subject['class_name']); ?>">
                                                                     <i class="fas fa-file-excel me-1"></i> Excel
                                                                 </a>
                                                                 <a href="student_broadsheet.php?exam_id=<?php echo $exam['id']; ?>&class_id=<?php echo $subject['class_id']; ?>&subject_id=<?php echo $subject['subject_id']; ?>"
                                                                    class="btn btn-outline-primary btn-sm" title="View student scores for this subject">
                                                                     <i class="fas fa-table me-1"></i> Scores
                                                                 </a>
                                                            </div>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <p class="card-text">
                                            <small class="text-muted">
                                                <?php if ($exam['is_published']): ?>
                                                    Published: <?php echo date('M j, Y', strtotime($exam['published_at'])); ?>
                                                <?php else: ?>
                                                    Created: <?php echo date('M j, Y', strtotime($exam['created_at'])); ?> (Not yet published)
                                                <?php endif; ?>
                                            </small>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No exams allocated to you yet.</p>
                        <p class="text-muted">Please contact your administrator to assign exams to your classes and subjects.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
