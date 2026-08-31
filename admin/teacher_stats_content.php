<?php
if (!isset($mysqli)) {
    require_once '../config/database.php';
    $mysqli = getDBConnection();
}

// Fetch all exams for the dropdown
$exams = [];
$exam_res = $mysqli->query("SELECT id, title FROM exams ORDER BY created_at DESC");
if ($exam_res) {
    while ($row = $exam_res->fetch_assoc()) {
        $exams[] = $row;
    }
}

$selected_exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : (count($exams) > 0 ? $exams[0]['id'] : 0);

// Fetch all classes for the dropdown
$classes = [];
$class_res = $mysqli->query("SELECT id, name FROM classes ORDER BY name");
if ($class_res) {
    while ($row = $class_res->fetch_assoc()) {
        $classes[] = $row;
    }
}

$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

$stats = [];
if ($selected_exam_id) {
    try {
        $query = "SELECT 
                    es.id as exam_subject_id,
                    s.name as subject_name,
                    c.name as class_name,
                    u.full_name as teacher_name,
                    u.email as teacher_email,
                    (SELECT COUNT(*) FROM questions q WHERE q.exam_subject_id = es.id) as question_count
                  FROM exam_subjects es
                  JOIN subjects s ON es.subject_id = s.id
                  JOIN classes c ON es.class_id = c.id
                  LEFT JOIN teacher_allocations ta ON (ta.subject_id = es.subject_id AND ta.class_id = es.class_id)
                  LEFT JOIN users u ON ta.teacher_id = u.id
                  WHERE es.exam_id = ?";
        
        if ($selected_class_id) {
            $query .= " AND es.class_id = ?";
        }
        
        $query .= " ORDER BY c.name, s.name";
        
        $stmt = $mysqli->prepare($query);
        if ($selected_class_id) {
            $stmt->bind_param("ii", $selected_exam_id, $selected_class_id);
        } else {
            $stmt->bind_param("i", $selected_exam_id);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $stats[] = $row;
        }
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}
?>

<div class="teacher-stats-container">
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i> Report Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="dashboard.php" class="row align-items-center">
                <input type="hidden" name="tab" value="teacher_stats">
                <div class="col-md-6">
                    <label class="form-label">Select Exam</label>
                    <select name="exam_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- All Exams --</option>
                        <?php foreach ($exams as $exam): ?>
                            <option value="<?php echo $exam['id']; ?>" <?php echo $selected_exam_id == $exam['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($exam['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Select Class</label>
                    <select name="class_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">-- All Classes --</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo $selected_class_id == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if (!$selected_exam_id): ?>
        <div class="text-center py-5">
            <i class="fas fa-file-invoice fa-4x text-muted mb-3"></i>
            <h4>No Exam Selected</h4>
            <p class="text-muted">Please select an examination from the dropdown above to view its statistics.</p>
        </div>
    <?php elseif (empty($stats)): ?>
        <div class="text-center py-5">
            <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
            <h4>No Subjects Found for this Exam</h4>
            <p class="text-muted">This examination doesn't have any subjects linked to it yet.</p>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Class & Subject</th>
                                <th>Assigned Teacher</th>
                                <th class="text-center">Questions Set</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['subject_name']); ?></strong>
                                        <div class="small text-muted"><?php echo htmlspecialchars($row['class_name']); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($row['teacher_name']): ?>
                                            <strong><?php echo htmlspecialchars($row['teacher_name']); ?></strong>
                                            <div class="small text-muted"><?php echo htmlspecialchars($row['teacher_email']); ?></div>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="<?php echo $row['question_count'] > 0 ? 'text-primary' : 'text-danger'; ?> fw-bold">
                                            <?php echo $row['question_count']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($row['question_count'] >= 10): ?>
                                            <span class="badge bg-success">Ready</span>
                                        <?php elseif ($row['question_count'] > 0): ?>
                                            <span class="badge bg-info">In Progress</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
