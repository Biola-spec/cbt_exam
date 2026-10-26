<?php
require_once '../config/database.php';
require_once '../config/session.php';

if (!isPublicExamSession() || getUserRole() !== 'student') {
    header('Location: ../login.php');
    exit();
}

$body_class = 'student-dashboard-page';
?>
<?php include '../includes/header.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Published Subject Exams</h1>
            <p class="text-muted mb-0">Signed in as <?php echo htmlspecialchars(getUserName()); ?>. This access is limited to exams for your subject code.</p>
        </div>
        <a class="btn btn-outline-secondary" href="../logout.php">Exit</a>
    </div>
    <?php require 'available_exams.php'; ?>
</div>
<?php include '../includes/footer.php'; ?>
