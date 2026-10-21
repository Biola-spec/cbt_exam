<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireLogin();
if (getUserRole() !== 'admin') { http_response_code(403); exit('Forbidden'); }
$mysqli = getDBConnection();
$gradeRows = [];
$gradeMessage = '';
$gradeMessageType = 'info';
if (empty($_SESSION['grade_settings_csrf'])) $_SESSION['grade_settings_csrf'] = bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_grade_scale') {
    if (!hash_equals($_SESSION['grade_settings_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
        $gradeMessage = 'Your session token expired. Refresh and try again.'; $gradeMessageType = 'danger';
    } else {
        $mins = $_POST['minimum_score'] ?? []; $maxs = $_POST['maximum_score'] ?? [];
        $letters = $_POST['grade_letter'] ?? []; $remarks = $_POST['remark'] ?? []; $passing = $_POST['is_passing'] ?? [];
        $candidate = [];
        foreach ($letters as $i => $letter) {
            $minText = trim((string)($mins[$i] ?? '')); $maxText = trim((string)($maxs[$i] ?? ''));
            $letter = strtoupper(trim((string)$letter)); $remark = trim((string)($remarks[$i] ?? ''));
            if ($minText === '' && $maxText === '' && $letter === '' && $remark === '') continue;
            if (!is_numeric($minText) || !is_numeric($maxText) || $letter === '' || $remark === '') {
                $gradeMessage = 'Complete every row or remove it before saving.'; $gradeMessageType = 'danger'; break;
            }
            $min = round((float)$minText, 2); $max = round((float)$maxText, 2);
            if ($min < 0 || $max > 100 || $min > $max || strlen($letter) > 20 || strlen($remark) > 120) {
                $gradeMessage = 'Ranges must be between 0 and 100, and each row needs a valid grade and remark.'; $gradeMessageType = 'danger'; break;
            }
            $candidate[] = ['min' => $min, 'max' => $max, 'letter' => $letter, 'remark' => $remark, 'passing' => isset($passing[$i]) ? 1 : 0];
        }
        if ($gradeMessage === '') {
            usort($candidate, static function ($a, $b) { return $a['min'] <=> $b['min']; });
            $valid = count($candidate) > 0 && abs($candidate[0]['min']) < 0.001 && abs($candidate[count($candidate)-1]['max'] - 100) < 0.001;
            $seen = [];
            foreach ($candidate as $i => $row) {
                $key = strtolower($row['letter']);
                if (isset($seen[$key])) $valid = false;
                $seen[$key] = true;
                if ($i > 0) {
                    $previousMax = $candidate[$i-1]['max'];
                    $integerBand = abs($previousMax - round($previousMax, 0)) < 0.001
                        && abs($row['min'] - round($row['min'], 0)) < 0.001;
                    $expectedMinimum = $previousMax + ($integerBand ? 1 : 0.01);
                    if (abs($row['min'] - $expectedMinimum) > 0.001) $valid = false;
                }
            }
            if (!$valid) {
                $gradeMessage = 'The scale must cover 0.00 through 100.00 without gaps, overlaps, or duplicate grade letters.'; $gradeMessageType = 'danger';
            } elseif (!$mysqli) {
                $gradeMessage = 'Database connection failed.'; $gradeMessageType = 'danger';
            } else {
                try {
                    $mysqli->begin_transaction();
                    if (!$mysqli->query('DELETE FROM grade_settings')) throw new RuntimeException('Could not replace the grade scale.');
                    $stmt = $mysqli->prepare('INSERT INTO grade_settings (minimum_score, maximum_score, grade_letter, remark, is_passing, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
                    foreach ($candidate as $i => $row) {
                        $order = $i + 1;
                        $stmt->bind_param('ddssii', $row['min'], $row['max'], $row['letter'], $row['remark'], $row['passing'], $order);
                        if (!$stmt->execute()) throw new RuntimeException('Could not save the grade scale.');
                    }
                    $mysqli->commit(); $gradeMessage = 'Grade settings saved.'; $gradeMessageType = 'success';
                } catch (Throwable $e) {
                    $mysqli->rollback(); $gradeMessage = 'Grade settings could not be saved. Check the database migration and try again.'; $gradeMessageType = 'danger';
                }
            }
        }
    }
}
try {
    if ($mysqli) {
        $res = $mysqli->query('SELECT minimum_score, maximum_score, grade_letter, remark, is_passing FROM grade_settings ORDER BY minimum_score');
        while ($res && ($row = $res->fetch_assoc())) $gradeRows[] = $row;
    }
} catch (Throwable $e) {
    if ($gradeMessage === '') { $gradeMessage = 'Apply database/student_results_migration.sql before editing grade settings.'; $gradeMessageType = 'warning'; }
}
?>
<h5><i class="fas fa-sliders-h me-2"></i>Grade Settings</h5>
<p class="text-muted">Set percentage ranges, grade labels, remarks, and which grades count as passing.</p>
<?php if ($gradeMessage): ?><div class="alert alert-<?php echo htmlspecialchars($gradeMessageType); ?>"><?php echo htmlspecialchars($gradeMessage); ?></div><?php endif; ?>
<?php if (!$gradeRows && !$gradeMessage): ?><div class="alert alert-warning">Apply <code>database/student_results_migration.sql</code> to create the grading scale.</div><?php endif; ?>
<form method="post" id="grade-scale-form">
    <input type="hidden" name="action" value="save_grade_scale">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['grade_settings_csrf']); ?>">
    <div class="table-responsive">
        <table class="table table-bordered align-middle" id="grade-scale-table">
            <thead class="table-light"><tr><th>Minimum %</th><th>Maximum %</th><th>Grade</th><th>Remark</th><th>Passing</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($gradeRows as $i => $row): ?>
                <tr>
                    <td><input class="form-control" type="number" name="minimum_score[<?php echo $i; ?>]" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars($row['minimum_score']); ?>" required></td>
                    <td><input class="form-control" type="number" name="maximum_score[<?php echo $i; ?>]" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars($row['maximum_score']); ?>" required></td>
                    <td><input class="form-control" name="grade_letter[<?php echo $i; ?>]" maxlength="20" value="<?php echo htmlspecialchars($row['grade_letter']); ?>" required></td>
                    <td><input class="form-control" name="remark[<?php echo $i; ?>]" maxlength="120" value="<?php echo htmlspecialchars($row['remark']); ?>" required></td>
                    <td class="text-center"><input type="checkbox" class="form-check-input" name="is_passing[<?php echo $i; ?>]" value="1" <?php echo $row['is_passing'] ? 'checked' : ''; ?> aria-label="Passing grade"></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-grade">Delete</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-outline-secondary" id="add-grade">Add grade</button>
    <button type="button" class="btn btn-outline-info" id="load-example-scale">Load Example Scale</button>
    <button type="submit" class="btn btn-primary">Save grade settings</button>
</form>
<p class="small text-muted mt-3 mb-0">Ranges may use whole-number bands such as 0.00–39.00 followed by 40.00–44.00, or precise decimal bands with 0.01 continuity. Grade letters must be unique.</p>
<script>
(function(){
    const body = document.querySelector('#grade-scale-table tbody');
    let nextIndex = <?php echo count($gradeRows); ?>;
    const exampleScale = [
        [0, 39, 'R', 'Resit', false],
        [40, 44, 'E', 'Pass', true],
        [45, 49, 'D', 'Fair', true],
        [50, 54, 'C6', 'Average', true],
        [55, 59, 'C5', 'Good', true],
        [60, 64, 'C4', 'Very Good', true],
        [65, 69, 'B3', 'Very Good', true],
        [70, 74, 'B2', 'Very Good', true],
        [75, 100, 'A1', 'Excellent', true]
    ];
    function addRow(minimum, maximum, letter, remark, passing) {
        const i = nextIndex++;
        const tr = document.createElement('tr');
        tr.innerHTML = '<td><input class="form-control" type="number" name="minimum_score['+i+']" min="0" max="100" step="0.01" value="'+minimum.toFixed(2)+'" required></td>'+
          '<td><input class="form-control" type="number" name="maximum_score['+i+']" min="0" max="100" step="0.01" value="'+maximum.toFixed(2)+'" required></td>'+
          '<td><input class="form-control" name="grade_letter['+i+']" maxlength="20" value="'+letter+'" required></td>'+
          '<td><input class="form-control" name="remark['+i+']" maxlength="120" value="'+remark+'" required></td>'+
          '<td class="text-center"><input type="checkbox" class="form-check-input" name="is_passing['+i+']" value="1" '+(passing ? 'checked' : '')+' aria-label="Passing grade"></td>'+
          '<td><button type="button" class="btn btn-sm btn-outline-danger remove-grade">Delete</button></td>';
        body.appendChild(tr);
    }
    function reindex(){
        body.querySelectorAll('tr').forEach((row, i) => row.querySelectorAll('[name]').forEach(input => {
            input.name = input.name.replace(/\[[^\]]*\]/, '[' + i + ']');
        }));
    }
    body.addEventListener('click', function(event){
        if (!event.target.classList.contains('remove-grade')) return;
        const row = event.target.closest('tr'); const rows = Array.from(body.querySelectorAll('tr')); const index = rows.indexOf(row);
        if (rows.length <= 1) return;
        const values = row.querySelectorAll('input');
        if (index > 0) rows[index - 1].querySelectorAll('input')[1].value = values[1].value;
        else rows[index + 1].querySelectorAll('input')[0].value = '0.00';
        row.remove(); reindex();
    });
    document.getElementById('add-grade').addEventListener('click', function(){
        addRow(0, 0, '', '', true);
    });
    document.getElementById('load-example-scale').addEventListener('click', function(){
        body.innerHTML = '';
        nextIndex = 0;
        exampleScale.forEach(scale => addRow(scale[0], scale[1], scale[2], scale[3], scale[4]));
    });
})();
</script>
