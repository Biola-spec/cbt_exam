<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireLogin();
if (getUserRole() !== 'admin') { header('Location: ../login.php'); exit; }
$db = getDBConnection();
$classId = (int)($_GET['class_id'] ?? 0);
$classes = $db->query('SELECT id,name FROM early_years_classes ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$school = $db->query('SELECT school_name,school_address,school_phone FROM school_config ORDER BY id DESC LIMIT 1')->fetch_assoc() ?: [];
$logoPath = getSchoolLogo();
$logoPath = ltrim(preg_replace('~^(\./|\.\./)+~', '', (string)$logoPath), '/');
$sql = 'SELECT s.full_name,s.admission_number,s.date_of_birth,s.gender,s.passport_path,c.name class_name FROM early_years_students s JOIN early_years_classes c ON c.id=s.class_id'.($classId ? ' WHERE c.id=?' : '').' ORDER BY c.name,s.full_name';
$stmt = $db->prepare($sql);
if ($classId) $stmt->bind_param('i',$classId);
$stmt->execute();
$pupils = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$selectedName = 'All Early Years classes';
foreach ($classes as $class) if ((int)$class['id'] === $classId) $selectedName = $class['name'];
include '../includes/header.php';
?>
<main class="container py-4">
<div class="d-flex justify-content-between align-items-center mb-3 no-print"><h1 class="h4">Print Early Years pupil list</h1><a class="btn btn-outline-secondary" href="early_years.php">Back</a></div>
<form method="get" class="card card-body mb-3 no-print"><label class="form-label" for="class_id">Choose a class, or print all pupils</label><div class="d-flex gap-2"><select id="class_id" name="class_id" class="form-select"><option value="0">All Early Years classes</option><?php foreach($classes as $class):?><option value="<?=$class['id']?>" <?=$classId===(int)$class['id']?'selected':''?>><?=htmlspecialchars($class['name'])?></option><?php endforeach;?></select><button class="btn btn-primary">Load</button><button type="button" class="btn btn-outline-primary" onclick="window.print()">Print</button></div></form>
<section id="pupil-list"><header class="text-center mb-3"><?php if($logoPath!==''):?><img src="../<?=htmlspecialchars($logoPath)?>" alt="School logo" style="max-width:75px;max-height:75px;object-fit:contain"><?php endif;?><h1 class="h4 fw-bold mb-1"><?=htmlspecialchars($school['school_name']??'School')?></h1><div><?=htmlspecialchars($school['school_address']??'')?> <?=!empty($school['school_phone'])?' · '.htmlspecialchars($school['school_phone']):''?></div><h2 class="h5 mt-3">Early Years Pupil List — <?=htmlspecialchars($selectedName)?></h2><small>Printed <?=date('j F Y')?></small></header>
<div class="table-responsive"><table class="table table-bordered table-sm align-middle"><thead><tr><th>#</th><th>Photo</th><th>Pupil name</th><th>Admission no.</th><th>Class</th><th>Date of birth</th><th>Gender</th></tr></thead><tbody><?php foreach($pupils as $i=>$pupil):?><tr><td><?=$i+1?></td><td><?php if($pupil['passport_path']):?><img src="../<?=htmlspecialchars($pupil['passport_path'])?>" alt="" style="width:16mm;height:19mm;object-fit:cover"><?php endif;?></td><td><?=htmlspecialchars($pupil['full_name'])?></td><td><?=htmlspecialchars($pupil['admission_number']??'')?></td><td><?=htmlspecialchars($pupil['class_name'])?></td><td><?=htmlspecialchars($pupil['date_of_birth']??'')?></td><td><?=htmlspecialchars($pupil['gender']??'')?></td></tr><?php endforeach;?></tbody></table></div><?php if(!$pupils):?><p>No pupils found in this selection.</p><?php endif;?></section>
</main><style>@media print{@page{size:A4 portrait;margin:12mm}.no-print,body>footer{display:none!important}body *{visibility:hidden!important}#pupil-list,#pupil-list *{visibility:visible!important}#pupil-list{position:absolute;inset:0;width:100%}#pupil-list table{font-size:9pt}#pupil-list tr{break-inside:avoid;page-break-inside:avoid}}</style><?php include '../includes/footer.php';
