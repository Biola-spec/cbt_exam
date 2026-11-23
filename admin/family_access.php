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
            $namePrefix = preg_replace('/\\s+FAMILY$/i', '', $label);
            $namePrefix = strtoupper(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $namePrefix), '-'));
            $namePrefix = substr($namePrefix, 0, 32);
            $code = ($namePrefix !== '' ? $namePrefix . '-' : '') . 'FAMAIIS-FAMILY-' . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
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
        } elseif ($action === 'edit_family_code') {
            $familyId = (int)($_POST['family_id'] ?? 0);
            $label = trim($_POST['family_label'] ?? '');
            $code = strtoupper(trim($_POST['access_code'] ?? ''));
            if (!$familyId || $label === '') throw new RuntimeException('Enter a family name.');
            if (!preg_match('/^(?:FAMAIIS(?:-EY)?-[0-9]+|[A-Z0-9]+(?:-[A-Z0-9]+)*-FAMAIIS-FAMILY-[0-9]+)$/', $code)) throw new RuntimeException('Enter a valid family code.');
            $stmt = $db->prepare('UPDATE early_years_family_codes SET family_label=?,access_code=? WHERE id=?');
            $stmt->bind_param('ssi', $label, $code, $familyId); $stmt->execute(); $stmt->close();
            $message = 'Family code updated.';
        } elseif ($action === 'delete_family_code') {
            $familyId = (int)($_POST['family_id'] ?? 0);
            if (!$familyId) throw new RuntimeException('Choose a valid family code.');
            $stmt = $db->prepare('DELETE FROM early_years_family_codes WHERE id=?');
            $stmt->bind_param('i', $familyId); $stmt->execute(); $deleted = $stmt->affected_rows; $stmt->close();
            if (!$deleted) throw new RuntimeException('Family code not found.');
            $message = 'Family code and its linked student access were deleted.';
        } elseif ($action === 'unlink') {
            $familyId = (int)($_POST['family_id'] ?? 0); $studentId = (int)($_POST['student_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM family_student_links WHERE family_id=? AND student_type='student' AND student_id=?");
            $stmt->bind_param('ii', $familyId, $studentId); $stmt->execute(); $stmt->close(); $message = 'Student unlinked.';
        }
    } catch (Throwable $e) { $message = $e instanceof mysqli_sql_exception ? ($e->getCode() === 1062 ? 'That family code is already in use.' : 'Could not save. Check that the all sections family access migration has been applied.') : $e->getMessage(); }
}
$families = $familySchemaReady ? $db->query('SELECT id,access_code,family_label FROM early_years_family_codes ORDER BY id DESC')->fetch_all(MYSQLI_ASSOC) : [];
$classes = $familySchemaReady ? $db->query("SELECT DISTINCT c.id,c.name FROM classes c JOIN users u ON u.class_id=c.id AND u.role='student' ORDER BY c.name")->fetch_all(MYSQLI_ASSOC) : [];
$students = $familySchemaReady ? $db->query("SELECT u.id,u.full_name,u.admission_number,u.class_id,c.name class_name FROM users u LEFT JOIN classes c ON c.id=u.class_id WHERE u.role='student' ORDER BY c.name,u.full_name")->fetch_all(MYSQLI_ASSOC) : [];
$links = $familySchemaReady ? $db->query("SELECT fsl.family_id,fsl.student_id,fc.access_code,fc.family_label,u.full_name,u.admission_number,c.name class_name FROM family_student_links fsl JOIN early_years_family_codes fc ON fc.id=fsl.family_id JOIN users u ON u.id=fsl.student_id AND u.role='student' LEFT JOIN classes c ON c.id=u.class_id WHERE fsl.student_type='student' ORDER BY fc.id DESC,c.name,u.full_name")->fetch_all(MYSQLI_ASSOC) : [];
$familyRegister = [];
foreach ($families as $family) $familyRegister[(int)$family['id']] = $family + ['students' => []];
if ($familySchemaReady) {
    $registerRows = $db->query("SELECT fc.id family_id,fc.access_code,fc.family_label,fsl.student_id,fsl.student_type,u.full_name student_name,c.name class_name FROM early_years_family_codes fc LEFT JOIN family_student_links fsl ON fsl.family_id=fc.id AND fsl.student_type='student' LEFT JOIN users u ON u.id=fsl.student_id AND u.role='student' LEFT JOIN classes c ON c.id=u.class_id
        UNION ALL
        SELECT fc.id family_id,fc.access_code,fc.family_label,fsl.student_id,fsl.student_type,s.full_name student_name,c.name class_name FROM early_years_family_codes fc LEFT JOIN family_student_links fsl ON fsl.family_id=fc.id AND fsl.student_type='early_years' LEFT JOIN early_years_students s ON s.id=fsl.student_id LEFT JOIN early_years_classes c ON c.id=s.class_id
        ORDER BY family_label,access_code,student_name")->fetch_all(MYSQLI_ASSOC);
    foreach ($registerRows as $row) {
        $familyId = (int)$row['family_id'];
        if (!isset($familyRegister[$familyId])) $familyRegister[$familyId] = ['id' => $familyId, 'access_code' => $row['access_code'], 'family_label' => $row['family_label'], 'students' => []];
        if ($row['student_name'] !== null) $familyRegister[$familyId]['students'][] = ['id' => (int)$row['student_id'], 'type' => $row['student_type'] ?? '', 'name' => $row['student_name'], 'class' => $row['class_name']];
    }
}
$schoolName = getSchoolName();
$schoolLogo = getSchoolLogo();
$schoolLogoSrc = '';
if ($schoolLogo) {
    $logoPath = str_replace('\\', '/', trim($schoolLogo));
    if (preg_match('~^(?:https?:)?//|^data:~i', $logoPath)) {
        $schoolLogoSrc = $logoPath;
    } else {
        while (strpos($logoPath, '../') === 0) $logoPath = substr($logoPath, 3);
        $logoFile = __DIR__ . '/../' . ltrim($logoPath, '/');
        if (is_file($logoFile)) $schoolLogoSrc = (strpos($logoPath, '/') === 0 ? '' : '../') . ltrim($logoPath, '/');
    }
}
$tabRequest = $_POST['action'] ?? $_GET['tab'] ?? '';
$activeTab = in_array($tabRequest, ['edit_family_code','delete_family_code','register'], true) ? 'register' : ($tabRequest === 'create' ? 'create' : ($tabRequest === 'link' ? 'link' : 'linked'));
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
.family-register-head{display:flex;align-items:center;justify-content:center;gap:1rem;text-align:center;padding:1rem 0 1.25rem;border-bottom:2px solid #24364d;margin-bottom:1rem}.family-register-head img{width:76px;height:76px;object-fit:contain}.family-register-head h2{font-size:1.35rem;font-weight:800;margin:0}.family-register-head p{margin:.25rem 0 0;color:#58677c}
.family-code-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem}.family-code-card{position:relative;padding:1.1rem;border:1px solid #e2e9f2;border-radius:18px;background:linear-gradient(145deg,#fff,#f8fbff);box-shadow:0 8px 22px rgba(34,57,88,.07);transition:transform .18s ease,box-shadow .18s ease}.family-code-card:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(34,57,88,.12)}.family-code-card-head{display:flex;align-items:center;gap:.75rem;margin-bottom:.85rem}.family-code-icon{display:grid;place-items:center;width:42px;height:42px;border-radius:13px;background:#eaf2ff;color:#2463c5;font-size:1.05rem}.family-code-name{margin:0;color:#21334d;font-size:1.05rem;font-weight:750}.family-code-caption{color:#7b8799;font-size:.82rem}.family-code-open{display:flex;align-items:center;justify-content:space-between;gap:.6rem;width:100%;padding:.8rem .9rem;border:1px solid #d6e3f5;border-radius:12px;background:#f3f7fd;color:#1e4f94;text-align:left;cursor:pointer}.family-code-open:hover{border-color:#8aafe5;background:#eaf2ff}.family-code-open code{font-size:.96rem;font-weight:800;overflow-wrap:anywhere;color:#1c4f95}.family-code-open i{flex:none}.family-code-card-foot{display:flex;align-items:center;justify-content:space-between;gap:.6rem;margin-top:.85rem}.family-code-count{font-size:.84rem;color:#68778d}.family-code-actions{display:flex;align-items:center;gap:.4rem}.family-code-edit{position:absolute;z-index:2;right:0;top:3.2rem;width:min(280px,calc(100vw - 48px));padding:.9rem;border:1px solid #dbe4ef;border-radius:13px;background:#fff;box-shadow:0 12px 30px #182b4424}.family-modal{display:none;position:fixed;z-index:2000;inset:0;align-items:center;justify-content:center;padding:1rem}.family-modal.is-open{display:flex}.family-modal-backdrop{position:absolute;inset:0;background:rgba(12,25,44,.58);backdrop-filter:blur(3px)}.family-modal-panel{position:relative;z-index:1;width:min(760px,100%);max-height:min(88vh,900px);overflow:auto;border:0;border-radius:20px;background:#fff;box-shadow:0 24px 70px #101e3540}.family-modal-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.25rem 1.4rem;background:linear-gradient(120deg,#174f9d,#4784df);color:#fff}.family-modal-heading{display:flex;align-items:center;gap:.8rem}.family-modal-heading img{width:52px;height:52px;object-fit:contain;background:#fff;border-radius:12px;padding:4px}.family-modal-heading h2{margin:0;font-size:1.15rem;font-weight:750}.family-modal-heading p{margin:.2rem 0 0;color:#e1ecff;font-size:.86rem}.family-modal-close{border:0;background:transparent;color:#fff;font-size:1.6rem;line-height:1;cursor:pointer}.family-modal-body{padding:1.35rem}.family-modal-code{margin-bottom:1rem;padding:1rem;border:1px solid #d9e5f4;border-radius:13px;background:#f5f8fd}.family-modal-code small{display:block;color:#6d7d94;text-transform:uppercase;font-weight:700;font-size:.72rem;letter-spacing:.08em}.family-modal-code strong{display:block;margin-top:.35rem;color:#1e4f94;font-family:monospace;font-size:1.3rem;overflow-wrap:anywhere}.family-modal-family{margin-top:.3rem;color:#65758b}.family-roster-table{width:100%;border-collapse:collapse}.family-roster-table th,.family-roster-table td{padding:.7rem .75rem;border-bottom:1px solid #e6ecf3;text-align:left}.family-roster-table th{background:#f3f6fa;color:#475970;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em}.family-roster-empty{padding:1.4rem;text-align:center;border:1px dashed #cad6e4;border-radius:12px;color:#76849a}.family-modal-actions{display:flex;justify-content:flex-end;gap:.6rem;padding:0 1.35rem 1.25rem}.family-register-empty{grid-column:1/-1;padding:2.2rem;text-align:center;border:1px dashed #ccd8e6;border-radius:16px;color:#728198;background:#fbfcfe}
@media(max-width:600px){.family-code-grid{grid-template-columns:1fr}.family-modal{padding:.5rem}.family-modal-panel{max-height:94vh;border-radius:15px}.family-modal-head,.family-modal-body{padding:1rem}.family-modal-heading img{width:42px;height:42px}}
@media print{@page{size:A4 portrait;margin:12mm}body:not(.printing-family-roster) *{visibility:hidden!important}body:not(.printing-family-roster) .family-tabs,body:not(.printing-family-roster) .family-register-toolbar,body:not(.printing-family-roster) .family-panel:not(#family-register-panel){display:none!important}body:not(.printing-family-roster) #family-register-panel,body:not(.printing-family-roster) #family-register-panel *{visibility:visible!important}body:not(.printing-family-roster) #family-register-panel{display:block!important;position:absolute;inset:0;width:100%;margin:0!important;padding:0!important;opacity:1!important}.family-register-head{padding:.2rem 0 .65rem;margin-bottom:.65rem}.family-register-head img{width:62px;height:62px}.family-register-head h2{font-size:16pt}.family-code-grid{display:block}.family-code-card{break-inside:avoid;margin-bottom:8mm;border:1px solid #ccd5df;box-shadow:none}.family-code-card:hover{transform:none;box-shadow:none}.family-code-actions{display:none}.family-code-open{padding:.5rem;border:0;background:#fff}.family-code-open i{display:none}.family-code-count{color:#444}body.printing-family-roster *{visibility:hidden!important}body.printing-family-roster #family-roster-modal,body.printing-family-roster #family-roster-modal *{visibility:visible!important}body.printing-family-roster #family-roster-modal{display:block!important;position:absolute;inset:0;padding:0;background:#fff}body.printing-family-roster .family-modal-backdrop,body.printing-family-roster .family-modal-actions,body.printing-family-roster .family-modal-close{display:none!important}body.printing-family-roster .family-modal-panel{width:100%;max-height:none;overflow:visible;border:0;border-radius:0;box-shadow:none}body.printing-family-roster .family-modal-head{padding:.2rem 0 .7rem;background:#fff;color:#1d2b3d;border-bottom:2px solid #24364d}body.printing-family-roster .family-modal-heading p{color:#596a80}body.printing-family-roster .family-modal-body{padding:1rem 0}body.printing-family-roster .family-modal-code{background:#fff}}

</style>
<ul class="nav nav-pills nav-fill family-tabs" role="tablist" aria-label="Family code management">
 <li class="nav-item" role="presentation"><button class="nav-link <?=$activeTab==='create'?'active':''?>" id="create-family-tab" data-bs-toggle="tab" data-bs-target="#create-family-panel" type="button" role="tab" aria-controls="create-family-panel" aria-selected="<?=$activeTab==='create'?'true':'false'?>"><i class="fas fa-key"></i><span>Create family code</span></button></li>
 <li class="nav-item" role="presentation"><button class="nav-link <?=$activeTab==='link'?'active':''?>" id="link-students-tab" data-bs-toggle="tab" data-bs-target="#link-students-panel" type="button" role="tab" aria-controls="link-students-panel" aria-selected="<?=$activeTab==='link'?'true':'false'?>"><i class="fas fa-user-group"></i><span>Link students</span></button></li>
 <li class="nav-item" role="presentation"><button class="nav-link <?=$activeTab==='linked'?'active':''?>" id="linked-students-tab" data-bs-toggle="tab" data-bs-target="#linked-students-panel" type="button" role="tab" aria-controls="linked-students-panel" aria-selected="<?=$activeTab==='linked'?'true':'false'?>"><i class="fas fa-list-check"></i><span>Linked students</span></button></li>
 <li class="nav-item" role="presentation"><button class="nav-link <?=$activeTab==='register'?'active':''?>" id="family-register-tab" data-bs-toggle="tab" data-bs-target="#family-register-panel" type="button" role="tab" aria-controls="family-register-panel" aria-selected="<?=$activeTab==='register'?'true':'false'?>"><i class="fas fa-print"></i><span>Family code register</span></button></li>
</ul>
<div class="tab-content">
 <section class="tab-pane fade <?=$activeTab==='create'?'show active':''?> family-panel" id="create-family-panel" role="tabpanel" aria-labelledby="create-family-tab" tabindex="0">
  <div class="card card-body family-panel-card"><div class="family-panel-title"><i class="fas fa-key"></i><div><h2>Create family code</h2><p>Generate a family code using the family name as its prefix.</p></div></div><form method="post" class="row g-3 align-items-end"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(termResultsCsrfToken())?>"><input type="hidden" name="action" value="create"><div class="col-md-7"><label class="form-label" for="family_label">Family label (optional)</label><input id="family_label" name="family_label" class="form-control" placeholder="e.g. Muhammad Family"></div><div class="col-md-3"><button class="btn btn-primary px-4"><i class="fas fa-wand-magic-sparkles me-1"></i>Generate code</button></div></form></div>
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
  <div class="card card-body family-panel-card"><div class="family-panel-title"><i class="fas fa-list-check"></i><div><h2>Linked students</h2><p>Review family access and remove a student link when needed.</p></div></div><div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>Family</th><th>Student</th><th>Class</th><th></th></tr></thead><tbody><?php foreach($links as $link): ?><tr><td><?=htmlspecialchars($link['family_label'] ?: 'Family')?></td><td><?=htmlspecialchars($link['full_name'])?></td><td><?=htmlspecialchars($link['class_name'] ?: 'No class assigned')?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(termResultsCsrfToken())?>"><input type="hidden" name="action" value="unlink"><input type="hidden" name="family_id" value="<?=$link['family_id']?>"><input type="hidden" name="student_id" value="<?=$link['student_id']?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-link-slash me-1"></i>Unlink</button></form></td></tr><?php endforeach; ?><?php if (!$links): ?><tr><td colspan="4" class="text-muted text-center py-4">No regular students linked yet.</td></tr><?php endif; ?></tbody></table></div></div>
 </section>
 <section class="tab-pane fade <?=$activeTab==='register'?'show active':''?> family-panel" id="family-register-panel" role="tabpanel" aria-labelledby="family-register-tab" tabindex="0">
  <div class="family-register-toolbar d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div><h2 class="h5 mb-1">Family codes</h2><p class="text-muted mb-0">Each code appears once. Select a code to view and print everyone linked to that family.</p></div><button type="button" class="btn btn-primary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print family codes</button></div>
  <div class="family-register-head"><?php if($schoolLogoSrc): ?><img src="<?=htmlspecialchars($schoolLogoSrc,ENT_QUOTES)?>" alt="School logo"><?php endif; ?><div><h2><?=htmlspecialchars($schoolName)?></h2><p>Family Access Codes</p></div></div>
  <div class="family-code-grid"><?php foreach($familyRegister as $family): ?><article class="family-code-card">
   <div class="family-code-card-head"><span class="family-code-icon"><i class="fas fa-people-roof"></i></span><div><h3 class="family-code-name"><?=htmlspecialchars($family['family_label'] ?: 'Family')?></h3><div class="family-code-caption">Family access</div></div></div>
   <button type="button" class="family-code-open" data-family-id="<?=$family['id']?>" aria-label="View students linked to <?=htmlspecialchars($family['family_label'] ?: 'Family', ENT_QUOTES)?>"><code><?=htmlspecialchars($family['access_code'])?></code><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></button>
   <div class="family-code-card-foot"><span class="family-code-count"><i class="fas fa-user-group me-1"></i><?=count($family['students'])?> linked student<?=count($family['students'])===1?'':'s'?></span><div class="family-code-actions"><details><summary class="btn btn-sm btn-outline-primary">Edit</summary><form method="post" class="family-code-edit"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(termResultsCsrfToken())?>"><input type="hidden" name="action" value="edit_family_code"><input type="hidden" name="family_id" value="<?=$family['id']?>"><label class="form-label small">Family name</label><input class="form-control form-control-sm mb-2" name="family_label" value="<?=htmlspecialchars($family['family_label'] ?? '')?>" required><label class="form-label small">Family code</label><input class="form-control form-control-sm mb-2" name="access_code" value="<?=htmlspecialchars($family['access_code'])?>" required><button class="btn btn-sm btn-primary">Save changes</button></form></details><form method="post" onsubmit="return confirm('Delete this family code and unlink all attached students?');"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(termResultsCsrfToken())?>"><input type="hidden" name="action" value="delete_family_code"><input type="hidden" name="family_id" value="<?=$family['id']?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></div></div>
  </article><?php endforeach; ?><?php if(!$familyRegister): ?><div class="family-register-empty"><i class="fas fa-key fa-2x mb-2"></i><div>No family codes yet.</div></div><?php endif; ?></div>
 </section>
</div>
<div class="family-modal" id="family-roster-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="family-roster-title">
 <div class="family-modal-backdrop" data-close-family-modal></div><section class="family-modal-panel">
  <header class="family-modal-head"><div class="family-modal-heading"><?php if($schoolLogoSrc): ?><img src="<?=htmlspecialchars($schoolLogoSrc,ENT_QUOTES)?>" alt=""><?php endif; ?><div><h2 id="family-roster-title"><?=htmlspecialchars($schoolName)?></h2><p>Family student roster</p></div></div><button type="button" class="family-modal-close" data-close-family-modal aria-label="Close">&times;</button></header>
  <div class="family-modal-body"><div class="family-modal-code"><small>Family access code</small><strong id="family-modal-code"></strong><div class="family-modal-family" id="family-modal-family"></div></div><div id="family-modal-roster"></div></div>
  <footer class="family-modal-actions"><button type="button" class="btn btn-outline-secondary" data-close-family-modal>Close</button><button type="button" class="btn btn-primary" id="family-modal-print"><i class="fas fa-print me-1"></i>Print this family roster</button></footer>
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
(function(){
 const families=<?=json_encode(array_values($familyRegister),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
 const modal=document.getElementById('family-roster-modal');
 const codeOutput=document.getElementById('family-modal-code');
 const familyOutput=document.getElementById('family-modal-family');
 const roster=document.getElementById('family-modal-roster');
 let previousFocus=null;
 function closeModal(){modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');document.body.style.overflow='';if(previousFocus)previousFocus.focus();}
 document.querySelectorAll('.family-code-open').forEach(button=>button.addEventListener('click',()=>{
  const family=families.find(item=>String(item.id)===button.dataset.familyId);
  if(!family)return;
  previousFocus=button;codeOutput.textContent=family.access_code;familyOutput.textContent=family.family_label||'Family';roster.replaceChildren();
  if(!family.students.length){const empty=document.createElement('div');empty.className='family-roster-empty';empty.textContent='No students are linked to this family code yet.';roster.append(empty);}
  else {
   const table=document.createElement('table');table.className='family-roster-table';
   const head=document.createElement('thead'),headerRow=document.createElement('tr');
   ['#','Student','Section','Class'].forEach(label=>{const th=document.createElement('th');th.textContent=label;headerRow.append(th);});head.append(headerRow);table.append(head);
   const body=document.createElement('tbody');
   family.students.forEach((student,index)=>{const row=document.createElement('tr');[String(index+1),student.name,student.type==='early_years'?'Early Years':'School',student.class||'Not assigned'].forEach(value=>{const td=document.createElement('td');td.textContent=value;row.append(td);});body.append(row);});
   table.append(body);roster.append(table);
  }
  modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';modal.querySelector('.family-modal-close').focus();
 }));
 modal.querySelectorAll('[data-close-family-modal]').forEach(button=>button.addEventListener('click',closeModal));
 document.addEventListener('keydown',event=>{if(event.key==='Escape'&&modal.classList.contains('is-open'))closeModal();});
 document.getElementById('family-modal-print').addEventListener('click',()=>{document.body.classList.add('printing-family-roster');window.print();});
 window.addEventListener('afterprint',()=>document.body.classList.remove('printing-family-roster'));
})();
</script>
</main><?php include '../includes/footer.php'; ?>
