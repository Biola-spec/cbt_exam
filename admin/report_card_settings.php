<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/term_results.php';
requireLogin();
if (getUserRole() !== 'admin') { header('Location: ../login.php'); exit(); }

$mysqli = getDBConnection();
if (!$mysqli) die('Database connection failed.');
$sessions = termResultsSessionRows($mysqli);
$selectedSession = (int)($_REQUEST['session_id'] ?? 0);
if (!$selectedSession) foreach ($sessions as $session) if ((int)$session['is_current'] === 1) $selectedSession = (int)$session['id'];
if (!$selectedSession && $sessions) $selectedSession = (int)$sessions[0]['id'];
$selectedTerm = (int)($_REQUEST['term'] ?? 1);
if ($selectedTerm < 1 || $selectedTerm > 3) $selectedTerm = 1;
$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!termResultsVerifyCsrf($_POST['csrf_token'] ?? '')) {
        $message = 'Your session expired. Refresh and try again.';
        $messageType = 'warning';
    } else {
        try {
            $action = $_POST['action'] ?? '';
            if ($action === 'save_days') {
                $selectedSession = (int)($_POST['session_id'] ?? 0);
                $selectedTerm = (int)($_POST['term'] ?? 1);
                $daysOpen = filter_var($_POST['school_days_open'] ?? null, FILTER_VALIDATE_INT);
                if (!in_array($selectedSession, array_map(static fn($session) => (int)$session['id'], $sessions), true) || $selectedTerm < 1 || $selectedTerm > 3 || $daysOpen === false || $daysOpen < 0 || $daysOpen > 366) {
                    throw new RuntimeException('Choose a valid session and term, and enter between 0 and 366 school days.');
                }
                $adminId = (int)getUserId();
                $stmt = $mysqli->prepare('INSERT INTO term_report_settings (session_id, term, school_days_open, updated_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE school_days_open=VALUES(school_days_open), updated_by=VALUES(updated_by)');
                $stmt->bind_param('iiii', $selectedSession, $selectedTerm, $daysOpen, $adminId);
                $stmt->execute();
                $stmt->close();
                $message = 'School days opened saved for this term.';
            } elseif ($action === 'add_category') {
                $domain = (string)($_POST['domain'] ?? '');
                $label = trim((string)($_POST['label'] ?? ''));
                if (!in_array($domain, ['psychomotor', 'locomotor'], true) || $label === '' || mb_strlen($label) > 80) throw new RuntimeException('Choose a skill group and enter a category name up to 80 characters.');
                $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
                $adminId = (int)getUserId();
                $stmt = $mysqli->prepare('INSERT INTO class_report_categories (domain, label, sort_order, created_by) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('ssii', $domain, $label, $sortOrder, $adminId);
                $stmt->execute();
                $stmt->close();
                $message = 'Report-card skill added.';
            } elseif ($action === 'toggle_category') {
                $categoryId = (int)($_POST['category_id'] ?? 0);
                $nextActive = (int)($_POST['next_active'] ?? 0) === 1 ? 1 : 0;
                $stmt = $mysqli->prepare('UPDATE class_report_categories SET is_active=? WHERE id=?');
                $stmt->bind_param('ii', $nextActive, $categoryId);
                $stmt->execute();
                $stmt->close();
                $message = $nextActive ? 'Report-card skill reactivated.' : 'Report-card skill deactivated. Existing report history is preserved.';
            } elseif ($action === 'delete_category') {
                $categoryId = (int)($_POST['category_id'] ?? 0);
                if (!$categoryId) throw new RuntimeException('Choose a valid report-card skill to delete.');
                $stmt = $mysqli->prepare('DELETE FROM class_report_categories WHERE id=?');
                $stmt->bind_param('i', $categoryId);
                $stmt->execute();
                $deleted = $stmt->affected_rows;
                $stmt->close();
                if (!$deleted) throw new RuntimeException('That report-card skill no longer exists.');
                $message = 'Report-card skill and its saved student ratings were deleted.';
            } elseif ($action === 'rename_category') {
                $categoryId = (int)($_POST['category_id'] ?? 0);
                $label = trim((string)($_POST['label'] ?? ''));
                if (!$categoryId || $label === '' || mb_strlen($label) > 80) throw new RuntimeException('Enter a skill name up to 80 characters.');
                $stmt = $mysqli->prepare('SELECT id FROM class_report_categories WHERE domain=(SELECT domain FROM class_report_categories WHERE id=?) AND label=? AND id<>?');
                $stmt->bind_param('isi', $categoryId, $label, $categoryId);
                $stmt->execute();
                $duplicate = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($duplicate) throw new RuntimeException('That skill name already exists in this group.');
                $stmt = $mysqli->prepare('UPDATE class_report_categories SET label=? WHERE id=?');
                $stmt->bind_param('si', $label, $categoryId);
                $stmt->execute();
                $stmt->close();
                $message = 'Report-card skill renamed. Existing ratings are preserved.';
            }
        } catch (Throwable $e) {
            $message = $e instanceof mysqli_sql_exception ? 'That skill name already exists in this group.' : $e->getMessage();
            $messageType = 'warning';
        }
    }
}

$daysOpen = 0;
if ($selectedSession) {
    $stmt = $mysqli->prepare('SELECT school_days_open FROM term_report_settings WHERE session_id=? AND term=?');
    $stmt->bind_param('ii', $selectedSession, $selectedTerm);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) $daysOpen = (int)$row['school_days_open'];
    $stmt->close();
}
$categories = [];
$result = $mysqli->query('SELECT id, domain, label, sort_order, is_active FROM class_report_categories ORDER BY domain, sort_order, label');
while ($row = $result->fetch_assoc()) $categories[] = $row;
include '../includes/header.php';
?>
<main class="container py-4">
    <header class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4"><div><div class="small text-uppercase fw-bold text-primary">Academic reports</div><h1 class="h3 mb-1">Report Card Settings</h1><p class="text-muted mb-0">Configure term attendance totals and skill ratings.</p></div><a class="btn btn-outline-secondary" href="dashboard.php?tab=classes"><i class="fas fa-arrow-left me-1"></i> Admin dashboard</a></header>
    <?php if ($message): ?><div class="alert alert-<?php echo $messageType === 'warning' ? 'warning' : 'info'; ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if (!$sessions): ?><div class="alert alert-warning">Create an academic session in Term Results &amp; Release before configuring report cards.</div><?php else: ?>
        <form method="get" class="card card-body mb-4"><div class="row g-3 align-items-end">
            <div class="col-md-5"><label class="form-label" for="session_id">Academic session</label><select class="form-select" id="session_id" name="session_id"><?php foreach ($sessions as $session): ?><option value="<?php echo (int)$session['id']; ?>" <?php echo (int)$session['id'] === $selectedSession ? 'selected' : ''; ?>><?php echo htmlspecialchars($session['name']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label" for="term">Term</label><select class="form-select" id="term" name="term"><?php for ($term=1; $term<=3; $term++): ?><option value="<?php echo $term; ?>" <?php echo $term === $selectedTerm ? 'selected' : ''; ?>><?php echo htmlspecialchars(termLabel($term)); ?></option><?php endfor; ?></select></div>
            <div class="col-md-3"><button class="btn btn-outline-primary w-100" type="submit"><i class="fas fa-filter me-1"></i> Load term</button></div>
        </div></form>
        <section class="mb-5"><h2 class="h5">Attendance</h2><p class="text-muted">Set the number of days school opened. Assigned class teachers enter each student’s days present; absent days are calculated on the report card.</p>
            <form method="post" class="card card-body"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(termResultsCsrfToken()); ?>"><input type="hidden" name="action" value="save_days"><input type="hidden" name="session_id" value="<?php echo $selectedSession; ?>"><input type="hidden" name="term" value="<?php echo $selectedTerm; ?>"><div class="row g-3 align-items-end">
                <div class="col-md-5"><label class="form-label" for="school_days_open">Days school opened</label><input class="form-control" type="number" min="0" max="366" step="1" id="school_days_open" name="school_days_open" value="<?php echo $daysOpen; ?>" required></div>
                <div class="col-md-4 text-muted small"><?php echo htmlspecialchars($sessions[array_search($selectedSession, array_map(static fn($session) => (int)$session['id'], $sessions), true)]['name'] ?? ''); ?> · <?php echo htmlspecialchars(termLabel($selectedTerm)); ?></div>
                <div class="col-md-3"><button class="btn btn-primary w-100" type="submit"><i class="fas fa-save me-1"></i> Save school days</button></div>
            </div></form>
        </section>
    <?php endif; ?>
    <section class="mb-4"><h2 class="h5">Psychomotor and locomotor skills</h2><p class="text-muted">Add the rating fields teachers should complete. Ratings use a 1–5 scale; deactivating a field keeps past report cards intact.</p>
        <form method="post" class="card card-body mb-3"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(termResultsCsrfToken()); ?>"><input type="hidden" name="action" value="add_category"><div class="row g-3 align-items-end">
            <div class="col-md-4"><label class="form-label" for="domain">Skill group</label><select class="form-select" id="domain" name="domain"><option value="psychomotor">Psychomotor skills</option><option value="locomotor">Locomotor skills</option></select></div>
            <div class="col-md-5"><label class="form-label" for="label">Custom skill name</label><input class="form-control" id="label" name="label" maxlength="80" placeholder="e.g. Handwriting" required></div>
            <div class="col-md-1"><label class="form-label" for="sort_order">Order</label><input class="form-control" type="number" min="0" id="sort_order" name="sort_order" value="0"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="fas fa-plus me-1"></i> Add skill</button></div>
        </div></form>
        <div class="table-responsive"><table class="table table-bordered align-middle"><thead class="table-light"><tr><th>Skill group</th><th>Custom skill</th><th>Order</th><th>Status</th><th></th></tr></thead><tbody>
            <?php if (!$categories): ?><tr><td colspan="5" class="text-center text-muted py-4">No skills configured yet.</td></tr><?php endif; ?>
            <?php foreach ($categories as $category): $categoryFormId = 'category-' . (int)$category['id']; ?><tr><td><?php echo $category['domain'] === 'psychomotor' ? 'Psychomotor' : 'Locomotor'; ?></td><td><input class="form-control form-control-sm" name="label" value="<?php echo htmlspecialchars($category['label']); ?>" maxlength="80" required form="<?php echo $categoryFormId; ?>"></td><td><?php echo (int)$category['sort_order']; ?></td><td><?php echo (int)$category['is_active'] ? 'Active' : 'Inactive'; ?></td><td class="text-end"><div class="d-flex justify-content-end gap-2"><form method="post" id="<?php echo $categoryFormId; ?>"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(termResultsCsrfToken()); ?>"><input type="hidden" name="action" value="rename_category"><input type="hidden" name="category_id" value="<?php echo (int)$category['id']; ?>"><button class="btn btn-sm btn-outline-primary" type="submit">Save name</button></form><form method="post"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(termResultsCsrfToken()); ?>"><input type="hidden" name="action" value="toggle_category"><input type="hidden" name="category_id" value="<?php echo (int)$category['id']; ?>"><input type="hidden" name="next_active" value="<?php echo (int)$category['is_active'] ? 0 : 1; ?>"><button class="btn btn-sm btn-outline-secondary" type="submit"><?php echo (int)$category['is_active'] ? 'Deactivate' : 'Reactivate'; ?></button></form><form method="post" onsubmit="return confirm('Delete this skill and all saved student ratings for it? This cannot be undone.')"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(termResultsCsrfToken()); ?>"><input type="hidden" name="action" value="delete_category"><input type="hidden" name="category_id" value="<?php echo (int)$category['id']; ?>"><button class="btn btn-sm btn-outline-danger" type="submit" title="Delete skill and its ratings" aria-label="Delete <?php echo htmlspecialchars($category['label']); ?>"><i class="fas fa-trash" aria-hidden="true"></i></button></form></div></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
</main>
<?php include '../includes/footer.php'; ?>
