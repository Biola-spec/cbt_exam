<?php
$mysqli = $mysqli ?? getDBConnection();
$resit_notices = [];
try {
    $student_id = (int)getUserId();
    $stmt = $mysqli->prepare("SELECT rr.id, rr.status, rr.reason, rr.admin_note, rr.resit_date,
                                     rr.resit_start_time, rr.resit_end_time, rr.venue,
                                     e.title AS exam_title, s.name AS subject_name,
                                     c.name AS class_name, res.is_published AS resit_subject_published
                              FROM resit_requests rr
                              JOIN exams e ON e.id = rr.exam_id
                              JOIN exam_subjects es ON es.id = rr.exam_subject_id
                              JOIN subjects s ON s.id = es.subject_id
                              JOIN classes c ON c.id = es.class_id
                              LEFT JOIN exam_subjects res ON res.id = rr.resit_exam_subject_id
                              WHERE rr.student_id = ?
                                AND rr.status IN ('pending', 'approved')
                              ORDER BY rr.requested_at DESC
                              LIMIT 5");
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $resit_notices[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    $resit_notices = [];
}
?>

<?php foreach ($resit_notices as $notice): ?>
    <?php $notice_class = $notice['status'] === 'approved' ? 'success' : ($notice['status'] === 'rejected' ? 'danger' : 'warning'); ?>
    <div class="alert alert-<?php echo $notice_class; ?> border-<?php echo $notice_class; ?> border-2 shadow-sm mb-4">
        <div class="d-flex align-items-start gap-3">
            <i class="fas fa-<?php echo $notice['status'] === 'approved' ? 'calendar-check' : ($notice['status'] === 'rejected' ? 'times-circle' : 'bullhorn'); ?> fa-2x mt-1"></i>
            <div class="flex-grow-1">
                <h4 class="alert-heading fw-bold mb-2">
                    <?php echo $notice['status'] === 'approved' ? 'RESIT APPROVED' : ($notice['status'] === 'rejected' ? 'RESIT REQUEST DECLINED' : 'RESIT REQUEST NOTICE'); ?>
                </h4>
                <p class="mb-1"><strong><?php echo htmlspecialchars($notice['subject_name']); ?></strong> - <?php echo htmlspecialchars($notice['exam_title']); ?></p>
                <?php if ($notice['status'] === 'approved'): ?>
                    <p class="mb-1"><strong>Date:</strong> <?php echo htmlspecialchars(date('l, F j, Y', strtotime($notice['resit_date']))); ?></p>
                    <p class="mb-1"><strong>Time:</strong> <?php echo htmlspecialchars(date('g:i A', strtotime($notice['resit_start_time'])) . ' - ' . date('g:i A', strtotime($notice['resit_end_time']))); ?></p>
                    <p class="mb-1"><strong>Venue:</strong> <?php echo htmlspecialchars($notice['venue']); ?></p>
                    <?php if (!$notice['resit_subject_published']): ?><p class="mb-0 fw-semibold">Your resit questions are being prepared. The exam will appear under Available Exams after your teacher completes the question set.</p><?php endif; ?>
                <?php elseif ($notice['status'] === 'pending'): ?>
                    <p class="mb-0">Your teacher has requested that you resit this subject. The administrator is reviewing the request and will publish the date, time, and venue here.</p>
                <?php else: ?>
                    <p class="mb-0"><?php echo htmlspecialchars($notice['admin_note'] ?: 'The administrator did not approve this resit request.'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
