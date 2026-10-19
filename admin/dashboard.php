<?php 
require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is logged in and is an admin
requireLogin();
if (getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'school';
$message = isset($_GET['message']) ? $_GET['message'] : '';

// Count results for badge
$results_count = 0;
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
?>

<?php include '../includes/header.php'; ?>

<div class="dashboard-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="dashboard-title"><i class="fas fa-tachometer-alt me-2"></i> Admin Dashboard</h1>
                <p class="mb-0">Manage your CBT Exam System</p>
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
                        <div class="row text-center mt-2">
                            <div class="col-md-4 mb-4">
                                <div class="card bg-light h-100">
                                    <div class="card-body">
                                        <i class="fas fa-school fa-3x mb-3 text-primary"></i>
                                        <h5>School Settings</h5>
                                        <p class="text-muted">Manage school name, logo and details.</p>
                                        <a href="dashboard.php?tab=school" class="btn btn-outline-primary btn-sm">Manage</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="card bg-light h-100">
                                    <div class="card-body">
                                        <i class="fas fa-file-alt fa-3x mb-3 text-success"></i>
                                        <h5>Exam Management</h5>
                                        <p class="text-muted">Create and manage CBT exams.</p>
                                        <a href="dashboard.php?tab=exams" class="btn btn-outline-success btn-sm">Manage</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="card bg-light h-100">
                                    <div class="card-body">
                                        <i class="fas fa-users-cog fa-3x mb-3 text-warning"></i>
                                        <h5>Staff Allocation</h5>
                                        <p class="text-muted">Allocate subjects to teachers.</p>
                                        <a href="dashboard.php?tab=teachers" class="btn btn-outline-warning btn-sm">Manage</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="card bg-light">
                                    <div class="card-body d-flex align-items-center">
                                        <i class="fas fa-chart-bar fa-2x me-3 text-info"></i>
                                        <div class="text-start">
                                            <h5 class="mb-1">Exam Results</h5>
                                            <p class="text-muted small mb-0">View all student exam attempts and scores.</p>
                                        </div>
                                        <a href="dashboard.php?tab=results" class="btn btn-sm btn-info text-white ms-auto">View</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="card bg-light">
                                    <div class="card-body d-flex align-items-center">
                                        <i class="fas fa-user-graduate fa-2x me-3 text-danger"></i>
                                        <div class="text-start">
                                            <h5 class="mb-1">Student Management</h5>
                                            <p class="text-muted small mb-0">Add, edit and manage students.</p>
                                        </div>
                                        <a href="dashboard.php?tab=students" class="btn btn-sm btn-danger ms-auto">Manage</a>
                                    </div>
                                </div>
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
