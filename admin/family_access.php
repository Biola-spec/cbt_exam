<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/term_results.php';
require_once '../includes/family_access.php';
requireLogin();
if (getUserRole() !== 'admin') { header('Location: ../login.php'); exit; }
$db = getDBConnection();
$message = '';
$familySchemaReady = $db && familyAccessEnsureSchema($db);
if (!$familySchemaReady) $message = 'Could not set up family access. Apply database/family_access_all_sections.sql or grant the database user permission to create tables.';
if ($familySchemaReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!termResultsVerifyCsrf($_POST['csrf_token'] ?? '')) $message = 'Session expired. Refresh and try again.';
    else try {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $label = trim($_POST['family_label'] ?? '');
            $next = (int)($db->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(access_code,'-',-1) AS UNSIGNED)),0)+1 n FROM early_years_family_codes")->fetch_assoc()['n'] ?? 1);
            $code = 'FAMAIIS-' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
            $createdBy = (int)getUserId();
            $stmt = $db->prepare('INSERT INTO early_years_family_codes(access_code,family_label,created_by) VALUES(?,?,?)');
            $stmt->bind_param('ssi', $code, $label, $createdBy); $stmt->execute(); $stmt->close();
            $message = 'Family code created: ' . $code;
        } elseif ($action === 'link') {
            $familyId = (int)($_POST['family_id'] ?? 0);
            $classIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['class_ids'] ?? [])))));
            $studentIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['student_ids'] ?? [])))));
            if (!$familyId || !$classIds || !$studentIds) throw new RuntimeException('Choose a family, at least one class, and at least one student.');
            $checkFamily = $db->prepare('SELECT 1 FROM early_years_family_codes WHERE id=?');
            $checkFamily->bind_param('i', $familyId); $checkFamily->execute(); $familyExists = (bool)$checkFamily->get_result()->fetch_row(); $checkFamily->close();
            if (!$familyExists) throw new RuntimeException('Choose a valid family code.');
            $safeIds = implode(',', $studentIds);
            $safeClasses = implode(',', $classIds);
            $validCount = (int)($db->query("SELECT COUNT(DISTINCT id) total FROM users WHERE role='student' AND class_id IN ($safeClasses) AND id IN ($safeIds)")->fetch_assoc()['total'] ?? 0);
            if ($validCount !== count($studentIds)) throw new RuntimeException('One or more selected students are invalid. Refresh and select them again.');
            $db->begin_transaction();
            try {
                $insert = $db->prepare("INSERT IGNORE INTO family_student_links(family_id,student_type,student_id) VALUES(?,'student',?)");
                foreach ($studentIds as $studentId) { $insert->bind_param('ii', $familyId, $studentId); $insert->execute(); }
                $insert->close(); $db->commit();
            } catch (Throwable $e) { $db->rollback(); throw $e; }
            $message = count($studentIds) . ' student' . (count($studentIds) === 1 ? ' linked' : 's linked') . ' to the family code.';
        } elseif ($action === 'unlink') {
            $familyId = (int)($_POST['family_id'] ?? 0); $studentId = (int)($_POST['student_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM family_student_links WHERE family_id=? AND student_type='student' AND student_id=?");
            $stmt->bind_param('ii', $familyId, $studentId); $stmt->execute(); $stmt->close(); $message = 'Student unlinked.';
        }
    } catch (Throwable $e) { $message = $e instanceof mysqli_sql_exception ? 'Could not save. Check that the all sections family access migration has been applied.' : $e->getMessage(); }
}
$families = $familySchemaReady ? $db->query('SELECT id,access_code,family_label FROM early_years_family_codes ORDER BY id DESC')->fetch_all(MYSQLI_ASSOC) : [];
$classes = $familySchemaReady ? $db->query("SELECT DISTINCT c.id,c.name FROM classes c JOIN users u ON u.class_id=c.id AND u.role='student' ORDER BY c.name")->fetch_all(MYSQLI_ASSOC) : [];
$students = $familySchemaReady ? $db->query("SELECT u.id,u.full_name,u.admission_number,u.class_id,c.name class_name FROM users u LEFT JOIN classes c ON c.id=u.class_id WHERE u.role='student' ORDER BY c.name,u.full_name")->fetch_all(MYSQLI_ASSOC) : [];
$links = $familySchemaReady ? $db->query("SELECT fsl.family_id,fsl.student_id,fc.access_code,fc.family_label,u.full_name,u.admission_number,c.name class_name FROM family_student_links fsl JOIN early_years_family_codes fc ON fc.id=fsl.family_id JOIN users u ON u.id=fsl.student_id AND u.role='student' LEFT JOIN classes c ON c.id=u.class_id WHERE fsl.student_type='student' ORDER BY fc.id DESC,c.name,u.full_name")->fetch_all(MYSQLI_ASSOC) : [];
$activeTab = ($_POST['action'] ?? $_GET['tab'] ?? '') === 'create' ? 'create' : ((in_array($_POST['action'] ?? $_GET['tab'] ?? '', ['link'], true) ? 'link' : 'linked'));
include '../includes/header.php';
?>
<main class="container py-4">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div><h1 class="h3 mb-1">Family access across all sections</h1><p class="text-muted mb-0">Create a code once, then link any school student or Early Years pupil to it.</p></div><a class="btn btn-outline-secondary" href="dashboard.php">Back to admin</a></div>
<?php if ($message): ?><div class="alert alert-info"><?=htmlspecialchars($message)?></div><?php endif; ?>
<style>
.family-tabs{gap:.5rem;padding:.55rem;margin-bottom:1.25rem;border:1px solid #e3eaf4;border-radius:18px;background:linear-gradient(135deg,#f8fbff,#edf4ff);box-shadow:0 10px 28px rgba(30,60,100,.07)}
.family-tabs .nav-link{display:flex;align-items:center;gap:.65rem;padding:.85rem 1rem;border:0;border-radius:13px;color:#53647d;font-weight:700;transition:all .18s ease}
.family-tabs .nav-link i{display:grid;place-items:center;width:36px;height:36px;border-radius:11px;background:#fff;color:#3470ca;box-shadow:0 3px 10px rgba(30,60,100,.08)}
.family-tabs .nav-link:hover{color:#174c9b;background:#fff;transform:translateY(-1px)}
.family-tabs .nav-link.active{color:#fff;background:linear-gradient(120deg,#2463c5,#4b86e8);box-shadow:0 7px 18px rgba(36,99,197,.22)}
.family-tabs .nav-link.active i{color:#2463c5}
.family-panel{animation:familyPanelIn .2s ease-out}.family-panel-card{border:1px solid #e5ebf4;border-radius:17px;box-shadow:0 8px 24px rgba(33,52,80,.055)}
.family-panel-title{display:flex;align-items:center;gap:.85rem;margin-bottom:1.25rem}.family-panel-title i{display:grid;place-items:center;width:44px;height:44px;border-radius:14px;background:#edf4ff;color:#2865c2}
.family-panel-title h2{margin:0;color:#21334d;font-size:1.18rem;font-weight:750}.family-panel-title p{margin:.2rem 0 0;color:#718096;font-size:.9rem}
.family-help{font-size:.84rem;color:#738198}.family-check-list{display:grid;gap:.55rem;max-height:330px;overflow:auto;padding:.75rem;border:1px solid #e1e8f1;border-radius:13px;background:#fbfcfe}.family-check-row{display:flex;align-items:center;gap:.7rem;padding:.65rem .75rem;border:1px solid #e8edf4;border-radius:11px;background:#fff;cursor:pointer}.family-check-row:hover{border-color:#9ab8e8;background:#f7faff}.family-check-row .form-check-input{width:1.1rem;height:1.1rem;margin:0}.family-check-row span{font-weight:600;color:#34445d}.family-class-group{padding:.75rem;border:1px solid #e5ebf4;border-radius:13px;background:#f8faff}.family-class-group+.family-class-group{margin-top:.6rem}.family-class-group-title{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:.55rem;color:#294366;font-weight:700}.family-student-count{padding:.55rem .8rem;border-radius:999px;background:#edf4ff;color:#245eae;font-weight:700}
@keyframes familyPanelIn{from{opacity:.55;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:600px){.family-tabs{gap:.15rem}.family-tabs .nav-link{flex-direction:column;justify-content:center;gap:.3rem;padding:.65rem .25rem;font-size:.76rem}.family-tabs .nav-link i{width:32px;height:32px}}
@media(prefers-reduced-motion:reduce){.family-panel,.family-tabs .nav-link{animation:none;transition:none}}
</style>
<ul class="nav nav-pills nav-fill family-tabs" role="tablist" aria-label="Family code management">
 <li class="nav-item" role="presentation"><button class="nav-link <?=$activeTab==='create'?'active':''?>" id="create-family-tab" data-bs-toggle="tab" data-bs-target="#create-family-panel" type="button" role="tab" aria-controls="create-family-panel" aria-selected="<?=$activeTab==='create'?'true':'false'?>"><i class="fas fa-key"></i><span>Create family code</span></button></li>
 <li class="nav-item" role="presentation"><button class="nav-link <?=$activeTab==='link'?'active':''?>" id="link-students-tab" data-bs-toggle="tab" data-bs-target="#link-students-panel" type="button" role="tab" aria-controls="link-students-panel" aria-selected="<?=$activeTab==='link'?'true':'false'?>"><i class="fas fa-user-group"></i><span>Link students</span></button></li>
 <li class="nav-item" role="presentation"><button class="nav-link <?=$activeTab==='linked'?'active':''?>" id="linked-students-tab" data-bs-toggle="tab" data-bs-target="#linked-students-panel" type="button" role="tab" aria-controls="linked-students-panel" aria-selected="<?=$activeTab==='linked'?'true':'false'?>"><i class="fas fa-list-check"></i><span>Linked students</span></button></li>
</ul>
<div class="tab-content">
 <section class="tab-pane fade <?=$activeTab==='create'?'show active':''?> family-panel" id="create-family-panel" role="tabpanel" aria-labelledby="create-family-tab" tabindex="0">
  <div class="card card-body family-panel-card"><div class="family-panel-title"><i class="fas fa-key"></i><div><h2>Create family code</h2><p>Generate one access code that can be shared by the whole family.</p></div></div><form method="post" class="row g-3 align-items-end"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(termResultsCsrfToken())?>"><input type="hidden" name="action" value="create"><div class="col-md-7"><label class="form-label" for="family_label">Family label (optional)</label><input id="family_label" name="family_label" class="form-control" placeholder="e.g. Adewale family"></div><div class="col-md-3"><button class="btn btn-primary px-4"><i class="fas fa-wand-magic-sparkles me-1"></i>Generate code</button></div></form></div>
 </section>
 <section class="tab-pane fade <?=$activeTab==='link'?'show active':''?> family-panel" id="link-students-panel" role="tabpanel" aria-labelledby="link-students-tab" tabindex="0">
  <div class="card card-body family-panel-card"><div class="family-panel-title"><i class="fas fa-user-group"></i><div><h2>Link students</h2><p>Check the classes, then tick each student to link. The selection count updates as you go.</p></div></div>
  <form method="post" id="familyLinkForm"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(termResultsCsrfToken())?>"><input type="hidden" name="action" value="link"><div class="row g-3">
   <div class="col-12"><label class="form-label" for="family_id">Family code</label><select class="form-select" id="family_id" name="family_id" required><option value="">Choose family</option><?php foreach($families as $family): ?><option value="<?=$family['id']?>"><?=htmlspecialchars(($family['family_label'] ?: 'Family').' · '.$family['access_code'])?></option><?php endforeach; ?></select></div>
   <div class="col-lg-5"><label class="form-label">1. Check classes</label><div class="family-check-list" id="class_ids"><?php foreach($classes as $class): ?><label class="family-check-row"><input class="form-check-input family-class-checkbox" type="checkbox" name="class_ids[]" value="<?=$class['id']?>"><span><?=htmlspecialchars($class['name'])?></span></label><?php endforeach; ?><?php if(!$classes): ?><div class="text-muted small">No classes with registered students were found.</div><?php endif; ?></div><div class="family-help mt-1">Choose as many classes as needed.</div></div>
   <div class="col-lg-7"><div class="d-flex justify-content-between align-items-center mb-2"><label class="form-label mb-0">2. Check students</label><span class="family-student-count" id="family_student_count">0 selected</span></div><div class="family-check-list" id="student_checkbox_list"><div class="text-muted small">Choose a class to show its students.</div></div><div class="family-help mt-1">Students from every checked class appear here. You can select individuals or all students in a class.</div></div>
   <div class="col-12 d-flex justify-content-end"><button class="btn btn-success px-4"><i class="fas fa-link me-1"></i>Link selected students</button></div>
  </div></form></div>
 </section>
 <section class="tab-pane fade <?=$activeTab==='linked'?'show active':''?> family-panel" id="linked-students-panel" role="tabpanel" aria-labelledby="linked-students-tab" tabindex="0">
  <div class="card card-body family-panel-card"><div class="family-panel-title"><i class="fas fa-list-check"></i><div><h2>Linked students</h2><p>Review family access and remove a student link when needed.</p></div></div><div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>Family</th><th>Code</th><th>Student</th><th>Class</th><th></th></tr></thead><tbody><?php foreach($links as $link): ?><tr><td><?=htmlspecialchars($link['family_label'] ?: 'Family')?></td><td><code><?=htmlspecialchars($link['access_code'])?></code></td><td><?=htmlspecialchars($link['full_name'])?></td><td><?=htmlspecialchars($link['class_name'] ?: 'No class assigned')?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(termResultsCsrfToken())?>"><input type="hidden" name="action" value="unlink"><input type="hidden" name="family_id" value="<?=$link['family_id']?>"><input type="hidden" name="student_id" value="<?=$link['student_id']?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-link-slash me-1"></i>Unlink</button></form></td></tr><?php endforeach; ?><?php if (!$links): ?><tr><td colspan="5" class="text-muted text-center py-4">No regular students linked yet.</td></tr><?php endif; ?></tbody></table></div></div>
 </section>
</div>
<script>
(function(){
 const allStudents=<?=json_encode($students,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
 const classInputs=Array.from(document.querySelectorAll('.family-class-checkbox'));
 const studentList=document.getElementById('student_checkbox_list'), countLabel=document.getElementById('family_student_count');
 if(!classInputs.length||!studentList)return;
 function updateCount(){const count=studentList.querySelectorAll('.family-student-checkbox:checked').length;countLabel.textContent=count+' selected';}
 function refreshStudents(){
  const selectedClasses=classInputs.filter(input=>input.checked).map(input=>input.value);
  const previouslyChecked=new Set(Array.from(studentList.querySelectorAll('.family-student-checkbox:checked')).map(input=>input.value));
  studentList.replaceChildren();
  if(!selectedClasses.length){studentList.innerHTML='<div class="text-muted small">Choose a class to show its students.</div>';updateCount();return;}
  const matches=allStudents.filter(student=>selectedClasses.includes(String(student.class_id)));
  const groups=new Map();matches.forEach(student=>{const key=String(student.class_id);if(!groups.has(key))groups.set(key,{name:student.class_name||'Class',students:[]});groups.get(key).students.push(student);});
  if(!matches.length){studentList.innerHTML='<div class="text-muted small">No students found in the selected classes.</div>';updateCount();return;}
  groups.forEach((group,classId)=>{
   const box=document.createElement('section');box.className='family-class-group';
   const header=document.createElement('div');header.className='family-class-group-title';
   const title=document.createElement('span');title.textContent=group.name+' · '+group.students.length+' student'+(group.students.length===1?'':'s');
   const allLabel=document.createElement('label');allLabel.className='small d-flex align-items-center gap-2';allLabel.append('Select all');
   const allBox=document.createElement('input');allBox.type='checkbox';allBox.className='form-check-input family-select-all';allBox.dataset.classId=classId;allLabel.append(allBox);
   header.append(title,allLabel);box.append(header);
   group.students.forEach(student=>{
    const label=document.createElement('label');label.className='family-check-row';
    const checkbox=document.createElement('input');checkbox.type='checkbox';checkbox.name='student_ids[]';checkbox.value=student.id;checkbox.className='form-check-input family-student-checkbox';checkbox.checked=previouslyChecked.has(String(student.id));checkbox.addEventListener('change',()=>{updateCount();updateSelectAll(classId,box);});
    const detail=[student.full_name,student.admission_number].filter(Boolean).join(' · ');const text=document.createElement('span');text.textContent=detail;label.append(checkbox,text);box.append(label);
   });
   allBox.addEventListener('change',()=>{box.querySelectorAll('.family-student-checkbox').forEach(input=>input.checked=allBox.checked);updateCount();});
   studentList.append(box);updateSelectAll(classId,box);
  });updateCount();
 }
 function updateSelectAll(classId,box){const all=box.querySelector('.family-select-all'),items=Array.from(box.querySelectorAll('.family-student-checkbox'));all.checked=items.length>0&&items.every(input=>input.checked);all.indeterminate=items.some(input=>input.checked)&&!all.checked;}
 classInputs.forEach(input=>input.addEventListener('change',refreshStudents));
 document.getElementById('familyLinkForm').addEventListener('submit',event=>{if(!classInputs.some(input=>input.checked)){event.preventDefault();alert('Check at least one class.');return;}if(!studentList.querySelector('.family-student-checkbox:checked')){event.preventDefault();alert('Check at least one student to link.');}});
 refreshStudents();
})();
</script>
</main><?php include '../includes/footer.php'; ?>
