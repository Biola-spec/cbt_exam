<?php
require_once '../config/database.php';
require_once '../config/session.php';

$message = '';
$exam_history = [];

try {
    $mysqli = getDBConnection();
    ensureTheoryExamSchema($mysqli);
    
    // Get current user ID and store in a variable to avoid pass by reference error
    $current_user_id = getUserId();
    
    // Fixed ambiguous column references by specifying table aliases
    // Also get marks_per_question from classes table
    $stmt = $mysqli->prepare("SELECT ea.id as attempt_id, ea.score, ea.total_questions, ea.submitted_at, 
                          e.title as exam_title, c.name as class_name, s.name as subject_name, es.time_limit, c.marks_per_question,
                          (SELECT COUNT(*) FROM questions q WHERE q.exam_subject_id = ea.exam_subject_id AND COALESCE(q.question_type, 'objective') = 'objective') AS objective_questions,
                          (SELECT COALESCE(SUM(q.theory_marks), 0) FROM questions q WHERE q.exam_subject_id = ea.exam_subject_id AND q.question_type = 'theory') AS theory_total_marks,
                          (SELECT COUNT(*) FROM student_answers sa JOIN questions q ON sa.question_id = q.id WHERE sa.attempt_id = ea.id AND q.question_type = 'theory' AND COALESCE(sa.answer_text, '') <> '' AND sa.marked_at IS NULL) AS pending_theory
                          FROM exam_attempts ea
                          JOIN exams e ON ea.exam_id = e.id
                          JOIN exam_subjects es ON ea.exam_subject_id = es.id
                          JOIN classes c ON es.class_id = c.id
                          JOIN subjects s ON es.subject_id = s.id
                          WHERE ea.student_id = ? AND ea.submitted_at IS NOT NULL
                          ORDER BY ea.submitted_at DESC");
    $stmt->bind_param("i", $current_user_id); // Use variable instead of function call
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $exam_history[] = $row;
    }
} catch (Exception $e) {
    $message = 'Error loading exam history: ' . $e->getMessage();
}
?>

<h5>Exam History</h5>

<?php if ($message): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if (count($exam_history) > 0): ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Exam</th>
                    <th>Class - Subject</th>
                    <th>Time Limit</th>
                    <th>Score</th>
                    <th>Total Questions</th>
                    <th>Percentage</th>
                    <th>Submitted At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($exam_history as $exam): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($exam['exam_title']); ?></td>
                        <td><?php echo htmlspecialchars($exam['class_name']); ?> - <?php echo htmlspecialchars($exam['subject_name']); ?></td>
                        <td><?php echo $exam['time_limit']; ?> minutes</td>
                        <td><?php echo number_format($exam['score'], 2); ?></td>
                        <td><?php echo $exam['total_questions']; ?></td>
                        <td>
                            <?php 
                            // Calculate percentage based on maximum possible score
                            $objective_questions = (int)($exam['objective_questions'] ?? $exam['total_questions']);
                            $theory_total_marks = (float)($exam['theory_total_marks'] ?? 0);
                            $max_possible_score = ($objective_questions * (float)$exam['marks_per_question']) + $theory_total_marks;
                            if ($max_possible_score > 0) {
                                $percentage = ($exam['score'] / $max_possible_score) * 100;
                                echo round($percentage, 2) . '%';
                                if ((int)($exam['pending_theory'] ?? 0) > 0) {
                                    echo ' <span class="badge bg-warning text-dark">Pending Theory</span>';
                                }
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td><?php echo date('M j, Y g:i A', strtotime($exam['submitted_at'])); ?></td>
                        <td>
                            <a href="print_exam_result.php?attempt_id=<?php echo $exam['attempt_id']; ?>" class="btn btn-sm btn-outline-primary" title="Print Results" target="_blank">
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
        <p>You haven't taken any exams yet.</p>
        <p>Available exams will appear in the "Available Exams" section when your teacher publishes them.</p>
    </div>
<?php endif; ?>
