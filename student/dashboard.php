<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is logged in and is a student
requireLogin();
if (getUserRole() !== 'student') {
    header('Location: ../login.php');
    exit();
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'available';
?>

<?php include '../includes/header.php'; ?>

<div class="dashboard-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="dashboard-title"><i class="fas fa-user-graduate me-2"></i> Student Dashboard</h1>
                <p class="mb-0">Welcome, <?php echo htmlspecialchars(getUserName()); ?></p>
            </div>
            <div class="col-md-6 text-md-end">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">
                            <?php 
                            switch ($active_tab) {
                                case 'available': echo 'Available Exams'; break;
                                case 'history': echo 'Exam History'; break;
                                case 'results': echo 'My Examination Results'; break;
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

<div class="row">
    <div class="col-md-3 order-2 order-md-1">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-cogs me-2"></i> Navigation</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="dashboard.php?tab=available" class="list-group-item list-group-item-action <?php echo $active_tab === 'available' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt me-2"></i> Available Exams
                </a>
                <a href="dashboard.php?tab=history" class="list-group-item list-group-item-action <?php echo $active_tab === 'history' ? 'active' : ''; ?>">
                    <i class="fas fa-history me-2"></i> Exam History
                </a>
                <a href="dashboard.php?tab=results" class="list-group-item list-group-item-action <?php echo $active_tab === 'results' ? 'active' : ''; ?>">
                    <i class="fas fa-poll me-2"></i> My Examination Results
                </a>
                <form method="POST" action="../logout.php" class="list-group-item list-group-item-action p-0">
                    <button type="submit" class="btn btn-link text-danger w-100 text-start px-3 py-2 border-0 text-decoration-none" style="display: block; background: none; box-shadow: none;">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-9 order-1 order-md-2">
        <div class="card shadow-sm fade-in">
            <div class="card-header">
                <h4 class="mb-0">
                    <?php 
                    switch ($active_tab) {
                        case 'available': echo '<i class="fas fa-file-alt me-2"></i> Available Exams'; break;
                        case 'history': echo '<i class="fas fa-history me-2"></i> Exam History'; break;
                        case 'results': echo '<i class="fas fa-poll me-2"></i> My Examination Results'; break;
                        default: echo '<i class="fas fa-user-graduate me-2"></i> Dashboard';
                    }
                    ?>
                </h4>
            </div>
            <div class="card-body">
                <?php
                switch ($active_tab) {
                    case 'available':
                        include 'available_exams.php';
                        break;
                    case 'history':
                        include 'exam_history.php';
                        break;
                    case 'results':
                        include 'results_list.php';
                        break;
                    default:
                        ?>
                        <div class="animate-fade-in text-center py-4">
                            <div class="mb-4">
                                <div class="d-inline-flex align-items-center justify-content-center bg-primary-soft rounded-circle mb-3" style="width: 80px; height: 80px;">
                                    <i class="fas fa-graduation-cap fa-2x text-primary"></i>
                                </div>
                                <h3 class="fw-800">Hello, <?php echo explode(' ', getUserName())[0]; ?>!</h3>
                                <p class="text-muted">Ready to excel in your assessments today?</p>
                            </div>
                            
                            <div class="row g-4 mt-2">
                                <div class="col-md-6">
                                    <a href="dashboard.php?tab=available" class="card h-100 border-0 shadow-sm text-decoration-none hover-lift">
                                        <div class="card-body p-4">
                                            <div class="stat-icon bg-primary-soft text-primary mb-3 mx-auto">
                                                <i class="fas fa-file-signature"></i>
                                            </div>
                                            <h5 class="fw-bold text-dark">Take Exam</h5>
                                            <p class="small text-muted mb-0">View all active assessments currently available for you</p>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a href="dashboard.php?tab=history" class="card h-100 border-0 shadow-sm text-decoration-none hover-lift">
                                        <div class="card-body p-4">
                                            <div class="stat-icon bg-success-soft text-success mb-3 mx-auto">
                                                <i class="fas fa-medal"></i>
                                            </div>
                                            <h5 class="fw-bold text-dark">My Progress</h5>
                                            <p class="small text-muted mb-0">Review your past scores and detailed performance history</p>
                                        </div>
                                    </a>
                                </div>
                            </div>

                            <div class="mt-4">
                                <?php
                                $calendar_widget_id = 'student_home_calendar';
                                $show_dropdown_toggle = true;
                                $calendar_collapsed_default = true;
                                include '../includes/school_calendar.php';
                                ?>
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
