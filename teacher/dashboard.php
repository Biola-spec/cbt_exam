<?php
require_once '../config/session.php';
require_once '../config/database.php';

// Check if user is logged in and is a teacher or admin
requireLogin();
if (!in_array(getUserRole(), ['teacher', 'admin'])) {
    header('Location: ../login.php');
    exit();
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'exams';
$body_class = 'teacher-dashboard-page';
$termReleaseSummary = ['released' => 0, 'awaiting' => 0, 'not_submitted' => 0];
try {
    $termDb = getDBConnection();
    if ($termDb) {
        $termTeacherId = (int)getUserId();
        $termStmt = $termDb->prepare("SELECT
            SUM(release_status='approved') AS released,
            SUM(release_status='submitted') AS awaiting,
            SUM(release_status IN ('draft','recalled')) AS not_submitted
            FROM term_scores WHERE teacher_id=?");
        $termStmt->bind_param('i', $termTeacherId);
        $termStmt->execute();
        $termReleaseSummary = array_merge($termReleaseSummary, $termStmt->get_result()->fetch_assoc() ?: []);
        $termStmt->close();
    }
} catch (Throwable $e) {
    // Keep the existing dashboard available if the term-results tables are not installed yet.
}
?>

<?php include '../includes/header.php'; ?>

<div class="dashboard-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="dashboard-title"><i class="fas fa-chalkboard-teacher me-2"></i> Teacher Dashboard</h1>
                <p class="mb-0">Manage your exams and questions</p>
            </div>
            <div class="col-md-6 text-md-end">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">
                            <?php 
                            switch ($active_tab) {
                                case 'exams': echo 'My Exams'; break;
                                case 'results': echo 'Exam Results'; break;
                                case 'performance_summary': echo 'Class Performance Summary'; break;
                                default: echo 'Dashboard';
                            }
                            ?>
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<div class="container mt-3">
    <div class="alert alert-light border d-flex justify-content-between align-items-center flex-wrap gap-2 mb-0">
        <div><strong>Term result updates</strong><div class="small text-muted">
            <?php echo (int)$termReleaseSummary['released']; ?> released by admin ·
            <?php echo (int)$termReleaseSummary['awaiting']; ?> awaiting admin release ·
            <?php echo (int)$termReleaseSummary['not_submitted']; ?> not submitted or recalled
        </div></div>
        <a class="btn btn-sm btn-outline-primary" href="term_scores.php"><i class="fas fa-clipboard-list me-1"></i> Open score sheets</a>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-cogs me-2"></i> Navigation</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="dashboard.php?tab=exams" class="list-group-item list-group-item-action <?php echo $active_tab === 'exams' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt me-2"></i> My Exams
                </a>
                <a href="dashboard.php?tab=results" class="list-group-item list-group-item-action <?php echo $active_tab === 'results' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar me-2"></i> Exam Results
                </a>
                <a href="term_scores.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-edit me-2"></i> Enter Term Scores
                </a>
                <a href="class_duties.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-calendar-check me-2"></i> Class Duties
                </a>
                <a href="dashboard.php?tab=performance_summary" class="list-group-item list-group-item-action <?php echo $active_tab === 'performance_summary' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line me-2"></i> Class Performance Summary
                </a>
                <a href="student_broadsheet.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-table me-2"></i> Student Broadsheet
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="card shadow-sm fade-in">
            <div class="card-header">
                <h4 class="mb-0">
                    <?php 
                    switch ($active_tab) {
                        case 'exams': echo '<i class="fas fa-file-alt me-2"></i> My Exams'; break;
                        case 'results': echo '<i class="fas fa-chart-bar me-2"></i> Exam Results'; break;
                        case 'performance_summary': echo '<i class="fas fa-chart-line me-2"></i> Class Performance Summary'; break;
                        default: echo '<i class="fas fa-tachometer-alt me-2"></i> Dashboard';
                    }
                    ?>
                </h4>
            </div>
            <div class="card-body">
                <?php
                if ($active_tab == 'exams'):
                    // Fetch the exams allocated to this teacher (not created by them)
                    $exams = [];
                    try {
                        $conn = getDBConnection();
                        // Get the current user ID and store in a variable to avoid pass by reference error
                        $current_user_id = getUserId();
                        // Get exams that are allocated to this teacher through teacher_allocations table
                        $stmt = $conn->prepare("SELECT DISTINCT e.id, e.title, e.description, e.is_published, e.created_at 
                                              FROM exams e
                                              JOIN exam_subjects es ON e.id = es.exam_id
                                              JOIN teacher_allocations ta ON es.class_id = ta.class_id AND es.subject_id = ta.subject_id
                                              WHERE ta.teacher_id = ?
                                              ORDER BY e.created_at DESC");
                        $stmt->bind_param("i", $current_user_id); // Use the variable instead of function call
                        $stmt->execute();
                        $result = $stmt->get_result();
                        while ($row = $result->fetch_assoc()) {
                            $exams[] = $row;
                        }
                        $stmt->close();
                    } catch (Exception $e) {
                        // Handle error silently
                    }
                    ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0"><i class="fas fa-file-alt text-primary me-2"></i>My Exams</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($exams) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Exam Title</th>
                                                <th>Created</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($exams as $exam): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($exam['title']); ?></strong>
                                                        <?php if ($exam['description']): ?>
                                                            <div class="text-muted small"><?php echo htmlspecialchars($exam['description']); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($exam['created_at'])); ?></td>
                                                    <td>
                                                        <?php if ($exam['is_published']): ?>
                                                            <span class="badge bg-success">Published</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Draft</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="my_exams.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-edit me-1"></i>Manage
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
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
                    
                    <!-- Import Questions Help Card -->
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0"><i class="fas fa-question-circle text-primary me-2"></i>Need Help Importing Questions?</h5>
                        </div>
                        <div class="card-body">
                            <p>Download our sample questions file to see the correct format for importing questions:</p>
                            <a href="../sample_questions.txt" class="btn btn-success" download>
                                <i class="fas fa-download me-1"></i>Download Sample Questions File
                            </a>
                            <p class="mt-3 mb-0"><small class="text-muted">The file shows the correct format for importing multiple-choice questions with answers.</small></p>
                        </div>
                    </div>
                <?php elseif ($active_tab == 'results'): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0"><i class="fas fa-chart-bar text-primary me-2"></i>Exam Results</h5>
                        </div>
                        <div class="card-body">
                            <?php 
                            $included_from_dashboard = true;
                            include 'exam_results.php'; 
                            ?>
                        </div>
                    </div>
                <?php elseif ($active_tab == 'performance_summary'): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0"><i class="fas fa-chart-line text-primary me-2"></i>Class Performance Summary</h5>
                        </div>
                        <div class="card-body">
                            <?php 
                            $included_from_dashboard = true;
                            include 'class_performance_summary.php'; 
                            ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="animate-fade-in text-center py-4">
                        <div class="mb-4">
                            <div class="d-inline-flex align-items-center justify-content-center bg-primary-soft rounded-circle mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-chalkboard-teacher fa-2x text-primary"></i>
                            </div>
                            <h3 class="fw-800">Welcome Back, <?php echo explode(' ', getUserName())[0]; ?>!</h3>
                            <p class="text-muted">You have full access to your assigned academic modules</p>
                        </div>
                        
                        <div class="row g-4 mt-2">
                            <div class="col-md-4">
                                <a href="dashboard.php?tab=exams" class="card h-100 border-0 shadow-sm text-decoration-none hover-lift">
                                    <div class="card-body p-4">
                                        <div class="stat-icon bg-primary-soft text-primary mb-3">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <h5 class="fw-bold text-dark">My Exams</h5>
                                        <p class="small text-muted mb-0">Manage questions and configurations for your exams</p>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="dashboard.php?tab=results" class="card h-100 border-0 shadow-sm text-decoration-none hover-lift">
                                    <div class="card-body p-4">
                                        <div class="stat-icon bg-success-soft text-success mb-3">
                                            <i class="fas fa-chart-bar"></i>
                                        </div>
                                        <h5 class="fw-bold text-dark">Exam Results</h5>
                                        <p class="small text-muted mb-0">Review student scores and individual attempt details</p>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="dashboard.php?tab=performance_summary" class="card h-100 border-0 shadow-sm text-decoration-none hover-lift">
                                    <div class="card-body p-4">
                                        <div class="stat-icon bg-info-soft text-info mb-3">
                                            <i class="fas fa-chart-line"></i>
                                        </div>
                                        <h5 class="fw-bold text-dark">Performance</h5>
                                        <p class="small text-muted mb-0">View aggregate class performance summaries</p>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="class_duties.php" class="card h-100 border-0 shadow-sm text-decoration-none hover-lift">
                                    <div class="card-body p-4">
                                        <div class="stat-icon bg-success-soft text-success mb-3"><i class="fas fa-calendar-check"></i></div>
                                        <h5 class="fw-bold text-dark">Class Duties</h5>
                                        <p class="small text-muted mb-0">Take daily attendance and write term remarks for assigned classes</p>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="student_broadsheet.php" class="card h-100 border-0 shadow-sm text-decoration-none hover-lift">
                                    <div class="card-body p-4">
                                        <div class="stat-icon bg-warning-soft text-warning mb-3">
                                            <i class="fas fa-table"></i>
                                        </div>
                                        <h5 class="fw-bold text-dark">Student Broadsheet</h5>
                                        <p class="small text-muted mb-0">View each student’s score for an assigned subject</p>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <div class="mt-4">
                            <?php
                            $calendar_widget_id = 'teacher_home_calendar';
                            $show_dropdown_toggle = true;
                            $calendar_collapsed_default = true;
                            include '../includes/school_calendar.php';
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
