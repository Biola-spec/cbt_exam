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
<style>
.teacher-home{max-width:1120px;margin:0 auto}
.teacher-home-heading{padding:1.5rem 0 2rem;text-align:center}
.teacher-home-heading .eyebrow{display:inline-flex;align-items:center;gap:.5rem;padding:.45rem .8rem;border:1px solid #d8e6f7;border-radius:999px;background:#f4f8ff;color:#285ca2;font-size:.78rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
.teacher-home-heading h1{margin:.9rem 0 .45rem;font-weight:800;letter-spacing:-.03em}
.teacher-home-heading p{max-width:560px;margin:auto;color:#64748b}
.teacher-section-card{position:relative;display:flex;height:100%;min-height:280px;flex-direction:column;overflow:hidden;border:1px solid #e1e9f3;border-radius:22px;background:#fff;box-shadow:0 12px 32px rgba(30,55,90,.08);transition:transform .2s ease,box-shadow .2s ease}
.teacher-section-card:hover{transform:translateY(-4px);box-shadow:0 18px 40px rgba(30,55,90,.14)}
.teacher-section-card::before{position:absolute;inset:0 0 auto;height:6px;background:var(--section-accent);content:""}
.teacher-section-card.normal{--section-accent:linear-gradient(90deg,#2563eb,#60a5fa);--section-soft:#eff6ff;--section-color:#1d4ed8}
.teacher-section-card.early{--section-accent:linear-gradient(90deg,#0f9f8f,#5bd3b2);--section-soft:#e9fbf5;--section-color:#087c70}
.teacher-section-card-body{display:flex;flex:1;flex-direction:column;padding:2rem}
.teacher-section-icon{display:grid;width:62px;height:62px;place-items:center;border-radius:18px;background:var(--section-soft);color:var(--section-color);font-size:1.55rem}
.teacher-section-card h2{margin:1.35rem 0 .5rem;font-size:1.35rem;font-weight:800}
.teacher-section-card p{margin:0 0 1.5rem;color:#64748b;line-height:1.65}
.teacher-section-card .btn{align-self:flex-start;margin-top:auto;border-radius:11px;padding:.7rem 1rem;font-weight:700}
@media(max-width:575px){.teacher-home-heading{padding:1rem 0 1.5rem}.teacher-section-card{min-height:250px}.teacher-section-card-body{padding:1.5rem}}
@media(prefers-reduced-motion:reduce){.teacher-section-card{transition:none}.teacher-section-card:hover{transform:none}}
</style>
<main class="container py-4 py-md-5">
    <div class="teacher-home">
        <header class="teacher-home-heading">
            <span class="eyebrow"><i class="fas fa-chalkboard-teacher"></i> Teacher home</span>
            <h1 class="h2">Where would you like to work?</h1>
            <p>Choose a school section to open its dashboard and teaching tools.</p>
        </header>
        <div class="row g-4" aria-label="Teacher dashboard sections">
            <div class="col-md-6">
                <section class="teacher-section-card normal">
                    <div class="teacher-section-card-body">
                        <div class="teacher-section-icon"><i class="fas fa-user-graduate" aria-hidden="true"></i></div>
                        <h2>Normal Sections</h2>
                        <p>Manage exams, questions, class performance, term scores, and other school activities.</p>
                        <a class="btn btn-primary" href="dashboard.php?tab=exams">Open Normal Sections <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i></a>
                    </div>
                </section>
            </div>
            <div class="col-md-6">
                <section class="teacher-section-card early">
                    <div class="teacher-section-card-body">
                        <div class="teacher-section-icon"><i class="fas fa-child" aria-hidden="true"></i></div>
                        <h2>Early Years</h2>
                        <p>Open your assigned Early Years classes and enter pupil progress reports.</p>
                        <a class="btn btn-success" href="early_years.php">Open Early Years <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i></a>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>
<?php include '../includes/footer.php'; ?>
