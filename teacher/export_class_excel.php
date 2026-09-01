<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is logged in and is a teacher or admin
requireLogin();
$user_role = getUserRole();
if (!in_array($user_role, ['teacher', 'admin'])) {
    header('Location: ../login.php');
    exit();
}

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$pass_mark = isset($_GET['pass_mark']) ? (float)$_GET['pass_mark'] : 50.0;
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'csv';

if ($class_id <= 0) {
    die("Error: Please select a valid class to export results.");
}

try {
    $mysqli = getDBConnection();
    if (!$mysqli) {
        die("Error: Database connection failed.");
    }
    ensureTheoryExamSchema($mysqli);
    
    $current_user_id = getUserId();
    
    // Check permission if user is a teacher
    if ($user_role === 'teacher') {
        $check_stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM teacher_allocations WHERE teacher_id = ? AND class_id = ?");
        $check_stmt->bind_param("ii", $current_user_id, $class_id);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result()->fetch_assoc();
        if ($check_res['count'] == 0) {
            die("Error: You do not have permission to view or export results for this class.");
        }
        $check_stmt->close();
    }
    
    // 1. Fetch Class Information
    $class_name = "Class_" . $class_id;
    $marks_per_question = 1;
    $stmt = $mysqli->prepare("SELECT name, COALESCE(marks_per_question, 1) as marks_per_question FROM classes WHERE id = ?");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $class_name = $row['name'];
        $marks_per_question = (float)$row['marks_per_question'];
    }
    $stmt->close();
    
    // 2. Fetch Exam Information
    $exam_title = "All Examinations";
    if ($exam_id > 0) {
        $stmt = $mysqli->prepare("SELECT title FROM exams WHERE id = ?");
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $exam_title = $row['title'];
        }
        $stmt->close();
    }
    
    // 3. Fetch Subjects configured for this Class
    $subjects = [];
    if ($exam_id > 0) {
        $sql_sub = "SELECT DISTINCT s.id AS subject_id, s.name AS subject_name, es.id AS exam_subject_id
                    FROM exam_subjects es
                    JOIN subjects s ON es.subject_id = s.id
                    WHERE es.class_id = ? AND es.exam_id = ?
                    ORDER BY s.name ASC";
        $stmt = $mysqli->prepare($sql_sub);
        $stmt->bind_param("ii", $class_id, $exam_id);
    } else {
        $sql_sub = "SELECT DISTINCT s.id AS subject_id, s.name AS subject_name, MIN(es.id) AS exam_subject_id
                    FROM exam_subjects es
                    JOIN subjects s ON es.subject_id = s.id
                    WHERE es.class_id = ?
                    GROUP BY s.id, s.name
                    ORDER BY s.name ASC";
        $stmt = $mysqli->prepare($sql_sub);
        $stmt->bind_param("i", $class_id);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $subjects[] = $row;
    }
    $stmt->close();
    
    // 4. Fetch All Students in this class
    $students = [];
    $sql_stu = "SELECT DISTINCT u.id AS student_id, u.full_name, u.email
                FROM users u
                LEFT JOIN exam_attempts ea ON u.id = ea.student_id
                LEFT JOIN exam_subjects es ON ea.exam_subject_id = es.id
                WHERE u.role = 'student'
                  AND (u.class_id = ? OR es.class_id = ?)
                ORDER BY u.full_name ASC";
    $stmt = $mysqli->prepare($sql_stu);
    $stmt->bind_param("ii", $class_id, $class_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $students[$row['student_id']] = $row;
    }
    $stmt->close();
    
    // 5. Fetch Attempts Data for each student and subject
    $scores_matrix = [];
    
    foreach ($students as $stu_id => $stu) {
        $scores_matrix[$stu_id] = [];
        
        foreach ($subjects as $sub) {
            $sub_id = $sub['subject_id'];
            
            if ($exam_id > 0) {
                $sql_att = "SELECT ea.score, ea.total_questions, c.marks_per_question, ea.submitted_at, ea.exam_subject_id,
                                  (SELECT COUNT(*) FROM questions q WHERE q.exam_subject_id = ea.exam_subject_id AND COALESCE(q.question_type, 'objective') = 'objective') AS objective_questions,
                                  (SELECT COALESCE(SUM(q.theory_marks), 0) FROM questions q WHERE q.exam_subject_id = ea.exam_subject_id AND q.question_type = 'theory') AS theory_total_marks
                            FROM exam_attempts ea
                            JOIN exam_subjects es ON ea.exam_subject_id = es.id
                            JOIN classes c ON es.class_id = c.id
                            WHERE ea.student_id = ? AND es.class_id = ? AND es.subject_id = ? AND ea.exam_id = ? AND ea.submitted_at IS NOT NULL
                            ORDER BY ea.submitted_at DESC LIMIT 1";
                $stmt = $mysqli->prepare($sql_att);
                $stmt->bind_param("iiii", $stu_id, $class_id, $sub_id, $exam_id);
            } else {
                $sql_att = "SELECT ea.score, ea.total_questions, c.marks_per_question, ea.submitted_at, ea.exam_subject_id,
                                  (SELECT COUNT(*) FROM questions q WHERE q.exam_subject_id = ea.exam_subject_id AND COALESCE(q.question_type, 'objective') = 'objective') AS objective_questions,
                                  (SELECT COALESCE(SUM(q.theory_marks), 0) FROM questions q WHERE q.exam_subject_id = ea.exam_subject_id AND q.question_type = 'theory') AS theory_total_marks
                            FROM exam_attempts ea
                            JOIN exam_subjects es ON ea.exam_subject_id = es.id
                            JOIN classes c ON es.class_id = c.id
                            WHERE ea.student_id = ? AND es.class_id = ? AND es.subject_id = ? AND ea.submitted_at IS NOT NULL
                            ORDER BY ea.submitted_at DESC LIMIT 1";
                $stmt = $mysqli->prepare($sql_att);
                $stmt->bind_param("iii", $stu_id, $class_id, $sub_id);
            }
            
            $stmt->execute();
            $att_res = $stmt->get_result();
            if ($att_row = $att_res->fetch_assoc()) {
                $obj_q = (int)($att_row['objective_questions'] ?? $att_row['total_questions']);
                $mpq = (float)($att_row['marks_per_question'] ?? 1);
                $theory_total = (float)($att_row['theory_total_marks'] ?? 0);
                
                $max_score = ($obj_q * $mpq) + $theory_total;
                if ($max_score <= 0) {
                    $max_score = (float)$att_row['total_questions'] * $mpq;
                }
                
                $score = (float)$att_row['score'];
                $pct = $max_score > 0 ? ($score / $max_score) * 100 : 0;
                
                $scores_matrix[$stu_id][$sub_id] = [
                    'score' => $score,
                    'max_score' => $max_score,
                    'percentage' => round($pct, 2),
                    'attempted' => true
                ];
            } else {
                $scores_matrix[$stu_id][$sub_id] = [
                    'score' => 0,
                    'max_score' => 0,
                    'percentage' => 0,
                    'attempted' => false
                ];
            }
            $stmt->close();
        }
    }

    // Build Clean Filename
    $clean_class = preg_replace('/[^A-Za-z0-9_\-]/', '_', $class_name);
    $clean_exam = preg_replace('/[^A-Za-z0-9_\-]/', '_', $exam_title);
    $filename = "Results_" . $clean_class . "_" . $clean_exam . "_" . date('Ymd_His');

    if ($format === 'xml') {
        // Output Microsoft Excel XML Spreadsheet 2003 (SpreadsheetML) format
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xml"');
        header('Cache-Control: max-age=0');
        
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
        ?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <Title><?php echo htmlspecialchars($filename); ?></Title>
  <Author>CBT Exam System</Author>
 </DocumentProperties>
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Bottom"/>
   <Font ss:FontName="Segoe UI" ss:Size="10" ss:Color="#000000"/>
  </Style>
  <Style ss:ID="Title">
   <Font ss:FontName="Segoe UI" ss:Size="14" ss:Color="#1E3A8A" ss:Bold="1"/>
  </Style>
  <Style ss:ID="MetaLabel">
   <Font ss:FontName="Segoe UI" ss:Size="10" ss:Color="#475569" ss:Bold="1"/>
  </Style>
  <Style ss:ID="Header">
   <Font ss:FontName="Segoe UI" ss:Size="11" ss:Color="#FFFFFF" ss:Bold="1"/>
   <Interior ss:Color="#1E40AF" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1E3A8A"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1E3A8A"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1E3A8A"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1E3A8A"/>
   </Borders>
  </Style>
  <Style ss:ID="Data">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
  </Style>
  <Style ss:ID="StudentName">
   <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
   <Font ss:FontName="Segoe UI" ss:Size="10" ss:Bold="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
  </Style>
  <Style ss:ID="Passed">
   <Font ss:FontName="Segoe UI" ss:Size="10" ss:Color="#166534" ss:Bold="1"/>
   <Interior ss:Color="#DCFCE7" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
  </Style>
  <Style ss:ID="Failed">
   <Font ss:FontName="Segoe UI" ss:Size="10" ss:Color="#991B1B" ss:Bold="1"/>
   <Interior ss:Color="#FEE2E2" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
  </Style>
  <Style ss:ID="NotTaken">
   <Font ss:FontName="Segoe UI" ss:Size="10" ss:Color="#64748B" ss:Italic="1"/>
   <Interior ss:Color="#F8FAFC" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
  </Style>
 </Styles>
 <Worksheet ss:Name="Class Results">
  <Table>
   <Column ss:Width="40"/>
   <Column ss:Width="180"/>
   <Column ss:Width="80"/>
   <?php foreach ($subjects as $sub): ?>
       <Column ss:Width="110"/>
   <?php endforeach; ?>
   <Column ss:Width="90"/>
   <Column ss:Width="110"/>
   <Column ss:Width="100"/>
   <Column ss:Width="90"/>

   <Row>
    <Cell ss:StyleID="Title"><Data ss:Type="String">CBT EXAMINATION SYSTEM - STUDENT RESULTS BROADSHEET</Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Class:</Data></Cell>
    <Cell><Data ss:Type="String"><?php echo htmlspecialchars($class_name); ?></Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Examination:</Data></Cell>
    <Cell><Data ss:Type="String"><?php echo htmlspecialchars($exam_title); ?></Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Pass Mark:</Data></Cell>
    <Cell><Data ss:Type="String"><?php echo $pass_mark; ?>%</Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Export Date:</Data></Cell>
    <Cell><Data ss:Type="String"><?php echo date('Y-m-d H:i:s'); ?></Data></Cell>
   </Row>
   <Row/>

   <!-- Header Row -->
   <Row ss:Height="24">
    <Cell ss:StyleID="Header"><Data ss:Type="String">S/N</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Student Name</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Class</Data></Cell>
    <?php foreach ($subjects as $sub): ?>
        <Cell ss:StyleID="Header"><Data ss:Type="String"><?php echo htmlspecialchars($sub['subject_name']); ?></Data></Cell>
    <?php endforeach; ?>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Total Score</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Max Possible Score</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Overall (%)</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Status</Data></Cell>
   </Row>

   <!-- Data Rows -->
   <?php 
   $sn = 1;
   foreach ($students as $stu_id => $stu):
       $student_total_score = 0;
       $student_max_score = 0;
       $attempted_count = 0;
   ?>
    <Row ss:Height="20">
     <Cell ss:StyleID="Data"><Data ss:Type="Number"><?php echo $sn++; ?></Data></Cell>
     <Cell ss:StyleID="StudentName"><Data ss:Type="String"><?php echo htmlspecialchars($stu['full_name']); ?></Data></Cell>
     <Cell ss:StyleID="Data"><Data ss:Type="String"><?php echo htmlspecialchars($class_name); ?></Data></Cell>
     
     <?php foreach ($subjects as $sub): 
         $sub_id = $sub['subject_id'];
         $data = $scores_matrix[$stu_id][$sub_id] ?? ['attempted' => false];
         if ($data['attempted']):
             $student_total_score += $data['score'];
             $student_max_score += $data['max_score'];
             $attempted_count++;
     ?>
             <Cell ss:StyleID="Data"><Data ss:Type="Number"><?php echo round($data['score'], 2); ?></Data></Cell>
         <?php else: ?>
             <Cell ss:StyleID="NotTaken"><Data ss:Type="String">N/A</Data></Cell>
         <?php endif; ?>
     <?php endforeach; ?>
     
     <?php 
     $overall_pct = $student_max_score > 0 ? ($student_total_score / $student_max_score) * 100 : 0;
     $status = $attempted_count > 0 ? ($overall_pct >= $pass_mark ? 'Passed' : 'Failed') : 'No Attempts';
     $status_style = $attempted_count > 0 ? ($overall_pct >= $pass_mark ? 'Passed' : 'Failed') : 'NotTaken';
     ?>
     
     <Cell ss:StyleID="Data"><Data ss:Type="Number"><?php echo round($student_total_score, 2); ?></Data></Cell>
     <Cell ss:StyleID="Data"><Data ss:Type="Number"><?php echo round($student_max_score, 2); ?></Data></Cell>
     <Cell ss:StyleID="Data"><Data ss:Type="String"><?php echo round($overall_pct, 2) . '%'; ?></Data></Cell>
     <Cell ss:StyleID="<?php echo $status_style; ?>"><Data ss:Type="String"><?php echo $status; ?></Data></Cell>
    </Row>
   <?php endforeach; ?>
  </Table>
 </Worksheet>
</Workbook>
        <?php
        exit();

    } else {
        // Output Universal CSV File (.csv) - Opens natively in Excel with ZERO warnings!
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Cache-Control: max-age=0');
        
        $output = fopen('php://output', 'w');
        // UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Metadata headers
        fputcsv($output, ["CBT EXAMINATION SYSTEM - STUDENT RESULTS BROADSHEET"]);
        fputcsv($output, ["Class:", $class_name]);
        fputcsv($output, ["Examination:", $exam_title]);
        fputcsv($output, ["Pass Mark:", $pass_mark . '%']);
        fputcsv($output, ["Date Exported:", date('Y-m-d H:i:s')]);
        fputcsv($output, []);
        
        // Table Headers
        $headers = ['S/N', 'Student Name', 'Class'];
        foreach ($subjects as $sub) {
            $headers[] = $sub['subject_name'];
        }
        $headers[] = 'Total Score Obtained';
        $headers[] = 'Total Max Score';
        $headers[] = 'Overall Percentage (%)';
        $headers[] = 'Status';
        fputcsv($output, $headers);
        
        // Data Rows
        $sn = 1;
        foreach ($students as $stu_id => $stu) {
            $row = [
                $sn++,
                $stu['full_name'],
                $class_name
            ];
            
            $student_total_score = 0;
            $student_max_score = 0;
            $attempted_count = 0;
            
            foreach ($subjects as $sub) {
                $sub_id = $sub['subject_id'];
                $data = $scores_matrix[$stu_id][$sub_id] ?? ['attempted' => false];
                
                if ($data['attempted']) {
                    $row[] = number_format($data['score'], 2, '.', '');
                    $student_total_score += $data['score'];
                    $student_max_score += $data['max_score'];
                    $attempted_count++;
                } else {
                    $row[] = 'N/A';
                }
            }
            
            $overall_pct = $student_max_score > 0 ? ($student_total_score / $student_max_score) * 100 : 0;
            $status = $attempted_count > 0 ? ($overall_pct >= $pass_mark ? 'Passed' : 'Failed') : 'No Attempts';
            
            $row[] = number_format($student_total_score, 2, '.', '');
            $row[] = number_format($student_max_score, 2, '.', '');
            $row[] = round($overall_pct, 2) . '%';
            $row[] = $status;
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit();
    }
} catch (Exception $e) {
    die("Error exporting results: " . $e->getMessage());
}
?>
