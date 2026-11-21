<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/term_results.php';
requireLogin();
if (!in_array(getUserRole(), ['teacher', 'admin'], true)) { header('Location: ../login.php'); exit(); }

$mysqli = getDBConnection();
if (!$mysqli) die('Database connection failed.');
$userId = (int)getUserId();
$isAdmin = getUserRole() === 'admin';
$mode = in_array(($_REQUEST['mode'] ?? 'attendance'), ['remarks', 'ratings', 'report_remarks'], true) ? (string)$_REQUEST['mode'] : 'attendance';
$selectedClass = (int)($_REQUEST['class_id'] ?? 0);
$selectedDate = (string)($_REQUEST['attendance_date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) || strtotime($selectedDate) === false) $selectedDate = date('Y-m-d');
$selectedSession = (int)($_REQUEST['session_id'] ?? 0);
$selectedTerm = (int)($_REQUEST['term'] ?? 1);
if ($selectedTerm < 1 || $selectedTerm > 3) $selectedTerm = 1;
$message = '';
$classes = [];
$sessions = termResultsSessionRows($mysqli);
$categories = [];
$categoryResult = $mysqli->query("SELECT id, domain, label FROM class_report_categories WHERE is_active=1 ORDER BY FIELD(domain,'affective','psychomotor','locomotor'), sort_order, label");
while ($row = $categoryResult->fetch_assoc()) $categories[] = $row;
if (!$selectedSession) foreach ($sessions as $session) if ((int)$session['is_current'] === 1) $selectedSession = (int)$session['id'];
$schoolDaysOpen = 0;
if ($selectedSession) {
    $stmt = $mysqli->prepare('SELECT school_days_open FROM term_report_settings WHERE session_id=? AND term=?');
    $stmt->bind_param('ii', $selectedSession, $selectedTerm);
    $stmt->execute();
    $daysRow = $stmt->get_result()->fetch_assoc();
    if ($daysRow) $schoolDaysOpen = (int)$daysRow['school_days_open'];
    $stmt->close();
}

if ($isAdmin) {
    $classResult = $mysqli->query('SELECT id, name FROM classes ORDER BY name');
} else {
    $stmt = $mysqli->prepare('SELECT DISTINCT c.id, c.name FROM classes c LEFT JOIN class_teacher_assignments cta ON cta.class_id=c.id LEFT JOIN class_report_signatories crs ON crs.class_id=c.id WHERE cta.teacher_id=? OR crs.staff_id=? ORDER BY c.name');
    $stmt->bind_param('ii', $userId, $userId);
    $stmt->execute();
    $classResult = $stmt->get_result();
}
while ($row = $classResult->fetch_assoc()) $classes[] = $row;
if (!$isAdmin) $stmt->close();
$authorizedClassIds = array_map(static fn($class) => (int)$class['id'], $classes);
if (!$selectedClass && $classes) $selectedClass = $authorizedClassIds[0];
if (!$isAdmin && $selectedClass && !in_array($selectedClass, $authorizedClassIds, true)) $selectedClass = $authorizedClassIds[0] ?? 0;
$canManageClass = $isAdmin || in_array($selectedClass, $authorizedClassIds, true);
$isClassTeacher = $isAdmin;
if (!$isAdmin && $selectedClass) {
    $stmt = $mysqli->prepare('SELECT 1 FROM class_teacher_assignments WHERE class_id=? AND teacher_id=? LIMIT 1');
    $stmt->bind_param('ii', $selectedClass, $userId);
    $stmt->execute();
    $isClassTeacher = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
}
$remarkPositionOptions = [];
if ($selectedClass) {
    if ($isAdmin) {
        $stmt = $mysqli->prepare('SELECT position, title FROM class_report_signatories WHERE class_id=? ORDER BY position');
        $stmt->bind_param('i', $selectedClass);
    } else {
        $stmt = $mysqli->prepare('SELECT position, title FROM class_report_signatories WHERE class_id=? AND staff_id=? ORDER BY position');
        $stmt->bind_param('ii', $selectedClass, $userId);
    }
    $stmt->execute();
    $positions = $stmt->get_result();
    while ($position = $positions->fetch_assoc()) $remarkPositionOptions[$position['position']] = $position['title'];
    $stmt->close();
}
if ($isAdmin && !$remarkPositionOptions) $remarkPositionOptions = ['head_teacher' => 'Head Teacher', 'principal' => 'Principal'];
if (!$isAdmin && !$isClassTeacher && $remarkPositionOptions) $mode = 'report_remarks';
$selectedRemarkPosition = (string)($_REQUEST['remark_position'] ?? array_key_first($remarkPositionOptions) ?? '');
if (!isset($remarkPositionOptions[$selectedRemarkPosition])) $selectedRemarkPosition = (string)(array_key_first($remarkPositionOptions) ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!termResultsVerifyCsrf($_POST['csrf_token'] ?? '')) {
        $message = 'Your session expired. Refresh and try again.';
    } elseif (!$canManageClass || ($mode === 'report_remarks' ? !isset($remarkPositionOptions[$selectedRemarkPosition]) : !$isClassTeacher)) {
        $message = $mode === 'report_remarks' ? 'You are not assigned as a report signatory for this class.' : 'You are not assigned as a class teacher for this class.';
    } elseif ($mode === 'attendance' && $selectedDate > date('Y-m-d')) {
        $message = 'Attendance cannot be recorded for a future date.';
    } else {
        $transactionActive = false;
        try {
            $mysqli->begin_transaction();
            $transactionActive = true;
            if ($mode === 'attendance') {
                $studentStmt = $mysqli->prepare("SELECT id FROM users WHERE role='student' AND class_id=?");
                $studentStmt->bind_param('i', $selectedClass);
                $studentStmt->execute();
                $studentRows = $studentStmt->get_result();
                $upsert = $mysqli->prepare('INSERT INTO class_attendance (class_id, student_id, attendance_date, status, marked_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status=VALUES(status), marked_by=VALUES(marked_by)');
                $allowedStatuses = ['present', 'absent', 'late', 'excused'];
                $savedCount = 0;
                while ($student = $studentRows->fetch_assoc()) {
                    $studentId = (int)$student['id'];
                    $status = (string)($_POST['attendance'][$studentId] ?? '');
                    if (!in_array($status, $allowedStatuses, true)) throw new RuntimeException('Select an attendance status for every student.');
                    $upsert->bind_param('iissi', $selectedClass, $studentId, $selectedDate, $status, $userId);
                    $upsert->execute();
                    $savedCount++;
                }
                $upsert->close();
                $studentStmt->close();
                if (!$savedCount) throw new RuntimeException('There are no students enrolled in this class.');
                $message = "Attendance saved for {$savedCount} students on " . date('M j, Y', strtotime($selectedDate)) . '.';
            } elseif ($mode === 'remarks') {
                if (!$selectedSession || !in_array($selectedSession, array_map(static fn($session) => (int)$session['id'], $sessions), true)) throw new RuntimeException('Choose an academic session.');
                $studentStmt = $mysqli->prepare("SELECT id FROM users WHERE role='student' AND class_id=?");
                $studentStmt->bind_param('i', $selectedClass);
                $studentStmt->execute();
                $studentRows = $studentStmt->get_result();
                $upsert = $mysqli->prepare('INSERT INTO class_term_remarks (class_id, session_id, term, student_id, remark, updated_by) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE remark=VALUES(remark), updated_by=VALUES(updated_by)');
                $savedCount = 0;
                while ($student = $studentRows->fetch_assoc()) {
                    $studentId = (int)$student['id'];
                    $remark = trim((string)($_POST['remarks'][$studentId] ?? ''));
                    if (mb_strlen($remark) > 2000) throw new RuntimeException('Each remark must be 2,000 characters or fewer.');
                    $upsert->bind_param('iiiisi', $selectedClass, $selectedSession, $selectedTerm, $studentId, $remark, $userId);
                    $upsert->execute();
                    $savedCount++;
                }
                $upsert->close();
                $studentStmt->close();
                if (!$savedCount) throw new RuntimeException('There are no students enrolled in this class.');
                $message = "Class remarks saved for {$savedCount} students.";
            } elseif ($mode === 'report_remarks') {
                if (!$selectedSession || !in_array($selectedSession, array_map(static fn($session) => (int)$session['id'], $sessions), true)) throw new RuntimeException('Choose an academic session.');
                $studentStmt = $mysqli->prepare("SELECT id FROM users WHERE role='student' AND class_id=?");
                $studentStmt->bind_param('i', $selectedClass);
                $studentStmt->execute();
                $studentRows = $studentStmt->get_result();
                $upsert = $mysqli->prepare('INSERT INTO class_report_remarks (class_id, session_id, term, student_id, position, remark, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE remark=VALUES(remark), updated_by=VALUES(updated_by)');
                $savedCount = 0;
                while ($student = $studentRows->fetch_assoc()) {
                    $studentId = (int)$student['id'];
                    $remark = trim((string)($_POST['authority_remarks'][$studentId] ?? ''));
                    if (mb_strlen($remark) > 2000) throw new RuntimeException('Each report remark must be 2,000 characters or fewer.');
                    $upsert->bind_param('iiiissi', $selectedClass, $selectedSession, $selectedTerm, $studentId, $selectedRemarkPosition, $remark, $userId);
                    $upsert->execute();
                    $savedCount++;
                }
                $upsert->close();
                $studentStmt->close();
                if (!$savedCount) throw new RuntimeException('There are no students enrolled in this class.');
                $message = "{$remarkPositionOptions[$selectedRemarkPosition]} remarks saved for {$savedCount} students.";
            } else {
                if (!$selectedSession || !in_array($selectedSession, array_map(static fn($session) => (int)$session['id'], $sessions), true)) throw new RuntimeException('Choose an academic session.');
                if ($schoolDaysOpen < 1) throw new RuntimeException('Ask the administrator to set the number of school days opened for this term first.');
                if (!$categories) throw new RuntimeException('Ask the administrator to add report-card rating fields first.');
                if (isset($_POST['student_id'])) {
                    $studentId = (int)$_POST['student_id'];
                    $studentCheck = $mysqli->prepare("SELECT id FROM users WHERE id=? AND role='student' AND class_id=?");
                    $studentCheck->bind_param('ii', $studentId, $selectedClass);
                    $studentCheck->execute();
                    $validStudent = (bool)$studentCheck->get_result()->fetch_row();
                    $studentCheck->close();
                    if (!$validStudent) throw new RuntimeException('That student is not enrolled in the selected class.');
                    $daysPresent = filter_var($_POST['days_present'] ?? null, FILTER_VALIDATE_INT);
                    if ($daysPresent === false || $daysPresent < 0 || $daysPresent > $schoolDaysOpen) throw new RuntimeException('Days present must be between 0 and the configured days school opened.');
                    $summaryStmt = $mysqli->prepare('INSERT INTO class_student_attendance_summary (class_id, session_id, term, student_id, days_present, updated_by) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE days_present=VALUES(days_present), updated_by=VALUES(updated_by)');
                    $summaryStmt->bind_param('iiiiii', $selectedClass, $selectedSession, $selectedTerm, $studentId, $daysPresent, $userId);
                    $summaryStmt->execute();
                    $summaryStmt->close();
                    $ratingStmt = $mysqli->prepare('INSERT INTO class_student_ratings (category_id, class_id, session_id, term, student_id, score, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE score=VALUES(score), updated_by=VALUES(updated_by)');
                    foreach ($categories as $category) {
                        $categoryId = (int)$category['id'];
                        $score = filter_var($_POST['ratings'][$categoryId] ?? null, FILTER_VALIDATE_INT);
                        if ($score === false || $score < 1 || $score > 5) throw new RuntimeException('Choose a 1–5 rating for every skill before saving.');
                        $ratingStmt->bind_param('iiiiiii', $categoryId, $selectedClass, $selectedSession, $selectedTerm, $studentId, $score, $userId);
                        $ratingStmt->execute();
                    }
                    $ratingStmt->close();
                    $studentNameStmt = $mysqli->prepare('SELECT full_name FROM users WHERE id=?');
                    $studentNameStmt->bind_param('i', $studentId);
                    $studentNameStmt->execute();
                    $studentName = (string)($studentNameStmt->get_result()->fetch_assoc()['full_name'] ?? 'Student');
                    $studentNameStmt->close();
                    $message = 'Ratings saved for ' . $studentName . '.';
                } else {
                $studentStmt = $mysqli->prepare("SELECT id FROM users WHERE role='student' AND class_id=?");
                $studentStmt->bind_param('i', $selectedClass);
                $studentStmt->execute();
                $studentRows = $studentStmt->get_result();
                $summaryStmt = $mysqli->prepare('INSERT INTO class_student_attendance_summary (class_id, session_id, term, student_id, days_present, updated_by) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE days_present=VALUES(days_present), updated_by=VALUES(updated_by)');
                $ratingStmt = $mysqli->prepare('INSERT INTO class_student_ratings (category_id, class_id, session_id, term, student_id, score, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE score=VALUES(score), updated_by=VALUES(updated_by)');
                $savedCount = 0;
                while ($student = $studentRows->fetch_assoc()) {
                    $studentId = (int)$student['id'];
                    $daysPresent = filter_var($_POST['days_present'][$studentId] ?? null, FILTER_VALIDATE_INT);
                    if ($daysPresent === false || $daysPresent < 0 || $daysPresent > $schoolDaysOpen) throw new RuntimeException('Days present must be between 0 and the configured days school opened.');
                    $summaryStmt->bind_param('iiiiii', $selectedClass, $selectedSession, $selectedTerm, $studentId, $daysPresent, $userId);
                    $summaryStmt->execute();
                    foreach ($categories as $category) {
                        $categoryId = (int)$category['id'];
                        $score = filter_var($_POST['ratings'][$studentId][$categoryId] ?? null, FILTER_VALIDATE_INT);
                        if ($score === false || $score < 1 || $score > 5) throw new RuntimeException('Choose a 1–5 rating for every configured skill and student.');
                        $ratingStmt->bind_param('iiiiiii', $categoryId, $selectedClass, $selectedSession, $selectedTerm, $studentId, $score, $userId);
                        $ratingStmt->execute();
                    }
                    $savedCount++;
                }
                $summaryStmt->close();
                $ratingStmt->close();
                $studentStmt->close();
                if (!$savedCount) throw new RuntimeException('There are no students enrolled in this class.');
                $message = "Attendance totals and skill ratings saved for {$savedCount} students.";
                }
            }
            $mysqli->commit();
            $transactionActive = false;
        } catch (Throwable $e) {
            if ($transactionActive) $mysqli->rollback();
            $message = $e instanceof mysqli_sql_exception ? 'Could not save. Confirm the class attendance migration has been installed.' : $e->getMessage();
        }
    }
}

$students = [];
$savedAttendance = [];
$savedRemarks = [];
$savedDaysPresent = [];
$savedRatings = [];
$savedAuthorityRemarks = [];
if ($canManageClass && $selectedClass) {
    $stmt = $mysqli->prepare("SELECT id, full_name, admission_number FROM users WHERE role='student' AND class_id=? ORDER BY full_name");
    $stmt->bind_param('i', $selectedClass);
    $stmt->execute();
    $rows = $stmt->get_result();
    while ($row = $rows->fetch_assoc()) $students[] = $row;
    $stmt->close();
    if ($mode === 'attendance') {
        $stmt = $mysqli->prepare('SELECT student_id, status FROM class_attendance WHERE class_id=? AND attendance_date=?');
        $stmt->bind_param('is', $selectedClass, $selectedDate);
        $stmt->execute();
        $rows = $stmt->get_result();
        while ($row = $rows->fetch_assoc()) $savedAttendance[(int)$row['student_id']] = $row['status'];
        $stmt->close();
    } elseif ($selectedSession && $mode === 'remarks') {
        $stmt = $mysqli->prepare('SELECT student_id, remark FROM class_term_remarks WHERE class_id=? AND session_id=? AND term=?');
        $stmt->bind_param('iii', $selectedClass, $selectedSession, $selectedTerm);
        $stmt->execute();
        $rows = $stmt->get_result();
        while ($row = $rows->fetch_assoc()) $savedRemarks[(int)$row['student_id']] = $row['remark'];
        $stmt->close();
    } elseif ($selectedSession && $mode === 'report_remarks' && $selectedRemarkPosition !== '') {
        $stmt = $mysqli->prepare('SELECT student_id, remark FROM class_report_remarks WHERE class_id=? AND session_id=? AND term=? AND position=?');
        $stmt->bind_param('iiis', $selectedClass, $selectedSession, $selectedTerm, $selectedRemarkPosition);
        $stmt->execute();
        $rows = $stmt->get_result();
        while ($row = $rows->fetch_assoc()) $savedAuthorityRemarks[(int)$row['student_id']] = $row['remark'];
        $stmt->close();
    } elseif ($selectedSession && $mode === 'ratings') {
        $stmt = $mysqli->prepare('SELECT student_id, days_present FROM class_student_attendance_summary WHERE class_id=? AND session_id=? AND term=?');
        $stmt->bind_param('iii', $selectedClass, $selectedSession, $selectedTerm);
        $stmt->execute();
        $rows = $stmt->get_result();
        while ($row = $rows->fetch_assoc()) $savedDaysPresent[(int)$row['student_id']] = (int)$row['days_present'];
        $stmt->close();
        $stmt = $mysqli->prepare('SELECT student_id, category_id, score FROM class_student_ratings WHERE class_id=? AND session_id=? AND term=?');
        $stmt->bind_param('iii', $selectedClass, $selectedSession, $selectedTerm);
        $stmt->execute();
        $rows = $stmt->get_result();
        while ($row = $rows->fetch_assoc()) $savedRatings[(int)$row['student_id']][(int)$row['category_id']] = (int)$row['score'];
        $stmt->close();
    }
}
include '../includes/header.php';
?>
<style>
.rating-student-list .list-group-item { font-size: .9rem; }
.rating-entry-modal .modal-dialog { height: calc(100vh - 1rem); height: calc(100dvh - 1rem); max-height: calc(100vh - 1rem); max-height: calc(100dvh - 1rem); margin: .5rem auto; }
.rating-entry-modal .modal-content { display: flex; max-height: 100%; overflow: hidden; }
.rating-entry-modal .modal-header, .rating-entry-modal .modal-footer { flex: 0 0 auto; padding: .65rem .9rem; }
.rating-entry-modal .modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto !important; overscroll-behavior: contain; padding: .75rem .9rem; font-size: .84rem; }
.rating-entry-modal .modal-title { font-size: 1rem; }
.rating-entry-modal h3 { font-size: .86rem; }
.rating-entry-modal .form-label, .rating-entry-modal .fw-semibold { font-size: .82rem; }
.rating-entry-modal .form-control { min-height: 36px; padding: .35rem .55rem; font-size: .85rem; }
.rating-entry-modal .skill-rating-row { padding: .5rem 0 !important; }
.rating-entry-modal .skill-rating-row .btn { width: 36px !important; height: 36px !important; padding: .35rem !important; font-size: .82rem; }
.rating-entry-modal .modal-footer .btn { padding: .38rem .65rem; font-size: .82rem; }
</style>
<main class="container-fluid py-4">
    <header class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div><div class="text-uppercase small fw-bold text-primary">Class duties and report remarks</div><h1 class="h3 mb-1">Class Duties</h1><p class="text-muted mb-0">Record attendance, class teacher remarks, and report-authority comments.</p></div>
        <a class="btn btn-outline-secondary" href="dashboard.php"><i class="fas fa-arrow-left me-1"></i> Teacher dashboard</a>
    </header>
    <?php if ($message): ?><div class="alert alert-info" role="status"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if (!$classes): ?><div class="alert alert-warning">No classes are assigned to you yet. Ask an administrator to assign you as a class teacher.</div>
    <?php else: ?>
        <nav class="nav nav-tabs mb-3">
            <?php if ($isClassTeacher): ?><a class="nav-link <?php echo $mode === 'attendance' ? 'active' : ''; ?>" href="?mode=attendance&amp;class_id=<?php echo $selectedClass; ?>"><i class="fas fa-calendar-check me-1"></i> Daily attendance</a>
            <a class="nav-link <?php echo $mode === 'ratings' ? 'active' : ''; ?>" href="?mode=ratings&amp;class_id=<?php echo $selectedClass; ?>"><i class="fas fa-star-half-alt me-1"></i> Term attendance &amp; skills</a>
            <a class="nav-link <?php echo $mode === 'remarks' ? 'active' : ''; ?>" href="?mode=remarks&amp;class_id=<?php echo $selectedClass; ?>"><i class="fas fa-comment-alt me-1"></i> Student remarks</a><?php endif; ?>
            <?php if ($remarkPositionOptions): ?><a class="nav-link <?php echo $mode === 'report_remarks' ? 'active' : ''; ?>" href="?mode=report_remarks&amp;class_id=<?php echo $selectedClass; ?>"><i class="fas fa-pen-nib me-1"></i> Report authority remarks</a><?php endif; ?>
        </nav>
        <form method="get" class="card card-body mb-3"><input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>"><div class="row g-3 align-items-end">
            <div class="col-md-4"><label class="form-label" for="class_id">Class</label><select class="form-select" id="class_id" name="class_id" required><?php foreach ($classes as $class): ?><option value="<?php echo (int)$class['id']; ?>" <?php echo (int)$class['id'] === $selectedClass ? 'selected' : ''; ?>><?php echo htmlspecialchars($class['name']); ?></option><?php endforeach; ?></select></div>
            <?php if ($mode === 'attendance'): ?>
                <div class="col-md-3"><label class="form-label" for="attendance_date">Attendance date</label><input class="form-control" type="date" id="attendance_date" name="attendance_date" value="<?php echo htmlspecialchars($selectedDate); ?>" max="<?php echo date('Y-m-d'); ?>" required></div>
            <?php else: ?>
                <div class="col-md-3"><label class="form-label" for="session_id">Academic session</label><select class="form-select" id="session_id" name="session_id" required><?php foreach ($sessions as $session): ?><option value="<?php echo (int)$session['id']; ?>" <?php echo (int)$session['id'] === $selectedSession ? 'selected' : ''; ?>><?php echo htmlspecialchars($session['name']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label" for="term">Term</label><select class="form-select" id="term" name="term"><?php for ($termOption=1; $termOption<=3; $termOption++): ?><option value="<?php echo $termOption; ?>" <?php echo $termOption === $selectedTerm ? 'selected' : ''; ?>><?php echo htmlspecialchars(termLabel($termOption)); ?></option><?php endfor; ?></select></div>
                <?php if ($mode === 'report_remarks'): ?><div class="col-md-3"><label class="form-label" for="remark_position">Report role</label><select class="form-select" id="remark_position" name="remark_position"><?php foreach ($remarkPositionOptions as $position => $title): ?><option value="<?php echo htmlspecialchars($position); ?>" <?php echo $position === $selectedRemarkPosition ? 'selected' : ''; ?>><?php echo htmlspecialchars($title); ?></option><?php endforeach; ?></select></div><?php endif; ?>
            <?php endif; ?>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit"><i class="fas fa-filter me-1"></i> Load class</button></div>
        </div></form>
        <?php if ($mode === 'ratings' && $schoolDaysOpen < 1): ?><div class="alert alert-warning">The administrator must set days school opened for this session and term in Report Card Settings before you can enter attendance totals.</div>
        <?php elseif ($mode === 'ratings' && !$categories): ?><div class="alert alert-warning">The administrator must add report-card skills in Report Card Settings before ratings can be entered.</div>
        <?php elseif (!$students): ?><div class="alert alert-light border">No students are enrolled in this class.</div>
        <?php else: ?>
            <?php if ($mode === 'ratings'): ?>
                <?php $ratingDomains = ['affective' => 'Affective', 'psychomotor' => 'Psychomotor', 'locomotor' => 'Locomotor']; ?>
                <div class="card mb-3 rating-student-list"><div class="card-header d-flex justify-content-between flex-wrap gap-2"><strong><?php echo htmlspecialchars($classes[array_search($selectedClass, $authorizedClassIds, true)]['name'] ?? 'Class'); ?> · Select a student to enter ratings</strong><span class="small text-muted"><?php echo htmlspecialchars(termLabel($selectedTerm)); ?> · <?php echo $schoolDaysOpen; ?> days opened</span></div>
                    <div class="list-group list-group-flush"><?php foreach ($students as $index => $student): $studentId = (int)$student['id']; $ratedCount = 0; foreach ($categories as $category) if (isset($savedRatings[$studentId][(int)$category['id']])) $ratedCount++; ?>
                        <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3" data-bs-toggle="modal" data-bs-target="#ratings-student-<?php echo $studentId; ?>"><span><span class="text-muted me-2"><?php echo $index + 1; ?>.</span><strong><?php echo htmlspecialchars($student['full_name']); ?></strong><small class="d-block text-muted ms-4"><?php echo htmlspecialchars($student['admission_number'] ?: 'No admission number'); ?></small></span><span class="badge <?php echo $ratedCount === count($categories) ? 'text-bg-success' : 'text-bg-light'; ?>"><?php echo $ratedCount; ?>/<?php echo count($categories); ?> rated <i class="fas fa-chevron-right ms-2"></i></span></button>
                    <?php endforeach; ?></div></div>
                <?php foreach ($students as $index => $student): $studentId = (int)$student['id']; ?>
                    <div class="modal fade rating-entry-modal" id="ratings-student-<?php echo $studentId; ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
                        <form method="post"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(termResultsCsrfToken()); ?>"><input type="hidden" name="mode" value="ratings"><input type="hidden" name="class_id" value="<?php echo $selectedClass; ?>"><input type="hidden" name="session_id" value="<?php echo $selectedSession; ?>"><input type="hidden" name="term" value="<?php echo $selectedTerm; ?>"><input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                        <div class="modal-header"><div><div class="small text-uppercase text-primary fw-bold">Student <?php echo $index + 1; ?> of <?php echo count($students); ?></div><h2 class="modal-title h5 mb-0"><?php echo htmlspecialchars($student['full_name']); ?></h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                        <div class="modal-body"><div class="mb-4"><label class="form-label fw-semibold" for="days-present-<?php echo $studentId; ?>">Days present (out of <?php echo $schoolDaysOpen; ?>)</label><input class="form-control" id="days-present-<?php echo $studentId; ?>" type="number" name="days_present" min="0" max="<?php echo $schoolDaysOpen; ?>" value="<?php echo isset($savedDaysPresent[$studentId]) ? $savedDaysPresent[$studentId] : ''; ?>" required></div>
                            <?php foreach ($ratingDomains as $domain => $domainTitle): $domainCategories = array_values(array_filter($categories, static fn($category) => $category['domain'] === $domain)); if (!$domainCategories) continue; ?><section class="mb-3"><h3 class="h6 text-primary border-bottom pb-2"><?php echo $domainTitle; ?></h3><?php foreach ($domainCategories as $category): $categoryId = (int)$category['id']; $radioName = 'rating-' . $studentId . '-' . $categoryId; ?><div class="skill-rating-row py-3 border-bottom"><div class="fw-semibold mb-2"><?php echo htmlspecialchars($category['label']); ?></div><div class="d-flex flex-wrap gap-2" role="radiogroup" aria-label="<?php echo htmlspecialchars($category['label']); ?> rating"><?php for ($rating = 1; $rating <= 5; $rating++): $inputId = $radioName . '-' . $rating; ?><input class="btn-check" type="radio" id="<?php echo $inputId; ?>" name="ratings[<?php echo $categoryId; ?>]" value="<?php echo $rating; ?>" <?php echo ($savedRatings[$studentId][$categoryId] ?? 0) === $rating ? 'checked' : ''; ?> required><label class="btn btn-outline-primary rounded-circle" style="width:36px;height:36px;padding:.35rem" for="<?php echo $inputId; ?>"><?php echo $rating; ?></label><?php endfor; ?></div></div><?php endforeach; ?></section><?php endforeach; ?>
                        </div><div class="modal-footer justify-content-between"><span class="small text-muted">Select the best-fit grade for every skill.</span><div><button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i> Save ratings</button></div></div></form>
                    </div></div></div>
                <?php endforeach; ?>
            <?php else: ?>
            <form method="post" class="card"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(termResultsCsrfToken()); ?>"><input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>"><input type="hidden" name="class_id" value="<?php echo $selectedClass; ?>"><input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($selectedDate); ?>"><input type="hidden" name="session_id" value="<?php echo $selectedSession; ?>"><input type="hidden" name="term" value="<?php echo $selectedTerm; ?>"><input type="hidden" name="remark_position" value="<?php echo htmlspecialchars($selectedRemarkPosition); ?>">
                <div class="card-header d-flex justify-content-between align-items-center"><strong><?php echo htmlspecialchars($classes[array_search($selectedClass, $authorizedClassIds, true)]['name'] ?? 'Class'); ?> · <?php echo count($students); ?> students</strong><span class="small text-muted"><?php echo $mode === 'attendance' ? date('l, M j, Y', strtotime($selectedDate)) : htmlspecialchars(termLabel($selectedTerm)); ?><?php if ($mode === 'ratings'): ?> · <?php echo $schoolDaysOpen; ?> days opened<?php endif; ?></span></div>
                <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th class="ps-3" style="width:60px">#</th><th>Student</th><th>Admission number</th><?php if ($mode === 'ratings'): ?><th style="min-width:150px">Days present / <?php echo $schoolDaysOpen; ?></th><?php foreach ($categories as $category): ?><th style="min-width:125px"><?php echo htmlspecialchars($category['label']); ?><small class="d-block text-muted fw-normal"><?php echo htmlspecialchars(ucfirst($category['domain'])); ?> · 1–5</small></th><?php endforeach; ?><?php else: ?><th style="min-width:190px"><?php echo $mode === 'attendance' ? 'Attendance status' : ($mode === 'remarks' ? 'Class teacher remark' : htmlspecialchars($remarkPositionOptions[$selectedRemarkPosition] ?? 'Report remark')); ?></th><?php endif; ?></tr></thead><tbody>
                <?php foreach ($students as $index => $student): ?><tr><td class="ps-3"><?php echo $index + 1; ?></td><td class="fw-semibold"><?php echo htmlspecialchars($student['full_name']); ?></td><td><?php echo htmlspecialchars($student['admission_number'] ?: 'Not recorded'); ?></td><td>
                    <?php if ($mode === 'attendance'): ?><select class="form-select form-select-sm" name="attendance[<?php echo (int)$student['id']; ?>]" required><option value="">Select status</option><?php foreach (['present'=>'Present','absent'=>'Absent','late'=>'Late','excused'=>'Excused'] as $value=>$label): ?><option value="<?php echo $value; ?>" <?php echo ($savedAttendance[(int)$student['id']] ?? '') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select>
                    <?php elseif ($mode === 'remarks'): ?><textarea class="form-control form-control-sm" name="remarks[<?php echo (int)$student['id']; ?>]" rows="2" maxlength="2000" placeholder="Write a brief term remark"><?php echo htmlspecialchars($savedRemarks[(int)$student['id']] ?? ''); ?></textarea>
                    <?php elseif ($mode === 'report_remarks'): ?><textarea class="form-control form-control-sm" name="authority_remarks[<?php echo (int)$student['id']; ?>]" rows="2" maxlength="2000" placeholder="Write a <?php echo htmlspecialchars(strtolower($remarkPositionOptions[$selectedRemarkPosition] ?? 'report')); ?> remark"><?php echo htmlspecialchars($savedAuthorityRemarks[(int)$student['id']] ?? ''); ?></textarea>
                    <?php else: ?><input class="form-control form-control-sm" type="number" name="days_present[<?php echo (int)$student['id']; ?>]" min="0" max="<?php echo $schoolDaysOpen; ?>" value="<?php echo isset($savedDaysPresent[(int)$student['id']]) ? $savedDaysPresent[(int)$student['id']] : ''; ?>" required><?php endif; ?>
                </td><?php if ($mode === 'ratings'): foreach ($categories as $category): $studentId = (int)$student['id']; $categoryId = (int)$category['id']; ?><td><select class="form-select form-select-sm" name="ratings[<?php echo $studentId; ?>][<?php echo $categoryId; ?>]" required><option value="">Rate</option><?php for ($rating=1; $rating<=5; $rating++): ?><option value="<?php echo $rating; ?>" <?php echo ($savedRatings[$studentId][$categoryId] ?? 0) === $rating ? 'selected' : ''; ?>><?php echo $rating; ?></option><?php endfor; ?></select></td><?php endforeach; endif; ?></tr><?php endforeach; ?>
                </tbody></table></div><div class="card-footer d-flex justify-content-end"><button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i><?php echo $mode === 'attendance' ? 'Save attendance' : ($mode === 'ratings' ? 'Save attendance & ratings' : 'Save remarks'); ?></button></div>
            </form>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php include '../includes/footer.php'; ?>
