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
$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selected_exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$selected_subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$selection = null;

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
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">No students are registered in this class.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php elseif ($selected_class_id > 0 || $selected_exam_id > 0): ?>
    <div class="alert alert-info mt-4">Select a class, exam, and subject to view the student scores.</div>
<?php endif; ?>

<style>
@media print {
    nav, .dashboard-header, form, .btn, .alert, .card:not(.border-0) { display: none !important; }
    body, .container { margin: 0 !important; padding: 0 !important; }
    .table { font-size: 11px; }
}
</style>

<?php include '../includes/footer.php'; ?>
