<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireLogin();
if (getUserRole() !== 'admin') { http_response_code(403); exit('Forbidden'); }

$db = getDBConnection();
$message = '';
if (!$db) { http_response_code(503); exit('Database unavailable.'); }
$activeTab = (($_GET['tab'] ?? 'students') === 'teachers') ? 'teachers' : 'students';
$candidateType = $activeTab === 'teachers' ? 'teacher' : 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'create_entrance_exam') {
            $title = trim((string)($_POST['title'] ?? ''));
            $classId = (int)($_POST['class_id'] ?? 0);
            $subjectIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['subject_ids'] ?? [])))));
            $teacherId = (int)($_POST['teacher_id'] ?? 0);
            $examCandidateType = (string)($_POST['candidate_type'] ?? 'student');
            $timeLimit = max(1, min(300, (int)($_POST['time_limit'] ?? 60)));
            if ($title === '' || !$classId || !$subjectIds || !$teacherId || !in_array($examCandidateType, ['student','teacher'], true)) throw new RuntimeException('Complete all entrance exam fields and select at least one subject.');
            $check = $db->prepare('SELECT 1 FROM subject_class_link WHERE class_id=? AND subject_id=? LIMIT 1');
            foreach ($subjectIds as $subjectId) {
                $check->bind_param('ii', $classId, $subjectId); $check->execute();
                if (!$check->get_result()->fetch_row()) throw new RuntimeException('Every selected subject must be linked to the target class.');
            }
            $db->begin_transaction();
            $userId = (int)getUserId();
            $description = 'Entrance examination';
            $stmt = $db->prepare('INSERT INTO exams (title, description, created_by) VALUES (?, ?, ?)');
            $stmt->bind_param('ssi', $title, $description, $userId); $stmt->execute(); $examId = $stmt->insert_id; $stmt->close();
            foreach ($subjectIds as $subjectId) {
                $stmt = $db->prepare('INSERT INTO exam_subjects (exam_id, subject_id, class_id, time_limit) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('iiii', $examId, $subjectId, $classId, $timeLimit); $stmt->execute(); $examSubjectId = $stmt->insert_id; $stmt->close();
                $stmt = $db->prepare('INSERT INTO entrance_exams (exam_subject_id, target_class_id, teacher_id, created_by, candidate_type) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('iiiis', $examSubjectId, $classId, $teacherId, $userId, $examCandidateType); $stmt->execute(); $stmt->close();
            }
            $db->commit(); $message = 'Entrance exam created with ' . count($subjectIds) . ' subject section(s) and assigned to the selected teacher.';
        } elseif ($action === 'create_candidate') {
            $name = trim((string)($_POST['full_name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $entranceExamId = (int)($_POST['entrance_exam_id'] ?? 0);
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || !$entranceExamId) throw new RuntimeException('Enter a name, valid email, password of at least 8 characters, and entrance exam.');
            $stmt = $db->prepare('SELECT ee.id,ee.target_class_id,ee.candidate_type FROM entrance_exams ee JOIN exam_subjects es ON es.id=ee.exam_subject_id WHERE es.exam_id=? ORDER BY es.id LIMIT 1'); $stmt->bind_param('i', $entranceExamId); $stmt->execute();
            $entrance = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$entrance) throw new RuntimeException('Selected entrance exam was not found.');
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->begin_transaction();
            $stmt = $db->prepare("INSERT INTO users (email,password,full_name,role) VALUES (?,?,?,'student')");
            $stmt->bind_param('sss', $email, $hash, $name); $stmt->execute(); $studentId = $stmt->insert_id; $stmt->close();
            $classId = (int)$entrance['target_class_id'];
            $rootEntranceId = (int)$entrance['id'];
            $appCandidateType = (string)$entrance['candidate_type'];
            $stmt = $db->prepare("INSERT INTO entrance_applications (user_id,target_class_id,entrance_exam_id,status,candidate_type) VALUES (?,?,?,'pending',?)");
            $stmt->bind_param('iiis', $studentId, $classId, $rootEntranceId, $appCandidateType); $stmt->execute(); $stmt->close();
            $db->commit(); $_SESSION['entrance_credential_flash'] = ['name'=>$name,'email'=>$email,'password'=>$password]; $message = 'Candidate account created. Share these login details with the candidate.';
        } elseif ($action === 'update_candidate') {
            $studentId = (int)($_POST['student_id'] ?? 0); $name = trim((string)($_POST['full_name'] ?? '')); $email = trim((string)($_POST['email'] ?? '')); $password = (string)($_POST['password'] ?? '');
            if (!$studentId || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || ($password !== '' && strlen($password) < 8)) throw new RuntimeException('Enter a valid name and email. A new password must be at least 8 characters.');
            $stmt = $db->prepare('SELECT id FROM entrance_applications WHERE user_id=?'); $stmt->bind_param('i', $studentId); $stmt->execute(); $exists = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$exists) throw new RuntimeException('Entrance candidate not found.');
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT); $stmt = $db->prepare("UPDATE users SET full_name=?,email=?,password=? WHERE id=? AND role='student'"); $stmt->bind_param('sssi', $name, $email, $hash, $studentId);
            } else { $stmt = $db->prepare("UPDATE users SET full_name=?,email=? WHERE id=? AND role='student'"); $stmt->bind_param('ssi', $name, $email, $studentId); }
            $stmt->execute(); $stmt->close();
            if ($password !== '') $_SESSION['entrance_credential_flash'] = ['name'=>$name,'email'=>$email,'password'=>$password];
            $message = $password !== '' ? 'Candidate details and password updated. Share the new login details.' : 'Candidate details updated.';
        } elseif ($action === 'review_application') {
            $applicationId = (int)($_POST['application_id'] ?? 0);
            $decision = (string)($_POST['decision'] ?? '');
            if (!$applicationId || !in_array($decision, ['admitted','rejected'], true)) throw new RuntimeException('Invalid admission decision.');
            $db->begin_transaction();
            $stmt = $db->prepare('SELECT user_id,target_class_id,candidate_type FROM entrance_applications WHERE id=? FOR UPDATE');
            $stmt->bind_param('i', $applicationId); $stmt->execute(); $app = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$app) throw new RuntimeException('Application not found.');
            if ($app['candidate_type'] !== 'student') throw new RuntimeException('Teacher interview candidates are not admitted into student classes. Review their exam score only.');
            $studentId = (int)$app['user_id']; $classId = (int)$app['target_class_id']; $adminId = (int)getUserId();
            if ($decision === 'admitted') {
                $stmt = $db->prepare("UPDATE users SET class_id=? WHERE id=? AND role='student'");
                $stmt->bind_param('ii', $classId, $studentId); $stmt->execute(); $stmt->close();
            } else {
                $stmt = $db->prepare("UPDATE users SET class_id=NULL WHERE id=? AND role='student'");
                $stmt->bind_param('i', $studentId); $stmt->execute(); $stmt->close();
            }
            $stmt = $db->prepare('UPDATE entrance_applications SET status=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?');
            $stmt->bind_param('sii', $decision, $adminId, $applicationId); $stmt->execute(); $stmt->close();
            $db->commit(); $message = $decision === 'admitted' ? 'Candidate admitted to the assigned class.' : 'Candidate application rejected.';
        } elseif ($action === 'publish_entrance_exam') {
            $examId = (int)($_POST['exam_id'] ?? 0);
            $publish = (int)($_POST['publish'] ?? 0) === 1;
            if (!$examId) throw new RuntimeException('Select a valid entrance exam.');
            $stmt = $db->prepare('SELECT e.id FROM exams e WHERE e.id=? AND EXISTS (SELECT 1 FROM entrance_exams ee JOIN exam_subjects es ON es.id=ee.exam_subject_id WHERE es.exam_id=e.id AND ee.candidate_type=?)');
            $stmt->bind_param('is', $examId, $candidateType); $stmt->execute(); $exam = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$exam) throw new RuntimeException('Entrance exam not found.');
            if ($publish) {
                $stmt = $db->prepare('SELECT COUNT(DISTINCT es.id) AS missing FROM exam_subjects es JOIN entrance_exams ee ON ee.exam_subject_id=es.id WHERE es.exam_id=? AND ee.candidate_type=? AND NOT EXISTS (SELECT 1 FROM questions q WHERE q.exam_subject_id=es.id)');
                $stmt->bind_param('is', $examId, $candidateType); $stmt->execute(); $missingSections = (int)$stmt->get_result()->fetch_assoc()['missing']; $stmt->close();
                if ($missingSections > 0) throw new RuntimeException('Add at least one question to every subject before publishing this entrance exam.');
            }
            $db->begin_transaction();
            $publishedAt = $publish ? 'NOW()' : 'NULL';
            $stmt = $db->prepare("UPDATE exam_subjects es JOIN entrance_exams ee ON ee.exam_subject_id=es.id SET es.is_published=?,es.published_at={$publishedAt} WHERE es.exam_id=? AND ee.candidate_type=?");
            $published = (int)$publish; $stmt->bind_param('iis', $published, $examId, $candidateType); $stmt->execute(); $stmt->close();
            $stmt = $db->prepare("UPDATE exams SET is_published=?,published_at={$publishedAt} WHERE id=?");
            $stmt->bind_param('ii', $published, $examId); $stmt->execute(); $stmt->close();
            $db->commit();
            $message = $publish ? 'Entrance exam published. Candidates can now start all subject sections.' : 'Entrance exam unpublished.';
        } elseif ($action === 'delete_entrance_exam') {
            $examId = (int)($_POST['exam_id'] ?? 0);
            if (!$examId) throw new RuntimeException('Select a valid entrance exam.');
            $stmt = $db->prepare("SELECT (SELECT COUNT(*) FROM entrance_applications ea JOIN entrance_exams ee ON ee.id=ea.entrance_exam_id JOIN exam_subjects es ON es.id=ee.exam_subject_id WHERE es.exam_id=e.id) AS application_count,(SELECT COUNT(*) FROM exam_attempts a JOIN exam_subjects es ON es.id=a.exam_subject_id WHERE es.exam_id=e.id) AS attempt_count FROM exams e WHERE e.id=? AND EXISTS (SELECT 1 FROM entrance_exams ee JOIN exam_subjects es ON es.id=ee.exam_subject_id WHERE es.exam_id=e.id)");
            $stmt->bind_param('i', $examId); $stmt->execute(); $usage = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$usage) throw new RuntimeException('Entrance exam not found.');
            if ((int)$usage['application_count'] > 0 || (int)$usage['attempt_count'] > 0) throw new RuntimeException('This exam has candidates or attempts. Delete eligible pending candidates first; exams with attempts are retained to preserve results.');
            $stmt = $db->prepare('DELETE FROM exams WHERE id=?'); $stmt->bind_param('i', $examId); $stmt->execute(); $stmt->close();
            $message = 'Unused entrance exam deleted.';
        } elseif ($action === 'delete_candidate') {
            $applicationId = (int)($_POST['application_id'] ?? 0);
            if (!$applicationId) throw new RuntimeException('Select a valid candidate.');
            $stmt = $db->prepare('SELECT ea.user_id FROM entrance_applications ea WHERE ea.id=?');
            $stmt->bind_param('i', $applicationId); $stmt->execute(); $candidate = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$candidate) throw new RuntimeException('Candidate application not found.');
            $studentId = (int)$candidate['user_id'];
            $db->begin_transaction();
            $stmt = $db->prepare("DELETE FROM users WHERE id=? AND role='student'"); $stmt->bind_param('i', $studentId); $stmt->execute(); $stmt->close();
            $db->commit();
            $message = 'Candidate account, application and exam records deleted.';
        }
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
        $message = $e instanceof mysqli_sql_exception && (int)$e->getCode() === 1062 ? 'That email address is already in use.' : $e->getMessage();
    }
}

$classes = $db->query('SELECT id,name FROM classes ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$teachers = $db->query("SELECT id,full_name,role FROM users WHERE role IN ('teacher','admin','admin_teacher') ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$subjects = $db->query('SELECT id,name FROM subjects ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$classSubjectIds = [];
$classSubjectResult = $db->query('SELECT class_id,subject_id FROM subject_class_link');
while ($classSubjectRow = $classSubjectResult->fetch_assoc()) $classSubjectIds[(int)$classSubjectRow['subject_id']][] = (int)$classSubjectRow['class_id'];
$entranceExams = $db->query("SELECT e.id AS exam_id, MIN(ee.id) AS id, e.title, c.name AS class_name, GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS subject_names, GROUP_CONCAT(DISTINCT u.full_name ORDER BY u.full_name SEPARATOR ', ') AS teacher_name, COUNT(DISTINCT es.id) AS subject_count, SUM((SELECT COUNT(*) FROM questions q WHERE q.exam_subject_id=es.id)) AS question_count, MIN(es.is_published) AS is_published,(SELECT COUNT(*) FROM entrance_applications a JOIN entrance_exams ar ON ar.id=a.entrance_exam_id JOIN exam_subjects ars ON ars.id=ar.exam_subject_id WHERE ars.exam_id=e.id) AS application_count,(SELECT COUNT(*) FROM exam_attempts a JOIN exam_subjects ats ON ats.id=a.exam_subject_id WHERE ats.exam_id=e.id) AS attempt_count FROM entrance_exams ee JOIN exam_subjects es ON es.id=ee.exam_subject_id JOIN exams e ON e.id=es.exam_id JOIN classes c ON c.id=ee.target_class_id JOIN subjects s ON s.id=es.subject_id JOIN users u ON u.id=ee.teacher_id WHERE ee.candidate_type='".$db->real_escape_string($candidateType)."' GROUP BY e.id,e.title,c.name ORDER BY MAX(ee.created_at) DESC")->fetch_all(MYSQLI_ASSOC);
$applications = $db->query("SELECT ea.id,ea.status,ea.candidate_type,ea.created_at,u.id AS user_id,u.full_name,u.email,c.name AS class_name,e.title AS exam_title,ea.entrance_exam_id,(SELECT COUNT(DISTINCT a.exam_subject_id) FROM exam_attempts a JOIN exam_subjects sx ON sx.id=a.exam_subject_id JOIN entrance_exams ex ON ex.exam_subject_id=sx.id WHERE a.student_id=u.id AND sx.exam_id=es.exam_id AND a.submitted_at IS NOT NULL) AS completed_subjects,(SELECT COUNT(*) FROM entrance_exams ex JOIN exam_subjects sx ON sx.id=ex.exam_subject_id WHERE sx.exam_id=es.exam_id) AS subject_count,(SELECT COUNT(*) FROM exam_attempts a WHERE a.student_id=u.id) AS attempt_count,(SELECT SUM(a.score) FROM exam_attempts a JOIN exam_subjects sx ON sx.id=a.exam_subject_id WHERE a.student_id=u.id AND sx.exam_id=es.exam_id AND a.submitted_at IS NOT NULL AND a.id=(SELECT MAX(a2.id) FROM exam_attempts a2 WHERE a2.student_id=u.id AND a2.exam_subject_id=a.exam_subject_id AND a2.submitted_at IS NOT NULL)) AS score_total FROM entrance_applications ea JOIN users u ON u.id=ea.user_id JOIN classes c ON c.id=ea.target_class_id JOIN entrance_exams ee ON ee.id=ea.entrance_exam_id JOIN exam_subjects es ON es.id=ee.exam_subject_id JOIN exams e ON e.id=es.exam_id WHERE ea.candidate_type='".$db->real_escape_string($candidateType)."' ORDER BY FIELD(ea.status,'pending','admitted','rejected'),ea.created_at DESC")->fetch_all(MYSQLI_ASSOC);
$pendingCount = count(array_filter($applications, fn($row) => $row['status'] === 'pending'));
$admittedCount = count(array_filter($applications, fn($row) => $row['status'] === 'admitted'));
$rejectedCount = count(array_filter($applications, fn($row) => $row['status'] === 'rejected'));
$credentialFlash = $_SESSION['entrance_credential_flash'] ?? null;
unset($_SESSION['entrance_credential_flash']);
?>
<?php include '../includes/header.php'; ?>
<style>
.admissions-page{--admissions-ink:var(--ink);--admissions-muted:var(--muted);--admissions-blue:var(--brand-navy);max-width:1440px;color:var(--admissions-ink);padding-bottom:2rem}
.admissions-hero{position:relative;overflow:hidden;padding:2rem 2.2rem;border:1px solid rgba(61,184,216,.25);border-radius:var(--radius-xl);background:var(--brand-navy);color:#fff;box-shadow:var(--shadow-soft)}
.admissions-hero h1{font-family:var(--font-display);font-size:clamp(1.6rem,3vw,2.1rem);font-weight:800;letter-spacing:-.035em}.admissions-hero p{color:rgba(255,255,255,.8)}
.admissions-hero .btn{position:relative;z-index:1;border:1px solid rgba(255,255,255,.48);color:#fff;border-radius:11px;background:rgba(255,255,255,.08);backdrop-filter:blur(8px)}.admissions-hero .btn:hover{background:#fff;color:var(--brand-navy)}
.admissions-tabs{gap:.5rem;padding:.35rem;border:1px solid var(--line);border-radius:12px;background:#fff;box-shadow:0 4px 12px rgba(26,36,104,.04)}.admissions-tabs .nav-link{padding:.62rem 1rem;border-radius:8px;color:var(--muted);font-weight:700;transition:background-color .15s ease,color .15s ease}.admissions-tabs .nav-link:hover{background:var(--brand-sky-soft);color:var(--brand-navy)}.admissions-tabs .nav-link.active{background:var(--brand-navy);color:#fff;box-shadow:none}
.admission-stat{height:100%;padding:1.15rem 1.3rem;border:1px solid var(--line);border-radius:12px;background:var(--surface);box-shadow:0 4px 12px rgba(26,36,104,.045)}.admission-stat span{display:block;color:var(--admissions-muted);font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.admission-stat strong{display:block;margin-top:.2rem;font-family:var(--font-display);font-size:1.7rem;line-height:1.2;color:var(--brand-navy)}.admission-stat.pending strong{color:#b87913}.admission-stat.admitted strong{color:var(--brand-green)}.admission-stat.rejected strong{color:var(--brand-red)}
.admissions-page .card{border:1px solid var(--line);border-radius:12px;background:var(--surface);box-shadow:0 5px 16px rgba(26,36,104,.05);overflow:hidden}.admissions-page .card-header{padding:1.1rem 1.35rem;background:#f7fbfe;border-bottom:1px solid var(--line)}.admissions-page .card-header h2{font-family:var(--font-display);font-weight:800;color:var(--brand-navy-deep)}.admissions-page .card-body{padding:1.4rem}
.admissions-page .form-label{font-size:.86rem;font-weight:700;color:var(--brand-navy-deep)}.admissions-page .form-control,.admissions-page .form-select{min-height:45px;border-color:#cfd8ee;border-radius:11px;background-color:#fff}.admissions-page .form-control:focus,.admissions-page .form-select:focus{border-color:var(--brand-sky);box-shadow:0 0 0 .22rem rgba(61,184,216,.18)}.admissions-page .btn{border-radius:10px;font-weight:700}.admissions-page .btn-primary{background:var(--brand-navy);border-color:var(--brand-navy)}.admissions-page .btn-primary:hover{background:var(--brand-navy-deep);border-color:var(--brand-navy-deep)}.admissions-page .btn-success{background:var(--brand-green);border-color:var(--brand-green)}.admissions-page .btn-outline-primary{color:var(--brand-navy);border-color:var(--brand-navy)}.admissions-page .btn-outline-primary:hover{color:#fff;background:var(--brand-navy)}
.admissions-page .table{margin:0}.admissions-page .table thead th{padding:.9rem 1rem;background:var(--brand-sky-soft);color:var(--brand-navy-deep);border-bottom:1px solid var(--line);font-size:.72rem;letter-spacing:.06em;text-transform:uppercase;white-space:nowrap}.admissions-page .table td{padding:.9rem 1rem;border-color:#e8ecf7;vertical-align:middle}.admissions-page .table tbody tr{transition:background .15s ease}.admissions-page .table tbody tr:hover{background:#f6fbfd}.admissions-page .badge{padding:.48em .72em;border-radius:20px;font-weight:700}
.candidate-actions{position:relative}.candidate-actions summary{cursor:pointer;list-style:none;padding:.5rem .78rem;border:1px solid #cfd8ee;border-radius:10px;color:var(--brand-navy);font-size:.84rem;font-weight:700;white-space:nowrap;transition:all .15s ease}.candidate-actions summary::-webkit-details-marker{display:none}.candidate-actions summary:hover,.candidate-actions[open] summary{border-color:var(--brand-sky);background:var(--brand-sky-soft)}.candidate-action-panel{position:relative;z-index:2;width:min(340px,80vw);margin-top:.4rem;padding:1rem;border:1px solid var(--line);border-radius:15px;background:#fff;box-shadow:var(--shadow-soft)}.candidate-action-panel .form-label{margin-bottom:.25rem;font-size:.75rem}
.subject-picker{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.55rem;padding:.85rem;border:1px solid var(--line);border-radius:14px;background:var(--brand-sky-soft);max-height:190px;overflow:auto}.subject-choice{display:flex;align-items:center;gap:.55rem;padding:.6rem .68rem;border:1px solid #dfe8f2;border-radius:10px;background:#fff;color:var(--muted);font-size:.86rem;cursor:pointer;transition:all .15s ease}.subject-choice:hover{border-color:var(--brand-sky)}.subject-choice[hidden]{display:none!important}.subject-choice:has(input:checked){border-color:var(--brand-sky);background:#e8f8fc;color:var(--brand-navy-deep);box-shadow:0 0 0 2px rgba(61,184,216,.1)}.subject-choice input{accent-color:var(--brand-navy);width:16px;height:16px}
@media(max-width:767px){.admissions-hero{padding:1.5rem;border-radius:17px}.admission-stat{padding:.9rem}.admissions-page .card-body{padding:1.1rem}.subject-picker{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
<div class="container py-4 admissions-page">
    <div class="admissions-hero d-flex justify-content-between align-items-center gap-3 mb-4"><div><div class="text-uppercase small fw-bold opacity-75 mb-2">Entrance workspace</div><h1 class="mb-1"><?php echo $candidateType==='teacher'?'Teacher interview exams':'Student admissions'; ?></h1><p class="mb-0"><?php echo $candidateType==='teacher'?'Manage teacher interview exams and review scores. Interview candidates are not enrolled in classes.':'Set up student entrance exams and review class admission decisions.'; ?></p></div><a class="btn" href="dashboard.php"><i class="fas fa-arrow-left me-2"></i>Admin dashboard</a></div>
    <ul class="nav nav-pills admissions-tabs mb-4"><li class="nav-item"><a class="nav-link <?php echo $activeTab==='students'?'active':''; ?>" href="entrance_admissions.php?tab=students"><i class="fas fa-user-graduate me-2"></i>Student</a></li><li class="nav-item"><a class="nav-link <?php echo $activeTab==='teachers'?'active':''; ?>" href="entrance_admissions.php?tab=teachers"><i class="fas fa-chalkboard-teacher me-2"></i>Teacher Interview</a></li></ul>
    <?php if ($message): ?><div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($credentialFlash): ?><div class="alert alert-success border-0 shadow-sm"><strong><i class="fas fa-key me-2"></i>Candidate login details</strong><div class="mt-2"><?php echo htmlspecialchars($credentialFlash['name']); ?> · <?php echo htmlspecialchars($credentialFlash['email']); ?> <span class="mx-2">|</span> Password: <code class="fs-6"><?php echo htmlspecialchars($credentialFlash['password']); ?></code></div><small>Copy these details now. The password is shown only once.</small></div><?php endif; ?>
    <div class="row g-3 mb-4"><div class="col-4"><div class="admission-stat pending"><span><?php echo $candidateType==='teacher'?'Interview applicants':'Awaiting decision'; ?></span><strong><?php echo $pendingCount; ?></strong></div></div><div class="col-4"><div class="admission-stat admitted"><span><?php echo $candidateType==='teacher'?'Exams completed':'Admitted'; ?></span><strong><?php echo $candidateType==='teacher'?count(array_filter($applications,fn($row)=>(int)$row['completed_subjects']>=(int)$row['subject_count'])):$admittedCount; ?></strong></div></div><div class="col-4"><div class="admission-stat rejected"><span><?php echo $candidateType==='teacher'?'Exams not started':'Not admitted'; ?></span><strong><?php echo $candidateType==='teacher'?count(array_filter($applications,fn($row)=>(int)$row['completed_subjects']===0)):$rejectedCount; ?></strong></div></div></div>
    <div class="row g-4">
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h2 class="h5 mb-0">Create <?php echo $candidateType==='teacher'?'teacher interview exam':'student entrance exam'; ?></h2></div><div class="card-body"><form method="post"><input type="hidden" name="action" value="create_entrance_exam"><input type="hidden" name="candidate_type" value="<?php echo htmlspecialchars($candidateType); ?>"><label class="form-label">Exam title</label><input class="form-control mb-3" name="title" required><div class="mb-3"><label class="form-label"><?php echo $candidateType==='teacher'?'Scoring class':'Target class'; ?></label><select class="form-select" name="class_id" id="entrance_target_class" required><option value="">Select class</option><?php foreach($classes as $r): ?><option value="<?php echo (int)$r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option><?php endforeach; ?></select><?php if($candidateType==='teacher'): ?><small class="text-muted">Used for exam scoring only. Teacher interview candidates will not be enrolled in this class.</small><?php endif; ?></div><label class="form-label">Subjects included</label><div class="subject-picker mb-1" id="entrance_subject_picker"><?php foreach($subjects as $r): ?><label class="subject-choice" data-class-ids="<?php echo htmlspecialchars(implode(',', $classSubjectIds[(int)$r['id']] ?? [])); ?>" hidden><input type="checkbox" name="subject_ids[]" value="<?php echo (int)$r['id']; ?>"><span><?php echo htmlspecialchars($r['name']); ?></span></label><?php endforeach; ?></div><div id="entrance_subject_hint" class="form-text mb-3">Select a class to see its assigned subjects.</div><div class="row"><div class="col-md-8 mb-3"><label class="form-label">Assigned teacher</label><select class="form-select" name="teacher_id" required><option value="">Select teacher or admin</option><?php foreach($teachers as $r): ?><option value="<?php echo (int)$r['id']; ?>"><?php echo htmlspecialchars($r['full_name'].' · '.ucfirst($r['role'])); ?></option><?php endforeach; ?></select></div><div class="col-md-4 mb-3"><label class="form-label">Minutes per subject</label><input class="form-control" type="number" name="time_limit" min="1" max="300" value="60" required></div></div><button class="btn btn-primary"><i class="fas fa-plus me-2"></i>Create grouped exam</button></form></div></div></div>
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h2 class="h5 mb-0">Create <?php echo $candidateType==='teacher'?'interview candidate':'student candidate'; ?> login</h2></div><div class="card-body"><form method="post"><input type="hidden" name="action" value="create_candidate"><label class="form-label">Candidate name</label><input class="form-control mb-3" name="full_name" required><label class="form-label">Email/login</label><input class="form-control mb-3" name="email" type="email" required><label class="form-label">Password to give candidate</label><div class="input-group mb-3"><input class="form-control entrance-password" name="password" minlength="8" required><button class="btn btn-outline-primary generate-entrance-password" type="button">Generate</button></div><label class="form-label">Entrance exam</label><select class="form-select mb-3" name="entrance_exam_id" required><option value="">Select exam</option><?php foreach($entranceExams as $r): ?><option value="<?php echo (int)$r['exam_id']; ?>"><?php echo htmlspecialchars($r['title'].' - '.$r['class_name'].' ('.$r['subject_count'].' subjects)'); ?></option><?php endforeach; ?></select><button class="btn btn-success" <?php echo !$entranceExams ? 'disabled' : ''; ?>>Create candidate</button></form><small class="text-muted d-block mt-2">Generated passwords use the normal student format: Famaiis followed by three digits. <?php echo $candidateType==='teacher'?'Interview candidates take the exam and view scores; no class admission is needed.':'Candidates remain outside the class roster until admitted.'; ?></small></div></div></div>
    </div>
    <div class="card mt-4"><div class="card-header"><h2 class="h5 mb-0"><?php echo $candidateType==='teacher'?'Teacher interview exams':'Student entrance exams'; ?></h2></div><div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Exam</th><th><?php echo $candidateType==='teacher'?'Scoring context and subjects':'Target class and subjects'; ?></th><th>Assigned staff</th><th>Questions</th><th>Published</th><th>Actions</th></tr></thead><tbody><?php foreach($entranceExams as $r): ?><tr><td><?php echo htmlspecialchars($r['title']); ?></td><td><?php echo htmlspecialchars($r['class_name'].' / '.$r['subject_names']); ?><br><small><?php echo (int)$r['subject_count']; ?> subject sections</small></td><td><?php echo htmlspecialchars($r['teacher_name']); ?></td><td><?php echo (int)$r['question_count']; ?></td><td><span class="badge bg-<?php echo $r['is_published']?'success':'secondary'; ?>"><?php echo $r['is_published']?'Published':'Draft'; ?></span></td><td><form method="post" class="d-inline me-2"><input type="hidden" name="action" value="publish_entrance_exam"><input type="hidden" name="exam_id" value="<?php echo (int)$r['exam_id']; ?>"><input type="hidden" name="publish" value="<?php echo $r['is_published']?0:1; ?>"><button class="btn btn-sm btn-outline-<?php echo $r['is_published']?'warning':'success'; ?>"><?php echo $r['is_published']?'Unpublish':'Publish'; ?></button></form><?php if((int)$r['application_count']===0&&(int)$r['attempt_count']===0): ?><form method="post" onsubmit="return confirm('Delete this unused entrance exam and its subject sections?');"><input type="hidden" name="action" value="delete_entrance_exam"><input type="hidden" name="exam_id" value="<?php echo (int)$r['exam_id']; ?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>Delete</button></form><?php else: ?><button class="btn btn-sm btn-outline-secondary" disabled title="Exam has candidates or attempts">In use</button><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$entranceExams): ?><tr><td colspan="6" class="text-muted">No exams created yet.</td></tr><?php endif; ?></tbody></table></div></div>
    <div class="card mt-4"><div class="card-header"><h2 class="h5 mb-0"><?php echo $candidateType==='teacher'?'Teacher interview applicants':'Candidate applications'; ?></h2></div><div class="table-responsive"><table class="table table-striped align-middle mb-0"><thead><tr><th>Candidate</th><th><?php echo $candidateType==='teacher'?'Interview exam':'Entrance exam'; ?></th><th>Progress</th><?php if($candidateType==='teacher'): ?><th>Total score</th><?php endif; ?><th><?php echo $candidateType==='teacher'?'Interview status':'Admission status'; ?></th><th>Actions</th></tr></thead><tbody><?php foreach($applications as $r): ?><tr><td><?php echo htmlspecialchars($r['full_name'].' · '.$r['email']); ?><br><small class="text-muted"><?php echo $candidateType==='teacher'?'Interview candidate · not enrolled in a class':'Target class: '.htmlspecialchars($r['class_name']); ?></small></td><td><?php echo htmlspecialchars($r['exam_title']); ?></td><td><?php echo (int)$r['completed_subjects']; ?> / <?php echo (int)$r['subject_count']; ?> subjects submitted</td><?php if($candidateType==='teacher'): ?><td><strong><?php echo $r['score_total']===null?'-':htmlspecialchars(number_format((float)$r['score_total'],2)); ?></strong></td><?php endif; ?><td><?php if($candidateType==='teacher'): ?><span class="badge bg-<?php echo (int)$r['completed_subjects']>=(int)$r['subject_count']?'success':'info text-dark'; ?>"><?php echo (int)$r['completed_subjects']>=(int)$r['subject_count']?'Exam completed':'Interview applicant'; ?></span><?php else: ?><span class="badge bg-<?php echo $r['status']==='admitted'?'success':($r['status']==='rejected'?'danger':'warning text-dark'); ?>"><?php echo htmlspecialchars(ucfirst($r['status'])); ?></span><?php endif; ?></td><td><details class="candidate-actions"><summary>Manage</summary><div class="candidate-action-panel"><form method="post" class="mb-2"><input type="hidden" name="action" value="update_candidate"><input type="hidden" name="student_id" value="<?php echo (int)$r['user_id']; ?>"><label class="form-label">Name</label><input class="form-control form-control-sm mb-2" name="full_name" value="<?php echo htmlspecialchars($r['full_name']); ?>" required><label class="form-label">Email</label><input class="form-control form-control-sm mb-2" type="email" name="email" value="<?php echo htmlspecialchars($r['email']); ?>" required><label class="form-label">Replace password (optional)</label><div class="input-group input-group-sm mb-2"><input class="form-control entrance-password" name="password" minlength="8"><button class="btn btn-outline-primary generate-entrance-password" type="button">Generate</button></div><button class="btn btn-sm btn-primary">Save candidate</button></form><?php if($candidateType==='student'&&$r['status']==='pending'): ?><form method="post" class="d-flex gap-2 mb-2"><input type="hidden" name="action" value="review_application"><input type="hidden" name="application_id" value="<?php echo (int)$r['id']; ?>"><button class="btn btn-sm btn-success" name="decision" value="admitted">Admit</button><button class="btn btn-sm btn-outline-danger" name="decision" value="rejected">Reject</button></form><?php endif; ?><form method="post" onsubmit="return confirm('Delete this candidate account, application, and all exam records?');"><input type="hidden" name="action" value="delete_candidate"><input type="hidden" name="application_id" value="<?php echo (int)$r['id']; ?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>Delete candidate</button></form></div></details></td></tr><?php endforeach; ?><?php if(!$applications): ?><tr><td colspan="<?php echo $candidateType==='teacher'?6:5; ?>" class="text-muted">No applicants yet.</td></tr><?php endif; ?></tbody></table></div></div>
</div>
<script>
document.querySelectorAll('.generate-entrance-password').forEach(function(button){button.addEventListener('click',function(){var input=button.closest('.input-group').querySelector('.entrance-password');input.value='Famaiis'+(100+crypto.getRandomValues(new Uint32Array(1))[0]%900);input.type='text';input.focus();});});
var entranceClass=document.getElementById('entrance_target_class');
var entrancePicker=document.getElementById('entrance_subject_picker');
var entranceHint=document.getElementById('entrance_subject_hint');
function filterEntranceSubjects(){
    if(!entranceClass||!entrancePicker)return;
    var selectedClass=entranceClass.value;var visibleCount=0;
    entrancePicker.querySelectorAll('.subject-choice').forEach(function(choice){
        var classIds=(choice.dataset.classIds||'').split(',');
        var show=!!selectedClass&&classIds.indexOf(selectedClass)!==-1;
        choice.hidden=!show;
        if(!show)choice.querySelector('input').checked=false;
        if(show)visibleCount++;
    });
    entranceHint.textContent=!selectedClass?'Select a class to see its assigned subjects.':(visibleCount?'Select one or more subjects for this class.':'No subjects are assigned to this class yet.');
}
if(entranceClass)entranceClass.addEventListener('change',filterEntranceSubjects);
filterEntranceSubjects();
</script>
<?php include '../includes/footer.php'; ?>
