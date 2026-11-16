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
            } elseif ($action === 'save_all_settings') {
                $sessionId = (int)($_POST['session_id'] ?? 0);
                $postedClasses = $_POST['classes'] ?? [];
                if (!$sessionId || !is_array($postedClasses) || !$postedClasses) throw new RuntimeException('No class settings were submitted.');
                $mysqli->begin_transaction();
                $transactionActive = true;
                $settingsStmt = $mysqli->prepare('INSERT INTO class_result_settings (class_id, session_id, ca_count, exam_count) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE ca_count = VALUES(ca_count), exam_count = VALUES(exam_count)');
                $deleteStmt = $mysqli->prepare('DELETE FROM class_result_component_names WHERE class_id=? AND session_id=?');
                $componentStmt = $mysqli->prepare('INSERT INTO class_result_component_names (class_id,session_id,component_type,component_number,label,max_score) VALUES (?,?,?,?,?,?)');
                $validClasses = [];
                $classResult = $mysqli->query('SELECT id FROM classes');
                while ($classRow = $classResult->fetch_assoc()) $validClasses[(int)$classRow['id']] = true;
                foreach ($postedClasses as $classKey => $classData) {
                    $classId = (int)$classKey;
                    if (!$classId || !isset($validClasses[$classId]) || !is_array($classData)) throw new RuntimeException('Invalid class settings submitted.');
                    $caCount = filter_var($classData['ca_count'] ?? null, FILTER_VALIDATE_INT);
                    $examCount = filter_var($classData['exam_count'] ?? null, FILTER_VALIDATE_INT);
                    if ($caCount === false || $examCount === false || $caCount < 0 || $caCount > 10 || $examCount < 0 || $examCount > 5 || $caCount + $examCount === 0) throw new RuntimeException('Use 0–10 CA components and 0–5 exam components for every class, with at least one component.');
                    $settingsStmt->bind_param('iiii', $classId, $sessionId, $caCount, $examCount); $settingsStmt->execute();
                    $deleteStmt->bind_param('ii', $classId, $sessionId); $deleteStmt->execute();
                    foreach (['ca' => $caCount, 'exam' => $examCount] as $type => $count) {
                        for ($number = 1; $number <= $count; $number++) {
                            $default = ($type === 'ca' ? 'CA ' : 'Exam ') . $number;
                            $label = trim($classData['component_names'][$type][$number] ?? $default);
                            if ($label === '' || mb_strlen($label) > 60) throw new RuntimeException('Component names must be between 1 and 60 characters.');
                            $maxScore = $classData['component_max_scores'][$type][$number] ?? '100';
                            if (!is_numeric($maxScore) || (float)$maxScore <= 0 || (float)$maxScore > 10000) throw new RuntimeException('Each component maximum must be greater than 0 and no more than 10,000.');
                            $maxScore = (float)$maxScore;
                            $componentStmt->bind_param('iisisd', $classId, $sessionId, $type, $number, $label, $maxScore); $componentStmt->execute();
                        }
                    }
                }
                $settingsStmt->close(); $deleteStmt->close(); $componentStmt->close();
                $mysqli->commit();
                $transactionActive = false;
                $selectedSession = $sessionId;
                $message = 'Score components, names and maximum marks saved for all classes in this session.';
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
            header('Location: term_results_view.php?period_id=' . (int)$_POST['period_id'] . '&student_id=' . (int)($_POST['student_id'] ?? 0) . '&message=' . rawurlencode($message));
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
.term-settings .settings-table{min-width:560px;margin-bottom:.5rem}
.term-settings .settings-table th,.term-settings .settings-table td{padding:.45rem .55rem;vertical-align:middle}
.term-settings .settings-table .form-control{min-width:72px}
.term-settings .settings-components summary{cursor:pointer;color:#137c72;font-size:.88rem;font-weight:600;white-space:nowrap}
.term-settings .settings-components[open] summary{margin-bottom:.5rem}
.term-settings .settings-components .component-fields{max-width:560px;max-height:250px;overflow:auto;padding:.25rem}
.term-settings .settings-components .component-setting{padding:.55rem}
.term-settings .settings-components .component-setting-title{margin-bottom:.35rem;font-size:.8rem}
.term-settings .settings-components .form-label{margin-bottom:.15rem}
.term-settings .settings-components .form-control{padding:.25rem .45rem;font-size:.85rem}
.term-settings .settings-help{font-size:.85rem;margin-bottom:.65rem}
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
            <p class="small text-muted settings-help">Set counts and save once. Open “Components” only when you need to change names or maximum marks. Raising a count adds its fields immediately.</p>
            <form method="post" id="all-class-settings"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(termResultsCsrfToken()); ?>"><input type="hidden" name="action" value="save_all_settings"><input type="hidden" name="session_id" value="<?php echo $selectedSession; ?>">
            <div class="table-responsive"><table class="table table-sm settings-table"><thead><tr><th>Class</th><th>CA</th><th>Exam</th><th>Components</th></tr></thead><tbody>
            <?php foreach ($classes as $class): $classId=(int)$class['id']; $config=$settings[$classId]??['ca_count'=>3,'exam_count'=>1]; $formId='class-settings-'.$classId; ?>
                <tr><td class="fw-semibold"><?php echo htmlspecialchars($class['name']); ?></td><td><input class="form-control class-count" data-class="<?php echo $classId; ?>" data-type="ca" type="number" name="classes[<?php echo $classId; ?>][ca_count]" min="0" max="10" value="<?php echo (int)$config['ca_count']; ?>" aria-label="<?php echo htmlspecialchars($class['name']); ?> CA count"></td><td><input class="form-control class-count" data-class="<?php echo $classId; ?>" data-type="exam" type="number" name="classes[<?php echo $classId; ?>][exam_count]" min="0" max="5" value="<?php echo (int)$config['exam_count']; ?>" aria-label="<?php echo htmlspecialchars($class['name']); ?> exam count"></td><td><details class="settings-components"><summary>Components <span class="text-muted">(<?php echo (int)$config['ca_count']; ?> CA, <?php echo (int)$config['exam_count']; ?> exam)</span></summary><div class="row g-2 pb-2 component-fields" data-class="<?php echo $classId; ?>">
                    <?php foreach (['ca' => (int)$config['ca_count'], 'exam' => (int)$config['exam_count']] as $type => $count): for ($number = 1; $number <= $count; $number++): $fallback = ($type === 'ca' ? 'CA ' : 'Exam ') . $number; $component = $componentSettings[$classId][$type][$number] ?? ['label' => $fallback, 'max_score' => 100]; ?>
                        <div class="col-sm-6 col-xl-4" data-component="<?php echo $type.'-'.$number; ?>"><div class="component-setting"><div class="component-setting-title"><?php echo $type === 'ca' ? 'CA' : 'Exam'; ?> <?php echo $number; ?></div><label class="form-label small" for="<?php echo $formId.'-'.$type.'-'.$number; ?>-name">Display name</label><input class="form-control form-control-sm mb-2" id="<?php echo $formId.'-'.$type.'-'.$number; ?>-name" name="classes[<?php echo $classId; ?>][component_names][<?php echo $type; ?>][<?php echo $number; ?>]" maxlength="60" value="<?php echo htmlspecialchars($component['label']); ?>"><label class="form-label small" for="<?php echo $formId.'-'.$type.'-'.$number; ?>-max">Maximum score</label><input class="form-control form-control-sm" type="number" min="0.01" max="10000" step="0.01" id="<?php echo $formId.'-'.$type.'-'.$number; ?>-max" name="classes[<?php echo $classId; ?>][component_max_scores][<?php echo $type; ?>][<?php echo $number; ?>]" value="<?php echo htmlspecialchars((string)$component['max_score']); ?>" required></div></div>
                    <?php endfor; endforeach; ?>
                </div></details></td></tr>
            <?php endforeach; ?>
            </tbody></table></div><div class="d-flex justify-content-end"><button class="btn btn-primary"><i class="fas fa-save me-1"></i>Save all class settings</button></div></form><?php endif; ?>
        </div></section></div>
    </div>
    <section class="card mt-3"><div class="card-header"><h5 class="mb-0">Term result sheets</h5></div><div class="card-body">
        <?php if (!$periods): ?><p class="text-muted mb-0">No term sheets have been started for this session.</p><?php else: ?><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Class</th><th>Term</th><th>Scores entered</th><th>Released</th><th>Awaiting release</th><th>Updated</th><th></th></tr></thead><tbody>
        <?php foreach ($periods as $period): ?><tr><td><?php echo htmlspecialchars($period['class_name']); ?></td><td><?php echo htmlspecialchars(termLabel((int)$period['term'])); ?></td><td><?php echo (int)$period['score_count']; ?></td><td><span class="badge bg-success"><?php echo (int)$period['released_count']; ?></span></td><td><span class="badge bg-primary"><?php echo (int)$period['pending_count']; ?></span></td><td><?php echo htmlspecialchars(date('M j, Y H:i', strtotime($period['updated_at']))); ?></td><td><a class="btn btn-sm btn-outline-primary" href="term_results_view.php?period_id=<?php echo (int)$period['id']; ?>"><i class="fas fa-clipboard-check me-1"></i> Review individual results</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div></section>
</main>
<script>
(() => {
    const form = document.getElementById('all-class-settings');
    if (!form) return;
    const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const renderClassComponents = (classId) => {
        const fields = form.querySelector('.component-fields[data-class="' + classId + '"]');
        if (!fields) return;
        const existing = {};
        fields.querySelectorAll('[data-component]').forEach((card) => {
            const key = card.dataset.component;
            existing[key] = {
                label: card.querySelector('input[name*="[component_names]"]').value,
                max: card.querySelector('input[name*="[component_max_scores]"]').value
            };
        });
        let html = '';
        ['ca', 'exam'].forEach((type) => {
            const countInput = form.querySelector('.class-count[data-class="' + classId + '"][data-type="' + type + '"]');
            const count = Math.max(0, Math.min(type === 'ca' ? 10 : 5, parseInt(countInput.value, 10) || 0));
            countInput.value = count;
            for (let number = 1; number <= count; number++) {
                const key = type + '-' + number;
                const prefix = 'classes[' + classId + ']';
                const label = existing[key]?.label || (type === 'ca' ? 'CA ' : 'Exam ') + number;
                const max = existing[key]?.max || '100';
                const id = 'class-settings-' + classId + '-' + key;
                html += '<div class="col-sm-6 col-xl-4" data-component="' + key + '"><div class="component-setting"><div class="component-setting-title">' + (type === 'ca' ? 'CA ' : 'Exam ') + number + '</div>' +
                    '<label class="form-label small" for="' + id + '-name">Display name</label><input class="form-control form-control-sm mb-2" id="' + id + '-name" name="' + prefix + '[component_names][' + type + '][' + number + ']" maxlength="60" value="' + escapeHtml(label) + '" required>' +
                    '<label class="form-label small" for="' + id + '-max">Maximum score</label><input class="form-control form-control-sm" type="number" min="0.01" max="10000" step="0.01" id="' + id + '-max" name="' + prefix + '[component_max_scores][' + type + '][' + number + ']" value="' + escapeHtml(max) + '" required></div></div>';
            }
        });
        fields.innerHTML = html;
        const details = fields.closest('details');
        const caCount = form.querySelector('.class-count[data-class="' + classId + '"][data-type="ca"]').value;
        const examCount = form.querySelector('.class-count[data-class="' + classId + '"][data-type="exam"]').value;
        details.querySelector('summary').innerHTML = 'Components <span class="text-muted">(' + caCount + ' CA, ' + examCount + ' exam)</span>';
    };
    form.querySelectorAll('.class-count').forEach((input) => input.addEventListener('input', () => renderClassComponents(input.dataset.class)));
})();
</script>
<?php include '../includes/footer.php'; ?>
