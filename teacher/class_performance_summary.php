<?php
// Only initialize if not already included
if (!isset($included_from_dashboard)) {
    require_once '../config/database.php';
    require_once '../config/session.php';
    
    // Check if user is logged in and is a teacher or admin
    requireLogin();
    if (!in_array(getUserRole(), ['teacher', 'admin'])) {
        header('Location: ../login.php');
        exit();
    }
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
    
    // Get exams allocated to this teacher's classes
    $stmt = $mysqli->prepare("SELECT DISTINCT e.id, e.title 
                          FROM exams e
                          JOIN exam_subjects es ON e.id = es.exam_id
                          JOIN teacher_allocations ta ON es.class_id = ta.class_id AND es.subject_id = ta.subject_id
                          WHERE ta.teacher_id = ?
                          ORDER BY e.title");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }

    if ($selected_class_id > 0) {
        // Check if the selected class is allocated to this teacher
        $check_stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM teacher_allocations WHERE teacher_id = ? AND class_id = ?");
        $check_stmt->bind_param("ii", $current_user_id, $selected_class_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_row = $check_result->fetch_assoc();
        
        if ($check_row['count'] == 0) {
            $message = 'You do not have permission to view performance summary for this class.';
        } else {
            // Build query based on whether an exam is selected
            if ($selected_exam_id > 0) {
                // Check if the selected exam is allocated to this teacher
                $check_exam_stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM exam_subjects es JOIN teacher_allocations ta ON es.class_id = ta.class_id AND es.subject_id = ta.subject_id WHERE ta.teacher_id = ? AND es.class_id = ? AND es.exam_id = ?");
                $check_exam_stmt->bind_param("iii", $current_user_id, $selected_class_id, $selected_exam_id);
                $check_exam_stmt->execute();
                $check_exam_result = $check_exam_stmt->get_result();
                $check_exam_row = $check_exam_result->fetch_assoc();
                
                if ($check_exam_row['count'] == 0) {
                    $message = 'You do not have permission to view performance summary for this exam.';
                } else {
                    // Query to get class performance summary per subject for specific exam
                    $sql = "SELECT 
                                s.name AS subject_name,
                                COUNT(ea.student_id) AS total_students,
                                SUM(CASE WHEN (ea.score / (ea.total_questions * COALESCE(c.marks_per_question, 1))) * 100 >= ? THEN 1 ELSE 0 END) AS passed_count,
                                ROUND(AVG((ea.score / (ea.total_questions * COALESCE(c.marks_per_question, 1))) * 100), 2) AS average_percentage
                            FROM exam_attempts ea
                            JOIN exam_subjects es ON ea.exam_subject_id = es.id
                            JOIN subjects s ON es.subject_id = s.id
                            JOIN classes c ON es.class_id = c.id
                            JOIN teacher_allocations ta ON es.class_id = ta.class_id AND es.subject_id = ta.subject_id
                            WHERE es.class_id = ? AND ta.teacher_id = ? AND ea.exam_id = ?
                            AND ea.submitted_at IS NOT NULL
                            GROUP BY s.id, s.name
                            ORDER BY s.name";

                    $stmt = $mysqli->prepare($sql);
                    $stmt->bind_param("diii", $pass_mark, $selected_class_id, $current_user_id, $selected_exam_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    while ($row = $result->fetch_assoc()) {
                        $row['pass_rate'] = $row['total_students'] > 0 ? round(($row['passed_count'] / $row['total_students']) * 100, 2) : 0;
                        $performance_summary[] = $row;
                    }
                }
            } else {
                // Query to get class performance summary per subject for all exams
                $sql = "SELECT 
                            s.name AS subject_name,
                            COUNT(ea.student_id) AS total_students,
                            SUM(CASE WHEN (ea.score / (ea.total_questions * COALESCE(c.marks_per_question, 1))) * 100 >= ? THEN 1 ELSE 0 END) AS passed_count,
                            ROUND(AVG((ea.score / (ea.total_questions * COALESCE(c.marks_per_question, 1))) * 100), 2) AS average_percentage
                        FROM exam_attempts ea
                        JOIN exam_subjects es ON ea.exam_subject_id = es.id
                        JOIN subjects s ON es.subject_id = s.id
                        JOIN classes c ON es.class_id = c.id
                        JOIN teacher_allocations ta ON es.class_id = ta.class_id AND es.subject_id = ta.subject_id
                        WHERE es.class_id = ? AND ta.teacher_id = ?
                        AND ea.submitted_at IS NOT NULL
                        GROUP BY s.id, s.name
                        ORDER BY s.name";

                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param("dii", $pass_mark, $selected_class_id, $current_user_id);
                $stmt->execute();
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    $row['pass_rate'] = $row['total_students'] > 0 ? round(($row['passed_count'] / $row['total_students']) * 100, 2) : 0;
                    $performance_summary[] = $row;
                }
            }
        }
    }
} catch (Exception $e) {
    $message = 'Error loading performance summary: ' . $e->getMessage();
}
?>

<div class="glass-panel p-4 animate-fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-800 mb-0"><i class="fas fa-chart-line text-primary me-2"></i>Class Performance Analytics</h4>
        <div class="badge bg-primary-soft text-primary rounded-pill px-3 py-2">
            <i class="fas fa-clock me-1"></i> Term Analysis
        </div>
    </div>

    <!-- Modern Filter Toolbar -->
    <div class="bg-light rounded-xl p-4 mb-5 border border-white">
        <form method="GET">
            <input type="hidden" name="tab" value="performance_summary">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-700 text-muted uppercase">Select Class</label>
                    <select name="class_id" id="class_id" class="form-select form-select-lg rounded-xl">
                        <option value="0">Choose Class...</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo ($selected_class_id == $class['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-700 text-muted uppercase">Select Exam</label>
                    <select name="exam_id" id="exam_id" class="form-select form-select-lg rounded-xl">
                        <option value="0">All Examinations</option>
                        <?php foreach ($exams as $exam): ?>
                            <option value="<?php echo $exam['id']; ?>" <?php echo ($selected_exam_id == $exam['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($exam['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-700 text-muted uppercase">Pass Mark (%)</label>
                    <input type="number" name="pass_mark" id="pass_mark" class="form-control form-control-lg rounded-xl" min="0" max="100" step="0.1" value="<?php echo $pass_mark; ?>">
                </div>
                <div class="col-md-3">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill px-4 flex-grow-1 shadow-primary">
                            <i class="fas fa-search me-1"></i> Analyze
                        </button>
                        <?php if ($selected_class_id > 0): ?>
                            <a href="?tab=performance_summary" class="btn btn-light btn-lg rounded-pill px-3">
                                <i class="fas fa-redo"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-info rounded-xl shadow-sm mb-4 border-0">
            <i class="fas fa-info-circle me-2"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($selected_class_id > 0 && count($performance_summary) > 0): ?>
        <!-- Summary Cards Grid -->
        <?php 
        $total_subjects = count($performance_summary);
        $overall_pass_rate = $total_subjects > 0 ? array_sum(array_column($performance_summary, 'pass_rate')) / $total_subjects : 0;
        $overall_avg_score = $total_subjects > 0 ? array_sum(array_column($performance_summary, 'average_percentage')) / $total_subjects : 0;
        ?>
        
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-xl p-4 bg-white text-center">
                    <div class="stat-icon bg-primary-soft text-primary mx-auto mb-3">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h3 class="fw-800 mb-1"><?php echo $total_subjects; ?></h3>
                    <span class="text-muted small fw-700 text-uppercase">Subjects Analyzed</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-xl p-4 bg-white text-center">
                    <div class="stat-icon bg-success-soft text-success mx-auto mb-3">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <h3 class="fw-800 mb-1"><?php echo round($overall_pass_rate, 1); ?>%</h3>
                    <span class="text-muted small fw-700 text-uppercase">Avg. Pass Rate</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-xl p-4 bg-white text-center">
                    <div class="stat-icon bg-info-soft text-info mx-auto mb-3">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3 class="fw-800 mb-1"><?php echo round($overall_avg_score, 1); ?>%</h3>
                    <span class="text-muted small fw-700 text-uppercase">Class Performance Index</span>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-xl overflow-hidden">
            <div class="card-header bg-white p-4 border-0">
                <h5 class="fw-800 mb-0">Detailed Subject Performance</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 text-muted small fw-700 text-uppercase">Subject</th>
                            <th class="py-3 text-muted small fw-700 text-uppercase text-center">Population</th>
                            <th class="py-3 text-muted small fw-700 text-uppercase text-center">Pass/Fail</th>
                            <th class="py-3 text-muted small fw-700 text-uppercase">Pass Rate</th>
                            <th class="py-3 text-muted small fw-700 text-uppercase text-end pe-4">Avg. Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($performance_summary as $summary): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-800 text-dark"><?php echo htmlspecialchars($summary['subject_name']); ?></div>
                                    <div class="small text-muted">Performance Summary</div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark rounded-pill px-3"><?php echo $summary['total_students']; ?> Students</span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <span class="text-success fw-700"><i class="fas fa-caret-up me-1"></i><?php echo $summary['passed_count']; ?></span>
                                        <span class="text-danger fw-700"><i class="fas fa-caret-down me-1"></i><?php echo $summary['total_students'] - $summary['passed_count']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px; border-radius: 100px;">
                                            <div class="progress-bar <?php echo $summary['pass_rate'] >= 50 ? 'bg-success' : 'bg-danger'; ?>" 
                                                 style="width: <?php echo $summary['pass_rate']; ?>%"></div>
                                        </div>
                                        <span class="fw-800 small <?php echo $summary['pass_rate'] >= 50 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $summary['pass_rate']; ?>%
                                        </span>
                                    </div>
                                </td>
                                <td class="text-end pe-4">
                                    <span class="fs-5 fw-800 <?php echo $summary['average_percentage'] >= $pass_mark ? 'text-primary' : 'text-warning'; ?>">
                                        <?php echo $summary['average_percentage']; ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif ($selected_class_id > 0): ?>
        <div class="text-center py-5">
            <div class="stat-icon bg-light text-muted mx-auto mb-3">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <h5 class="fw-800">No Data Available</h5>
            <p class="text-muted">There are no completed exam results for this selection yet.</p>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <div class="stat-icon bg-primary-soft text-primary mx-auto mb-3">
                <i class="fas fa-filter"></i>
            </div>
            <h5 class="fw-800">Selection Required</h5>
            <p class="text-muted">Please choose a class from the menu above to generate analytics.</p>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passMarkInput = document.getElementById('pass_mark');
    if(passMarkInput) {
        passMarkInput.addEventListener('change', function() {
            const value = parseFloat(this.value);
            if (value < 0 || value > 100) {
                alert('Pass mark must be between 0 and 100');
                this.value = 50.0;
            }
        });
    }
});
</script>