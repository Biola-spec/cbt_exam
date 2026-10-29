<?php 
require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is logged in and is an admin
requireLogin();
if (getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'home';
$body_class = 'admin-dashboard-page';
$message = isset($_GET['message']) ? $_GET['message'] : '';

// Count results for badge
$results_count = 0;
$pending_resit_count = 0;
try {
    $mysqli = getDBConnection();
    $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM exam_attempts WHERE submitted_at IS NOT NULL");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $results_count = $row['count'];
    
    // Check if this admin has any teaching allocations
    $has_allocations = false;
    $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM teacher_allocations WHERE teacher_id = ?");
    $user_id = getUserId();
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $has_allocations = ($row['count'] > 0);
} catch (Exception $e) {
    // Handle error silently
}
try {
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS count FROM resit_requests WHERE status = 'pending'");
    $stmt->execute();
    $pending_resit_count = (int)$stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
} catch (Exception $e) {
    $pending_resit_count = 0;
}
?>

<?php include '../includes/header.php'; ?>

<style>
@media print {
    @page { size: A4 landscape; margin: 8mm; }
    body * { visibility: hidden !important; }
    .admin-print-area, .admin-print-area * { visibility: visible !important; }
    .admin-print-area { display: block !important; position: absolute !important; left: 0 !important; top: 0 !important; width: 100% !important; margin: 0 !important; padding: 0 !important; border: 0 !important; box-shadow: none !important; }
    .admin-print-area .no-print { display: none !important; }
    .admin-print-area .table-responsive { width: 100% !important; max-width: 100% !important; overflow: visible !important; }
    .admin-print-area table { width: 100% !important; border-collapse: collapse !important; font-size: 9pt; }
    .admin-print-area th, .admin-print-area td { border: 1px solid #66717a !important; padding: 4px !important; color: #111 !important; background: #fff !important; }
    .admin-print-area thead { display: table-header-group; }
    .admin-print-area tr { break-inside: avoid; }
    .admin-print-area img.student-export-photo { width: 22mm !important; height: 28mm !important; max-width: 22mm !important; max-height: 28mm !important; object-fit: contain !important; border-radius: 0 !important; }
    body:has(.admin-print-area) *:has(.admin-print-area) { display: contents !important; }
    body:has(.admin-print-area) *:not(:has(.admin-print-area)):not(.admin-print-area):not(.admin-print-area *) { display: none !important; }
    .admin-print-area, .admin-print-area * { display: revert !important; }
}
</style>

<div class="dashboard-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="dashboard-title"><i class="fas fa-tachometer-alt me-2"></i> Admin Dashboard</h1>
                <p class="mb-0">Manage your CBT Exam System</p>
                <div class="dashboard-profile-welcome">
                    <?php if (!empty($user_photo)): ?><img src="<?php echo htmlspecialchars($user_photo); ?>" alt="<?php echo htmlspecialchars(getUserName()); ?> passport photograph" class="dashboard-profile-photo" onerror="this.hidden=true"><?php endif; ?>
                    <span>Signed in as <strong><?php echo htmlspecialchars(getUserName()); ?></strong></span>
                </div>
            </div>
            <div class="col-md-6 text-md-end">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">
                            <?php 
                            switch ($active_tab) {
                                case 'school': echo 'School Settings'; break;
                                case 'classes': echo 'Classes'; break;
                                case 'subjects': echo 'Subjects'; break;
                                case 'teachers': echo 'Teacher Allocation'; break;
                                case 'students': echo 'Students'; break;
                                case 'exams': echo 'Exam Management'; break;
                                case 'results': echo 'Exam Results'; break;
                                case 'grades': echo 'Grade Settings'; break;
                                case 'performance_summary': echo 'Class Performance Summary'; break;
                                case 'teacher_stats': echo 'Teacher Statistics'; break;
                                
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

<?php if ($message): ?>
    <div class="container mt-3">
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-cogs me-2"></i> Navigation</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="dashboard.php?tab=home" class="list-group-item list-group-item-action <?php echo $active_tab === 'home' ? 'active' : ''; ?>">
                    <i class="fas fa-house me-2"></i> Overview
                </a>
                <a href="dashboard.php?tab=school" class="list-group-item list-group-item-action <?php echo $active_tab === 'school' ? 'active' : ''; ?>">
                    <i class="fas fa-cog me-2"></i> School Settings
                </a>
                <a href="dashboard.php?tab=classes" class="list-group-item list-group-item-action <?php echo $active_tab === 'classes' ? 'active' : ''; ?>">
                    <i class="fas fa-book me-2"></i> Classes
                </a>
                <a href="dashboard.php?tab=subjects" class="list-group-item list-group-item-action <?php echo $active_tab === 'subjects' ? 'active' : ''; ?>">
                    <i class="fas fa-book-open me-2"></i> Subjects
                </a>
                <a href="dashboard.php?tab=teachers" class="list-group-item list-group-item-action <?php echo $active_tab === 'teachers' ? 'active' : ''; ?>">
                    <i class="fas fa-chalkboard-teacher me-2"></i> Teacher Allocation
                </a>
                <a href="dashboard.php?tab=students" class="list-group-item list-group-item-action <?php echo $active_tab === 'students' ? 'active' : ''; ?>">
                    <i class="fas fa-user-graduate me-2"></i> Students
                </a>
                <a href="entrance_admissions.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-user-check me-2"></i> Entrance admissions
                </a>
                <a href="add_teacher.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-user-plus me-2"></i> Add Teacher
                </a>
                <a href="dashboard.php?tab=exams" class="list-group-item list-group-item-action <?php echo $active_tab === 'exams' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt me-2"></i> Exam Management
                </a>
                <a href="dashboard.php?tab=results" class="list-group-item list-group-item-action <?php echo $active_tab === 'results' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar me-2"></i> Exam Results
                    <?php if ($results_count > 0): ?><span class="badge bg-primary float-end"><?php echo $results_count; ?></span><?php endif; ?>
                </a>
                <a href="term_results.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-clipboard-check me-2"></i> Term Results &amp; Release
                </a>
                <a href="report_card_settings.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-star-half-alt me-2"></i> Report Card Settings
                </a>
                <a href="resit_requests.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-clipboard-check me-2"></i> Resit Requests
                    <?php if ($pending_resit_count > 0): ?><span class="badge bg-warning text-dark float-end"><?php echo $pending_resit_count; ?></span><?php endif; ?>
                </a>
                <a href="dashboard.php?tab=grades" class="list-group-item list-group-item-action <?php echo $active_tab === 'grades' ? 'active' : ''; ?>">
                    <i class="fas fa-sliders-h me-2"></i> Grade Settings
                </a>
                <a href="dashboard.php?tab=performance_summary" class="list-group-item list-group-item-action <?php echo $active_tab === 'performance_summary' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line me-2"></i> Class Performance Summary
                </a>
                <a href="dashboard.php?tab=teacher_stats" class="list-group-item list-group-item-action <?php echo $active_tab === 'teacher_stats' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie me-2"></i> Teacher Statistics
                </a>
                <a href="view_questions.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-question-circle me-2"></i> View Questions
                </a>
            </div>
        </div>

        <?php if ($has_allocations): ?>
            <div class="card mt-4">
                <div class="card-body text-center">
                    <h6 class="fw-bold mb-1">Teaching Duties</h6>
                    <p class="small text-muted mb-3">You have active class allocations</p>
                    <a href="../teacher/dashboard.php" class="btn btn-primary btn-sm w-100">
                        Open Teacher Portal
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-9">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <?php 
                    switch ($active_tab) {
                        case 'school': echo '<i class="fas fa-cog me-2"></i> School Settings'; break;
                        case 'classes': echo '<i class="fas fa-book me-2"></i> Class Management'; break;
                        case 'subjects': echo '<i class="fas fa-book-open me-2"></i> Subject Management'; break;
                        case 'teachers': echo '<i class="fas fa-chalkboard-teacher me-2"></i> Teacher Allocation'; break;
                        case 'students': echo '<i class="fas fa-user-graduate me-2"></i> Student Management'; break;
                        case 'exams': echo '<i class="fas fa-file-alt me-2"></i> Exam Management'; break;
                        case 'results': echo '<i class="fas fa-chart-bar me-2"></i> Exam Results'; break;
                        case 'grades': echo '<i class="fas fa-sliders-h me-2"></i> Grade Settings'; break;
                        case 'performance_summary': echo '<i class="fas fa-chart-line me-2"></i> Class Performance Summary'; break;
                        case 'teacher_stats': echo '<i class="fas fa-chart-pie me-2"></i> Teacher Statistics'; break;
                        
                        default: echo '<i class="fas fa-tachometer-alt me-2"></i> Dashboard';
                    }
                    ?>
                </h4>
            </div>
            <div class="card-body">
                <?php
                switch ($active_tab) {
                    case 'school':
                        include 'school_settings.php';
                        break;
                    case 'classes':
                        include 'class_management.php';
                        break;
                    case 'subjects':
                        include 'subject_management.php';
                        break;
                    case 'teachers':
                        include 'teacher_allocation.php';
                        break;
                    case 'students':
                        include 'student_management.php';
                        break;
                    case 'exams':
                        include 'exam_management.php';
                        break;
                    case 'results':
                        include 'view_all_results.php';
                        break;
                    case 'grades':
                        include 'grade_settings.php';
                        break;
                    case 'performance_summary':
                        include 'class_performance_summary.php';
                        break;
                    case 'teacher_stats':
                        include 'teacher_stats_content.php';
                        break;
                    
                    default:
                        ?>
                        <div class="admin-overview">
                            <div class="admin-overview-intro">
                                <div><span class="admin-eyebrow">SCHOOL OPERATIONS</span><h2 class="mb-1">Your school at a glance</h2><p class="mb-0">Jump into the areas you manage most.</p></div>
                                <a href="calendar_management.php" class="btn btn-outline-primary"><i class="far fa-calendar-alt me-2"></i>School calendar</a>
                            </div>
                            <div class="row g-3 admin-metrics">
                                <div class="col-md-6"><a href="dashboard.php?tab=results" class="admin-metric-card"><span class="admin-metric-icon"><i class="fas fa-chart-bar"></i></span><span><small>SUBMITTED EXAM ATTEMPTS</small><strong><?php echo number_format((int)$results_count); ?></strong></span><i class="fas fa-external-link-alt admin-metric-arrow"></i></a></div>
                                <div class="col-md-6"><a href="resit_requests.php" class="admin-metric-card"><span class="admin-metric-icon sky"><i class="fas fa-sync-alt"></i></span><span><small>RESIT REQUESTS TO REVIEW</small><strong><?php echo number_format((int)$pending_resit_count); ?></strong></span><i class="fas fa-external-link-alt admin-metric-arrow"></i></a></div>
                            </div>
                            <div class="admin-section-heading"><h3>Quick access</h3><span>Core administration</span></div>
                            <div class="row g-3 admin-shortcuts">
                                <div class="col-sm-6 col-xl-4"><a class="admin-shortcut" href="dashboard.php?tab=exams"><span class="admin-shortcut-art"><img class="admin-shortcut-image" src="../assets/images/dashboard/illustrations/examinations.svg?v=1" alt=""></span><span class="admin-shortcut-content"><span class="admin-shortcut-title">Examinations</span><span class="admin-shortcut-description">Create exams and manage schedules</span><span class="admin-shortcut-action">Open section <i class="fas fa-arrow-right"></i></span></span></a></div>
                                <div class="col-sm-6 col-xl-4"><a class="admin-shortcut" href="dashboard.php?tab=students"><span class="admin-shortcut-art"><img class="admin-shortcut-image" src="../assets/images/dashboard/illustrations/students.svg?v=1" alt=""></span><span class="admin-shortcut-content"><span class="admin-shortcut-title">Students</span><span class="admin-shortcut-description">Manage student accounts and classes</span><span class="admin-shortcut-action">Open section <i class="fas fa-arrow-right"></i></span></span></a></div>
                                <div class="col-sm-6 col-xl-4"><a class="admin-shortcut" href="dashboard.php?tab=teachers"><span class="admin-shortcut-art"><img class="admin-shortcut-image" src="../assets/images/dashboard/illustrations/teachers.svg?v=1" alt=""></span><span class="admin-shortcut-content"><span class="admin-shortcut-title">Teacher allocation</span><span class="admin-shortcut-description">Connect staff with classes and subjects</span><span class="admin-shortcut-action">Open section <i class="fas fa-arrow-right"></i></span></span></a></div>
                                <div class="col-sm-6 col-xl-4"><a class="admin-shortcut" href="dashboard.php?tab=classes"><span class="admin-shortcut-art"><img class="admin-shortcut-image" src="../assets/images/dashboard/illustrations/classes.svg?v=1" alt=""></span><span class="admin-shortcut-content"><span class="admin-shortcut-title">Classes</span><span class="admin-shortcut-description">Set up class groups and grade levels</span><span class="admin-shortcut-action">Open section <i class="fas fa-arrow-right"></i></span></span></a></div>
                                <div class="col-sm-6 col-xl-4"><a class="admin-shortcut" href="dashboard.php?tab=subjects"><span class="admin-shortcut-art"><img class="admin-shortcut-image" src="../assets/images/dashboard/illustrations/subjects.svg?v=1" alt=""></span><span class="admin-shortcut-content"><span class="admin-shortcut-title">Subjects</span><span class="admin-shortcut-description">Organize the curriculum by class</span><span class="admin-shortcut-action">Open section <i class="fas fa-arrow-right"></i></span></span></a></div>
                                <div class="col-sm-6 col-xl-4"><a class="admin-shortcut" href="dashboard.php?tab=school"><span class="admin-shortcut-art"><img class="admin-shortcut-image" src="../assets/images/dashboard/illustrations/school.svg?v=1" alt=""></span><span class="admin-shortcut-content"><span class="admin-shortcut-title">School profile</span><span class="admin-shortcut-description">Update school identity and settings</span><span class="admin-shortcut-action">Open section <i class="fas fa-arrow-right"></i></span></span></a></div>
                            </div>
                        </div>
                        <?php
                        break;
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
