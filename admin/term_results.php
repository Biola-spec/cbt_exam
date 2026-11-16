<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/term_results.php';
requireLogin();
if (getUserRole() !== 'admin') { header('Location: ../login.php'); exit(); }

$mysqli = getDBConnection();
$message = '';
if (!$mysqli) die('<div class="alert alert-danger">Database connection failed.</div>');
$mysqli->query("CREATE TABLE IF NOT EXISTS class_result_class_links (
    session_id INT NOT NULL,
    class_id INT NOT NULL,
    group_name VARCHAR(60) NOT NULL,
    PRIMARY KEY (session_id, class_id),
    KEY idx_result_class_link_group (session_id, group_name),
    CONSTRAINT fk_result_class_link_session FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_result_class_link_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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
            } elseif ($action === 'save_link_groups') {
                $sessionId = (int)($_POST['session_id'] ?? 0);
                $postedGroups = $_POST['link_groups'] ?? [];
                if (!$sessionId || !is_array($postedGroups)) throw new RuntimeException('Invalid class link groups.');
                $validClasses = [];
                $classResult = $mysqli->query('SELECT id FROM classes');
                while ($classRow = $classResult->fetch_assoc()) $validClasses[(int)$classRow['id']] = true;
                $normalizedGroups = [];
                $claimedClasses = [];
                foreach ($postedGroups as $group) {
                    if (!is_array($group)) continue;
                    $groupName = trim((string)($group['name'] ?? ''));
                    $memberIds = array_values(array_unique(array_map('intval', (array)($group['classes'] ?? []))));
                    if (!$memberIds) continue;
                    if ($groupName === '' || mb_strlen($groupName) > 60) throw new RuntimeException('Enter a link group name of 1–60 characters.');
                    if (count($memberIds) < 2) continue;
                    $groupKey = mb_strtolower($groupName);
                    if (isset($normalizedGroups[$groupKey])) throw new RuntimeException('Each link group must have a unique name.');
                    foreach ($memberIds as $classId) {
                        if (!$classId || !isset($validClasses[$classId])) throw new RuntimeException('A selected class is invalid.');
                        if (isset($claimedClasses[$classId])) throw new RuntimeException('A class can belong to only one link group in this session.');
                        $claimedClasses[$classId] = true;
                    }
                    $normalizedGroups[$groupKey] = ['name' => $groupName, 'classes' => $memberIds];
                }
                $mysqli->begin_transaction();
                $transactionActive = true;
                $stmt = $mysqli->prepare('DELETE FROM class_result_class_links WHERE session_id=?');
                $stmt->bind_param('i', $sessionId); $stmt->execute(); $stmt->close();
                $stmt = $mysqli->prepare('INSERT INTO class_result_class_links (session_id,class_id,group_name) VALUES (?,?,?)');
                foreach ($normalizedGroups as $group) foreach ($group['classes'] as $classId) {
                    $groupName = $group['name'];
                    $stmt->bind_param('iis', $sessionId, $classId, $groupName); $stmt->execute();
                }
                $stmt->close();
                $sourceSettingsStmt = $mysqli->prepare('SELECT ca_count, exam_count FROM class_result_settings WHERE class_id=? AND session_id=?');
                $sourceComponentsStmt = $mysqli->prepare('SELECT component_type, component_number, label, max_score FROM class_result_component_names WHERE class_id=? AND session_id=?');
                $saveSettingsStmt = $mysqli->prepare('INSERT INTO class_result_settings (class_id,session_id,ca_count,exam_count) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE ca_count=VALUES(ca_count),exam_count=VALUES(exam_count)');
                $clearComponentsStmt = $mysqli->prepare('DELETE FROM class_result_component_names WHERE class_id=? AND session_id=?');
                $saveComponentStmt = $mysqli->prepare('INSERT INTO class_result_component_names (class_id,session_id,component_type,component_number,label,max_score) VALUES (?,?,?,?,?,?)');
                foreach ($normalizedGroups as $group) {
                    $sourceClassId = (int)$group['classes'][0];
                    $sourceSettingsStmt->bind_param('ii', $sourceClassId, $sessionId); $sourceSettingsStmt->execute();
                    $sourceSettings = $sourceSettingsStmt->get_result()->fetch_assoc() ?: ['ca_count' => 3, 'exam_count' => 1];
                    $caCount = (int)$sourceSettings['ca_count']; $examCount = (int)$sourceSettings['exam_count'];
                    $sourceComponentsStmt->bind_param('ii', $sourceClassId, $sessionId); $sourceComponentsStmt->execute();
                    $sourceComponents = [];
                    $componentRows = $sourceComponentsStmt->get_result();
                    while ($componentRow = $componentRows->fetch_assoc()) $sourceComponents[$componentRow['component_type']][(int)$componentRow['component_number']] = $componentRow;
                    foreach (array_slice($group['classes'], 1) as $classId) {
                        $classId = (int)$classId;
                        $saveSettingsStmt->bind_param('iiii', $classId, $sessionId, $caCount, $examCount); $saveSettingsStmt->execute();
                        $clearComponentsStmt->bind_param('ii', $classId, $sessionId); $clearComponentsStmt->execute();
                        foreach (['ca' => $caCount, 'exam' => $examCount] as $type => $count) for ($number=1; $number<=$count; $number++) {
                            $component = $sourceComponents[$type][$number] ?? ['label' => ($type === 'ca' ? 'CA ' : 'Exam ') . $number, 'max_score' => 100];
                            $label = $component['label']; $maxScore = (float)$component['max_score'];
                            $saveComponentStmt->bind_param('iisisd', $classId, $sessionId, $type, $number, $label, $maxScore); $saveComponentStmt->execute();
                        }
                    }
                }
                $sourceSettingsStmt->close(); $sourceComponentsStmt->close(); $saveSettingsStmt->close(); $clearComponentsStmt->close(); $saveComponentStmt->close();
                $mysqli->commit();
                $transactionActive = false;
                $selectedSession = $sessionId;
                $message = 'Class link groups saved.';
            } elseif ($action === 'save_all_settings') {
                $sessionId = (int)($_POST['session_id'] ?? 0);
                $postedClasses = $_POST['classes'] ?? [];
                if (!$sessionId || !is_array($postedClasses) || !$postedClasses) throw new RuntimeException('No class settings were submitted.');
                $mysqli->begin_transaction();
                $transactionActive = true;
                $settingsStmt = $mysqli->prepare('INSERT INTO class_result_settings (class_id, session_id, ca_count, exam_count) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE ca_count = VALUES(ca_count), exam_count = VALUES(exam_count)');
                $deleteStmt = $mysqli->prepare('DELETE FROM class_result_component_names WHERE class_id=? AND session_id=?');
                $componentStmt = $mysqli->prepare('INSERT INTO class_result_component_names (class_id,session_id,component_type,component_number,label,max_score) VALUES (?,?,?,?,?,?)');
                $classLinkGroupByClass = [];
                $groupResult = $mysqli->prepare('SELECT class_id, group_name FROM class_result_class_links WHERE session_id=?');
                $groupResult->bind_param('i', $sessionId); $groupResult->execute(); $groupRows = $groupResult->get_result();
                while ($groupRow = $groupRows->fetch_assoc()) $classLinkGroupByClass[(int)$groupRow['class_id']] = $groupRow['group_name'];
                $groupResult->close();
                $validClasses = [];
                $classResult = $mysqli->query('SELECT id FROM classes');
                while ($classRow = $classResult->fetch_assoc()) $validClasses[(int)$classRow['id']] = true;
                $groupSources = [];
                foreach ($postedClasses as $classKey => $classData) {
                    $classId = (int)$classKey;
                    if (!$classId || !isset($validClasses[$classId]) || !is_array($classData)) throw new RuntimeException('Invalid class settings submitted.');
                    $groupName = trim((string)($classLinkGroupByClass[$classId] ?? ''));
                    $caCount = filter_var($classData['ca_count'] ?? null, FILTER_VALIDATE_INT);
                    $examCount = filter_var($classData['exam_count'] ?? null, FILTER_VALIDATE_INT);
                    if ($caCount === false || $examCount === false || $caCount < 0 || $caCount > 10 || $examCount < 0 || $examCount > 5 || $caCount + $examCount === 0) throw new RuntimeException('Use 0–10 CA components and 0–5 exam components for every class, with at least one component.');
                    $classComponents = [];
                    foreach (['ca' => $caCount, 'exam' => $examCount] as $type => $count) {
                        for ($number = 1; $number <= $count; $number++) {
                            $default = ($type === 'ca' ? 'CA ' : 'Exam ') . $number;
                            $label = trim($classData['component_names'][$type][$number] ?? $default);
                            if ($label === '' || mb_strlen($label) > 60) throw new RuntimeException('Component names must be between 1 and 60 characters.');
                            $maxScore = $classData['component_max_scores'][$type][$number] ?? '100';
                            if (!is_numeric($maxScore) || (float)$maxScore <= 0 || (float)$maxScore > 10000) throw new RuntimeException('Each component maximum must be greater than 0 and no more than 10,000.');
                            $classComponents[$type][$number] = ['label' => $label, 'max_score' => (float)$maxScore];
                        }
                    }
                    $groupKey = mb_strtolower($groupName);
                    if ($groupName !== '' && isset($groupSources[$groupKey])) {
                        [$caCount, $examCount, $classComponents] = $groupSources[$groupKey];
                    } elseif ($groupName !== '') {
                        $groupSources[$groupKey] = [$caCount, $examCount, $classComponents];
                    }
                    $settingsStmt->bind_param('iiii', $classId, $sessionId, $caCount, $examCount); $settingsStmt->execute();
                    $deleteStmt->bind_param('ii', $classId, $sessionId); $deleteStmt->execute();
                    foreach (['ca' => $caCount, 'exam' => $examCount] as $type => $count) {
                        for ($number = 1; $number <= $count; $number++) {
                            $label = $classComponents[$type][$number]['label']; $maxScore = $classComponents[$type][$number]['max_score'];
                            $componentStmt->bind_param('iisisd', $classId, $sessionId, $type, $number, $label, $maxScore); $componentStmt->execute();
                        }
                    }
                }
                $settingsStmt->close(); $deleteStmt->close(); $componentStmt->close();
                $mysqli->commit();
                $transactionActive = false;
                $selectedSession = $sessionId;
                $message = 'Class settings saved. Classes in the same link group now share component counts, names and maximum marks.';
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
$classLinkGroups = [];
$linkGroups = [];
$periods = [];
if ($selectedSession) {
    $res = $mysqli->query('SELECT id, name FROM classes ORDER BY name');
    while ($row = $res->fetch_assoc()) $classes[] = $row;
    $stmt = $mysqli->prepare('SELECT class_id, ca_count, exam_count FROM class_result_settings WHERE session_id = ?');
    $stmt->bind_param('i', $selectedSession); $stmt->execute();
    $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) $settings[(int)$row['class_id']] = $row;
    $stmt->close();
    $stmt = $mysqli->prepare('SELECT class_id, group_name FROM class_result_class_links WHERE session_id=?');
    $stmt->bind_param('i', $selectedSession); $stmt->execute(); $res=$stmt->get_result(); while ($row=$res->fetch_assoc()) { $classLinkGroups[(int)$row['class_id']]=$row['group_name']; $linkGroups[$row['group_name']][]=(int)$row['class_id']; } $stmt->close();
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
$validTabs = ['scoring-pane', 'class-links-pane', 'term-sheets-pane'];
$activeTab = in_array($_POST['active_tab'] ?? '', $validTabs, true) ? $_POST['active_tab'] : 'scoring-pane';
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
.term-settings .settings-table{width:100%;table-layout:fixed;margin-bottom:.5rem}
.term-settings .settings-table th,.term-settings .settings-table td{padding:.45rem .55rem;vertical-align:middle}
.term-settings .settings-table th:nth-child(1){width:25%}
.term-settings .settings-table th:nth-child(2),.term-settings .settings-table th:nth-child(3){width:13%}
.term-settings .settings-table th:nth-child(4){width:49%}
.term-settings .settings-table .form-control{min-width:0;width:100%;padding:.3rem}
.term-settings .settings-components summary{cursor:pointer;color:#137c72;font-size:.88rem;font-weight:600;white-space:nowrap}
.term-settings .settings-components[open] summary{margin-bottom:.5rem}
.term-settings .settings-components .component-fields{width:100%;max-height:220px;overflow:auto;padding:.25rem;margin:0}
.term-settings .settings-components .component-fields>[class*="col-"]{width:100%;max-width:100%;padding-left:0;padding-right:0}
.term-settings .settings-components .component-setting{padding:.55rem}
.term-settings .settings-components .component-setting-title{margin-bottom:.35rem;font-size:.8rem}
.term-settings .settings-components .form-label{margin-bottom:.15rem}
.term-settings .settings-components .form-control{padding:.25rem .45rem;font-size:.85rem}
.term-settings .settings-help{font-size:.85rem;margin-bottom:.65rem}
.term-settings .link-group-card{height:100%;border:1px solid var(--settings-line);border-radius:8px;background:#fff}
.term-settings .link-group-card summary{padding:.7rem .85rem;cursor:pointer;font-weight:600}
.term-settings .link-group-body{padding:0 .85rem .85rem}
.term-settings .link-class-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:.35rem .75rem}
.term-settings .link-class-option{display:flex;align-items:center;gap:.45rem;min-width:0;font-size:.9rem}
.term-settings .link-class-option span{overflow-wrap:anywhere}
@media(max-width:575px){.term-settings .settings-table th,.term-settings .settings-table td{padding:.35rem .25rem;font-size:.85rem}.term-settings .settings-table th:nth-child(1){width:26%}.term-settings .settings-table th:nth-child(2),.term-settings .settings-table th:nth-child(3){width:14%}.term-settings .settings-table th:nth-child(4){width:46%}.term-settings .settings-components summary{white-space:normal;font-size:.8rem}}
@media(max-width:767px){.term-settings .settings-title{font-size:1.35rem}}
</style>
<main class="container py-4 term-settings">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div><div class="text-uppercase small fw-bold text-success">Academic records</div><h1 class="settings-title mb-1">Term Results</h1><p class="text-muted mb-0">Sessions, class scoring and release approval</p></div>
        <a class="btn btn-outline-secondary" href="dashboard.php"><i class="fas fa-arrow-left me-1"></i> Admin dashboard</a>
    </div>
    <?php if ($message): ?><div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <ul class="nav nav-tabs mb-3" id="term-results-tabs" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link <?php echo $activeTab==='scoring-pane'?'active':''; ?>" id="scoring-tab" data-bs-toggle="tab" data-bs-target="#scoring-pane" type="button" role="tab" aria-controls="scoring-pane" aria-selected="<?php echo $activeTab==='scoring-pane'?'true':'false'; ?>">Scoring setup</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link <?php echo $activeTab==='class-links-pane'?'active':''; ?>" id="class-links-tab" data-bs-toggle="tab" data-bs-target="#class-links-pane" type="button" role="tab" aria-controls="class-links-pane" aria-selected="<?php echo $activeTab==='class-links-pane'?'true':'false'; ?>">Optional class links</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link <?php echo $activeTab==='term-sheets-pane'?'active':''; ?>" id="term-sheets-tab" data-bs-toggle="tab" data-bs-target="#term-sheets-pane" type="button" role="tab" aria-controls="term-sheets-pane" aria-selected="<?php echo $activeTab==='term-sheets-pane'?'true':'false'; ?>">Term result sheets</button></li>
    </ul>
    <div class="tab-content" id="term-results-tab-content">
    <div class="tab-pane fade <?php echo $activeTab==='scoring-pane'?'show active':''; ?>" id="scoring-pane" role="tabpanel" aria-labelledby="scoring-tab" tabindex="0">
    <div class="row g-3">
        <div class="col-lg-5">
            <section class="card h-100"><div class="card-header"><h5 class="mb-0">Academic sessions</h5></div><div class="card-body">
                <form method="post" class="row g-2 align-items-end mb-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(termResultsCsrfToken()); ?>"><input type="hidden" name="action" value="create_session"><input class="active-tab-field" type="hidden" name="active_tab" value="scoring-pane">
                    <div class="col"><label class="form-label" for="session-name">New session</label><input id="session-name" class="form-control" name="name" placeholder="2026/2027" required></div><div class="col-auto"><button class="btn btn-primary">Create session</button></div>
                </form>
                <?php if (!$sessions): ?><p class="text-muted mb-0">Create a session to begin configuring term results.</p><?php else: ?>
                <form method="get" class="mb-3"><label class="form-label" for="session-select">Configure session</label><select class="form-select" id="session-select" name="session_id" onchange="this.form.submit()">
                    <?php foreach ($sessions as $session): ?><option value="<?php echo (int)$session['id']; ?>" <?php echo (int)$session['id']===$selectedSession?'selected':''; ?>><?php echo htmlspecialchars($session['name'].((int)$session['is_current']?' · Current':'')); ?></option><?php endforeach; ?>
                </select></form>
                <?php foreach ($sessions as $session): if ((int)$session['is_current'] === 1) continue; ?><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(termResultsCsrfToken()); ?>"><input type="hidden" name="action" value="set_current"><input class="active-tab-field" type="hidden" name="active_tab" value="scoring-pane"><input type="hidden" name="session_id" value="<?php echo (int)$session['id']; ?>"><button class="btn btn-sm btn-outline-primary mb-2">Set <?php echo htmlspecialchars($session['name']); ?> as current</button></form><?php endforeach; ?>
                <?php endif; ?>
            </div></section>
        </div>
        <div class="col-lg-7"><section class="card"><div class="card-header"><h5 class="mb-0">CA and exam components</h5></div><div class="card-body">
            <?php if (!$selectedSession): ?><p class="text-muted mb-0">Create a session before configuring classes.</p><?php else: ?>
            <p class="small text-muted settings-help">Set counts and save once. Open “Components” only when needed; new fields appear as soon as counts change. Optional class links are managed separately below.</p>
            <form method="post" id="all-class-settings"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(termResultsCsrfToken()); ?>"><input type="hidden" name="action" value="save_all_settings"><input class="active-tab-field" type="hidden" name="active_tab" value="scoring-pane"><input type="hidden" name="session_id" value="<?php echo $selectedSession; ?>">
            <table class="table table-sm settings-table"><thead><tr><th>Class</th><th>CA</th><th>Exam</th><th>Components</th></tr></thead><tbody>
            <?php foreach ($classes as $class): $classId=(int)$class['id']; $config=$settings[$classId]??['ca_count'=>3,'exam_count'=>1]; $formId='class-settings-'.$classId; ?>
                <tr data-class="<?php echo $classId; ?>" data-link-group="<?php echo htmlspecialchars(mb_strtolower($classLinkGroups[$classId] ?? '')); ?>"><td class="fw-semibold"><?php echo htmlspecialchars($class['name']); ?></td><td><input class="form-control class-count" data-class="<?php echo $classId; ?>" data-type="ca" type="number" name="classes[<?php echo $classId; ?>][ca_count]" min="0" max="10" value="<?php echo (int)$config['ca_count']; ?>" aria-label="<?php echo htmlspecialchars($class['name']); ?> CA count"></td><td><input class="form-control class-count" data-class="<?php echo $classId; ?>" data-type="exam" type="number" name="classes[<?php echo $classId; ?>][exam_count]" min="0" max="5" value="<?php echo (int)$config['exam_count']; ?>" aria-label="<?php echo htmlspecialchars($class['name']); ?> exam count"></td><td><details class="settings-components"><summary>Components <span class="text-muted">(<?php echo (int)$config['ca_count']; ?> CA, <?php echo (int)$config['exam_count']; ?> exam)</span></summary><div class="row g-2 pb-2 component-fields" data-class="<?php echo $classId; ?>">
                    <?php foreach (['ca' => (int)$config['ca_count'], 'exam' => (int)$config['exam_count']] as $type => $count): for ($number = 1; $number <= $count; $number++): $fallback = ($type === 'ca' ? 'CA ' : 'Exam ') . $number; $component = $componentSettings[$classId][$type][$number] ?? ['label' => $fallback, 'max_score' => 100]; ?>
                        <div class="col-sm-6 col-xl-4" data-component="<?php echo $type.'-'.$number; ?>"><div class="component-setting"><div class="component-setting-title"><?php echo $type === 'ca' ? 'CA' : 'Exam'; ?> <?php echo $number; ?></div><label class="form-label small" for="<?php echo $formId.'-'.$type.'-'.$number; ?>-name">Display name</label><input class="form-control form-control-sm mb-2" id="<?php echo $formId.'-'.$type.'-'.$number; ?>-name" name="classes[<?php echo $classId; ?>][component_names][<?php echo $type; ?>][<?php echo $number; ?>]" maxlength="60" value="<?php echo htmlspecialchars($component['label']); ?>"><label class="form-label small" for="<?php echo $formId.'-'.$type.'-'.$number; ?>-max">Maximum score</label><input class="form-control form-control-sm" type="number" min="0.01" max="10000" step="0.01" id="<?php echo $formId.'-'.$type.'-'.$number; ?>-max" name="classes[<?php echo $classId; ?>][component_max_scores][<?php echo $type; ?>][<?php echo $number; ?>]" value="<?php echo htmlspecialchars((string)$component['max_score']); ?>" required></div></div>
                    <?php endfor; endforeach; ?>
                </div></details></td></tr>
            <?php endforeach; ?>
            </tbody></table><div class="d-flex justify-content-end"><button class="btn btn-primary"><i class="fas fa-save me-1"></i>Save all class settings</button></div></form><?php endif; ?>
        </div></section></div>
    </div>
    </div>
    <div class="tab-pane fade <?php echo $activeTab==='class-links-pane'?'show active':''; ?>" id="class-links-pane" role="tabpanel" aria-labelledby="class-links-tab" tabindex="0">
    <?php if ($selectedSession): ?>
    <section class="card mt-3"><div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2"><div><h5 class="mb-0">Optional class links</h5><small class="text-muted">Add a group, enter its name, and check the classes that should share one CA and exam setup. Uncheck all classes in a group to remove it.</small></div></div><div class="card-body">
        <form method="post" id="class-link-groups"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(termResultsCsrfToken()); ?>"><input type="hidden" name="action" value="save_link_groups"><input class="active-tab-field" type="hidden" name="active_tab" value="class-links-pane"><input type="hidden" name="session_id" value="<?php echo $selectedSession; ?>">
            <div class="row g-2" id="link-group-list">
                <?php $linkGroupIndex=0; foreach ($linkGroups as $groupName => $memberIds): $index=$linkGroupIndex++; ?>
                    <div class="col-12 col-xl-6"><details class="link-group-card"><summary><?php echo htmlspecialchars($groupName); ?> <span class="text-muted fw-normal">· <?php echo count($memberIds); ?> classes</span></summary><div class="link-group-body"><label class="form-label small" for="link-group-name-<?php echo $index; ?>">Group name</label><input class="form-control form-control-sm mb-2" id="link-group-name-<?php echo $index; ?>" name="link_groups[<?php echo $index; ?>][name]" maxlength="60" value="<?php echo htmlspecialchars($groupName); ?>" required><div class="link-class-list">
                        <?php foreach ($classes as $class): $classId=(int)$class['id']; ?><label class="link-class-option"><input class="form-check-input mt-0" type="checkbox" name="link_groups[<?php echo $index; ?>][classes][]" value="<?php echo $classId; ?>" <?php echo in_array($classId,$memberIds,true)?'checked':''; ?>><span><?php echo htmlspecialchars($class['name']); ?></span></label><?php endforeach; ?>
                    </div></div></details></div>
                <?php endforeach; ?>
            </div>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3"><button type="button" class="btn btn-sm btn-outline-primary" id="add-link-group"><i class="fas fa-plus me-1"></i>Add a link group</button><button class="btn btn-primary"><i class="fas fa-save me-1"></i>Save class links</button></div>
        </form>
        <template id="link-group-template"><div class="col-12 col-xl-6"><details class="link-group-card" open><summary>New link group <span class="text-muted fw-normal">· select classes</span></summary><div class="link-group-body"><label class="form-label small" for="link-group-name-__INDEX__">Group name</label><input class="form-control form-control-sm mb-2" id="link-group-name-__INDEX__" name="link_groups[__INDEX__][name]" maxlength="60" placeholder="e.g. Junior secondary" required><div class="link-class-list">
            <?php foreach ($classes as $class): ?><label class="link-class-option"><input class="form-check-input mt-0" type="checkbox" name="link_groups[__INDEX__][classes][]" value="<?php echo (int)$class['id']; ?>"><span><?php echo htmlspecialchars($class['name']); ?></span></label><?php endforeach; ?>
        </div></div></details></div></template>
    </div></section>
    <?php else: ?><p class="text-muted">Create an academic session before managing class links.</p><?php endif; ?>
    </div>
    <div class="tab-pane fade <?php echo $activeTab==='term-sheets-pane'?'show active':''; ?>" id="term-sheets-pane" role="tabpanel" aria-labelledby="term-sheets-tab" tabindex="0">
    <section class="card mt-3"><div class="card-header"><h5 class="mb-0">Term result sheets</h5></div><div class="card-body">
        <?php if (!$periods): ?><p class="text-muted mb-0">No term sheets have been started for this session.</p><?php else: ?><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Class</th><th>Term</th><th>Scores entered</th><th>Released</th><th>Awaiting release</th><th>Updated</th><th></th></tr></thead><tbody>
        <?php foreach ($periods as $period): ?><tr><td><?php echo htmlspecialchars($period['class_name']); ?></td><td><?php echo htmlspecialchars(termLabel((int)$period['term'])); ?></td><td><?php echo (int)$period['score_count']; ?></td><td><span class="badge bg-success"><?php echo (int)$period['released_count']; ?></span></td><td><span class="badge bg-primary"><?php echo (int)$period['pending_count']; ?></span></td><td><?php echo htmlspecialchars(date('M j, Y H:i', strtotime($period['updated_at']))); ?></td><td><a class="btn btn-sm btn-outline-primary" href="term_results_view.php?period_id=<?php echo (int)$period['id']; ?>"><i class="fas fa-clipboard-check me-1"></i> Review individual results</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div></section>
    </div>
    </div>
</main>
<script>
(() => {
    const form = document.getElementById('all-class-settings');
    if (!form) return;
    let syncing = false;
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
    const syncLinkedClasses = (sourceId) => {
        if (syncing) return;
        const sourceGroup = (form.querySelector('tr[data-class="' + sourceId + '"]').dataset.linkGroup || '').trim().toLocaleLowerCase();
        if (!sourceGroup) return;
        syncing = true;
        const sourceFields = form.querySelector('.component-fields[data-class="' + sourceId + '"]');
        const sourceComponents = {};
        sourceFields.querySelectorAll('[data-component]').forEach((card) => {
            sourceComponents[card.dataset.component] = Array.from(card.querySelectorAll('input')).map((input) => input.value);
        });
        form.querySelectorAll('tr[data-class][data-link-group]').forEach((targetRow) => {
            const targetId = targetRow.dataset.class;
            if (targetId === sourceId || targetRow.dataset.linkGroup.trim().toLocaleLowerCase() !== sourceGroup) return;
            ['ca', 'exam'].forEach((type) => {
                form.querySelector('.class-count[data-class="' + targetId + '"][data-type="' + type + '"]').value = form.querySelector('.class-count[data-class="' + sourceId + '"][data-type="' + type + '"]').value;
            });
            renderClassComponents(targetId);
            const targetFields = form.querySelector('.component-fields[data-class="' + targetId + '"]');
            targetFields.querySelectorAll('[data-component]').forEach((card) => {
                const values = sourceComponents[card.dataset.component];
                if (values) card.querySelectorAll('input').forEach((input, index) => { input.value = values[index] ?? ''; });
            });
        });
        syncing = false;
    };
    form.querySelectorAll('.class-count').forEach((input) => input.addEventListener('input', () => {
        renderClassComponents(input.dataset.class);
        syncLinkedClasses(input.dataset.class);
    }));
    form.addEventListener('input', (event) => {
        const sourceCard = event.target.closest('[data-component]');
        if (sourceCard) syncLinkedClasses(sourceCard.closest('.component-fields').dataset.class);
    });
    const groupForm = document.getElementById('class-link-groups');
    if (groupForm) {
        let nextGroupIndex = <?php echo (int)($linkGroupIndex ?? 0); ?>;
        document.getElementById('add-link-group').addEventListener('click', () => {
            const template = document.getElementById('link-group-template').innerHTML.replaceAll('__INDEX__', String(nextGroupIndex++));
            document.getElementById('link-group-list').insertAdjacentHTML('beforeend', template);
        });
        groupForm.addEventListener('change', (event) => {
            if (!event.target.matches('input[type="checkbox"][name*="[classes]"]') || !event.target.checked) return;
            groupForm.querySelectorAll('input[type="checkbox"][value="' + event.target.value + '"]').forEach((checkbox) => {
                if (checkbox !== event.target) checkbox.checked = false;
            });
        });
    }
})();
</script>
<script>
(() => {
    const tabButtons = document.querySelectorAll('#term-results-tabs [data-bs-toggle="tab"]');
    tabButtons.forEach((button) => button.addEventListener('shown.bs.tab', () => {
        document.querySelectorAll('.active-tab-field').forEach((field) => { field.value = button.dataset.bsTarget.slice(1); });
    }));
})();
</script>
<?php include '../includes/footer.php'; ?>
