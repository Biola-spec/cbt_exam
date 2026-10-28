<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireLogin();
if (getUserRole() !== 'student') { http_response_code(403); exit('Forbidden'); }
$db=getDBConnection(); $studentId=(int)getUserId(); $examId=(int)($_GET['exam_id']??0);
$stmt=$db->prepare("SELECT e.id,e.title,c.name AS class_name,ea.status FROM entrance_applications ea JOIN entrance_exams root_ee ON root_ee.id=ea.entrance_exam_id JOIN exam_subjects root_es ON root_es.id=root_ee.exam_subject_id JOIN exams e ON e.id=root_es.exam_id JOIN classes c ON c.id=ea.target_class_id WHERE ea.user_id=? AND e.id=? AND ea.status IN ('pending','admitted') LIMIT 1");
$stmt->bind_param('ii',$studentId,$examId);$stmt->execute();$application=$stmt->get_result()->fetch_assoc();$stmt->close();
if(!$application){http_response_code(403);exit('This entrance exam is not assigned to your account.');}
$stmt=$db->prepare("SELECT es.id AS exam_subject_id,s.name AS subject_name,es.time_limit,es.is_published,e.is_published AS exam_published,(SELECT id FROM exam_attempts a WHERE a.student_id=? AND a.exam_subject_id=es.id AND a.submitted_at IS NULL ORDER BY a.started_at DESC LIMIT 1) AS active_attempt,(SELECT id FROM exam_attempts a WHERE a.student_id=? AND a.exam_subject_id=es.id AND a.submitted_at IS NOT NULL ORDER BY a.submitted_at DESC LIMIT 1) AS submitted_attempt,(SELECT score FROM exam_attempts a WHERE a.student_id=? AND a.exam_subject_id=es.id AND a.submitted_at IS NOT NULL ORDER BY a.submitted_at DESC LIMIT 1) AS score FROM entrance_exams ee JOIN exam_subjects es ON es.id=ee.exam_subject_id JOIN exams e ON e.id=es.exam_id JOIN subjects s ON s.id=es.subject_id WHERE e.id=? ORDER BY es.id");
$stmt->bind_param('iiii',$studentId,$studentId,$studentId,$examId);$stmt->execute();$sections=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
if(!$sections){http_response_code(404);exit('No subjects are assigned to this entrance exam.');}
foreach($sections as $section){if(!(int)$section['is_published']||!(int)$section['exam_published']){$notReady=true;break;}}
if(!empty($notReady)){$error='The entrance exam will be available when all subject sections have been published.';$nextSection=null;}
else {
    $nextSection=null;
    foreach($sections as $section) if(!empty($section['active_attempt'])){$nextSection=$section;break;}
    if(!$nextSection) foreach($sections as $section) if(empty($section['submitted_attempt'])){$nextSection=$section;break;}
    if($nextSection){header('Location: take_exam.php?exam_subject_id='.(int)$nextSection['exam_subject_id']);exit();}
}
?>
<?php include '../includes/header.php'; ?>
<style>
.entrance-finish{max-width:850px}.entrance-finish-hero{padding:1.8rem 2rem;border-radius:20px;background:linear-gradient(115deg,#173951,#216d79);color:#fff;box-shadow:0 14px 32px rgba(27,78,93,.16)}.entrance-finish-hero h1{font-weight:750;letter-spacing:-.035em}.entrance-finish .card{border:1px solid #e5ebf2;border-radius:16px;box-shadow:0 8px 24px rgba(31,52,86,.06);overflow:hidden}.entrance-section-row{padding:1rem 1.2rem;border-bottom:1px solid #edf1f5}.entrance-section-row:last-child{border-bottom:0}.entrance-section-status{padding:.4rem .7rem;border-radius:18px;background:#edf8f2;color:#16835e;font-size:.78rem;font-weight:700}
</style>
<div class="container py-5 entrance-finish"><div class="entrance-finish-hero mb-4"><div class="text-uppercase small fw-bold opacity-75 mb-2">Entrance examination</div><h1 class="h3 mb-1"><?php echo htmlspecialchars($application['title']); ?></h1><p class="mb-0"><?php echo htmlspecialchars($application['class_name']); ?> · Complete each subject section in sequence.</p></div>
<?php if(!empty($error)): ?><div class="alert alert-info"><?php echo htmlspecialchars($error); ?></div><?php else: ?><div class="card"><div class="card-header bg-white p-3"><h2 class="h5 mb-0">All subject sections complete</h2><p class="text-muted small mb-0 mt-1">Your entrance exam contains <?php echo count($sections); ?> subjects.</p></div><?php foreach($sections as $section): ?><div class="entrance-section-row d-flex justify-content-between align-items-center"><div><strong><?php echo htmlspecialchars($section['subject_name']); ?></strong><div class="small text-muted"><?php echo (int)$section['time_limit']; ?> minutes</div></div><span class="entrance-section-status"><i class="fas fa-check me-1"></i>Submitted<?php if($section['score']!==null): ?> · <?php echo htmlspecialchars((string)$section['score']); ?> marks<?php endif; ?></span></div><?php endforeach; ?><div class="p-3 text-center"><a class="btn btn-primary btn-lg" href="result_sheet.php?exam_id=<?php echo $examId; ?>"><i class="fas fa-poll me-2"></i>View entrance results</a><a class="btn btn-outline-secondary btn-lg ms-2" href="dashboard.php?tab=results">My results</a></div></div><?php endif; ?></div>
<?php include '../includes/footer.php'; ?>
