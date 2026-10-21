<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();
if (!in_array(getUserRole(), ['teacher', 'admin'])) {
    header('Location: ../login.php');
    exit();
}

$mysqli = getDBConnection();
$current_user_id = (int)getUserId();
$user_role = getUserRole();
$message = '';
$classes = [];
$exams = [];
$subjects = [];
$subject_options = [];
$students = [];
$attempts = [];
$resit_requests = [];
$resit_workflow_available = false;
$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selected_exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$selected_subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$selection = null;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_resit') {
    $request_class_id = (int)($_POST['class_id'] ?? 0);
    $request_exam_id = (int)($_POST['exam_id'] ?? 0);
    $request_subject_id = (int)($_POST['subject_id'] ?? 0);
    $request_student_id = (int)($_POST['student_id'] ?? 0);
    $request_reason = trim($_POST['reason'] ?? 'Teacher requested a resit after reviewing the student broadsheet.');

    if (!hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
        $message = 'Invalid session token. Please refresh and try again.';
    } else {
        try {
            $table_check = $mysqli->query("SHOW TABLES LIKE 'resit_requests'");
            $resit_workflow_available = $table_check && $table_check->num_rows > 0;
            if (!$resit_workflow_available) {
                throw new Exception('Resit workflow is not installed. Import database/resit_workflow.sql first.');
            }

            $permission_sql = "SELECT es.id, es.exam_id, es.subject_id, es.class_id
                               FROM exam_subjects es";
            if ($user_role === 'teacher') {
                $permission_sql .= " JOIN teacher_allocations ta ON ta.class_id = es.class_id AND ta.subject_id = es.subject_id";
            }
            $permission_sql .= " WHERE es.exam_id = ? AND es.class_id = ? AND es.subject_id = ?";
            if ($user_role === 'teacher') {
                $permission_sql .= " AND ta.teacher_id = ?";
            }
            $permission_sql .= " LIMIT 1";
            $stmt = $mysqli->prepare($permission_sql);
            if ($user_role === 'teacher') {
                $stmt->bind_param('iiii', $request_exam_id, $request_class_id, $request_subject_id, $current_user_id);
            } else {
                $stmt->bind_param('iii', $request_exam_id, $request_class_id, $request_subject_id);
            }
            $stmt->execute();
            $exam_subject_row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$exam_subject_row) {
                throw new Exception('You do not have permission to request a resit for this subject.');
            }

            $stmt = $mysqli->prepare("SELECT id FROM users
                                      WHERE id = ? AND role = 'student'
                                        AND (class_id = ? OR EXISTS (
                                            SELECT 1 FROM exam_attempts
                                            WHERE student_id = users.id AND exam_subject_id = ?
                                        ))");
            $stmt->bind_param('iii', $request_student_id, $request_class_id, $exam_subject_row['id']);
            $stmt->execute();
            $student_exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$student_exists) {
                throw new Exception('The selected student is not in this class or subject.');
            }

            $stmt = $mysqli->prepare("SELECT status FROM resit_requests
                                      WHERE student_id = ? AND exam_subject_id = ?
                                      ORDER BY id DESC LIMIT 1");
            $stmt->bind_param('ii', $request_student_id, $exam_subject_row['id']);
            $stmt->execute();
            $previous_request = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($previous_request && in_array($previous_request['status'], ['pending', 'approved'], true)) {
                throw new Exception('A resit request is already pending or approved for this student.');
            }

            $stmt = $mysqli->prepare("INSERT INTO resit_requests (student_id, exam_id, exam_subject_id, requested_by, reason) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('iiiis', $request_student_id, $request_exam_id, $exam_subject_row['id'], $current_user_id, $request_reason);
            $stmt->execute();
            $stmt->close();
            $message = 'Resit request sent to the administrator and notice posted for the student.';
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
    }
}

try {
    if ($user_role === 'admin') {
        $stmt = $mysqli->prepare("SELECT id, name FROM classes ORDER BY name");
        $stmt->execute();
    } else {
        $stmt = $mysqli->prepare("SELECT DISTINCT c.id, c.name
                                  FROM classes c
                                  JOIN teacher_allocations ta ON ta.class_id = c.id
                                  WHERE ta.teacher_id = ?
                                  ORDER BY c.name");
        $stmt->bind_param('i', $current_user_id);
        $stmt->execute();
    }
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
    $stmt->close();

    if ($user_role === 'admin') {
        $stmt = $mysqli->prepare("SELECT DISTINCT e.id, e.title
                                  FROM exams e
                                  JOIN exam_subjects es ON es.exam_id = e.id
                                  ORDER BY e.title");
        $stmt->execute();
    } else {
        $stmt = $mysqli->prepare("SELECT DISTINCT e.id, e.title
                                  FROM exams e
                                  JOIN exam_subjects es ON es.exam_id = e.id
                                  JOIN teacher_allocations ta ON ta.class_id = es.class_id AND ta.subject_id = es.subject_id
                                  WHERE ta.teacher_id = ?
                                  ORDER BY e.title");
        $stmt->bind_param('i', $current_user_id);
        $stmt->execute();
    }
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
    $stmt->close();

    if ($user_role === 'admin') {
        $stmt = $mysqli->prepare("SELECT es.exam_id, es.class_id, es.id AS exam_subject_id,
                                         s.id AS subject_id, s.name AS subject_name
                                  FROM exam_subjects es
                                  JOIN subjects s ON s.id = es.subject_id
                                  ORDER BY es.class_id, es.exam_id, s.name");
        $stmt->execute();
    } else {
        $stmt = $mysqli->prepare("SELECT es.exam_id, es.class_id, es.id AS exam_subject_id,
                                         s.id AS subject_id, s.name AS subject_name
                                  FROM exam_subjects es
                                  JOIN subjects s ON s.id = es.subject_id
                                  JOIN teacher_allocations ta ON ta.class_id = es.class_id AND ta.subject_id = es.subject_id
                                  WHERE ta.teacher_id = ?
                                  ORDER BY es.class_id, es.exam_id, s.name");
        $stmt->bind_param('i', $current_user_id);
        $stmt->execute();
    }
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $subject_options[] = $row;
    }
    $stmt->close();

    if ($selected_class_id > 0 && $selected_exam_id > 0) {
        if ($user_role === 'admin') {
            $stmt = $mysqli->prepare("SELECT es.id AS exam_subject_id, s.id AS subject_id, s.name AS subject_name
                                      FROM exam_subjects es
                                      JOIN subjects s ON s.id = es.subject_id
                                      WHERE es.class_id = ? AND es.exam_id = ?
                                      ORDER BY s.name");
            $stmt->bind_param('ii', $selected_class_id, $selected_exam_id);
        } else {
            $stmt = $mysqli->prepare("SELECT es.id AS exam_subject_id, s.id AS subject_id, s.name AS subject_name
                                      FROM exam_subjects es
                                      JOIN subjects s ON s.id = es.subject_id
                                      JOIN teacher_allocations ta ON ta.class_id = es.class_id AND ta.subject_id = es.subject_id
                                      WHERE es.class_id = ? AND es.exam_id = ? AND ta.teacher_id = ?
                                      ORDER BY s.name");
            $stmt->bind_param('iii', $selected_class_id, $selected_exam_id, $current_user_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        $stmt->close();
    }

    foreach ($subjects as $subject) {
        if ((int)$subject['subject_id'] === $selected_subject_id) {
            $selection = $subject;
            break;
        }
    }

    if ($selected_subject_id > 0 && !$selection) {
        $selected_subject_id = 0;
        $message = 'That subject is not available for the selected class and exam.';
    }

    if ($selection) {
        $stmt = $mysqli->prepare("SELECT c.name AS class_name, e.title AS exam_title, s.name AS subject_name
                                  FROM exam_subjects es
                                  JOIN classes c ON c.id = es.class_id
                                  JOIN exams e ON e.id = es.exam_id
                                  JOIN subjects s ON s.id = es.subject_id
                                  WHERE es.id = ? AND es.class_id = ? AND es.exam_id = ?");
        $stmt->bind_param('iii', $selection['exam_subject_id'], $selected_class_id, $selected_exam_id);
        $stmt->execute();
        $selection = array_merge($selection, $stmt->get_result()->fetch_assoc() ?: []);
        $stmt->close();

        $stmt = $mysqli->prepare("SELECT DISTINCT u.id AS student_id, u.full_name, u.email
                                  FROM users u
                                  WHERE u.role = 'student'
                                    AND (u.class_id = ? OR EXISTS (
                                        SELECT 1
                                        FROM exam_attempts ea
                                        JOIN exam_subjects es ON es.id = ea.exam_subject_id
                                        WHERE ea.student_id = u.id
                                          AND ea.exam_id = ?
                                          AND es.id = ?
                                          AND es.class_id = ?
                                    ))
                                  ORDER BY u.full_name");
        $stmt->bind_param('iiii', $selected_class_id, $selected_exam_id, $selection['exam_subject_id'], $selected_class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();

        $stmt = $mysqli->prepare("SELECT ea.student_id, ea.score, ea.total_questions, ea.submitted_at,
                                         c.marks_per_question,
                                         (SELECT COUNT(*) FROM questions q
                                          WHERE q.exam_subject_id = ea.exam_subject_id
                                            AND COALESCE(q.question_type, 'objective') = 'objective') AS objective_questions,
                                         (SELECT COALESCE(SUM(q.theory_marks), 0) FROM questions q
                                          WHERE q.exam_subject_id = ea.exam_subject_id
                                            AND q.question_type = 'theory') AS theory_total_marks
                                  FROM exam_attempts ea
                                  JOIN exam_subjects es ON es.id = ea.exam_subject_id
                                  JOIN classes c ON c.id = es.class_id
                                  WHERE ea.exam_id = ?
                                    AND ea.exam_subject_id = ?
                                    AND ea.submitted_at IS NOT NULL
                                  ORDER BY ea.student_id, ea.submitted_at DESC, ea.id DESC");
        $stmt->bind_param('ii', $selected_exam_id, $selection['exam_subject_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if (!isset($attempts[$row['student_id']])) {
                $attempts[$row['student_id']] = $row;
            }
        }
        $stmt->close();

        $table_check = $mysqli->query("SHOW TABLES LIKE 'resit_requests'");
        $resit_workflow_available = $table_check && $table_check->num_rows > 0;
        if ($resit_workflow_available) {
            $stmt = $mysqli->prepare("SELECT rr.*, res.is_published AS resit_subject_published
                                      FROM resit_requests rr
                                      LEFT JOIN exam_subjects res ON res.id = rr.resit_exam_subject_id
                                      WHERE rr.exam_subject_id = ?
                                      ORDER BY rr.requested_at DESC");
            $stmt->bind_param('i', $selection['exam_subject_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                if (!isset($resit_requests[$row['student_id']])) {
                    $resit_requests[$row['student_id']] = $row;
                }
            }
            $stmt->close();
        }
    }
} catch (Exception $e) {
    $message = 'Error loading the student broadsheet: ' . $e->getMessage();
}

$class_name = '';
foreach ($classes as $class) {
    if ((int)$class['id'] === $selected_class_id) {
        $class_name = $class['name'];
        break;
    }
}
$exam_title = '';
foreach ($exams as $exam) {
    if ((int)$exam['id'] === $selected_exam_id) {
        $exam_title = $exam['title'];
        break;
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="dashboard-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h1 class="dashboard-title"><i class="fas fa-table me-2"></i> Student Broadsheet</h1>
                <p class="mb-0">View student scores for your assigned class subjects</p>
            </div>
            <div class="col-md-5 text-md-end">
                <a href="dashboard.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i> Teacher Dashboard</a>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="class_id" class="form-label fw-semibold">Class</label>
                <select name="class_id" id="class_id" class="form-select" required>
                    <option value="">Select class</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo (int)$class['id']; ?>" <?php echo $selected_class_id === (int)$class['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="exam_id" class="form-label fw-semibold">Exam</label>
                <select name="exam_id" id="exam_id" class="form-select" required>
                    <option value="">Select exam</option>
                    <?php foreach ($exams as $exam): ?>
                        <option value="<?php echo (int)$exam['id']; ?>" <?php echo $selected_exam_id === (int)$exam['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($exam['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="subject_id" class="form-label fw-semibold">Subject</label>
                <select name="subject_id" id="subject_id" class="form-select" required <?php echo $selected_class_id > 0 && $selected_exam_id > 0 && count($subjects) === 0 ? 'disabled' : ''; ?>>
                    <option value="">Select subject</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo (int)$subject['subject_id']; ?>" <?php echo $selected_subject_id === (int)$subject['subject_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($selected_class_id > 0 && $selected_exam_id > 0 && count($subjects) === 0): ?>
                    <div class="form-text text-danger">No assigned subject exists for this class and exam.</div>
                <?php else: ?>
                    <div class="form-text">Choose class and exam first, then select a subject.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-search me-1"></i> View Scores</button>
                <a href="student_broadsheet.php" class="btn btn-outline-secondary" title="Clear filters"><i class="fas fa-redo"></i></a>
            </div>
        </form>
    </div>
</div>

<script>
const assignedSubjectOptions = <?php echo json_encode($subject_options, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
const classSelect = document.getElementById('class_id');
const examSelect = document.getElementById('exam_id');
const subjectSelect = document.getElementById('subject_id');
const initialSubjectId = '<?php echo (int)$selected_subject_id; ?>';

function updateSubjectOptions() {
    const classId = classSelect.value;
    const examId = examSelect.value;
    const matchingSubjects = assignedSubjectOptions.filter(function(subject) {
        return String(subject.class_id) === classId && String(subject.exam_id) === examId;
    });

    subjectSelect.innerHTML = '<option value="">Select subject</option>';
    matchingSubjects.forEach(function(subject) {
        const option = document.createElement('option');
        option.value = subject.subject_id;
        option.textContent = subject.subject_name;
        if (String(subject.subject_id) === initialSubjectId) {
            option.selected = true;
        }
        subjectSelect.appendChild(option);
    });
    subjectSelect.disabled = !(classId && examId && matchingSubjects.length);
}

classSelect.addEventListener('change', updateSubjectOptions);
examSelect.addEventListener('change', updateSubjectOptions);
updateSubjectOptions();
</script>

<?php if ($message): ?>
    <div class="alert alert-warning mt-4"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($selection): ?>
    <?php
    $attempted_count = 0;
    $score_total = 0;
    $score_max_total = 0;
    foreach ($students as $student) {
        if (isset($attempts[$student['student_id']]) && $attempts[$student['student_id']]['score'] !== null) {
            $attempted_count++;
            $attempt = $attempts[$student['student_id']];
            $objective_questions = (int)$attempt['objective_questions'];
            $marks_per_question = (float)$attempt['marks_per_question'];
            $theory_total_marks = (float)$attempt['theory_total_marks'];
            $max_score = ($objective_questions * $marks_per_question) + $theory_total_marks;
            if ($max_score <= 0) {
                $max_score = (int)$attempt['total_questions'] * $marks_per_question;
            }
            $score_total += (float)$attempt['score'];
            $score_max_total += $max_score;
        }
    }
    $class_average = $score_max_total > 0 ? ($score_total / $score_max_total) * 100 : 0;
    ?>
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-1"><?php echo htmlspecialchars($selection['subject_name']); ?> Scores</h4>
            <div class="text-muted small">
                <?php echo htmlspecialchars($selection['class_name']); ?> &middot; <?php echo htmlspecialchars($selection['exam_title']); ?>
            </div>
        </div>
        <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-1"></i> Print Broadsheet</button>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card border-0 bg-light"><div class="card-body"><div class="text-muted small">Students</div><div class="fs-4 fw-bold"><?php echo count($students); ?></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 bg-light"><div class="card-body"><div class="text-muted small">Submitted</div><div class="fs-4 fw-bold"><?php echo $attempted_count; ?></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 bg-light"><div class="card-body"><div class="text-muted small">Average</div><div class="fs-4 fw-bold"><?php echo number_format($class_average, 2); ?>%</div></div></div></div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Score</th>
                    <th>Maximum</th>
                    <th>Percentage</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Resit Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($students) > 0): ?>
                    <?php foreach ($students as $index => $student): ?>
                        <?php
                        $attempt = $attempts[$student['student_id']] ?? null;
                        $score = null;
                        $max_score = null;
                        $percentage = null;
                        if ($attempt) {
                            $objective_questions = (int)$attempt['objective_questions'];
                            $marks_per_question = (float)$attempt['marks_per_question'];
                            $theory_total_marks = (float)$attempt['theory_total_marks'];
                            $max_score = ($objective_questions * $marks_per_question) + $theory_total_marks;
                            if ($max_score <= 0) {
                                $max_score = (int)$attempt['total_questions'] * $marks_per_question;
                            }
                            $score = $attempt['score'] === null ? null : (float)$attempt['score'];
                            $percentage = $score !== null && $max_score > 0 ? ($score / $max_score) * 100 : null;
                        }
                        ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($student['email']); ?></div>
                            </td>
                            <?php if ($attempt && $score !== null): ?>
                                <td><?php echo number_format($score, 2); ?></td>
                                <td><?php echo number_format($max_score, 2); ?></td>
                                <td><?php echo number_format($percentage, 2); ?>%</td>
                                <td><span class="badge <?php echo $percentage >= 50 ? 'bg-success' : 'bg-danger'; ?>"><?php echo $percentage >= 50 ? 'Pass' : 'Fail'; ?></span></td>
                                <td><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($attempt['submitted_at']))); ?></td>
                            <?php else: ?>
                                <td colspan="3" class="text-muted"><?php echo $attempt ? 'Score pending marking' : 'No submitted score'; ?></td>
                                <td><span class="badge bg-<?php echo $attempt ? 'warning text-dark' : 'secondary'; ?>"><?php echo $attempt ? 'Pending' : 'Not Taken'; ?></span></td>
                                <td class="text-muted">—</td>
                            <?php endif; ?>
                            <td>
                                <?php $resit_request = $resit_requests[$student['student_id']] ?? null; ?>
                                <?php if (!$resit_workflow_available): ?>
                                    <span class="small text-danger">Import resit_workflow.sql</span>
                                <?php elseif (!$attempt): ?>
                                    <span class="small text-muted">Student has not taken this subject</span>
                                <?php elseif ($resit_request && $resit_request['status'] === 'pending'): ?>
                                    <span class="badge bg-warning text-dark">Request sent</span>
                                    <div class="small text-muted mt-1">Waiting for admin approval</div>
                                <?php elseif ($resit_request && $resit_request['status'] === 'approved'): ?>
                                    <span class="badge bg-success">Approved</span>
                                    <div class="small text-muted mt-1"><?php echo htmlspecialchars(date('M j, Y', strtotime($resit_request['resit_date']))); ?> · <?php echo htmlspecialchars($resit_request['venue']); ?></div>
                                    <?php if (!empty($resit_request['resit_exam_id']) && !empty($resit_request['resit_exam_subject_id'])): ?>
                                        <a href="question_management.php?exam_id=<?php echo (int)$resit_request['resit_exam_id']; ?>&class_id=<?php echo $selected_class_id; ?>&subject_id=<?php echo $selection['subject_id']; ?>" class="btn btn-sm btn-outline-primary mt-2">
                                            <i class="fas fa-edit me-1"></i> Set Resit Questions
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if ($resit_request && $resit_request['status'] === 'rejected'): ?>
                                        <div class="small text-danger mb-1">Previous request rejected</div>
                                    <?php endif; ?>
                                    <form method="POST" class="resit-request-form" action="student_broadsheet.php?class_id=<?php echo $selected_class_id; ?>&exam_id=<?php echo $selected_exam_id; ?>&subject_id=<?php echo $selected_subject_id; ?>" data-student-name="<?php echo htmlspecialchars($student['full_name'], ENT_QUOTES); ?>">
                                        <input type="hidden" name="action" value="request_resit">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
                                        <input type="hidden" name="exam_id" value="<?php echo $selected_exam_id; ?>">
                                        <input type="hidden" name="subject_id" value="<?php echo $selection['subject_id']; ?>">
                                        <input type="hidden" name="student_id" value="<?php echo (int)$student['student_id']; ?>">
                                        <input type="hidden" name="reason" value="Teacher requested a resit after reviewing the student broadsheet.">
                                        <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-bullhorn me-1"></i> Request Resit</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">No students are registered in this class.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php elseif ($selected_class_id > 0 || $selected_exam_id > 0): ?>
    <div class="alert alert-info mt-4">Select a class, exam, and subject to view the student scores.</div>
<?php endif; ?>

<div class="modal fade" id="resitRequestModal" tabindex="-1" aria-labelledby="resitRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content resit-modal-content border-0">
            <div class="modal-header resit-modal-header border-0 text-white">
                <div class="d-flex align-items-center gap-3">
                    <div class="resit-modal-icon"><i class="fas fa-bullhorn"></i></div>
                    <div>
                        <h5 class="modal-title mb-1" id="resitRequestModalLabel">Request a Resit</h5>
                        <div class="small opacity-75">Administrator approval required</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="resit-student-card d-flex align-items-center gap-3 mb-4">
                    <div class="resit-student-avatar"><i class="fas fa-user-graduate"></i></div>
                    <div>
                        <div class="small text-muted text-uppercase fw-semibold">Student</div>
                        <div class="fs-5 fw-bold text-dark" id="resitStudentName">Selected student</div>
                    </div>
                </div>
                <p class="text-muted mb-3">This will send a formal resit request to the administrator. If approved, the administrator will set the date, time, and venue.</p>
                <div class="resit-modal-note d-flex gap-2 align-items-start">
                    <i class="fas fa-info-circle text-primary mt-1"></i>
                    <span>The student will receive a notice on their dashboard after this request is sent.</span>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" id="confirmResitRequest">
                    <i class="fas fa-paper-plane me-2"></i>Send Request
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.resit-modal-content {
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.25);
}

.resit-modal-header {
    background: linear-gradient(135deg, #1d4ed8 0%, #4338ca 100%);
    padding: 1.25rem 1.5rem;
}

.resit-modal-icon {
    width: 46px;
    height: 46px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    color: #1d4ed8;
    background: rgba(255, 255, 255, 0.95);
    font-size: 1.2rem;
}

.resit-student-card {
    padding: 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    background: #f8fafc;
}

.resit-student-avatar {
    width: 48px;
    height: 48px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    color: #1d4ed8;
    background: #dbeafe;
    font-size: 1.15rem;
}

.resit-modal-note {
    padding: 0.85rem 1rem;
    border-radius: 12px;
    color: #334155;
    background: #eff6ff;
    font-size: 0.9rem;
}

@media print {
    nav, .dashboard-header, form, .btn, .alert, .card:not(.border-0) { display: none !important; }
    body, .container { margin: 0 !important; padding: 0 !important; }
    .table { font-size: 11px; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let pendingResitForm = null;
    const resitRequestModalElement = document.getElementById('resitRequestModal');

    if (!resitRequestModalElement || typeof bootstrap === 'undefined') {
        return;
    }

    const resitRequestModal = new bootstrap.Modal(resitRequestModalElement);

    document.querySelectorAll('.resit-request-form').forEach(function(form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            pendingResitForm = form;
            document.getElementById('resitStudentName').textContent = form.dataset.studentName || 'Selected student';
            resitRequestModal.show();
        });
    });

    document.getElementById('confirmResitRequest')?.addEventListener('click', function() {
        if (pendingResitForm) {
            pendingResitForm.submit();
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
