<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/term_results.php';
requireLogin();
if (!in_array(getUserRole(), ['teacher', 'admin'], true)) { header('Location: ../login.php'); exit(); }
$mysqli = getDBConnection();
if (!$mysqli) die('<div class="alert alert-danger">Database connection failed.</div>');
$teacherId = (int)getUserId();
$message = '';
$sessions = termResultsSessionRows($mysqli);
$selectedSession = (int)($_GET['session_id'] ?? ($_POST['session_id'] ?? 0));
if (!$selectedSession) foreach ($sessions as $session) if ((int)$session['is_current'] === 1) $selectedSession = (int)$session['id'];
$classId = (int)($_GET['class_id'] ?? ($_POST['class_id'] ?? 0));
$subjectId = (int)($_GET['subject_id'] ?? ($_POST['subject_id'] ?? 0));
$term = (int)($_GET['term'] ?? ($_POST['term'] ?? 1));
if ($term < 1 || $term > 3) $term = 1;

$stmt = $mysqli->prepare('SELECT DISTINCT c.id AS class_id, c.name AS class_name, s.id AS subject_id, s.name AS subject_name
    FROM teacher_allocations ta JOIN classes c ON c.id=ta.class_id JOIN subjects s ON s.id=ta.subject_id
    WHERE ta.teacher_id=? ORDER BY c.name, s.name');
$stmt->bind_param('i', $teacherId); $stmt->execute(); $allocationResult=$stmt->get_result(); $allocations=[];
while ($row=$allocationResult->fetch_assoc()) $allocations[]=$row;
$stmt->close();
$isAssigned = false;
foreach ($allocations as $allocation) if ((int)$allocation['class_id']===$classId && (int)$allocation['subject_id']===$subjectId) $isAssigned=true;

function completedResitScores(mysqli $mysqli, int $classId, int $subjectId, int $sessionId, int $term, array $maxScores): array {
    $scores=[];
    $stmt=$mysqli->prepare("SELECT ea.student_id, ea.score, ea.total_questions, rr.penalty_percentage,
            resit_link.result_component_type, resit_link.result_component_number,
            c.marks_per_question,
            (SELECT COUNT(*) FROM questions q WHERE q.exam_subject_id=es.id AND COALESCE(q.question_type,'objective')='objective') AS objective_questions,
            (SELECT COALESCE(SUM(q.theory_marks),0) FROM questions q WHERE q.exam_subject_id=es.id AND q.question_type='theory') AS theory_total_marks
        FROM exam_subjects es
        JOIN exam_attempts ea ON ea.exam_subject_id=es.id AND ea.submitted_at IS NOT NULL AND ea.score IS NOT NULL
        JOIN resit_requests rr ON rr.id=es.resit_request_id AND rr.status='approved' AND rr.student_id=ea.student_id
        JOIN exam_class_result_links resit_link ON resit_link.exam_id=rr.exam_id AND resit_link.class_id=es.class_id
        JOIN classes c ON c.id=es.class_id
        WHERE es.class_id=? AND es.subject_id=? AND resit_link.result_session_id=? AND resit_link.result_term=?
        ORDER BY ea.submitted_at DESC, ea.id DESC");
    $stmt->bind_param('iiii',$classId,$subjectId,$sessionId,$term); $stmt->execute(); $res=$stmt->get_result();
    while($row=$res->fetch_assoc()) {
        $type=$row['result_component_type']; $number=(int)$row['result_component_number']; $studentId=(int)$row['student_id'];
        if(!isset($maxScores[$type][$number]) || isset($scores[$studentId][$type][$number])) continue;
        $examMaximum=((int)$row['objective_questions']*(float)$row['marks_per_question'])+(float)$row['theory_total_marks'];
        if($examMaximum<=0)$examMaximum=(int)$row['total_questions']*(float)$row['marks_per_question'];
        if($examMaximum<=0)continue;
        $creditedScore=(float)$row['score']*(1-((float)$row['penalty_percentage']/100));
        $scores[$studentId][$type][$number]=round(min($maxScores[$type][$number],($creditedScore/$examMaximum)*$maxScores[$type][$number]),2);
    }
    $stmt->close();
    return $scores;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!termResultsVerifyCsrf($_POST['csrf_token'] ?? '')) $message='Your session expired. Refresh and try again.';
    elseif (!$isAssigned || !$selectedSession || !in_array($selectedSession, array_map(fn($s)=>(int)$s['id'], $sessions), true)) $message='Choose a class, subject and session assigned to you.';
    else {
        $transactionActive=false;
        try {
            $stmt=$mysqli->prepare('SELECT ca_count, exam_count FROM class_result_settings WHERE class_id=? AND session_id=?');
            $stmt->bind_param('ii',$classId,$selectedSession); $stmt->execute(); $config=$stmt->get_result()->fetch_assoc(); $stmt->close();
            $caCount=$config ? (int)$config['ca_count'] : 3;
            $examCount=$config ? (int)$config['exam_count'] : 1;
            $maxScores=['ca'=>[],'exam'=>[]];
            foreach(['ca'=>$caCount,'exam'=>$examCount] as $type=>$count) for($i=1;$i<=$count;$i++) $maxScores[$type][$i]=100.0;
            $stmt=$mysqli->prepare('SELECT component_type,component_number,max_score FROM class_result_component_names WHERE class_id=? AND session_id=?');
            $stmt->bind_param('ii',$classId,$selectedSession); $stmt->execute(); $maxRes=$stmt->get_result();
            while($item=$maxRes->fetch_assoc()) $maxScores[$item['component_type']][(int)$item['component_number']]=(float)$item['max_score']; $stmt->close();
            $authoritativeResitScores=completedResitScores($mysqli,$classId,$subjectId,$selectedSession,$term,$maxScores);
            $submitted = ($_POST['save_action'] ?? '') === 'submit';
            $studentsStmt=$mysqli->prepare("SELECT id FROM users WHERE role='student' AND class_id=? ORDER BY full_name");
            $studentsStmt->bind_param('i',$classId); $studentsStmt->execute(); $studentRes=$studentsStmt->get_result(); $studentIds=[];
            while($student=$studentRes->fetch_assoc()) $studentIds[]=(int)$student['id'];
            $studentsStmt->close();
            if (!$studentIds) throw new RuntimeException('There are no students enrolled in this class.');

            $mysqli->begin_transaction();
            $transactionActive=true;
            $stmt=$mysqli->prepare('INSERT INTO result_periods (class_id,session_id,term,status) VALUES (?,?,?,\'draft\') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
            $stmt->bind_param('iii',$classId,$selectedSession,$term); $stmt->execute(); $periodId=(int)$mysqli->insert_id; $stmt->close();
            $stmt=$mysqli->prepare('SELECT status FROM result_periods WHERE id=? FOR UPDATE');
            $stmt->bind_param('i',$periodId); $stmt->execute(); $period=$stmt->get_result()->fetch_assoc(); $stmt->close();
            $stmt=$mysqli->prepare("SELECT COUNT(*) AS released_count FROM term_scores WHERE period_id=? AND subject_id=? AND release_status='approved'");
            $stmt->bind_param('ii',$periodId,$subjectId); $stmt->execute(); $releasedCount=(int)$stmt->get_result()->fetch_assoc()['released_count']; $stmt->close();
            $releasedStudents=[];
            if($releasedCount>0) {
                $stmt=$mysqli->prepare("SELECT student_id FROM term_scores WHERE period_id=? AND subject_id=? AND release_status='approved'");
                $stmt->bind_param('ii',$periodId,$subjectId); $stmt->execute(); $releasedRes=$stmt->get_result();
                while($releasedRow=$releasedRes->fetch_assoc())$releasedStudents[(int)$releasedRow['student_id']]=true;
                $stmt->close();
            }

            $componentValues=[]; $studentCompleteness=[]; $complete=true;
            foreach ($studentIds as $studentId) {
                if(isset($releasedStudents[$studentId])) continue;
                $studentCompleteness[$studentId]=true;
                foreach (['ca'=>$caCount,'exam'=>$examCount] as $type=>$count) {
                    for ($number=1;$number<=$count;$number++) {
                        $raw=$_POST['scores'][$studentId][$type][$number] ?? '';
                        if ($raw === '') { $componentValues[$studentId][$type][$number]=null; $complete=false; $studentCompleteness[$studentId]=false; continue; }
                        if (!is_numeric($raw) || (float)$raw<0 || (float)$raw>$maxScores[$type][$number]) throw new RuntimeException('A score is outside its configured maximum.');
                        $componentValues[$studentId][$type][$number]=(float)$raw;
                    }
                }
            }
            foreach($authoritativeResitScores as $studentId=>$components) foreach($components as $type=>$items) foreach($items as $number=>$score) {
                $componentValues[$studentId][$type][$number]=$score;
            }
            $scoreStmt=$mysqli->prepare('INSERT INTO term_scores (period_id,student_id,subject_id,teacher_id) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE teacher_id=VALUES(teacher_id)');
            $componentStmt=$mysqli->prepare('INSERT INTO term_score_items (term_score_id,component_type,component_number,score) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE score=VALUES(score)');
            $releaseStmt=$mysqli->prepare('UPDATE term_scores SET release_status=?,released_by=NULL,released_at=NULL WHERE id=?');
            foreach($studentIds as $studentId) {
                if(isset($releasedStudents[$studentId])) continue;
                $scoreStmt->bind_param('iiii',$periodId,$studentId,$subjectId,$teacherId); $scoreStmt->execute();
                $scoreId=(int)$mysqli->insert_id;
                if (!$scoreId) {
                    $lookup=$mysqli->prepare('SELECT id FROM term_scores WHERE period_id=? AND student_id=? AND subject_id=?');
                    $lookup->bind_param('iii',$periodId,$studentId,$subjectId); $lookup->execute(); $scoreId=(int)$lookup->get_result()->fetch_assoc()['id']; $lookup->close();
                }
                foreach($componentValues[$studentId] as $type=>$items) foreach($items as $number=>$value) {
                    $componentStmt->bind_param('isid',$scoreId,$type,$number,$value); $componentStmt->execute();
                }
                $rowStatus=$submitted && $studentCompleteness[$studentId]?'submitted':'draft';
                $releaseStmt->bind_param('si',$rowStatus,$scoreId); $releaseStmt->execute();
            }
            $scoreStmt->close(); $componentStmt->close(); $releaseStmt->close();
            $nextStatus = $submitted && $complete ? 'submitted' : 'draft';
            $stmt=$mysqli->prepare('UPDATE result_periods SET status=?, submitted_at=? WHERE id=?');
            $submittedAt=$nextStatus==='submitted'?date('Y-m-d H:i:s'):null;
            $stmt->bind_param('ssi',$nextStatus,$submittedAt,$periodId); $stmt->execute(); $stmt->close();
            $mysqli->commit();
            $transactionActive=false;
            $message=$nextStatus==='submitted'?'Scores submitted for admin approval.':($submitted?'Draft saved. Complete every score cell before submitting for approval.':'Draft saved.');
        } catch(Throwable $e) {
            if ($transactionActive) $mysqli->rollback();
            $message=$e instanceof mysqli_sql_exception?'Scores could not be saved. Confirm the term-results database tables have been installed.':$e->getMessage();
        }
    }
}

$students=[]; $config=['ca_count'=>3,'exam_count'=>1]; $status='Not started'; $saved=[]; $importedScores=[]; $resitScores=[]; $releaseStates=[]; $labels=['ca'=>[],'exam'=>[]]; $maxScores=['ca'=>[],'exam'=>[]];
if ($isAssigned && $selectedSession) {
    $stmt=$mysqli->prepare('SELECT ca_count,exam_count FROM class_result_settings WHERE class_id=? AND session_id=?');
    $stmt->bind_param('ii',$classId,$selectedSession); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); if($row)$config=$row; $stmt->close();
    foreach (['ca'=>(int)$config['ca_count'],'exam'=>(int)$config['exam_count']] as $type=>$count) for($i=1;$i<=$count;$i++) { $labels[$type][$i]=($type==='ca'?'CA ':'Exam ').$i; $maxScores[$type][$i]=100; }
    $stmt=$mysqli->prepare('SELECT component_type,component_number,label,max_score FROM class_result_component_names WHERE class_id=? AND session_id=?');
    $stmt->bind_param('ii',$classId,$selectedSession); $stmt->execute(); $res=$stmt->get_result();
    while($row=$res->fetch_assoc()) { $labels[$row['component_type']][(int)$row['component_number']]=$row['label']; $maxScores[$row['component_type']][(int)$row['component_number']]=(float)$row['max_score']; } $stmt->close();
    $resitScores=completedResitScores($mysqli,$classId,$subjectId,$selectedSession,$term,$maxScores);
    $stmt=$mysqli->prepare('SELECT id,full_name FROM users WHERE role=\'student\' AND class_id=? ORDER BY full_name');
    $stmt->bind_param('i',$classId); $stmt->execute(); $res=$stmt->get_result(); while($row=$res->fetch_assoc())$students[]=$row; $stmt->close();
    $stmt=$mysqli->prepare('SELECT id,status FROM result_periods WHERE class_id=? AND session_id=? AND term=?');
    $stmt->bind_param('iii',$classId,$selectedSession,$term); $stmt->execute(); $period=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if($period) {
        $status=ucfirst($period['status']);
        $stmt=$mysqli->prepare('SELECT ts.student_id,ts.release_status,tsi.component_type,tsi.component_number,tsi.score FROM term_scores ts LEFT JOIN term_score_items tsi ON tsi.term_score_id=ts.id WHERE ts.period_id=? AND ts.subject_id=?');
        $stmt->bind_param('ii',$period['id'],$subjectId); $stmt->execute(); $res=$stmt->get_result();
        while($row=$res->fetch_assoc()){$studentKey=(int)$row['student_id'];$releaseStates[$studentKey]=$row['release_status'];if($row['component_type'])$saved[$studentKey][$row['component_type']][(int)$row['component_number']]=$row['score'];} $stmt->close();
    }
    $stmt=$mysqli->prepare("SELECT ea.student_id, ea.score, ea.total_questions, ea.submitted_at,
            COALESCE(resit_link.result_component_type,ecl.result_component_type) AS result_component_type,
            COALESCE(resit_link.result_component_number,ecl.result_component_number) AS result_component_number,
            COALESCE(rr.penalty_percentage,0) AS penalty_percentage,
            (rr.id IS NOT NULL) AS is_resit,
            c.marks_per_question,
            (SELECT COUNT(*) FROM questions q WHERE q.exam_subject_id=es.id AND COALESCE(q.question_type,'objective')='objective') AS objective_questions,
            (SELECT COALESCE(SUM(q.theory_marks),0) FROM questions q WHERE q.exam_subject_id=es.id AND q.question_type='theory') AS theory_total_marks
        FROM exam_subjects es
        JOIN exams e ON e.id=es.exam_id
        LEFT JOIN exam_class_result_links ecl ON ecl.exam_id=e.id AND ecl.class_id=es.class_id
        LEFT JOIN resit_requests rr ON rr.id=es.resit_request_id AND rr.status='approved'
        LEFT JOIN exam_class_result_links resit_link ON resit_link.exam_id=rr.exam_id AND resit_link.class_id=es.class_id
        JOIN classes c ON c.id=es.class_id
        JOIN (
            SELECT student_id, exam_subject_id, MAX(id) AS attempt_id
            FROM exam_attempts WHERE submitted_at IS NOT NULL
            GROUP BY student_id, exam_subject_id
        ) latest ON latest.exam_subject_id=es.id
        JOIN exam_attempts ea ON ea.id=latest.attempt_id
        WHERE es.class_id=? AND es.subject_id=?
            AND (rr.id IS NULL OR rr.student_id=ea.student_id)
            AND COALESCE(resit_link.result_session_id,ecl.result_session_id)=?
            AND COALESCE(resit_link.result_term,ecl.result_term)=?
            AND ea.score IS NOT NULL
            AND COALESCE(resit_link.result_component_type,ecl.result_component_type) IS NOT NULL
        ORDER BY is_resit DESC, ea.submitted_at DESC, ea.id DESC");
    $stmt->bind_param('iiii',$classId,$subjectId,$selectedSession,$term); $stmt->execute(); $importRes=$stmt->get_result();
    while($row=$importRes->fetch_assoc()) {
        $studentKey=(int)$row['student_id']; $type=$row['result_component_type']; $number=(int)$row['result_component_number'];
        if(!isset($maxScores[$type][$number]) || (int)$row['is_resit']===1 || isset($resitScores[$studentKey][$type][$number]) || array_key_exists($number,$saved[$studentKey][$type] ?? []) || isset($importedScores[$studentKey][$type][$number])) continue;
        $examMaximum=((int)$row['objective_questions']*(float)$row['marks_per_question'])+(float)$row['theory_total_marks'];
        if($examMaximum<=0)$examMaximum=(int)$row['total_questions']*(float)$row['marks_per_question'];
        if($examMaximum<=0)continue;
        $creditedScore=(float)$row['score']*(1-((float)$row['penalty_percentage']/100));
        $importedScores[$studentKey][$type][$number]=round(min($maxScores[$type][$number],($creditedScore/$examMaximum)*$maxScores[$type][$number]),2);
    }
    $stmt->close();
}
$hasReleasedScores=in_array('approved',$releaseStates,true);
$chosenAllocation=null; foreach($allocations as $allocation) if((int)$allocation['class_id']===$classId && (int)$allocation['subject_id']===$subjectId)$chosenAllocation=$allocation;
include '../includes/header.php';
?>
<style>
.term-score-entry{--entry-line:#e1e8eb;--entry-teal:#137c72;color:#1d2b34}
.term-score-entry .entry-heading{padding:1.25rem 1.4rem;background:#f5faf9;border:1px solid #dcebe8;border-radius:10px}
.term-score-entry h1{font-size:1.55rem;font-weight:700}
.term-score-entry .card{border:1px solid var(--entry-line);border-radius:10px;box-shadow:0 4px 18px rgba(28,45,53,.045);overflow:hidden}
.term-score-entry .card-header{background:#f7f9fa;border-bottom:1px solid var(--entry-line)}
.term-score-entry .table thead th{position:sticky;top:0;background:#f1f5f6;z-index:1;white-space:nowrap}
.term-score-entry .table tbody th{background:#fff;position:sticky;left:0;z-index:1}
.term-score-entry input[type=number]{min-height:38px}
@media(max-width:767px){.term-score-entry h1{font-size:1.3rem}.term-score-entry .entry-heading{padding:1rem}}
@media print{
    @page{size:A4 landscape;margin:7mm}
    body *{visibility:hidden!important}
    #term-score-print-area,#term-score-print-area *{visibility:visible!important}
    #term-score-print-area{position:absolute!important;left:0!important;top:0!important;width:100%!important;margin:0!important;border:0!important;box-shadow:none!important;overflow:visible!important}
    #term-score-print-area .no-print{display:none!important}
    #term-score-print-area .table-responsive{overflow:visible!important;width:100%!important}
    #term-score-print-area .table{width:100%!important;table-layout:auto;font-size:7pt}
    #term-score-print-area th,#term-score-print-area td{padding:2px!important;white-space:normal!important}
    #term-score-print-area input[type=number]{min-width:0!important;width:100%!important;min-height:0!important;height:auto!important;padding:1px!important;border:0!important;background:transparent!important;box-shadow:none!important;font-size:7pt!important;color:#111!important;appearance:textfield}
    #term-score-print-area input[type=number]::-webkit-inner-spin-button,#term-score-print-area input[type=number]::-webkit-outer-spin-button{appearance:none;margin:0}
    #term-score-print-area tr{break-inside:avoid}
}
</style>
<main class="container-fluid py-4 term-score-entry">
    <div class="entry-heading d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div><div class="text-uppercase small fw-bold text-success">Teacher workspace</div><h1 class="mb-1">Manual Term Scores</h1><p class="text-muted mb-0">Enter marks for your assigned class and subject.</p></div><a class="btn btn-outline-secondary" href="dashboard.php"><i class="fas fa-arrow-left me-1"></i> Teacher dashboard</a></div>
    <?php if($message):?><div class="alert alert-info"><?php echo htmlspecialchars($message);?></div><?php endif;?>
    <form method="get" class="card card-body mb-3"><div class="row g-3 align-items-end">
        <div class="col-md-3"><label class="form-label" for="session_id">Session</label><select class="form-select" id="session_id" name="session_id" required><?php foreach($sessions as $session):?><option value="<?php echo (int)$session['id'];?>" <?php echo (int)$session['id']===$selectedSession?'selected':'';?>><?php echo htmlspecialchars($session['name'].((int)$session['is_current']?' · Current':''));?></option><?php endforeach;?></select></div>
        <div class="col-md-3"><label class="form-label" for="class_id">Class and subject</label><select class="form-select" id="class_id" name="class_id" required><?php foreach($allocations as $allocation):?><option value="<?php echo (int)$allocation['class_id'];?>" data-subject="<?php echo (int)$allocation['subject_id'];?>" <?php echo (int)$allocation['class_id']===$classId && (int)$allocation['subject_id']===$subjectId?'selected':'';?>><?php echo htmlspecialchars($allocation['class_name'].' · '.$allocation['subject_name']);?></option><?php endforeach;?></select><input type="hidden" id="subject_id" name="subject_id" value="<?php echo $subjectId;?>"></div>
        <div class="col-md-3"><label class="form-label" for="term">Term</label><select class="form-select" id="term" name="term"><?php for($i=1;$i<=3;$i++):?><option value="<?php echo $i;?>" <?php echo $term===$i?'selected':'';?>><?php echo htmlspecialchars(termLabel($i));?></option><?php endfor;?></select></div>
        <div class="col-md-3"><button class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> Load score sheet</button></div>
    </div></form>
    <script>document.getElementById('class_id').addEventListener('change',function(){document.getElementById('subject_id').value=this.options[this.selectedIndex].dataset.subject;});</script>
    <?php if(!$sessions):?><div class="alert alert-warning">The administrator has not created an academic session yet.</div>
    <?php elseif(!$allocations):?><div class="alert alert-warning">No class and subject have been assigned to you.</div>
    <?php elseif($chosenAllocation):?>
    <form method="post" class="card" id="term-score-print-area"><div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2"><div><h5 class="mb-1"><?php echo htmlspecialchars($chosenAllocation['class_name'].' · '.$chosenAllocation['subject_name']);?></h5><span class="text-muted"><?php echo htmlspecialchars(termLabel($term));?> · <?php echo (int)$config['ca_count'];?> CA and <?php echo (int)$config['exam_count'];?> exam components</span></div><div class="d-flex align-items-center gap-2"><span class="badge bg-<?php echo strtolower($status)==='approved'?'success':(strtolower($status)==='submitted'?'warning text-dark':'secondary');?>"><?php echo htmlspecialchars($status);?></span><button type="button" class="btn btn-outline-primary btn-sm no-print" onclick="window.print()"><i class="fas fa-print me-1"></i> Print / Save PDF</button></div></div>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(termResultsCsrfToken());?>"><input type="hidden" name="session_id" value="<?php echo $selectedSession;?>"><input type="hidden" name="class_id" value="<?php echo $classId;?>"><input type="hidden" name="subject_id" value="<?php echo $subjectId;?>"><input type="hidden" name="term" value="<?php echo $term;?>">
        <div class="table-responsive"><table class="table table-bordered table-sm align-middle mb-0"><thead class="table-light"><tr><th style="min-width:220px">Student</th><?php for($i=1;$i<=(int)$config['ca_count'];$i++):?><th><?php echo htmlspecialchars($labels['ca'][$i]);?><small class="d-block text-muted">/<?php echo htmlspecialchars((string)$maxScores['ca'][$i]);?></small></th><?php endfor;?><?php for($i=1;$i<=(int)$config['exam_count'];$i++):?><th><?php echo htmlspecialchars($labels['exam'][$i]);?><small class="d-block text-muted">/<?php echo htmlspecialchars((string)$maxScores['exam'][$i]);?></small></th><?php endfor;?><th style="min-width:140px">Release status</th></tr></thead><tbody>
        <?php foreach($students as $student):$studentId=(int)$student['id'];$rowReleaseStatus=$releaseStates[$studentId]??'draft';?><tr><th><?php echo htmlspecialchars($student['full_name']);?></th><?php foreach(['ca'=>(int)$config['ca_count'],'exam'=>(int)$config['exam_count']] as $type=>$count):for($i=1;$i<=$count;$i++):$hasResit=isset($resitScores[$studentId][$type][$i]);$hasSaved=array_key_exists($i,$saved[$studentId][$type]??[]);$value=$hasResit?$resitScores[$studentId][$type][$i]:($hasSaved?$saved[$studentId][$type][$i]:($importedScores[$studentId][$type][$i]??''));$wasImported=!$hasResit&&!$hasSaved&&isset($importedScores[$studentId][$type][$i]);?><td><input class="form-control form-control-sm" style="min-width:76px" type="number" min="0" max="<?php echo htmlspecialchars((string)$maxScores[$type][$i]);?>" step="0.01" name="scores[<?php echo $studentId;?>][<?php echo $type;?>][<?php echo $i;?>]" value="<?php echo htmlspecialchars((string)$value);?>" <?php echo $rowReleaseStatus==='approved'?'disabled':($hasResit?'readonly':'');?> aria-label="<?php echo htmlspecialchars($student['full_name'].' '.$labels[$type][$i]);?>" <?php echo $hasResit?'title="Final linked score from the completed resit, after penalty."':($wasImported?'title="Imported from the linked CBT exam; you can edit this score."':'');?>></td><?php endfor;endforeach;?><td><?php if($rowReleaseStatus==='approved'):?><span class="badge bg-success">Released</span><?php elseif($rowReleaseStatus==='submitted'):?><span class="badge bg-primary">Awaiting admin</span><?php elseif($rowReleaseStatus==='recalled'):?><span class="badge bg-secondary">Recalled</span><?php else:?><span class="badge bg-light text-dark">Not submitted</span><?php endif;?></td></tr><?php endforeach;?>
        </tbody></table></div>
        <?php if($hasReleasedScores):?><div class="card-footer small text-muted no-print">Released student rows are locked. Ask the administrator to recall a result before correcting that student’s scores. Other rows remain editable.<div class="d-flex gap-2 justify-content-end mt-2"><button class="btn btn-outline-primary" name="save_action" value="draft"><i class="fas fa-save me-1"></i> Save draft</button><button class="btn btn-success" name="save_action" value="submit"><i class="fas fa-paper-plane me-1"></i> Submit for approval</button></div></div><?php else:?><div class="card-footer d-flex gap-2 justify-content-end no-print"><button class="btn btn-outline-primary" name="save_action" value="draft"><i class="fas fa-save me-1"></i> Save draft</button><button class="btn btn-success" name="save_action" value="submit"><i class="fas fa-paper-plane me-1"></i> Submit for approval</button></div><?php endif;?></form>
    <?php endif;?>
</main>
<?php include '../includes/footer.php'; ?>
