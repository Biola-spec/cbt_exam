<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();
if (!in_array(getUserRole(), ['teacher', 'admin'])) {
    header('Location: ../login.php');
    exit();
}

$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
$message = '';
$message_type = 'info';
$attempt = null;
$answers = [];

function displayTheoryHtml($content) {
    $content = html_entity_decode((string)$content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $allowed_tags = '<p><br><strong><em><b><i><u><img><span><div><ul><ol><li><h1><h2><h3><h4><h5><h6><math><mfrac><mn><mi><mo><msup><msub><mrow>';
    $content = strip_tags($content, $allowed_tags);
    $content = preg_replace('/(<[^>]+) on[a-zA-Z]*="[^"]*"/i', '$1', $content);
    $content = preg_replace("/(<[^>]+) on[a-zA-Z]*='[^']*'/i", '$1', $content);
    return $content;
}

try {
    $mysqli = getDBConnection();
    ensureTheoryExamSchema($mysqli);
    $current_user_id = getUserId();
    $is_admin = getUserRole() === 'admin';

    if (!$attempt_id) {
        header('Location: dashboard.php?tab=results');
        exit();
    }

    $sql = "SELECT ea.*, e.title AS exam_title, c.name AS class_name, c.marks_per_question,
            s.name AS subject_name, u.full_name AS student_name, es.class_id, es.subject_id
            FROM exam_attempts ea
            JOIN exams e ON ea.exam_id = e.id
            JOIN exam_subjects es ON ea.exam_subject_id = es.id
            JOIN classes c ON es.class_id = c.id
            JOIN subjects s ON es.subject_id = s.id
            JOIN users u ON ea.student_id = u.id
            WHERE ea.id = ? AND ea.submitted_at IS NOT NULL";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $attempt_id);
    $stmt->execute();
    $attempt = $stmt->get_result()->fetch_assoc();

    if (!$attempt) {
        header('Location: dashboard.php?tab=results');
        exit();
    }

    if (!$is_admin) {
        $stmt = $mysqli->prepare("SELECT id FROM teacher_allocations WHERE teacher_id = ? AND class_id = ? AND subject_id = ? LIMIT 1");
        $stmt->bind_param("iii", $current_user_id, $attempt['class_id'], $attempt['subject_id']);
        $stmt->execute();
        $hasRegularAllocation = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        $stmt = $mysqli->prepare("SELECT 1 FROM entrance_exams WHERE exam_subject_id=? AND teacher_id=? LIMIT 1");
        $stmt->bind_param('ii', $attempt['exam_subject_id'], $current_user_id);
        $stmt->execute();
        $hasEntranceAssignment = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$hasRegularAllocation && !$hasEntranceAssignment) {
            header('Location: dashboard.php?tab=results');
            exit();
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marks']) && is_array($_POST['marks'])) {
        foreach ($_POST['marks'] as $answer_id => $mark_value) {
            $answer_id = (int)$answer_id;
            $awarded_marks = max(0, (float)$mark_value);
            $comment = trim($_POST['comments'][$answer_id] ?? '');

            $stmt = $mysqli->prepare("SELECT q.theory_marks FROM student_answers sa JOIN questions q ON sa.question_id = q.id WHERE sa.id = ? AND sa.attempt_id = ? AND q.question_type = 'theory'");
            $stmt->bind_param("ii", $answer_id, $attempt_id);
            $stmt->execute();
            $limit_row = $stmt->get_result()->fetch_assoc();
            if (!$limit_row) {
                continue;
            }

            $max_marks = (float)$limit_row['theory_marks'];
            $awarded_marks = min($awarded_marks, $max_marks);

            $stmt = $mysqli->prepare("UPDATE student_answers SET awarded_marks = ?, teacher_comment = ?, marked_by = ?, marked_at = NOW() WHERE id = ? AND attempt_id = ?");
            $stmt->bind_param("dsiii", $awarded_marks, $comment, $current_user_id, $answer_id, $attempt_id);
            $stmt->execute();
        }

        $stmt = $mysqli->prepare("SELECT COALESCE(SUM(CASE WHEN q.question_type = 'objective' AND sa.is_correct = 1 THEN ? WHEN q.question_type = 'theory' THEN COALESCE(sa.awarded_marks, 0) ELSE 0 END), 0) AS total_score
                                  FROM student_answers sa
                                  JOIN questions q ON sa.question_id = q.id
                                  WHERE sa.attempt_id = ?");
        $marks_per_question = (float)$attempt['marks_per_question'];
        $stmt->bind_param("di", $marks_per_question, $attempt_id);
        $stmt->execute();
        $score_row = $stmt->get_result()->fetch_assoc();
        $new_score = (float)($score_row['total_score'] ?? 0);

        $stmt = $mysqli->prepare("UPDATE exam_attempts SET score = ? WHERE id = ?");
        $stmt->bind_param("di", $new_score, $attempt_id);
        $stmt->execute();
        $attempt['score'] = $new_score;

        $message = 'Theory marks saved and total score recalculated.';
        $message_type = 'success';
    }

    $stmt = $mysqli->prepare("SELECT sa.id AS answer_id, sa.answer_text, sa.awarded_marks, sa.teacher_comment, sa.marked_at,
                          q.id AS question_id, q.question_text, q.theory_marks, q.marking_scheme
                          FROM student_answers sa
                          JOIN questions q ON sa.question_id = q.id
                          WHERE sa.attempt_id = ? AND q.question_type = 'theory'
                          ORDER BY COALESCE(q.order_position, q.id), q.id");
    $stmt->bind_param("i", $attempt_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $answers[] = $row;
    }
} catch (Exception $e) {
    $message = 'Error loading theory marking page: ' . $e->getMessage();
    $message_type = 'danger';
}
?>

<?php include '../includes/header.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Mark Theory Answers</h4>
            <?php if ($attempt): ?>
                <div class="text-muted">
                    <?php echo htmlspecialchars($attempt['student_name']); ?> -
                    <?php echo htmlspecialchars($attempt['exam_title']); ?> -
                    <?php echo htmlspecialchars($attempt['class_name']); ?> / <?php echo htmlspecialchars($attempt['subject_name']); ?>
                </div>
            <?php endif; ?>
        </div>
        <a href="dashboard.php?tab=results" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Results
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($attempt): ?>
        <div class="card mb-4">
            <div class="card-body">
                <strong>Current Score:</strong> <?php echo number_format((float)$attempt['score'], 2); ?>
                <span class="text-muted ms-3">Submitted <?php echo date('M j, Y g:i A', strtotime($attempt['submitted_at'])); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (count($answers) > 0): ?>
        <form method="POST">
            <?php foreach ($answers as $index => $answer): ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Question <?php echo $index + 1; ?></strong>
                        <span class="badge bg-primary"><?php echo number_format((float)$answer['theory_marks'], 2); ?> marks</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="fw-bold mb-2">Question</div>
                            <div><?php echo displayTheoryHtml($answer['question_text']); ?></div>
                        </div>

                        <div class="mb-3">
                            <div class="fw-bold mb-2">Student Answer</div>
                            <div class="border rounded p-3 bg-light" style="white-space: pre-wrap;"><?php echo htmlspecialchars($answer['answer_text'] ?? 'No answer submitted.'); ?></div>
                        </div>

                        <?php if (!empty($answer['marking_scheme'])): ?>
                            <div class="mb-3">
                                <div class="fw-bold mb-2">Marking Guide</div>
                                <div class="border rounded p-3"><?php echo displayTheoryHtml($answer['marking_scheme']); ?></div>
                            </div>
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Awarded Marks</label>
                                <input type="number" class="form-control" name="marks[<?php echo $answer['answer_id']; ?>]" min="0" max="<?php echo htmlspecialchars($answer['theory_marks']); ?>" step="0.01" value="<?php echo htmlspecialchars($answer['awarded_marks'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-9">
                                <label class="form-label">Teacher Comment</label>
                                <textarea class="form-control" name="comments[<?php echo $answer['answer_id']; ?>]" rows="2"><?php echo htmlspecialchars($answer['teacher_comment'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i> Save Marks
            </button>
        </form>
    <?php else: ?>
        <div class="alert alert-info">No theory answers were submitted for this attempt.</div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
