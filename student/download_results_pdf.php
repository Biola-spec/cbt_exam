<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/results_sheet.php';
require_once '../includes/simple_pdf.php';
requireLogin();
if (getUserRole() !== 'student') { http_response_code(403); exit('Forbidden'); }
$examId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$mysqli = getDBConnection();
$sheet = ($mysqli && $examId > 0) ? resultSheetLoad($mysqli, (int)getUserId(), $examId) : null;
if (!$sheet) { http_response_code(404); exit('Results are not available.'); }
$pdf = resultPdfBuild($sheet);
$filename = 'results-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $sheet['exam']['title']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
header('X-Content-Type-Options: nosniff');
echo $pdf;
