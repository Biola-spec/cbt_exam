<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/results_sheet.php';
requireLogin();
if (getUserRole() !== 'student') { header('Location: ../login.php'); exit(); }
$examId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
if ($examId < 1) { header('Location: dashboard.php?tab=results'); exit(); }
$mysqli = getDBConnection();
$sheet = $mysqli ? resultSheetLoad($mysqli, (int)getUserId(), $examId) : null;
if (!$sheet) { http_response_code(404); echo 'Results are not available.'; exit(); }
$exam = $sheet['exam']; $summary = $sheet['summary'];
$logoPath = trim((string)getSchoolLogo());
$logoUrl = $logoPath !== '' ? '../' . ltrim(preg_replace('#^\.\./#', '', $logoPath), '/') : '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars(($exam['school_name'] ?: 'School') . ' - ' . $exam['title'] . ' Results'); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
<style>
.results-paper{max-width:980px;margin:2rem auto;background:#fff;padding:2rem;border:1px solid #d7dce2;color:#17212b}
.results-brand{text-align:center;border-bottom:2px solid #203b5b;padding-bottom:1rem;margin-bottom:1.25rem}
.results-brand img{max-height:76px;max-width:120px;object-fit:contain;margin-bottom:.5rem}
.results-brand h1{font-size:1.45rem;font-weight:700;margin:0}.results-brand h2{font-size:1rem;letter-spacing:.08em;margin:.45rem 0 0}
.results-brand .school-contact{font-size:.82rem;line-height:1.35;color:#526273;margin-top:.35rem}
.results-meta{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.5rem 1.25rem;margin:1rem 0 1.25rem}
.results-meta div{border-bottom:1px solid #dce1e6;padding:.45rem 0}.results-meta strong{display:block;color:#526273;font-size:.76rem;text-transform:uppercase;letter-spacing:.04em}
.results-table{width:100%;border-collapse:collapse}.results-table th,.results-table td{border:1px solid #aeb8c2;padding:.55rem .6rem}.results-table thead th{background:#eaf0f6;color:#203b5b}.results-table td.numeric{text-align:right;font-variant-numeric:tabular-nums}
.results-summary{display:flex;flex-wrap:wrap;gap:.75rem 1.5rem;padding:1rem;margin-top:1rem;background:#f4f7fa;border:1px solid #dce1e6}
.results-actions{display:flex;gap:.5rem;justify-content:flex-end;max-width:980px;margin:1.5rem auto 0}.status-pending{font-weight:600}
@page{size:A4 portrait;margin:14mm}
@media print{body{background:#fff!important}.results-paper{max-width:none;margin:0;padding:0;border:0}.results-actions,.dashboard-header,.container>.row>.col-md-3,header,nav,footer{display:none!important}.container,.container-fluid{max-width:none!important;width:100%!important;padding:0!important}.results-brand,.results-meta,.results-summary{break-inside:avoid}.results-table{font-size:10pt}.results-table thead{display:table-header-group}.results-table tr{break-inside:avoid}.results-table th,.results-table td{padding:5px}.card,.card-body{border:0!important;box-shadow:none!important;padding:0!important}}
@media(max-width:650px){.results-paper{margin:1rem .25rem;padding:1rem}.results-meta{grid-template-columns:repeat(2,minmax(0,1fr))}.results-actions{margin:.75rem .25rem;justify-content:flex-start}}
</style>
 </head>
<body>
<div class="container mt-4">
<div class="results-actions no-print">
    <a class="btn btn-outline-secondary" href="dashboard.php?tab=results">Back to Examinations</a>
    <button class="btn btn-outline-primary" onclick="window.print()" type="button">Print Results</button>
    <a class="btn btn-primary" href="download_results_pdf.php?exam_id=<?php echo (int)$examId; ?>">Download PDF</a>
</div>
<main class="results-paper">
    <div class="results-brand">
        <?php if ($logoUrl): ?><img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="School logo"><br><?php endif; ?>
        <h1><?php echo htmlspecialchars($exam['school_name'] ?: getSchoolName()); ?></h1>
        <h2>STUDENT EXAMINATION RESULTS SHEET</h2>
        <?php if (!empty($exam['school_address']) || !empty($exam['school_phone'])): ?><div class="school-contact">
            <?php if (!empty($exam['school_address'])): ?><div><?php echo htmlspecialchars($exam['school_address']); ?></div><?php endif; ?>
            <?php if (!empty($exam['school_phone'])): ?><div><?php echo htmlspecialchars($exam['school_phone']); ?></div><?php endif; ?>
        </div><?php endif; ?>
    </div>
    <section class="results-meta" aria-label="Student and examination details">
        <div><strong>Student Name</strong><?php echo htmlspecialchars($exam['full_name']); ?></div>
        <div><strong>Admission Number</strong><?php echo htmlspecialchars($exam['admission_number'] ?: 'Not recorded'); ?></div>
        <div><strong>Class</strong><?php echo htmlspecialchars($exam['class_name']); ?></div>
        <div><strong>Examination</strong><?php echo htmlspecialchars($exam['title']); ?></div>
        <div><strong>Academic Session</strong><?php echo htmlspecialchars($exam['academic_session'] ?: 'Not recorded'); ?></div>
        <div><strong>Print Date</strong><?php echo date('M j, Y'); ?></div>
    </section>
    <div class="table-responsive">
        <table class="results-table">
            <thead><tr><th>S/N</th><th>Subject</th><th>Score</th><th>Grade</th><th>Remark</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($sheet['subjects'] as $i => $subject): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                    <?php if ($subject['score_released']): ?>
                        <td class="numeric"><?php echo number_format((float)$subject['score'], 2); ?> / <?php echo number_format((float)$subject['max_score'], 2); ?><br><small><?php echo number_format((float)$subject['percentage'], 2); ?>%</small></td>
                        <td><?php echo htmlspecialchars($subject['grade']['grade_letter'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($subject['grade']['remark'] ?? 'Grade not configured'); ?></td>
                        <td><?php echo htmlspecialchars($subject['status']); ?></td>
                    <?php else: ?>
                        <td>—</td><td>—</td><td>—</td><td class="status-pending"><?php echo htmlspecialchars($subject['status']); ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <section class="results-summary" aria-label="Results summary">
        <span><strong>Subjects taken:</strong> <?php echo (int)$summary['count']; ?></span>
        <span><strong>Results released:</strong> <?php echo (int)$summary['available']; ?></span>
        <span><strong>Passed:</strong> <?php echo (int)$summary['passed']; ?></span>
        <span><strong>Failed:</strong> <?php echo (int)$summary['failed']; ?></span>
        <span><strong>Total score:</strong> <?php echo number_format((float)$summary['total'], 2); ?></span>
        <span><strong>Average:</strong> <?php echo $summary['average'] === null ? '—' : number_format((float)$summary['average'], 2) . '%'; ?></span>
        <?php if (!$summary['all_released']): ?><span class="text-muted">Some subjects have no released result yet.</span><?php endif; ?>
    </section>
</main>
</div>
</body>
</html>
