<?php
// Only initialize if not already included
if (!isset($included_from_dashboard)) {
    require_once '../config/database.php';
    require_once '../config/session.php'; // Added this to ensure session functions are available
    
    // Check if user is logged in and is a teacher or admin
    requireLogin();
    if (!in_array(getUserRole(), ['teacher', 'admin'])) {
        header('Location: ../login.php');
        exit();
    }
}

$message = '';
$results = [];
$classes = [];
$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

try {
    $mysqli = getDBConnection();
    ensureTheoryExamSchema($mysqli);
    
    // Get current user ID and store in a variable to avoid pass by reference error
    $current_user_id = getUserId();
    
    // Get classes allocated to this teacher
    $stmt = $mysqli->prepare("SELECT DISTINCT c.id, c.name 
                          FROM classes c
                          JOIN teacher_allocations ta ON c.id = ta.class_id
                          WHERE ta.teacher_id = ?
                          ORDER BY c.name");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
    
    // Build the query based on whether a class is selected
    $sql = "SELECT ea.*, e.title as exam_title, c.name as class_name, c.marks_per_question, s.name as subject_name, u.full_name as student_name,
            (SELECT COUNT(*) FROM questions q WHERE q.exam_subject_id = ea.exam_subject_id AND COALESCE(q.question_type, 'objective') = 'objective') AS objective_questions,
            (SELECT COALESCE(SUM(q.theory_marks), 0) FROM questions q WHERE q.exam_subject_id = ea.exam_subject_id AND q.question_type = 'theory') AS theory_total_marks,
            (SELECT COUNT(*) FROM student_answers sa JOIN questions q ON sa.question_id = q.id WHERE sa.attempt_id = ea.id AND q.question_type = 'theory' AND COALESCE(sa.answer_text, '') <> '' AND sa.marked_at IS NULL) AS unmarked_theory_count
            FROM exam_attempts ea
            JOIN exams e ON ea.exam_id = e.id
            JOIN exam_subjects es ON ea.exam_subject_id = es.id
            JOIN classes c ON es.class_id = c.id
            JOIN subjects s ON es.subject_id = s.id
            JOIN users u ON ea.student_id = u.id
            JOIN teacher_allocations ta ON es.class_id = ta.class_id AND es.subject_id = ta.subject_id
            WHERE ta.teacher_id = ? AND ea.submitted_at IS NOT NULL";
    
    $params = array($current_user_id);
    $types = "i";
    
    if ($selected_class_id > 0) {
        $sql .= " AND c.id = ?";
        $params[] = $selected_class_id;
        $types .= "i";
    }
    
    $sql .= " ORDER BY ea.submitted_at DESC";
    
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
} catch (Exception $e) {
    $message = 'Error loading results: ' . $e->getMessage();
}
?>

<h5>Exam Results</h5>

<!-- Class Filter Form -->
<form method="GET" class="mb-4">
    <input type="hidden" name="tab" value="results">
    <div class="row">
        <div class="col-md-4">
            <label for="class_id" class="form-label">Filter by Class:</label>
            <select name="class_id" id="class_id" class="form-select">
                <option value="0">All Classes</option>
                <?php foreach ($classes as $class): ?>
                    <option value="<?php echo $class['id']; ?>" <?php echo ($selected_class_id == $class['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($class['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 d-flex align-items-end justify-content-md-end mt-3 mt-md-0">
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($selected_class_id > 0): ?>
                <a href="?tab=results" class="btn btn-secondary ms-2">Clear</a>
                <a href="export_class_excel.php?class_id=<?php echo $selected_class_id; ?>" class="btn btn-success ms-3" title="Download Excel Broadsheet containing all students and subject scores for this class">
                    <i class="fas fa-file-excel me-1"></i> Download Class Excel
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if ($message): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if (count($results) > 0): ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Exam</th>
                    <th>Class - Subject</th>
                    <th>Score</th>
                    <th>Total Questions</th>
                    <th>Percentage</th>
                    <th>Submitted At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $result): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($result['student_name']); ?></td>
                        <td><?php echo htmlspecialchars($result['exam_title']); ?></td>
                        <td><?php echo htmlspecialchars($result['class_name']); ?> - <?php echo htmlspecialchars($result['subject_name']); ?></td>
                        <td><?php echo number_format((float)$result['score'], 2); ?></td>
                        <td><?php echo $result['total_questions']; ?></td>
                        <td>
                            <?php 
                            $marks_per_question = (float)$result['marks_per_question'];
                            $objective_questions = (int)($result['objective_questions'] ?? $result['total_questions']);
                            $theory_total_marks = (float)($result['theory_total_marks'] ?? 0);
                            $total_score = ($objective_questions * $marks_per_question) + $theory_total_marks;
                            $percentage = 0;
                            if ($total_score > 0) {
                                $percentage = ($result['score'] / $total_score) * 100;
                            }
                            echo round($percentage, 2) . '%';
                            ?>
                        </td>
                        <td><?php echo date('M j, Y g:i A', strtotime($result['submitted_at'])); ?></td>
                        <td>
                            <?php if ((int)($result['unmarked_theory_count'] ?? 0) > 0): ?>
                                <a href="mark_theory.php?attempt_id=<?php echo $result['id']; ?>" class="btn btn-sm btn-warning" title="Mark Theory">
                                    <i class="fas fa-pen"></i> <?php echo (int)$result['unmarked_theory_count']; ?>
                                </a>
                            <?php elseif ((float)($result['theory_total_marks'] ?? 0) > 0): ?>
                                <a href="mark_theory.php?attempt_id=<?php echo $result['id']; ?>" class="btn btn-sm btn-outline-success" title="Review Theory Marks">
                                    <i class="fas fa-check"></i>
                                </a>
                            <?php endif; ?>
                            <a href="print_exam_result.php?attempt_id=<?php echo $result['id']; ?>" class="btn btn-sm btn-outline-primary" title="Print Results" target="_blank">
                                <i class="fas fa-print"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <p>No exam results available yet.</p>
        <p>Students need to complete exams before results will appear here.</p>
    </div>
<?php endif; ?>
