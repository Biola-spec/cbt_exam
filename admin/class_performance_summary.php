<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is logged in and is an admin
requireLogin();
if (getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$performance_summary = [];
$classes = [];
$exams = [];
$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selected_exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$pass_mark = isset($_GET['pass_mark']) ? (float)$_GET['pass_mark'] : 50.0; // Default pass mark is 50%

try {
    $mysqli = getDBConnection();

    // Get all classes
    $stmt = $mysqli->prepare("SELECT id, name FROM classes ORDER BY name");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
    
    // Get all exams
    $stmt = $mysqli->prepare("SELECT id, title FROM exams ORDER BY title");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }

    if ($selected_class_id > 0) {
        // Build query based on whether an exam is selected
        if ($selected_exam_id > 0) {
            // Query to get class performance summary per subject for specific exam
            $sql = "SELECT 
                        s.name AS subject_name,
                        COUNT(ea.student_id) AS total_students,
                        SUM(CASE WHEN (ea.score / COALESCE(NULLIF(((SELECT COUNT(*) FROM questions q WHERE q.exam_subject_id = ea.exam_subject_id AND COALESCE(q.question_type, 'objective') = 'objective') * COALESCE(c.marks_per_question, 1)) + (SELECT COALESCE(SUM(q.theory_marks), 0) FROM questions q WHERE q.exam_subject_id = ea.exam_subject_id AND q.question_type = 'theory'), 0), ea.total_questions * COALESCE(c.marks_per_question, 1))) * 100 >= ? THEN 1 ELSE 0 END) AS passed_count,
                        ROUND(AVG((ea.score / COALESCE(NULLIF(((SELECT COUNT(*) FROM questions q WHERE q.exam_subject_id = ea.exam_subject_id AND COALESCE(q.question_type, 'objective') = 'objective') * COALESCE(c.marks_per_question, 1)) + (SELECT COALESCE(SUM(q.theory_marks), 0) FROM questions q WHERE q.exam_subject_id = ea.exam_subject_id AND q.question_type = 'theory'), 0), ea.total_questions * COALESCE(c.marks_per_question, 1))) * 100), 2) AS average_percentage
                    FROM exam_attempts ea
                    JOIN exam_subjects es ON ea.exam_subject_id = es.id
                    JOIN subjects s ON es.subject_id = s.id
                    JOIN classes c ON es.class_id = c.id
                    WHERE es.class_id = ? AND ea.exam_id = ?
                    AND ea.submitted_at IS NOT NULL
                    GROUP BY s.id, s.name
                    ORDER BY s.name";
            
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("dii", $pass_mark, $selected_class_id, $selected_exam_id);
        } else {
            // Query to get class performance summary per subject for all exams
            $sql = "SELECT 
                        s.name AS subject_name,
                        COUNT(ea.student_id) AS total_students,
                        SUM(CASE WHEN (ea.score / COALESCE(NULLIF(((SELECT COUNT(*) FROM questions q WHERE q.exam_subject_id = ea.exam_subject_id AND COALESCE(q.question_type, 'objective') = 'objective') * COALESCE(c.marks_per_question, 1)) + (SELECT COALESCE(SUM(q.theory_marks), 0) FROM questions q WHERE q.exam_subject_id = ea.exam_subject_id AND q.question_type = 'theory'), 0), ea.total_questions * COALESCE(c.marks_per_question, 1))) * 100 >= ? THEN 1 ELSE 0 END) AS passed_count,
                        ROUND(AVG((ea.score / COALESCE(NULLIF(((SELECT COUNT(*) FROM questions q WHERE q.exam_subject_id = ea.exam_subject_id AND COALESCE(q.question_type, 'objective') = 'objective') * COALESCE(c.marks_per_question, 1)) + (SELECT COALESCE(SUM(q.theory_marks), 0) FROM questions q WHERE q.exam_subject_id = ea.exam_subject_id AND q.question_type = 'theory'), 0), ea.total_questions * COALESCE(c.marks_per_question, 1))) * 100), 2) AS average_percentage
                    FROM exam_attempts ea
                    JOIN exam_subjects es ON ea.exam_subject_id = es.id
                    JOIN subjects s ON es.subject_id = s.id
                    JOIN classes c ON es.class_id = c.id
                    WHERE es.class_id = ?
                    AND ea.submitted_at IS NOT NULL
                    GROUP BY s.id, s.name
                    ORDER BY s.name";
            
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("di", $pass_mark, $selected_class_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $row['pass_rate'] = $row['total_students'] > 0 ? round(($row['passed_count'] / $row['total_students']) * 100, 2) : 0;
            $performance_summary[] = $row;
        }
    }
} catch (Exception $e) {
    $message = 'Error loading performance summary: ' . $e->getMessage();
}
?>

<h5>Class Performance Summary per Subject</h5>

<!-- Filter Form -->
<form method="GET" class="mb-4">
    <input type="hidden" name="tab" value="performance_summary">
    <div class="row">
        <div class="col-md-3">
            <label for="class_id" class="form-label">Select Class:</label>
            <select name="class_id" id="class_id" class="form-select">
                <option value="0">Select a Class</option>
                <?php foreach ($classes as $class): ?>
                    <option value="<?php echo $class['id']; ?>" <?php echo ($selected_class_id == $class['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($class['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="exam_id" class="form-label">Select Exam:</label>
            <select name="exam_id" id="exam_id" class="form-select">
                <option value="0">All Exams</option>
                <?php foreach ($exams as $exam): ?>
                    <option value="<?php echo $exam['id']; ?>" <?php echo ($selected_exam_id == $exam['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($exam['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="pass_mark" class="form-label">Pass Mark (%):</label>
            <input type="number" name="pass_mark" id="pass_mark" class="form-control" min="0" max="100" step="0.1" value="<?php echo $pass_mark; ?>">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary">Show Summary</button>
            <?php if ($selected_class_id > 0): ?>
                <a href="?tab=performance_summary" class="btn btn-secondary ms-2">Clear</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if ($message): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($selected_class_id > 0 && count($performance_summary) > 0): ?>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0">Performance Summary for <?php echo htmlspecialchars($classes[array_search($selected_class_id, array_column($classes, 'id'))]['name']); ?></h6>
            <a href="../teacher/export_class_excel.php?class_id=<?php echo $selected_class_id; ?>&exam_id=<?php echo $selected_exam_id; ?>&pass_mark=<?php echo $pass_mark; ?>" class="btn btn-sm btn-success">
                <i class="fas fa-file-excel me-1"></i> Download Class Excel Broadsheet
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Total Students</th>
                            <th>Passed</th>
                            <th>Failed</th>
                            <th>Pass Rate (%)</th>
                            <th>Average Score (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($performance_summary as $summary): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($summary['subject_name']); ?></strong></td>
                                <td><?php echo $summary['total_students']; ?></td>
                                <td><?php echo $summary['passed_count']; ?></td>
                                <td><?php echo $summary['total_students'] - $summary['passed_count']; ?></td>
                                <td>
                                    <span class="<?php echo $summary['pass_rate'] >= 50 ? 'text-success' : 'text-danger'; ?>">
                                        <strong><?php echo $summary['pass_rate']; ?>%</strong>
                                    </span>
                                </td>
                                <td>
                                    <span class="<?php echo $summary['average_percentage'] >= $pass_mark ? 'text-success' : 'text-danger'; ?>">
                                        <strong><?php echo $summary['average_percentage']; ?>%</strong>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Summary Statistics -->
            <?php 
            $total_subjects = count($performance_summary);
            $overall_pass_rate = $total_subjects > 0 ? array_sum(array_column($performance_summary, 'pass_rate')) / $total_subjects : 0;
            $overall_avg_score = $total_subjects > 0 ? array_sum(array_column($performance_summary, 'average_percentage')) / $total_subjects : 0;
            ?>
            
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h5 class="card-title"><?php echo $total_subjects; ?></h5>
                            <p class="card-text">Total Subjects</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card <?php echo $overall_pass_rate >= 50 ? 'bg-success text-white' : 'bg-danger text-white'; ?>">
                        <div class="card-body text-center">
                            <h5 class="card-title"><?php echo round($overall_pass_rate, 2); ?>%</h5>
                            <p class="card-text">Overall Pass Rate</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card <?php echo $overall_avg_score >= $pass_mark ? 'bg-success text-white' : 'bg-warning text-dark'; ?>">
                        <div class="card-body text-center">
                            <h5 class="card-title"><?php echo round($overall_avg_score, 2); ?>%</h5>
                            <p class="card-text">Average Class Score</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php elseif ($selected_class_id > 0): ?>
    <div class="alert alert-info">
        <p>No exam results available for this class yet.</p>
        <p>Students need to complete exams before performance data will appear here.</p>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <p>Please select a class to view performance summary per subject.</p>
    </div>
<?php endif; ?>

<script>
// Update pass mark input validation
document.addEventListener('DOMContentLoaded', function() {
    const passMarkInput = document.getElementById('pass_mark');
    
    passMarkInput.addEventListener('change', function() {
        const value = parseFloat(this.value);
        if (value < 0 || value > 100) {
            alert('Pass mark must be between 0 and 100');
            this.value = 50.0; // Reset to default
        }
    });
});
</script>