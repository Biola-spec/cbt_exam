<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/term_results.php';
requireLogin();
if (getUserRole() !== 'admin') { header('Location: ../login.php'); exit(); }

$mysqli = getDBConnection();
$message = '';
if (!$mysqli) die('<div class="alert alert-danger">Database connection failed.</div>');
$sessions = termResultsSessionRows($mysqli);
$selectedSession = (int)($_GET['session_id'] ?? ($_POST['session_id'] ?? 0));
if (!$selectedSession && $sessions) {
    foreach ($sessions as $session) if ((int)$session['is_current'] === 1) $selectedSession = (int)$session['id'];
    if (!$selectedSession) $selectedSession = (int)$sessions[0]['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!termResultsVerifyCsrf($_POST['csrf_token'] ?? '')) {
        $message = 'Your session expired. Refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $transactionActive = false;
        try {
            if ($action === 'create_session') {
                $name = trim($_POST['name'] ?? '');
                if (!preg_match('/^\d{4}\s*[-\/]\s*\d{4}$/', $name)) throw new RuntimeException('Enter a session such as 2026/2027.');
                $name = preg_replace('/\s+/', '', str_replace('-', '/', $name));
                $mysqli->begin_transaction();
                $transactionActive = true;
                $mysqli->query('UPDATE academic_sessions SET is_current = 0');
                $stmt = $mysqli->prepare('INSERT INTO academic_sessions (name, is_current, created_by) VALUES (?, 1, ?)');
                $adminId = (int)getUserId();
                $stmt->bind_param('si', $name, $adminId);
                $stmt->execute();
                $selectedSession = (int)$mysqli->insert_id;
                $stmt->close();
                $mysqli->commit();
                $transactionActive = false;
                $message = 'Academic session created and set as current.';
            } elseif ($action === 'set_current') {
                $sessionId = (int)($_POST['session_id'] ?? 0);
                $mysqli->begin_transaction();
                $transactionActive = true;
                $mysqli->query('UPDATE academic_sessions SET is_current = 0');
                $stmt = $mysqli->prepare('UPDATE academic_sessions SET is_current = 1 WHERE id = ?');
                $stmt->bind_param('i', $sessionId); $stmt->execute(); $stmt->close();
                $mysqli->commit();
                $transactionActive = false;
                $selectedSession = $sessionId;
                $message = 'Current session updated.';
            } elseif ($action === 'save_settings') {
                $classId = (int)($_POST['class_id'] ?? 0);
                $sessionId = (int)($_POST['session_id'] ?? 0);
                $caCount = filter_var($_POST['ca_count'] ?? null, FILTER_VALIDATE_INT);
                $examCount = filter_var($_POST['exam_count'] ?? null, FILTER_VALIDATE_INT);
                if (!$classId || !$sessionId || $caCount === false || $examCount === false || $caCount < 0 || $caCount > 10 || $examCount < 0 || $examCount > 5 || $caCount + $examCount === 0) {
                    throw new RuntimeException('Use 0–10 CA components and 0–5 exam components, with at least one component.');
                }
                $componentSettings = [];
                foreach (['ca' => $caCount, 'exam' => $examCount] as $type => $count) {
                    for ($number = 1; $number <= $count; $number++) {
                        $default = ($type === 'ca' ? 'CA ' : 'Exam ') . $number;
                        $label = trim($_POST['component_names'][$type][$number] ?? $default);
                        if ($label === '' || mb_strlen($label) > 60) throw new RuntimeException('Component names must be between 1 and 60 characters.');
                        $maxScore = $_POST['component_max_scores'][$type][$number] ?? '100';
                        if (!is_numeric($maxScore) || (float)$maxScore <= 0 || (float)$maxScore > 10000) throw new RuntimeException('Each component maximum must be greater than 0 and no more than 10,000.');
                        $componentSettings[$type][$number] = ['label' => $label, 'max_score' => (float)$maxScore];
                    }
                }
                $mysqli->begin_transaction();
                $transactionActive = true;
                $stmt = $mysqli->prepare('INSERT INTO class_result_settings (class_id, session_id, ca_count, exam_count) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE ca_count = VALUES(ca_count), exam_count = VALUES(exam_count)');
                $stmt->bind_param('iiii', $classId, $sessionId, $caCount, $examCount); $stmt->execute(); $stmt->close();
                $stmt = $mysqli->prepare('DELETE FROM class_result_component_names WHERE class_id=? AND session_id=?');
                $stmt->bind_param('ii', $classId, $sessionId); $stmt->execute(); $stmt->close();
                $stmt = $mysqli->prepare('INSERT INTO class_result_component_names (class_id,session_id,component_type,component_number,label,max_score) VALUES (?,?,?,?,?,?)');
                foreach ($componentSettings as $type => $items) foreach ($items as $number => $component) {
                    $label = $component['label']; $maxScore = $component['max_score'];
                    $stmt->bind_param('iisisd', $classId, $sessionId, $type, $number, $label, $maxScore); $stmt->execute();
                }
                $stmt->close();
                $mysqli->commit();
                $transactionActive = false;
                $selectedSession = $sessionId;
                $message = 'Score components and names saved for this class and session.';
            } elseif ($action === 'score_status') {
                $periodId = (int)($_POST['period_id'] ?? 0);
                $scoreId = (int)($_POST['score_id'] ?? 0);
                $nextStatus = $_POST['next_status'] ?? '';
                $stmt = $mysqli->prepare("SELECT ts.release_status,
                    COALESCE(crs.ca_count,3) + COALESCE(crs.exam_count,1) AS required_items,
                    (SELECT COUNT(*) FROM term_score_items tsi WHERE tsi.term_score_id=ts.id) AS actual_items,
                    (SELECT COUNT(*) FROM term_score_items tsi WHERE tsi.term_score_id=ts.id AND tsi.score IS NULL) AS blank_items
                    FROM term_scores ts JOIN result_periods rp ON rp.id=ts.period_id
                    LEFT JOIN class_result_settings crs ON crs.class_id=rp.class_id AND crs.session_id=rp.session_id
                    WHERE ts.id=? AND ts.period_id=?");
                $stmt->bind_param('ii', $scoreId, $periodId); $stmt->execute(); $score = $stmt->get_result()->fetch_assoc(); $stmt->close();
                if (!$score) throw new RuntimeException('Student result not found in this term.');
                if ($nextStatus === 'approved') {
                    if (!in_array($score['release_status'], ['draft','submitted','recalled'], true)) throw new RuntimeException('This student result is already released or cannot be released.');
                    if ((int)$score['actual_items'] !== (int)$score['required_items'] || (int)$score['blank_items'] > 0) {
                        throw new RuntimeException('This student result is incomplete. Enter every configured score before release.');
                    }
                    $adminId = (int)getUserId();
                    $stmt = $mysqli->prepare("UPDATE term_scores SET release_status='approved', released_by=?, released_at=NOW() WHERE id=? AND period_id=? AND release_status IN ('draft','submitted','recalled')");
                    $stmt->bind_param('iii', $adminId, $scoreId, $periodId); $stmt->execute();
                    if ($stmt->affected_rows !== 1) throw new RuntimeException('The result status changed. Refresh and try again.');
                    $stmt->close();
                    $message = 'Student result released.';
                } elseif ($nextStatus === 'recalled' && $score['release_status'] === 'approved') {
                    $stmt = $mysqli->prepare("UPDATE term_scores SET release_status='recalled', released_by=NULL, released_at=NULL WHERE id=? AND period_id=? AND release_status='approved'");
                    $stmt->bind_param('ii', $scoreId, $periodId); $stmt->execute();
                    if ($stmt->affected_rows !== 1) throw new RuntimeException('The result status changed. Refresh and try again.');
                    $stmt->close();
                    $message = 'Student result recalled. It is no longer visible to the student.';
                } else {
                    throw new RuntimeException('Choose a valid release or recall action.');
                }
            } elseif ($action === 'period_status') {
                throw new RuntimeException('Results are now released individually from the review screen.');
            }
        } catch (Throwable $e) {
            if ($transactionActive) $mysqli->rollback();
            $message = $e instanceof mysqli_sql_exception ? 'That session already exists or the change could not be saved.' : $e->getMessage();
        }
        if (($_POST['return_to'] ?? '') === 'review' && isset($_POST['period_id'])) {
            header('Location: term_results_view.php?period_id=' . (int)$_POST['period_id'] . '&message=' . rawurlencode($message));
            exit();
        }
        $sessions = termResultsSessionRows($mysqli);
    }
}

$classes = [];
$settings = [];
$componentSettings = [];
$periods = [];
if ($selectedSession) {
    $res = $mysqli->query('SELECT id, name FROM classes ORDER BY name');
    while ($row = $res->fetch_assoc()) $classes[] = $row;
    $stmt = $mysqli->prepare('SELECT class_id, ca_count, exam_count FROM class_result_settings WHERE session_id = ?');
    $stmt->bind_param('i', $selectedSession); $stmt->execute();
    $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) $settings[(int)$row['class_id']] = $row;
    $stmt->close();
    $stmt = $mysqli->prepare('SELECT class_id, component_type, component_number, label, max_score FROM class_result_component_names WHERE session_id=?');
    $stmt->bind_param('i', $selectedSession); $stmt->execute();
    $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) $componentSettings[(int)$row['class_id']][$row['component_type']][(int)$row['component_number']] = $row;
    $stmt->close();
    $stmt = $mysqli->prepare("SELECT rp.id, rp.class_id, rp.term, rp.status, rp.updated_at, c.name AS class_name, s.name AS session_name,
        (SELECT COUNT(*) FROM term_scores ts WHERE ts.period_id=rp.id) AS score_count,
        (SELECT COUNT(*) FROM term_scores ts WHERE ts.period_id=rp.id AND ts.release_status='approved') AS released_count,
        (SELECT COUNT(*) FROM term_scores ts WHERE ts.period_id=rp.id AND ts.release_status='submitted') AS pending_count
        FROM result_periods rp JOIN classes c ON c.id=rp.class_id JOIN academic_sessions s ON s.id=rp.session_id
        WHERE rp.session_id=? ORDER BY c.name, rp.term");
    $stmt->bind_param('i', $selectedSession); $stmt->execute(); $res=$stmt->get_result(); while ($row=$res->fetch_assoc()) $periods[]=$row; $stmt->close();
}
include '../includes/header.php';
?>
<style>
.term-settings{--settings-ink:#1d2b34;--settings-muted:#6c7a83;--settings-line:#e3e9ec;color:var(--settings-ink)}
.term-settings .settings-title{font-size:1.65rem;font-weight:700}
.term-settings .card{border:1px solid var(--settings-line);border-radius:10px;box-shadow:0 4px 18px rgba(28,45,53,.045);overflow:hidden}
.term-settings .card-header{background:#f7f9fa;border-bottom:1px solid var(--settings-line);padding:.9rem 1rem}
.term-settings .component-detail{background:#f8fafb;border-top:1px solid var(--settings-line)}
.term-settings .component-detail summary{cursor:pointer;font-weight:600;color:#137c72}
.term-settings .component-setting{height:100%;padding:.8rem;background:#fff;border:1px solid var(--settings-line);border-radius:8px}
.term-settings .component-setting-title{font-weight:700;font-size:.88rem;margin-bottom:.6rem}
.term-settings .table th{font-size:.8rem;color:#596972;text-transform:uppercase;white-space:nowrap}
@media(max-width:767px){.term-settings .settings-title{font-size:1.35rem}}
</style>
<main class="container py-4 term-settings">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div><div class="text-uppercase small fw-bold text-success">Academic records</div><h1 class="settings-title mb-1">Term Results</h1><p class="text-muted mb-0">Sessions, class scoring and release approval</p></div>
        <a class="btn btn-outline-secondary" href="dashboard.php"><i class="fas fa-arrow-left me-1"></i> Admin dashboard</a>
    </div>
    <?php if ($message): ?><div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <div class="row g-3">
        <div class="col-lg-5">
            <section class="card h-100"><div class="card-header"><h5 class="mb-0">Academic sessions</h5></div><div class="card-body">
                <form method="post" class="row g-2 align-items-end mb-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(termResultsCsrfToken()); ?>"><input type="hidden" name="action" value="create_session">
                    <div class="col"><label class="form-label" for="session-name">New session</label><input id="session-name" class="form-control" name="name" placeholder="2026/2027" required></div><div class="col-auto"><button class="btn btn-primary">Create session</button></div>
                </form>
                <?php if (!$sessions): ?><p class="text-muted mb-0">Create a session to begin configuring term results.</p><?php else: ?>
                <form method="get" class="mb-3"><label class="form-label" for="session-select">Configure session</label><select class="form-select" id="session-select" name="session_id" onchange="this.form.submit()">
                    <?php foreach ($sessions as $session): ?><option value="<?php echo (int)$session['id']; ?>" <?php echo (int)$session['id']===$selectedSession?'selected':''; ?>><?php echo htmlspecialchars($session['name'].((int)$session['is_current']?' · Current':'')); ?></option><?php endforeach; ?>
                </select></form>
                <?php foreach ($sessions as $session): if ((int)$session['is_current'] === 1) continue; ?><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(termResultsCsrfToken()); ?>"><input type="hidden" name="action" value="set_current"><input type="hidden" name="session_id" value="<?php echo (int)$session['id']; ?>"><button class="btn btn-sm btn-outline-primary mb-2">Set <?php echo htmlspecialchars($session['name']); ?> as current</button></form><?php endforeach; ?>
                <?php endif; ?>
            </div></section>
        </div>
        <div class="col-lg-7"><section class="card"><div class="card-header"><h5 class="mb-0">CA and exam components</h5></div><div class="card-body">
            <?php if (!$selectedSession): ?><p class="text-muted mb-0">Create a session before configuring classes.</p><?php else: ?>
            <p class="small text-muted">Set a name and maximum mark for every component. These settings apply to all three terms for the selected class and session.</p>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Class</th><th>CA count</th><th>Exam count</th><th></th></tr></thead><tbody>
            <?php foreach ($classes as $class): $config=$settings[(int)$class['id']]??['ca_count'=>3,'exam_count'=>1]; $formId='class-settings-'.(int)$class['id']; ?>
                <tr><td><?php echo htmlspecialchars($class['name']); ?><form id="<?php echo $formId; ?>" method="post"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(termResultsCsrfToken()); ?>"><input type="hidden" name="action" value="save_settings"><input type="hidden" name="class_id" value="<?php echo (int)$class['id']; ?>"><input type="hidden" name="session_id" value="<?php echo $selectedSession; ?>"></form></td><td><input form="<?php echo $formId; ?>" class="form-control" type="number" name="ca_count" min="0" max="10" value="<?php echo (int)$config['ca_count']; ?>" aria-label="<?php echo htmlspecialchars($class['name']); ?> CA count"></td><td><input form="<?php echo $formId; ?>" class="form-control" type="number" name="exam_count" min="0" max="5" value="<?php echo (int)$config['exam_count']; ?>" aria-label="<?php echo htmlspecialchars($class['name']); ?> exam count"></td><td><button form="<?php echo $formId; ?>" class="btn btn-sm btn-primary" aria-label="Save <?php echo htmlspecialchars($class['name']); ?> settings"><i class="fas fa-save"></i></button></td></tr>
                <tr><td colspan="4" class="component-detail"><details><summary class="py-2">Customize component names and maximum marks · <?php echo htmlspecialchars($class['name']); ?></summary><div class="row g-2 pb-2">
                    <?php foreach (['ca' => (int)$config['ca_count'], 'exam' => (int)$config['exam_count']] as $type => $count): for ($number = 1; $number <= $count; $number++): $fallback = ($type === 'ca' ? 'CA ' : 'Exam ') . $number; $component = $componentSettings[(int)$class['id']][$type][$number] ?? ['label' => $fallback, 'max_score' => 100]; ?>
                        <div class="col-sm-6 col-xl-4"><div class="component-setting"><div class="component-setting-title"><?php echo $type === 'ca' ? 'CA' : 'Exam'; ?> <?php echo $number; ?></div><label class="form-label small" for="<?php echo $formId.'-'.$type.'-'.$number; ?>-name">Display name</label><input form="<?php echo $formId; ?>" class="form-control form-control-sm mb-2" id="<?php echo $formId.'-'.$type.'-'.$number; ?>-name" name="component_names[<?php echo $type; ?>][<?php echo $number; ?>]" maxlength="60" value="<?php echo htmlspecialchars($component['label']); ?>"><label class="form-label small" for="<?php echo $formId.'-'.$type.'-'.$number; ?>-max">Maximum score</label><input form="<?php echo $formId; ?>" class="form-control form-control-sm" type="number" min="0.01" max="10000" step="0.01" id="<?php echo $formId.'-'.$type.'-'.$number; ?>-max" name="component_max_scores[<?php echo $type; ?>][<?php echo $number; ?>]" value="<?php echo htmlspecialchars((string)$component['max_score']); ?>" required></div></div>
                    <?php endfor; endforeach; ?>
                </div></details></td></tr>
            <?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </div></section></div>
    </div>
    <section class="card mt-3"><div class="card-header"><h5 class="mb-0">Term result sheets</h5></div><div class="card-body">
        <?php if (!$periods): ?><p class="text-muted mb-0">No term sheets have been started for this session.</p><?php else: ?><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Class</th><th>Term</th><th>Scores entered</th><th>Released</th><th>Awaiting release</th><th>Updated</th><th></th></tr></thead><tbody>
        <?php foreach ($periods as $period): ?><tr><td><?php echo htmlspecialchars($period['class_name']); ?></td><td><?php echo htmlspecialchars(termLabel((int)$period['term'])); ?></td><td><?php echo (int)$period['score_count']; ?></td><td><span class="badge bg-success"><?php echo (int)$period['released_count']; ?></span></td><td><span class="badge bg-primary"><?php echo (int)$period['pending_count']; ?></span></td><td><?php echo htmlspecialchars(date('M j, Y H:i', strtotime($period['updated_at']))); ?></td><td><a class="btn btn-sm btn-outline-primary" href="term_results_view.php?period_id=<?php echo (int)$period['id']; ?>"><i class="fas fa-clipboard-check me-1"></i> Review individual results</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div></section>
</main>
<?php include '../includes/footer.php'; ?>
