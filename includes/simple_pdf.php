<?php
/** Small dependency-free PDF writer for the text-and-table result sheet. */
function resultPdfText(string $text): string {
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($converted !== false) $text = $converted;
    }
    $text = preg_replace('/[^\x20-\xFF]/', '?', $text);
    return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ''], $text);
}

function resultPdfBuild(array $sheet): string {
    $pages = [];
    $page = '';
    $y = 800;
    $addText = static function (&$content, float $x, float $y, string $text, float $size = 10, bool $bold = false): void {
        $font = $bold ? 'F2' : 'F1';
        $content .= sprintf("BT\n/%s %.1f Tf\n1 0 0 1 %.1f %.1f Tm\n(%s) Tj\nET\n", $font, $size, $x, $y, resultPdfText($text));
    };
    $drawLine = static function (&$content, float $x1, float $y1, float $x2, float $y2): void {
        $content .= sprintf("q\n%.1f %.1f m %.1f %.1f l S\nQ\n", $x1, $y1, $x2, $y2);
    };
    $startPage = static function (&$content, &$y, bool $first) use (&$pages, $sheet, $addText, $drawLine): void {
        if ($content !== '') $pages[] = $content;
        $content = "0.15 0.23 0.32 rg\n0.15 0.23 0.32 RG\n";
        $y = 800;
        if ($first) {
            $addText($content, 38, $y, $sheet['exam']['school_name'] ?: 'School', 17, true); $y -= 25;
            $addText($content, 38, $y, 'STUDENT EXAMINATION RESULTS SHEET', 11, true); $y -= 27;
            $schoolAddress = trim((string)($sheet['exam']['school_address'] ?? ''));
            $schoolPhone = trim((string)($sheet['exam']['school_phone'] ?? ''));
            if ($schoolAddress !== '') {
                foreach (explode("\n", wordwrap('Address: ' . $schoolAddress, 100, "\n", true)) as $addressLine) {
                    $addText($content, 38, $y, $addressLine, 9); $y -= 13;
                }
            }
            if ($schoolPhone !== '') {
                foreach (explode("\n", wordwrap('Phone: ' . $schoolPhone, 100, "\n", true)) as $phoneLine) {
                    $addText($content, 38, $y, $phoneLine, 9); $y -= 13;
                }
            }
            if ($schoolAddress !== '' || $schoolPhone !== '') $y -= 4;
            $drawLine($content, 38, $y, 557, $y); $y -= 20;
            $exam = $sheet['exam'];
            $addText($content, 38, $y, 'Student: ' . ($exam['full_name'] ?: '')); $addText($content, 315, $y, 'Admission No: ' . ($exam['admission_number'] ?: 'Not recorded')); $y -= 16;
            $addText($content, 38, $y, 'Class: ' . ($exam['class_name'] ?: '')); $addText($content, 315, $y, 'Examination: ' . ($exam['title'] ?: '')); $y -= 16;
            $addText($content, 38, $y, 'Academic Session: ' . ($exam['academic_session'] ?: 'Not recorded')); $addText($content, 315, $y, 'Printed: ' . date('M j, Y')); $y -= 25;
        } else {
            $addText($content, 38, $y, $sheet['exam']['title'] . ' - Results (continued)', 12, true); $y -= 24;
        }
        $content .= "q\n0.91 0.94 0.97 rg\n38 " . ($y - 6) . " 519 21 re f\nQ\n0.15 0.23 0.32 RG\n";
        $addText($content, 42, $y, '#', 9, true); $addText($content, 67, $y, 'Subject', 9, true); $addText($content, 250, $y, 'Score', 9, true);
        $addText($content, 354, $y, 'Grade', 9, true); $addText($content, 404, $y, 'Remark', 9, true); $addText($content, 510, $y, 'Status', 9, true);
        $y -= 17;
    };
    $startPage($page, $y, true);
    $widths = [25, 183, 102, 50, 106, 53];
    foreach ($sheet['subjects'] as $index => $subject) {
        $cells = [(string)($index + 1), $subject['subject_name'], '', '', '', $subject['status']];
        if (!empty($subject['score_released'])) {
            $cells[2] = number_format((float)$subject['score'], 2) . ' / ' . number_format((float)$subject['max_score'], 2) . ' (' . number_format((float)$subject['percentage'], 2) . '%)';
            $cells[3] = $subject['grade']['grade_letter'] ?? '-'; $cells[4] = $subject['grade']['remark'] ?? 'Grade not configured';
        } else { $cells[2] = '-'; $cells[3] = '-'; $cells[4] = '-'; }
        $wrapped = [];
        foreach ($cells as $i => $cell) $wrapped[$i] = explode("\n", wordwrap((string)$cell, max(7, (int)floor($widths[$i] / 5.1)), "\n", true));
        $lineCount = max(array_map('count', $wrapped));
        $rowHeight = max(23, $lineCount * 11 + 8);
        if ($y - $rowHeight < 55) $startPage($page, $y, false);
        $x = 40;
        foreach ($wrapped as $col => $lines) {
            foreach ($lines as $lineIndex => $line) $addText($page, $x, $y - $lineIndex * 11, $line, 8.5, false);
            $x += $widths[$col];
        }
        // Place the row border below every wrapped line, not just below the first baseline.
        $drawLine($page, 38, $y - $rowHeight + 5, 557, $y - $rowHeight + 5);
        $y -= $rowHeight;
    }
    if ($y < 95) $startPage($page, $y, false);
    $summary = $sheet['summary']; $y -= 15;
    $addText($page, 38, $y, 'Subjects taken: ' . $summary['count'] . '    Released: ' . $summary['available'] . '    Passed: ' . $summary['passed'] . '    Failed: ' . $summary['failed'], 9, true); $y -= 15;
    $addText($page, 38, $y, 'Total score: ' . number_format((float)$summary['total'], 2) . '    Average: ' . ($summary['average'] === null ? '-' : number_format((float)$summary['average'], 2) . '%'), 9, true);
    $pages[] = $page;

    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    $pageRefs = [];
    foreach ($pages as $i => $stream) {
        $pageObj = 5 + $i * 2; $contentObj = $pageObj + 1; $pageRefs[] = "$pageObj 0 R";
        $objects[$pageObj] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $contentObj 0 R >>";
        $objects[$contentObj] = '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . "endstream";
    }
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageRefs) . '] /Count ' . count($pages) . ' >>';
    ksort($objects);
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $offsets = [0]; $maxObj = max(array_keys($objects));
    for ($i = 1; $i <= $maxObj; $i++) { $offsets[$i] = strlen($pdf); $pdf .= "$i 0 obj\n" . ($objects[$i] ?? 'null') . "\nendobj\n"; }
    $xref = strlen($pdf); $pdf .= "xref\n0 " . ($maxObj + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= $maxObj; $i++) $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
    return $pdf . "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
}
