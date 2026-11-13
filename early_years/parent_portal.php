<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/term_results.php';
require_once '../includes/early_years.php';
require_once '../includes/family_access.php';
require_once '../includes/results_sheet.php';
if (empty($_SESSION['early_years_family_id'])) { header('Location: ../login.php?tab=parent'); exit; }
$db = getDBConnection(); $familyId = (int)$_SESSION['early_years_family_id'];
if (!$db || !familyAccessEnsureSchema($db)) { http_response_code(503); exit('Family results are temporarily unavailable. Please contact the school administrator.'); }
$stmt = $db->prepare("SELECT l.student_type,l.student_id,
    CASE WHEN l.student_type='early_years' THEN ey.full_name ELSE u.full_name END full_name,
    CASE WHEN l.student_type='early_years' THEN ey.passport_path ELSE NULL END passport_path,
    CASE WHEN l.student_type='early_years' THEN eyc.name ELSE c.name END class_name
    FROM family_student_links l
    LEFT JOIN early_years_students ey ON l.student_type='early_years' AND ey.id=l.student_id
    LEFT JOIN early_years_classes eyc ON eyc.id=ey.class_id
    LEFT JOIN users u ON l.student_type='student' AND u.id=l.student_id AND u.role='student'
    LEFT JOIN classes c ON c.id=u.class_id
    WHERE l.family_id=? AND ((l.student_type='early_years' AND ey.id IS NOT NULL) OR (l.student_type='student' AND u.id IS NOT NULL))
    ORDER BY full_name");
$stmt->bind_param('i',$familyId); $stmt->execute(); $children=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
$earlyChildren=array_values(array_filter($children,static fn($child)=>$child['student_type']==='early_years'));
$schoolChildren=array_values(array_filter($children,static fn($child)=>$child['student_type']==='student'));
$schoolStudentId=(int)($_GET['student_id']??($schoolChildren[0]['student_id']??0));
$earlyStudentId=(int)($_GET['ey_student_id']??($earlyChildren[0]['student_id']??0));
$activeSection=($_GET['section']??'')==='school'?'school':'early_years';
$schoolStudent=null;foreach($schoolChildren as $child)if((int)$child['student_id']===$schoolStudentId){$schoolStudent=$child;break;}
$earlyStudent=null;foreach($earlyChildren as $child)if((int)$child['student_id']===$earlyStudentId){$earlyStudent=$child;break;}
$examResults=[];$error='';
if($schoolStudent){try{$examResults=resultSheetExamList($db,$schoolStudentId);}catch(Throwable $e){$error='Examination results are temporarily unavailable.';}}
$sessions=termResultsSessionRows($db);$sessionId=(int)($_GET['session_id']??0);
if(!$sessionId)foreach($sessions as $session)if((int)$session['is_current']){$sessionId=(int)$session['id'];break;}
if(!$sessionId&&$sessions)$sessionId=(int)$sessions[0]['id'];
$term=max(1,min(3,(int)($_GET['term']??1)));$type=($_GET['report_type']??'half_term')==='end_term'?'end_term':'half_term';
$earlyReport=$earlyStudent&&$sessionId?earlyYearsReport($db,$earlyStudentId,$sessionId,$term,$type):null;
$body_class='parent-portal';
$hasSectionParameter = array_key_exists('section', $_GET);
include '../includes/header.php';
?>
<style>
.parent-section-tabs{gap:.5rem;padding:.55rem;margin-bottom:1.25rem;border:1px solid #e3eaf4;border-radius:17px;background:linear-gradient(130deg,#f8fbff,#edf4ff);box-shadow:0 8px 24px rgba(30,60,100,.07)}
.parent-section-tabs .nav-link{display:flex;align-items:center;justify-content:center;gap:.6rem;padding:.8rem 1rem;border:0;border-radius:12px;color:#52647d;font-weight:700}.parent-section-tabs .nav-link i{color:#3470ca}
.parent-section-tabs .nav-link.active{color:#fff;background:linear-gradient(120deg,#2463c5,#4b86e8);box-shadow:0 7px 18px rgba(36,99,197,.2)}.parent-section-tabs .nav-link.active i{color:#fff}
.parent-section-panel{padding-top:.3rem}.parent-section-card{border:1px solid #e5ebf4;border-radius:16px;box-shadow:0 7px 22px rgba(33,52,80,.05)}
@media(max-width:575px){.parent-section-tabs .nav-link{flex-direction:column;gap:.25rem;padding:.65rem .3rem;font-size:.82rem}}
</style>
<main class="container py-4">
<div class="d-flex justify-content-between align-items-center mb-4"><div><div class="small text-uppercase text-primary fw-bold">Family access</div><h1 class="h3">Family results dashboard</h1><p class="text-muted mb-0">Welcome, <?=htmlspecialchars($_SESSION['early_years_family_label']??'Family')?>.</p></div><a class="btn btn-outline-secondary" href="parent_logout.php">Sign out</a></div>
<ul class="nav nav-pills nav-fill parent-section-tabs" role="tablist" aria-label="Student sections">
<li class="nav-item"><button class="nav-link <?=$activeSection==='early_years'?'active':''?>" id="early-years-tab" data-bs-toggle="tab" data-bs-target="#early-years-panel" type="button" role="tab"><i class="fas fa-child"></i> Early Years <span class="badge rounded-pill bg-light text-primary"><?=count($earlyChildren)?></span></button></li>
<li class="nav-item"><button class="nav-link <?=$activeSection==='school'?'active':''?>" id="school-sections-tab" data-bs-toggle="tab" data-bs-target="#school-sections-panel" type="button" role="tab"><i class="fas fa-user-graduate"></i> School Sections <span class="badge rounded-pill bg-light text-primary"><?=count($schoolChildren)?></span></button></li>
</ul>
<div class="tab-content">
<section class="tab-pane fade <?=$activeSection==='early_years'?'show active':''?> parent-section-panel" id="early-years-panel" role="tabpanel" aria-labelledby="early-years-tab">
<?php if(!$earlyChildren):?><div class="card card-body parent-section-card text-muted">No Early Years pupils are linked to this family code.</div><?php else:?>
<div class="row g-3 mb-4"><?php foreach($earlyChildren as $child):?><div class="col-md-6 col-xl-4"><article class="card h-100 parent-section-card"><div class="card-body d-flex align-items-center gap-3"><div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width:48px;height:48px"><i class="fas fa-child"></i></div><div class="flex-grow-1"><h2 class="h6 mb-1"><?=htmlspecialchars($child['full_name'])?></h2><div class="small text-muted"><?=htmlspecialchars($child['class_name']?:'Class not assigned')?></div></div></div><div class="card-footer bg-white border-0 pt-0"><a class="btn btn-outline-primary btn-sm" href="parent_portal.php?section=early_years&amp;ey_student_id=<?=(int)$child['student_id']?>#early-years-panel">View result</a></div></article></div><?php endforeach;?></div>
<form class="card card-body parent-section-card mb-4"><input type="hidden" name="section" value="early_years"><div class="row g-3 align-items-end"><div class="col-md-4"><label class="form-label">Early Years pupil</label><select class="form-select" name="ey_student_id"><?php foreach($earlyChildren as $child):?><option value="<?=$child['student_id']?>" <?=$earlyStudentId===(int)$child['student_id']?'selected':''?>><?=htmlspecialchars($child['full_name'].' · '.($child['class_name']?:'Class not assigned'))?></option><?php endforeach;?></select></div><div class="col-md-3"><label class="form-label">Session</label><select class="form-select" name="session_id"><?php foreach($sessions as $session):?><option value="<?=$session['id']?>" <?=$sessionId===(int)$session['id']?'selected':''?>><?=htmlspecialchars($session['name'])?></option><?php endforeach;?></select></div><div class="col-md-2"><label class="form-label">Term</label><select class="form-select" name="term"><?php for($i=1;$i<=3;$i++):?><option value="<?=$i?>" <?=$term===$i?'selected':''?>><?=htmlspecialchars(termLabel($i))?></option><?php endfor;?></select></div><div class="col-md-2"><label class="form-label">Report</label><select class="form-select" name="report_type"><option value="half_term" <?=$type==='half_term'?'selected':''?>>Half term</option><option value="end_term" <?=$type==='end_term'?'selected':''?>>End of term</option></select></div><div class="col-md-1"><button class="btn btn-primary w-100">View</button></div></div></form>
<?php if($earlyReport&&$earlyReport['report_data']):?><div class="alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-2"><span><?=htmlspecialchars($earlyReport['full_name'])?> · <?=htmlspecialchars($earlyReport['session_name']??'')?> · <?=htmlspecialchars(termLabel($term))?> · <?=$type==='half_term'?'Half term':'End of term'?></span><a class="btn btn-success" href="report.php?student_id=<?=$earlyStudentId?>&amp;session_id=<?=$sessionId?>&amp;term=<?=$term?>&amp;report_type=<?=htmlspecialchars($type)?>">Open / print report</a></div><?php else:?><div class="alert alert-secondary">This Early Years report has not been entered yet.</div><?php endif;?>
<?php endif;?></section>
<section class="tab-pane fade <?=$activeSection==='school'?'show active':''?> parent-section-panel" id="school-sections-panel" role="tabpanel" aria-labelledby="school-sections-tab">
<?php if(!$schoolChildren):?><div class="card card-body parent-section-card text-muted">No students from the regular school sections are linked to this family code.</div><?php else:?>
<div class="row g-3 mb-4"><?php foreach($schoolChildren as $child):?><div class="col-md-6 col-xl-4"><article class="card h-100 parent-section-card"><div class="card-body d-flex align-items-center gap-3"><div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width:48px;height:48px"><i class="fas fa-user-graduate"></i></div><div class="flex-grow-1"><h2 class="h6 mb-1"><?=htmlspecialchars($child['full_name'])?></h2><div class="small text-muted"><?=htmlspecialchars($child['class_name']?:'Class not assigned')?></div></div></div><div class="card-footer bg-white border-0 pt-0"><a class="btn btn-outline-primary btn-sm" href="parent_portal.php?section=school&amp;student_id=<?=(int)$child['student_id']?>#school-sections-panel">View result</a></div></article></div><?php endforeach;?></div>
<form class="card card-body parent-section-card mb-4"><input type="hidden" name="section" value="school"><label class="form-label" for="school_student_id">Student</label><div class="d-flex gap-2"><select class="form-select" id="school_student_id" name="student_id"><?php foreach($schoolChildren as $child):?><option value="<?=$child['student_id']?>" <?=$schoolStudentId===(int)$child['student_id']?'selected':''?>><?=htmlspecialchars($child['full_name'].' · '.($child['class_name']?:'Class not assigned'))?></option><?php endforeach;?></select><button class="btn btn-primary">View results</button></div></form>
<?php if($error):?><div class="alert alert-warning"><?=htmlspecialchars($error)?></div><?php endif;?>
<section class="card card-body parent-section-card mb-4"><h2 class="h5">My Examination Results</h2><p class="text-muted">Published results for examinations this student has submitted.</p><?php if(!$examResults):?><div class="alert alert-info mb-0">No published examination results are available yet.</div><?php else:?><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Examination</th><th>Academic Session</th><th>Subjects submitted</th><th>Submitted</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($examResults as $exam):?><tr><td><?=htmlspecialchars($exam['title'])?></td><td><?=htmlspecialchars($exam['academic_session']?:'Not recorded')?></td><td><?=(int)$exam['subjects_submitted']?></td><td><?=htmlspecialchars(date('M j, Y',strtotime($exam['last_submitted_at'])))?></td><td><?php if((int)$exam['pending_subjects']>0):?><span class="badge bg-warning text-dark">Marking pending</span><?php else:?><span class="badge bg-success">Available</span><?php endif;?></td><td><a class="btn btn-sm btn-outline-primary" href="../student/result_sheet.php?exam_id=<?=(int)$exam['id']?>&amp;student_id=<?=$schoolStudentId?>">View Results</a></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
<section class="card card-body parent-section-card"><h2 class="h5">Academic Term Results</h2><p class="text-muted">Open the student’s released term report sheets.</p><a class="btn btn-outline-primary align-self-start" href="../student/term_results.php?student_id=<?=$schoolStudentId?>"><i class="fas fa-award me-1"></i>View term results</a></section>
<?php endif;?></section>
</div>
</main>
<script>
document.querySelectorAll('#school-sections-panel form,#early-years-panel form').forEach(form=>form.addEventListener('submit',()=>{sessionStorage.setItem('familyResultsTab',form.closest('#school-sections-panel')?'school-sections-tab':'early-years-tab');}));
document.querySelectorAll('#school-sections-tab,#early-years-tab').forEach(tab=>tab.addEventListener('shown.bs.tab',()=>sessionStorage.setItem('familyResultsTab',tab.id)));
const savedFamilyTab=sessionStorage.getItem('familyResultsTab');if(!<?=json_encode($hasSectionParameter)?>&&savedFamilyTab==='school-sections-tab')bootstrap.Tab.getOrCreateInstance(document.getElementById(savedFamilyTab)).show();
</script>
<?php include '../includes/footer.php'; ?>
