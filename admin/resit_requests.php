<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();
if (getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$mysqli = getDBConnection();
$message = isset($_GET['message']) ? $_GET['message'] : '';
$message_type = 'info';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
        $message = 'Invalid session token. Please refresh and try again.';
        $message_type = 'danger';
    } else {
        $request_id = (int)($_POST['request_id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        $admin_note = trim($_POST['admin_note'] ?? '');
        $resit_date = trim($_POST['resit_date'] ?? '');
        $resit_start_time = trim($_POST['resit_start_time'] ?? '');
        $resit_end_time = trim($_POST['resit_end_time'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        $penalty_percentage = filter_var($_POST['penalty_percentage'] ?? null, FILTER_VALIDATE_FLOAT);

        try {
            $mysqli->begin_transaction();
            $stmt = $mysqli->prepare("SELECT rr.*, e.title AS exam_title, s.name AS subject_name,
                                             c.name AS class_name, u.full_name AS student_name
                                      FROM resit_requests rr
                                      JOIN exams e ON e.id = rr.exam_id
                                      JOIN exam_subjects es ON es.id = rr.exam_subject_id
                                      JOIN subjects s ON s.id = es.subject_id
                                      JOIN classes c ON c.id = es.class_id
                                      JOIN users u ON u.id = rr.student_id
                                      WHERE rr.id = ? AND rr.status = 'pending'
                                      FOR UPDATE");
            $stmt->bind_param('i', $request_id);
            $stmt->execute();
            $request = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$request) {
                throw new Exception('This request has already been reviewed or does not exist.');
            }

            if ($decision === 'approve') {
                if ($penalty_percentage === false || $penalty_percentage < 0 || $penalty_percentage > 100) {
                    throw new Exception('Penalty percentage must be between 0 and 100.');
                }
                if ($resit_date === '' || $resit_start_time === '' || $resit_end_time === '' || $venue === '') {
                    throw new Exception('Date, start time, end time, and venue are required for approval.');
                }
                if ($resit_end_time <= $resit_start_time) {
                    throw new Exception('The resit end time must be after the start time.');
                }

                $stmt = $mysqli->prepare("SELECT es.subject_id, es.class_id, es.time_limit, e.description
                                          FROM exam_subjects es
                                          JOIN exams e ON e.id = es.exam_id
                                          WHERE es.id = ?");
                $stmt->bind_param('i', $request['exam_subject_id']);
                $stmt->execute();
                $source = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$source) {
                    throw new Exception('The original exam subject could not be found.');
                }

                $resit_title = 'Resit - ' . $request['subject_name'] . ' - ' . $request['student_name'];
                $resit_description = 'Individual resit for ' . $request['exam_title'] . ' / ' . $request['subject_name'];
                $admin_id = (int)getUserId();
                $stmt = $mysqli->prepare("INSERT INTO exams (title, description, is_published, created_by) VALUES (?, ?, 0, ?)");
                $stmt->bind_param('ssi', $resit_title, $resit_description, $admin_id);
                $stmt->execute();
                $resit_exam_id = $mysqli->insert_id;
                $stmt->close();

                $stmt = $mysqli->prepare("INSERT INTO exam_subjects (exam_id, subject_id, class_id, resit_request_id, time_limit, is_published) VALUES (?, ?, ?, ?, ?, 0)");
                $stmt->bind_param('iiiii', $resit_exam_id, $source['subject_id'], $source['class_id'], $request_id, $source['time_limit']);
                $stmt->execute();
                $resit_exam_subject_id = $mysqli->insert_id;
                $stmt->close();

                $stmt = $mysqli->prepare("UPDATE resit_requests
                                          SET status = 'approved', admin_note = ?, resit_date = ?,
                                              resit_start_time = ?, resit_end_time = ?, venue = ?,
                                              penalty_percentage = ?,
                                              approved_by = ?, reviewed_at = NOW(),
                                              resit_exam_id = ?, resit_exam_subject_id = ?
                                          WHERE id = ?");
                $stmt->bind_param('sssssdiiii', $admin_note, $resit_date, $resit_start_time, $resit_end_time, $venue, $penalty_percentage, $admin_id, $resit_exam_id, $resit_exam_subject_id, $request_id);
                $stmt->execute();
                $stmt->close();
                $mysqli->commit();
                $message = 'Resit approved. The teacher can now create the individual question set.';
                $message_type = 'success';
            } elseif ($decision === 'reject') {
                $stmt = $mysqli->prepare("UPDATE resit_requests SET status = 'rejected', admin_note = ?, approved_by = ?, reviewed_at = NOW() WHERE id = ?");
                $admin_id = (int)getUserId();
                $stmt->bind_param('sii', $admin_note, $admin_id, $request_id);
                $stmt->execute();
                $stmt->close();
                $mysqli->commit();
                $message = 'Resit request rejected.';
                $message_type = 'success';
            } else {
                throw new Exception('Invalid decision.');
            }
        } catch (Exception $e) {
            $mysqli->rollback();
            $message = 'Unable to process request: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

$requests = [];
try {
    $stmt = $mysqli->prepare("SELECT rr.*, e.title AS exam_title, s.name AS subject_name,
                                     c.name AS class_name, u.full_name AS student_name,
                                     t.full_name AS teacher_name,
                                     res.is_published AS resit_subject_published,
                                     (SELECT COUNT(*) FROM questions rq
                                      WHERE rq.exam_subject_id = rr.resit_exam_subject_id) AS resit_question_count
                              FROM resit_requests rr
                              JOIN exams e ON e.id = rr.exam_id
                              JOIN exam_subjects es ON es.id = rr.exam_subject_id
                              JOIN subjects s ON s.id = es.subject_id
                              JOIN classes c ON c.id = es.class_id
                              JOIN users u ON u.id = rr.student_id
                              JOIN users t ON t.id = rr.requested_by
                              LEFT JOIN exam_subjects res ON res.id = rr.resit_exam_subject_id
                              ORDER BY FIELD(rr.status, 'pending', 'approved', 'rejected'), rr.requested_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    $database_name = 'unknown';
    if ($mysqli instanceof mysqli) {
        $database_result = $mysqli->query('SELECT DATABASE()');
        if ($database_result) {
            $database_name = (string)$database_result->fetch_row()[0];
        }
    }
    error_log('Resit requests query failed for database ' . $database_name . ': ' . $e->getMessage());
    $message = 'Resit requests could not be loaded from database ' . $database_name . ': ' . $e->getMessage();
    $message_type = 'danger';
}
?>

<?php include '../includes/header.php'; ?>

<div class="dashboard-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="dashboard-title"><i class="fas fa-clipboard-check me-2"></i> Resit Requests</h1>
                <p class="mb-0">Review teacher requests and schedule approved individual resits</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="dashboard.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i> Admin Dashboard</a>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <div class="alert alert-info">
            Approved resits are private and visible only to the approved student after the teacher adds questions. The administrator can still publish the resit from Exam Management when required.
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr><th>Student</th><th>Exam / Subject</th><th>Teacher</th><th>Status</th><th>Schedule / Action</th></tr>
                </thead>
                <tbody>
                <?php if ($requests): ?>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($request['student_name']); ?></strong><div class="small text-muted"><?php echo htmlspecialchars($request['class_name']); ?></div></td>
                            <td><?php echo htmlspecialchars($request['exam_title']); ?><div class="small text-muted"><?php echo htmlspecialchars($request['subject_name']); ?></div></td>
                            <td><?php echo htmlspecialchars($request['teacher_name']); ?><div class="small text-muted"><?php echo htmlspecialchars($request['requested_at']); ?></div></td>
                            <td><span class="badge bg-<?php echo $request['status'] === 'pending' ? 'warning text-dark' : ($request['status'] === 'approved' ? 'success' : 'danger'); ?>"><?php echo ucfirst($request['status']); ?></span></td>
                            <td>
                                <?php if ($request['status'] === 'pending'): ?>
                                    <form method="POST" class="row g-2">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
                                        <div class="col-md-6"><input type="date" name="resit_date" class="form-control form-control-sm"></div>
                                        <div class="col-md-3"><input type="time" name="resit_start_time" class="form-control form-control-sm"></div>
                                        <div class="col-md-3"><input type="time" name="resit_end_time" class="form-control form-control-sm"></div>
                                        <div class="col-md-7"><input type="text" name="venue" class="form-control form-control-sm" placeholder="Venue"></div>
                                        <div class="col-md-5"><input type="text" name="admin_note" class="form-control form-control-sm" placeholder="Note (optional)"></div>
                                        <div class="col-md-5"><label class="form-label small mb-1">Penalty (%)</label><input type="number" name="penalty_percentage" class="form-control form-control-sm" min="0" max="100" step="0.01" value="0" required></div>
                                        <div class="col-12 d-flex gap-2"><button name="decision" value="approve" class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i> Approve & Schedule</button><button name="decision" value="reject" class="btn btn-outline-danger btn-sm"><i class="fas fa-times me-1"></i> Reject</button></div>
                                    </form>
                                <?php else: ?>
                                    <div><?php echo $request['resit_date'] ? htmlspecialchars(date('M j, Y', strtotime($request['resit_date']))) : '—'; ?></div>
                                    <?php if ($request['status'] === 'approved'): ?><div class="small">Penalty: <?php echo number_format((float)$request['penalty_percentage'], 2); ?>%</div><?php endif; ?>
                                    <div class="small text-muted"><?php echo $request['resit_start_time'] ? htmlspecialchars(date('g:i A', strtotime($request['resit_start_time'])) . ' - ' . date('g:i A', strtotime($request['resit_end_time'])) . ' · ' . $request['venue']) : ''; ?></div>
                                    <?php if ($request['status'] === 'approved' && (int)$request['resit_question_count'] === 0): ?><div class="small text-warning mt-1">Awaiting teacher question setup</div><?php elseif ($request['status'] === 'approved' && !$request['resit_subject_published']): ?><div class="small text-success mt-1">Questions ready — available to the approved student</div><?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">No resit requests found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
