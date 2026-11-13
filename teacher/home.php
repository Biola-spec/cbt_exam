<?php
require_once '../config/session.php';
requireLogin();
if (!in_array(getUserRole(), ['teacher', 'admin'], true)) {
    header('Location: ../login.php');
    exit();
}
$body_class = 'teacher-home-page';
include '../includes/header.php';
?>
<main class="container py-5">
    <section class="card shadow-sm mx-auto" style="max-width:760px">
        <div class="card-body p-4 p-md-5 text-center">
            <div class="small text-uppercase text-primary fw-bold mb-2">Teacher home</div>
            <h1 class="h3 mb-2">Choose a dashboard</h1>
            <p class="text-muted mb-4">Select the school section you want to work in.</p>
            <ul class="nav nav-tabs nav-fill" aria-label="Choose teacher dashboard">
                <li class="nav-item"><a class="nav-link" href="dashboard.php?tab=exams"><i class="fas fa-user-graduate me-2"></i>Normal Sections</a></li>
                <li class="nav-item"><a class="nav-link" href="early_years.php"><i class="fas fa-child me-2"></i>Early Years</a></li>
            </ul>
        </div>
    </section>
</main>
<?php include '../includes/footer.php'; ?>
