<?php
require_once '../includes/results_sheet.php';
$result_exams = [];
$results_error = '';
try {
    $mysqli = getDBConnection();
    if (!$mysqli) throw new RuntimeException('Database connection failed.');
    $result_exams = resultSheetExamList($mysqli, (int)getUserId());
} catch (Throwable $e) {
    $results_error = 'Results are temporarily unavailable.';
}
?>
<h5>My Examination Results</h5>
<p class="text-muted">Published results for examinations you have submitted.</p>
<?php if ($results_error): ?><div class="alert alert-warning"><?php echo htmlspecialchars($results_error); ?></div><?php endif; ?>
<?php if (!$results_error && !$result_exams): ?>
    <div class="alert alert-info mb-0">No published examination results are available yet.</div>
<?php elseif ($result_exams): ?>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead><tr><th>Examination</th><th>Academic Session</th><th>Subjects submitted</th><th>Submitted</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($result_exams as $exam): ?>
                <tr>
                    <td><?php echo htmlspecialchars($exam['title']); ?></td>
                    <td><?php echo htmlspecialchars($exam['academic_session'] ?: 'Not recorded'); ?></td>
                    <td><?php echo (int)$exam['subjects_submitted']; ?></td>
                    <td><?php echo htmlspecialchars(date('M j, Y', strtotime($exam['last_submitted_at']))); ?></td>
                    <td><?php if ((int)$exam['pending_subjects'] > 0): ?><span class="badge bg-warning text-dark">Marking pending</span><?php else: ?><span class="badge bg-success">Available</span><?php endif; ?></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="result_sheet.php?exam_id=<?php echo (int)$exam['id']; ?>">View Results</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
