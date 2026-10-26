<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/term_results.php';
require_once '../includes/term_result_sheet.php';
requireLogin();
if (getUserRole() !== 'student') { header('Location: ../login.php'); exit(); }

$mysqli = getDBConnection();
$reports = [];
$error = '';
$maxComponentCount = 0;
$wideReport = false;
$printTableFontSize = '9pt';
$assetUrl = static function (?string $path): string {
    $path = trim((string)$path);
    if ($path === '' || preg_match('#^(?:[a-z]+:)?//#i', $path)) return '';
    $path = preg_replace('#^(?:\.\./)+#', '', str_replace('\\', '/', $path));
    return '../' . ltrim($path, '/');
};
try {
    if (!$mysqli) throw new RuntimeException('Database connection failed.');
    foreach (termResultPeriods($mysqli, (int)getUserId()) as $period) {
        $report = termResultSheetLoad($mysqli, (int)getUserId(), (int)$period['id']);
        if ($report) {
            $maxComponentCount = max($maxComponentCount, count($report['components']));
            $reports[] = $report;
        }
    }
} catch (Throwable $e) {
    $error = 'Released results are temporarily unavailable. Please try again later.';
}
$wideReport = $maxComponentCount > 7;
$printTableFontSize = $maxComponentCount > 9 ? '7pt' : '8pt';
include '../includes/header.php';
?>
<style>
.term-report-page{--report-ink:#202b33;--report-muted:#586770;--report-line:#aab4ba;color:var(--report-ink)}
.term-report-page .report-toolbar{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem}
.term-report-page .report-paper{position:relative;isolation:isolate;box-sizing:border-box;max-width:1000px;margin:0 auto 1.5rem;padding:1.6rem;background:#fff;border:1px solid #cbd2d6;box-shadow:0 5px 20px rgba(23,37,44,.08)}
.term-report-page .report-watermark{position:absolute;z-index:0;inset:20% 12%;display:flex;align-items:center;justify-content:center;pointer-events:none;opacity:.065}
.term-report-page .report-watermark img{display:block;width:100%;height:100%;object-fit:contain}
.term-report-page .report-content{position:relative;z-index:1}
.term-report-page .report-brand{display:grid;grid-template-columns:96px 1fr 100px;align-items:center;gap:1rem;border:1px solid var(--report-line);border-bottom:0;padding:.65rem .8rem;text-align:center}
.term-report-page .report-brand img{max-width:88px;max-height:72px;object-fit:contain}
.term-report-page .student-photo{width:78px;height:86px;object-fit:cover;border:1px solid var(--report-line)}
.term-report-page .school-name{font-size:2.2rem;font-weight:800;line-height:1.05;margin:0;overflow-wrap:anywhere}
.term-report-page .report-title{font-size:.9rem;font-weight:700;letter-spacing:.04em;margin:.35rem 0 0}
.term-report-page .school-contact{font-size:.72rem;line-height:1.2;color:var(--report-muted);margin-top:.2rem}
.term-report-page .student-name{font-size:1.1rem;font-weight:750;text-align:center;padding:.45rem;border:1px solid var(--report-line);margin-bottom:0}
.term-report-page .report-meta{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));border-left:1px solid var(--report-line);border-top:1px solid var(--report-line);margin-bottom:.8rem}
.term-report-page .report-meta div{padding:.45rem .55rem;border-right:1px solid var(--report-line);border-bottom:1px solid var(--report-line);min-height:48px}
.term-report-page .report-meta strong{display:block;font-size:.69rem;line-height:1.2;text-transform:uppercase;color:var(--report-muted);margin-bottom:.2rem}
.term-report-page .report-table{position:relative;z-index:2;width:100%;max-width:100%;border-collapse:collapse;margin:0;table-layout:fixed;background:#fff}
.term-report-page .report-table th,.term-report-page .report-table td{box-sizing:border-box;border:1px solid #707c82;padding:.4rem .45rem;vertical-align:middle;line-height:1.2}
.term-report-page .report-table thead th{background:#eef2f3;font-size:.76rem;text-align:center;white-space:normal}
.term-report-page .report-table th.component-header{padding:.3rem .15rem;line-height:1.05}
.term-report-page .report-table th.component-header span,.term-report-page .report-table th.component-header small{display:block;white-space:normal;overflow-wrap:normal;word-break:normal}
.term-report-page .report-table th.component-header small{font-size:.68em;font-weight:400}
.term-report-page .report-table td.numeric{text-align:center;font-variant-numeric:tabular-nums;white-space:nowrap}
.term-report-page .report-table td.remark-cell{text-align:left;white-space:normal;overflow-wrap:break-word;word-break:normal}
.term-report-page .report-table .subject-cell{min-width:145px;font-weight:600}
.term-report-page .report-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));margin-top:.8rem;border:1px solid var(--report-line)}
.term-report-page .report-summary div{padding:.55rem;border-right:1px solid var(--report-line)}
.term-report-page .report-summary div:last-child{border-right:0}
.term-report-page .report-summary strong{display:block;font-size:.7rem;text-transform:uppercase;color:var(--report-muted)}
.term-report-page .report-comment{min-height:44px;border-bottom:1px solid var(--report-line);padding:.55rem 0}
.term-report-page .report-approval-assets{display:flex;justify-content:flex-end;align-items:flex-end;gap:1rem;min-height:78px;margin-top:.75rem}
.term-report-page .report-approval-assets img{display:block;max-width:135px;max-height:62px;object-fit:contain}
.term-report-page .report-approval-assets img.report-stamp{max-width:74px;max-height:70px}
.term-report-page .report-attendance{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));border:1px solid var(--report-line);margin-top:.8rem}
.term-report-page .report-attendance div{padding:.45rem;border-right:1px solid var(--report-line)}
.term-report-page .report-attendance div:last-child{border-right:0}
.term-report-page .report-attendance strong,.term-report-page .report-skills h4{display:block;font-size:.68rem;text-transform:uppercase;color:var(--report-muted);margin:0 0 .2rem}
.term-report-page .report-skills{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;margin-top:.8rem}
.term-report-page .report-skills table{width:100%;border-collapse:collapse;font-size:.78rem}
.term-report-page .report-skills th,.term-report-page .report-skills td{border:1px solid var(--report-line);padding:.25rem .4rem}
.term-report-page .report-skills th:last-child,.term-report-page .report-skills td:last-child{text-align:center;width:20%}
.term-report-page .report-signatures{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:2.5rem;margin-top:1.7rem}
.term-report-page .signature-line{border-top:1px solid #48545a;padding-top:.35rem;text-align:center;font-size:.82rem}
.term-report-page .report-signatory{display:flex;min-height:60px;flex-direction:column;justify-content:flex-end;align-items:center}
.term-report-page .report-signature-assets{display:flex;height:46px;align-items:center;justify-content:center;gap:.5rem;margin-bottom:.2rem}
.term-report-page .report-signature-assets img{display:block;max-width:120px;max-height:42px;object-fit:contain}
.term-report-page .report-signature-assets img.report-stamp{max-width:58px;max-height:46px}
.term-report-page .report-signatory-caption{display:block;font-size:.78rem;font-weight:600}
.term-report-page .report-footer{border-top:1px solid var(--report-line);margin-top:1rem;padding-top:.45rem;text-align:center;font-size:.75rem;color:var(--report-muted)}
@media(max-width:767px){
 .term-report-page .report-paper{padding:.8rem}
 .term-report-page .report-brand{grid-template-columns:62px 1fr 62px;gap:.45rem;padding:.45rem}
 .term-report-page .report-brand img{max-width:58px;max-height:58px}
 .term-report-page .student-photo{width:54px;height:60px}
 .term-report-page .school-name{font-size:1.5rem;line-height:1.05}
 .term-report-page .report-meta{grid-template-columns:repeat(2,minmax(0,1fr))}
 .term-report-page .report-summary{grid-template-columns:repeat(2,minmax(0,1fr))}
 .term-report-page .report-summary div:nth-child(2){border-right:0}
 .term-report-page .report-summary div:nth-child(-n+2){border-bottom:1px solid var(--report-line)}
}
@page{size:A4 <?php echo $wideReport ? 'landscape' : 'portrait'; ?>;margin:2mm}
@media print{
 html,body{margin:0!important;background:#fff!important}
 .term-report-page{padding:0!important;width:100%!important;max-width:none!important}
 .term-report-page .report-toolbar,.term-report-page .no-print,.navbar,nav,footer{display:none!important}
 .term-report-page .report-paper{position:relative;width:100%;height:<?php echo $wideReport ? '190mm' : '260mm'; ?>;max-width:none;margin:0;padding:2mm;box-sizing:border-box;border:1px solid #aab4ba;box-shadow:none;break-after:page;break-inside:avoid;page-break-inside:avoid;overflow:hidden}
 .term-report-page .report-watermark{inset:20% 12%;print-color-adjust:exact;-webkit-print-color-adjust:exact}
 .term-report-page .report-paper:last-child{break-after:auto}
 body.print-one .term-report-page .report-paper.print-active{break-before:avoid-page!important;page-break-before:avoid!important;break-after:auto!important;page-break-after:auto!important;break-inside:avoid-page!important;page-break-inside:avoid!important}
 .term-report-page .report-content{width:calc(100% / var(--print-scale,1));zoom:var(--print-scale,1);transform-origin:top left}
 .term-report-page .report-brand,.term-report-page .report-meta,.term-report-page .report-summary,.term-report-page .report-signatures{break-inside:avoid}
 .term-report-page .report-table{display:table;width:100%!important;min-width:0!important;max-width:100%!important;font-size:<?php echo $printTableFontSize; ?>;line-height:1;table-layout:fixed}
 .term-report-page .table-responsive{display:block;width:100%!important;max-width:100%!important;overflow:hidden!important}
 .term-report-page .report-table th,.term-report-page .report-table td{min-width:0!important;padding:1.5px 2px;overflow-wrap:normal;word-break:normal}
 .term-report-page .report-table .subject-cell{min-width:0!important}
 .term-report-page .report-meta div{min-height:0;padding:1.5mm}
 .term-report-page .report-brand{padding:1.5mm}
 .term-report-page .report-summary div{padding:1.5mm}
 .term-report-page .report-comment{min-height:7mm;padding:1.5mm 0}
 .term-report-page .report-approval-assets{min-height:18mm;margin-top:2mm;gap:4mm}
 .term-report-page .report-approval-assets img{max-width:34mm;max-height:16mm}
 .term-report-page .report-approval-assets img.report-stamp{max-width:20mm;max-height:18mm}
 .term-report-page .report-signatures{margin-top:6mm;gap:5mm}
 .term-report-page .report-signatory{min-height:17mm}
 .term-report-page .report-signature-assets{height:12mm;margin-bottom:1mm}
 .term-report-page .report-signature-assets img{max-width:32mm;max-height:11mm}
 .term-report-page .report-signature-assets img.report-stamp{max-width:16mm;max-height:12mm}
 .term-report-page .report-signatory-caption{font-size:6pt}
 .term-report-page .report-table thead th{font-size:inherit}
 .term-report-page .report-table td.numeric{white-space:nowrap!important;word-break:normal!important;overflow-wrap:normal!important}
 .term-report-page .report-table th.component-header{height:auto;vertical-align:middle;text-align:center;padding:1px!important;font-size:7pt;line-height:1.15}
 .term-report-page .report-table th.component-header span,.term-report-page .report-table th.component-header small{display:block;writing-mode:horizontal-tb;transform:none;white-space:normal;overflow-wrap:normal;word-break:normal;font-size:inherit;line-height:1.1}
 .term-report-page .report-table th.component-header small{font-size:6pt;font-weight:400}
 .term-report-page .report-brand{grid-template-columns:18mm minmax(0,1fr) 18mm;gap:2mm;font-size:7pt}
 .term-report-page .report-brand img{max-width:16mm;max-height:16mm}
 .term-report-page .student-photo{width:15mm;height:17mm}
 .term-report-page .school-name{font-size:24pt;font-weight:800;line-height:1;overflow-wrap:anywhere}
 .term-report-page .report-title{font-size:7pt;margin-top:1mm}
 .term-report-page .school-contact{font-size:6pt;margin-top:.5mm}
 .term-report-page .student-name{font-size:10pt;padding:1mm}
 .term-report-page .report-meta{margin-bottom:2mm;grid-template-columns:repeat(3,minmax(0,1fr))}
 .term-report-page .report-meta div{font-size:8pt;overflow-wrap:anywhere}
 .term-report-page .report-meta strong{font-size:7pt}
 .term-report-page .report-summary{margin-top:2mm;font-size:8pt}
 .term-report-page .report-summary strong{font-size:7pt}
 .term-report-page .report-footer{font-size:6pt;margin-top:2mm}
 .term-report-page .report-attendance,.term-report-page .report-skills{font-size:6.5pt;margin-top:2mm}
 .term-report-page .report-attendance div{padding:1mm}
 .term-report-page .report-skills{gap:2mm}
 .term-report-page .report-skills table{font-size:6pt}
 .term-report-page .report-skills th,.term-report-page .report-skills td{padding:.5mm 1mm}
 .term-report-page .report-table tbody tr{break-inside:avoid}
 body.print-one .term-report-page .report-paper:not(.print-active){display:none!important}
}
body.print-one .term-report-page .report-paper:not(.print-active){display:none}
</style>
<main class="container-fluid py-4 term-report-page">
    <div class="report-toolbar no-print">
        <div><h1 class="h3 mb-1">My Term Results</h1><p class="text-muted mb-0">Released academic reports, organized by session and term.</p></div>
        <a class="btn btn-outline-secondary" href="dashboard.php"><i class="fas fa-arrow-left me-1"></i> Student dashboard</a>
    </div>
    <?php if ($error): ?><div class="alert alert-warning"><?php echo htmlspecialchars($error); ?></div>
    <?php elseif (!$reports): ?><div class="alert alert-info">No term results have been released for you yet.</div>
    <?php else: foreach ($reports as $index => $report): $reportId = 'term-report-' . $index; ?>
        <article class="report-paper" id="<?php echo $reportId; ?>">
            <?php $watermark = $assetUrl($report['school']['logo_path']); if ($watermark): ?><div class="report-watermark" aria-hidden="true"><img src="<?php echo htmlspecialchars($watermark); ?>" alt=""></div><?php endif; ?>
            <div class="report-content">
            <div class="report-toolbar no-print">
                <div><strong><?php echo htmlspecialchars($report['session']); ?> · <?php echo htmlspecialchars(termLabel($report['term'])); ?></strong><div class="small text-muted"><?php echo (int)$report['summary']['subjects']; ?> released subjects</div></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" onclick="printTermReport('<?php echo $reportId; ?>')"><i class="fas fa-print me-1"></i> Print / Save PDF</button>
                </div>
            </div>
            <header class="report-brand">
                <div><?php $logo = $assetUrl($report['school']['logo_path']); if ($logo): ?><img src="<?php echo htmlspecialchars($logo); ?>" alt="School logo"><?php endif; ?></div>
                <div><h2 class="school-name"><?php echo htmlspecialchars($report['school']['name']); ?></h2><div class="school-contact"><?php if ($report['school']['address'] !== ''): ?><div><?php echo htmlspecialchars($report['school']['address']); ?></div><?php endif; ?><?php if ($report['school']['phone'] !== ''): ?><div><?php echo htmlspecialchars($report['school']['phone']); ?></div><?php endif; ?></div><div class="report-title">STUDENT ACADEMIC REPORT CARD</div></div>
                <div><?php $photo = $assetUrl($report['student']['passport']); if ($photo): ?><img class="student-photo" src="<?php echo htmlspecialchars($photo); ?>" alt="Student photograph"><?php endif; ?></div>
            </header>
            <h3 class="student-name"><?php echo htmlspecialchars($report['student']['name']); ?></h3>
            <section class="report-meta" aria-label="Student and term details">
                <div><strong>Admission number</strong><?php echo htmlspecialchars($report['student']['admission_number']); ?></div>
                <div><strong>Class</strong><?php echo htmlspecialchars($report['class']); ?></div>
                <div><strong>Academic session</strong><?php echo htmlspecialchars($report['session']); ?></div>
                <div><strong>Term</strong><?php echo htmlspecialchars(termLabel($report['term'])); ?></div>
                <div><strong>Subjects reported</strong><?php echo (int)$report['summary']['subjects']; ?></div>
                <div><strong>Printed</strong><?php echo date('M j, Y'); ?></div>
            </section>
            <div class="table-responsive">
                <table class="report-table">
                    <colgroup>
                        <col style="width:4%">
                        <col style="width:<?php echo $wideReport ? '23' : '25'; ?>%">
                        <?php $componentColumnWidth = ($wideReport ? 42 : 36) / max(1, count($report['components'])); foreach ($report['components'] as $_component): ?><col style="width:<?php echo number_format($componentColumnWidth, 4, '.', ''); ?>%"><?php endforeach; ?>
                        <col style="width:<?php echo $wideReport ? '14' : '13'; ?>%">
                        <col style="width:<?php echo $wideReport ? '6' : '7'; ?>%">
                        <col style="width:<?php echo $wideReport ? '11' : '15'; ?>%">
                    </colgroup>
                    <thead><tr><th>S/N</th><th class="text-start">Subjects</th>
                    <?php foreach ($report['components'] as $component): ?><th class="component-header"><span><?php echo htmlspecialchars($component['label']); ?></span><small><?php echo htmlspecialchars(rtrim(rtrim(number_format($component['maximum'], 2, '.', ''), '0'), '.')); ?></small></th><?php endforeach; ?>
                    <?php $totalMaximum = array_sum(array_column($report['components'], 'maximum')); ?>
                    <th class="component-header"><span>Total</span><small><?php echo htmlspecialchars(rtrim(rtrim(number_format($totalMaximum, 2, '.', ''), '0'), '.')); ?></small></th><th>Grade</th><th>Remark</th></tr></thead>
                    <tbody>
                    <?php $printedSubjectIndex = 0; foreach ($report['subjects'] as $subject): if (!$subject['has_score']) continue; $printedSubjectIndex++; ?><tr>
                        <td class="numeric"><?php echo $printedSubjectIndex; ?></td>
                        <td class="subject-cell"><?php echo htmlspecialchars($subject['name']); ?></td>
                        <?php foreach ($report['components'] as $component): $componentScore = $subject['scores'][$component['type']][$component['number']] ?? null; ?>
                            <td class="numeric"><?php echo $componentScore === null ? '—' : htmlspecialchars(rtrim(rtrim(number_format($componentScore, 2, '.', ''), '0'), '.')); ?></td>
                        <?php endforeach; ?>
                        <td class="numeric fw-semibold"><?php echo $subject['has_score'] ? number_format($subject['total'], 2) : '—'; ?></td>
                        <td class="numeric fw-semibold"><?php echo htmlspecialchars($subject['grade'] ?: '—'); ?></td>
                        <td class="remark-cell"><?php echo htmlspecialchars($subject['grade_remark'] ?: '—'); ?></td>
                    </tr><?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <section class="report-summary" aria-label="Term summary">
                <div><strong>Total score</strong><?php echo $report['summary']['maximum'] > 0 ? number_format($report['summary']['total'], 2) . ' / ' . number_format($report['summary']['maximum'], 2) : '—'; ?></div>
                <div><strong>Average score</strong><?php echo $report['summary']['average'] === null ? '—' : number_format($report['summary']['average'], 1) . '%'; ?></div>
                <div><strong>Overall grade</strong><?php echo htmlspecialchars($report['summary']['grade'] ?: '—'); ?></div>
                <div><strong>Subjects</strong><?php echo (int)$report['summary']['subjects']; ?></div>
            </section>
            <?php if ($report['attendance']['school_days_open'] > 0): ?><section class="report-attendance" aria-label="Attendance summary">
                <div><strong>Days school opened</strong><?php echo $report['attendance']['school_days_open']; ?></div>
                <div><strong>Days present</strong><?php echo $report['attendance']['days_present'] === null ? 'Not entered' : $report['attendance']['days_present']; ?></div>
                <div><strong>Days absent</strong><?php echo $report['attendance']['days_absent'] === null ? 'Not entered' : $report['attendance']['days_absent']; ?></div>
            </section><?php endif; ?>
            <?php if ($report['ratings']['psychomotor'] || $report['ratings']['locomotor']): ?><section class="report-skills" aria-label="Student skill ratings">
                <?php foreach (['psychomotor' => 'Psychomotor skills', 'locomotor' => 'Locomotor skills'] as $domain => $heading): if (!$report['ratings'][$domain]) continue; ?><div><h4><?php echo $heading; ?></h4><table><tbody><?php foreach ($report['ratings'][$domain] as $rating): ?><tr><td><?php echo htmlspecialchars($rating['label']); ?></td><td><?php echo (int)$rating['score']; ?> / 5</td></tr><?php endforeach; ?></tbody></table></div><?php endforeach; ?>
            </section><?php endif; ?>
            <?php if (trim($report['class_teacher_remark']) !== ''): ?><div class="report-comment"><strong>Class teacher's remark:</strong> <?php echo nl2br(htmlspecialchars($report['class_teacher_remark'])); ?></div><?php endif; ?>
            <div class="report-approval-assets" aria-label="Principal signature and school stamp">
                <?php $signature = $assetUrl($report['school']['signatory_signature_path']); if ($signature): ?><img src="<?php echo htmlspecialchars($signature); ?>" alt="Principal signature"><?php endif; ?>
                <?php $stamp = $assetUrl($report['school']['stamp_path']); if ($stamp): ?><img class="report-stamp" src="<?php echo htmlspecialchars($stamp); ?>" alt="School stamp"><?php endif; ?>
            </div>
            <div class="report-footer"><?php echo htmlspecialchars($report['school']['name']); ?> · <?php echo htmlspecialchars($report['session']); ?> · <?php echo htmlspecialchars(termLabel($report['term'])); ?></div>
            </div>
        </article>
    <?php endforeach; endif; ?>
</main>
<script>
function printTermReport(id) {
    document.body.classList.add('print-one');
    document.querySelectorAll('.report-paper').forEach(function (paper) {
        paper.classList.toggle('print-active', paper.id === id);
    });
    window.__activeTermReportId = id;
    window.print();
}
window.addEventListener('beforeprint', function () {
    var papers = window.__activeTermReportId
        ? [document.getElementById(window.__activeTermReportId)]
        : Array.from(document.querySelectorAll('.term-report-page .report-paper'));
    var availableHeight = <?php echo $wideReport ? 186 : 256; ?> * 96 / 25.4;
    papers.filter(Boolean).forEach(function (paper) {
        var content = paper.querySelector('.report-content');
        if (!content || paper.getClientRects().length === 0) return;
        var scale = Math.min(1, availableHeight / content.scrollHeight);
        paper.style.setProperty('--print-scale', scale.toFixed(3));
    });
});
window.addEventListener('afterprint', function () {
    document.body.classList.remove('print-one');
    document.querySelectorAll('.report-paper').forEach(function (paper) {
        paper.classList.remove('print-active');
        paper.style.removeProperty('--print-scale');
    });
    window.__activeTermReportId = null;
});
</script>
<?php include '../includes/footer.php'; ?>
