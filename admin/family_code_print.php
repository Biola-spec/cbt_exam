<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/family_access.php';
requireLogin();
if (getUserRole() !== 'admin') { http_response_code(403); exit('Access denied.'); }
$db = getDBConnection();
if (!$db || !familyAccessEnsureSchema($db)) { http_response_code(500); exit('Family access is unavailable.'); }
$familyId = (int)($_GET['family_id'] ?? 0);
$stmt = $db->prepare('SELECT id,family_label,access_code FROM early_years_family_codes WHERE id=?');
$stmt->bind_param('i', $familyId);
$stmt->execute();
$family = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$family) { http_response_code(404); exit('Family code not found.'); }
$stmt = $db->prepare("SELECT u.full_name student_name,c.name class_name,'Student' section FROM family_student_links fsl JOIN users u ON u.id=fsl.student_id AND u.role='student' LEFT JOIN classes c ON c.id=u.class_id WHERE fsl.family_id=? AND fsl.student_type='student'
    UNION ALL
    SELECT s.full_name student_name,c.name class_name,'Early Years' section FROM family_student_links fsl JOIN early_years_students s ON s.id=fsl.student_id LEFT JOIN early_years_classes c ON c.id=s.class_id WHERE fsl.family_id=? AND fsl.student_type='early_years'
    ORDER BY section,student_name");
$stmt->bind_param('ii', $familyId, $familyId);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$schoolName = getSchoolName();
$schoolLogo = getSchoolLogo();
$logoSrc = '';
if ($schoolLogo) {
    $logoPath = str_replace('\\', '/', trim($schoolLogo));
    if (preg_match('~^(?:https?:)?//|^data:~i', $logoPath)) $logoSrc = $logoPath;
    else {
        while (strpos($logoPath, '../') === 0) $logoPath = substr($logoPath, 3);
        if (is_file(__DIR__ . '/../' . ltrim($logoPath, '/'))) $logoSrc = (strpos($logoPath, '/') === 0 ? '' : '../') . ltrim($logoPath, '/');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?=htmlspecialchars($family['access_code'])?> - Linked students</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#eef2f7;color:#1d2b3d;font-family:Arial,sans-serif}.toolbar{padding:1rem;text-align:center}.toolbar button{border:0;border-radius:7px;padding:.7rem 1rem;background:#175bb5;color:#fff;font-weight:700;cursor:pointer}.sheet{width:min(900px,calc(100% - 2rem));margin:1rem auto;padding:2rem;border:1px solid #d9e0e9;border-radius:14px;background:#fff;box-shadow:0 10px 35px #162b4518}.brand{display:flex;align-items:center;justify-content:center;gap:1rem;text-align:center;padding-bottom:1.2rem;border-bottom:2px solid #24364d}.brand img{width:76px;height:76px;object-fit:contain}.brand h1{font-size:1.4rem;margin:0}.brand p{margin:.3rem 0 0;color:#65748a}.family{text-align:center;margin:1.2rem 0}.family h2{margin:0 0 .3rem;font-size:1.05rem;color:#53647a;text-transform:uppercase;letter-spacing:.08em}.family code{display:inline-block;margin-top:.35rem;padding:.55rem .9rem;border-radius:8px;background:#f0f5fb;color:#182b44;font-family:monospace;font-size:1.3rem;font-weight:700;overflow-wrap:anywhere}.family-label{color:#53647a}.roster{width:100%;border-collapse:collapse;margin-top:1.2rem}.roster th,.roster td{border:1px solid #cad4e0;padding:.65rem .7rem;text-align:left}.roster th{background:#edf2f8}.empty{text-align:center;color:#65748a;padding:2rem}.count{text-align:right;color:#65748a;font-size:.9rem;margin-top:.6rem}
@media print{@page{size:A4 portrait;margin:14mm}body{background:#fff}.toolbar{display:none}.sheet{width:100%;margin:0;padding:0;border:0;border-radius:0;box-shadow:none}.brand img{width:65px;height:65px}.roster{font-size:10pt}.roster thead{display:table-header-group}.roster tr{break-inside:avoid}}
</style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">Print linked students</button></div>
<main class="sheet">
<header class="brand"><?php if($logoSrc): ?><img src="<?=htmlspecialchars($logoSrc,ENT_QUOTES)?>" alt="School logo"><?php endif; ?><div><h1><?=htmlspecialchars($schoolName)?></h1><p>Family access</p></div></header>
<div class="family"><h2>Family code</h2><code><?=htmlspecialchars($family['access_code'])?></code><div class="family-label"><?=htmlspecialchars($family['family_label'] ?: 'Family')?></div></div>
<table class="roster"><thead><tr><th style="width:7%">#</th><th>Student name</th><th style="width:23%">Section</th><th style="width:25%">Class</th></tr></thead><tbody><?php foreach($students as $index=>$student): ?><tr><td><?=$index+1?></td><td><?=htmlspecialchars($student['student_name'])?></td><td><?=htmlspecialchars($student['section'])?></td><td><?=htmlspecialchars($student['class_name'] ?: 'Not assigned')?></td></tr><?php endforeach; ?><?php if(!$students): ?><tr><td colspan="4" class="empty">No students are linked to this family code.</td></tr><?php endif; ?></tbody></table>
<div class="count"><?=count($students)?> linked student<?=count($students)===1?'':'s'?></div>
</main>
<script>window.addEventListener('load',()=>setTimeout(()=>window.print(),350));</script>
</body></html>
