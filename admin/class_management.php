<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/term_results.php';

$message = '';
$classes = [];
$search_term = '';
$editing_class = null;
$selectedTeacherIds = [];
$selectedSignatory = ['staff_id' => 0, 'title' => '', 'position' => 'head_teacher', 'signature_path' => ''];
$teacherOptions = [];
$classTeacherNames = [];
$classSignatoryNames = [];
$adminId = (int)getUserId();
$validTeacherIds = [];
$saveSignatoryUpload = static function (): ?string {
    $file = $_FILES['signatory_signature'] ?? null;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('The signatory signature must upload successfully and be no larger than 5 MB.');
    }
    $temporaryPath = $file['tmp_name'] ?? '';
    $imageInfo = $temporaryPath !== '' ? @getimagesize($temporaryPath) : false;
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $mime = $imageInfo['mime'] ?? '';
    if (!isset($extensions[$mime])) throw new RuntimeException('The signatory signature must be a JPG, PNG, GIF, or WebP image.');
    $uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('The image upload folder could not be created.');
    }
    $filename = 'report_signatory_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($temporaryPath, $uploadDirectory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('A signatory signature could not be saved. Please try again.');
    }
    return '../uploads/' . $filename;
};

try {
    $mysqli = getDBConnection();
    $teacherResult = $mysqli->query("SELECT id, full_name, role FROM users WHERE role IN ('teacher','admin','admin_teacher') ORDER BY full_name");
    while ($teacher = $teacherResult->fetch_assoc()) {
        $teacherOptions[] = $teacher;
        $validTeacherIds[] = (int)$teacher['id'];
    }
} catch (Throwable $e) {
    $message = 'Unable to load teacher list.';
}

// Handle search
if (isset($_GET['search'])) {
    $search_term = trim($_GET['search']);
}

// Check if we're editing a class
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    try {
        $mysqli = getDBConnection();
        $stmt = $mysqli->prepare("SELECT id, name, description, marks_per_question FROM classes WHERE id = ?");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $editing_class = $result->fetch_assoc();
        if ($editing_class) {
            $stmt = $mysqli->prepare('SELECT teacher_id FROM class_teacher_assignments WHERE class_id=?');
            $stmt->bind_param('i', $edit_id);
            $stmt->execute();
            $assigned = $stmt->get_result();
            while ($teacher = $assigned->fetch_assoc()) $selectedTeacherIds[] = (int)$teacher['teacher_id'];
            $stmt->close();
            $stmt = $mysqli->prepare('SELECT position, staff_id, title, signature_path FROM class_report_signatories WHERE class_id=? ORDER BY position="principal" DESC LIMIT 1');
            $stmt->bind_param('i', $edit_id);
            $stmt->execute();
            $signatory = $stmt->get_result()->fetch_assoc();
            if ($signatory) {
                $selectedSignatory = ['staff_id' => (int)$signatory['staff_id'], 'title' => $signatory['title'], 'position' => $signatory['position'], 'signature_path' => $signatory['signature_path'] ?? ''];
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        $message = 'Error loading class for editing: ' . $e->getMessage();
    }
}

// Load classes with search filter
try {
    $mysqli = getDBConnection();
    
    if (!empty($search_term)) {
        $stmt = $mysqli->prepare("SELECT id, name, description, marks_per_question, created_at FROM classes WHERE name LIKE ? OR description LIKE ? ORDER BY name");
        $search_param = "%$search_term%";
        $stmt->bind_param("ss", $search_param, $search_param);
    } else {
        $stmt = $mysqli->prepare("SELECT id, name, description, marks_per_question, created_at FROM classes ORDER BY name");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
} catch (Exception $e) {
    $message = 'Error loading classes: ' . $e->getMessage();
}

// Handle form submission for adding new class
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $marks_per_question = isset($_POST['marks_per_question']) ? floatval($_POST['marks_per_question']) : 1.0;
    
    if (!empty($name)) {
        try {
            $transactionActive = false;
            // Check if class already exists
            $mysqli = getDBConnection();
            $stmt = $mysqli->prepare("SELECT id FROM classes WHERE name = ?");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $message = 'A class with this name already exists';
            } else {
                $teacherIds = array_values(array_unique(array_filter(array_map('intval', $_POST['teacher_ids'] ?? []))));
                $teacherIds = array_values(array_intersect($teacherIds, $validTeacherIds));
                $mysqli->begin_transaction();
                $transactionActive = true;
                $stmt = $mysqli->prepare("INSERT INTO classes (name, description, marks_per_question) VALUES (?, ?, ?)");
                $stmt->bind_param("ssd", $name, $description, $marks_per_question);
                $stmt->execute();
                $newClassId = (int)$mysqli->insert_id;
                if ($teacherIds) {
                    $assignStmt = $mysqli->prepare('INSERT INTO class_teacher_assignments (class_id, teacher_id, assigned_by) VALUES (?, ?, ?)');
                    foreach ($teacherIds as $teacherId) {
                        $assignStmt->bind_param('iii', $newClassId, $teacherId, $adminId);
                        $assignStmt->execute();
                    }
                    $assignStmt->close();
                }
                $signatoryId = (int)($_POST['signatory_id'] ?? 0);
                if (in_array($signatoryId, $validTeacherIds, true)) {
                    $signatoryTitle = trim(substr((string)($_POST['signatory_title'] ?? ''), 0, 80));
                    if ($signatoryTitle === '') $signatoryTitle = 'Head Teacher';
                    $position = stripos($signatoryTitle, 'principal') !== false ? 'principal' : 'head_teacher';
                    $signaturePath = $saveSignatoryUpload();
                    $signatoryStmt = $mysqli->prepare('INSERT INTO class_report_signatories (class_id, position, staff_id, title, signature_path, assigned_by) VALUES (?, ?, ?, ?, ?, ?)');
                    $signatoryStmt->bind_param('isissi', $newClassId, $position, $signatoryId, $signatoryTitle, $signaturePath, $adminId);
                    $signatoryStmt->execute();
                    $signatoryStmt->close();
                }
                $mysqli->commit();
                $transactionActive = false;
                $message = 'Class added successfully';
                
                // Refresh classes list
                if (!empty($search_term)) {
                    $stmt = $mysqli->prepare("SELECT id, name, description, marks_per_question, created_at FROM classes WHERE name LIKE ? OR description LIKE ? ORDER BY name");
                    $search_param = "%$search_term%";
                    $stmt->bind_param("ss", $search_param, $search_param);
                } else {
                    $stmt = $mysqli->prepare("SELECT id, name, description, marks_per_question, created_at FROM classes ORDER BY name");
                }
                $stmt->execute();
                $result = $stmt->get_result();
                $classes = [];
                while ($row = $result->fetch_assoc()) {
                    $classes[] = $row;
                }
            }
        } catch (Exception $e) {
            if ($transactionActive) $mysqli->rollback();
            $message = 'Error adding class: ' . $e->getMessage();
        }
    } else {
        $message = 'Class name is required';
    }
}

// Handle form submission for editing class
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $class_id = (int)$_POST['class_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $marks_per_question = isset($_POST['marks_per_question']) ? floatval($_POST['marks_per_question']) : 1.0;
    
    if (!empty($name)) {
        try {
            $transactionActive = false;
            $mysqli = getDBConnection();
            
            // Check if another class already has this name
            $stmt = $mysqli->prepare("SELECT id FROM classes WHERE name = ? AND id != ?");
            $stmt->bind_param("si", $name, $class_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $message = 'Another class with this name already exists';
            } else {
                $teacherIds = array_values(array_unique(array_filter(array_map('intval', $_POST['teacher_ids'] ?? []))));
                $teacherIds = array_values(array_intersect($teacherIds, $validTeacherIds));
                $mysqli->begin_transaction();
                $transactionActive = true;
                $stmt = $mysqli->prepare("UPDATE classes SET name = ?, description = ?, marks_per_question = ? WHERE id = ?");
                $stmt->bind_param("ssdi", $name, $description, $marks_per_question, $class_id);
                $stmt->execute();
                $stmt = $mysqli->prepare('DELETE FROM class_teacher_assignments WHERE class_id=?');
                $stmt->bind_param('i', $class_id);
                $stmt->execute();
                $stmt->close();
                if ($teacherIds) {
                    $assignStmt = $mysqli->prepare('INSERT INTO class_teacher_assignments (class_id, teacher_id, assigned_by) VALUES (?, ?, ?)');
                    foreach ($teacherIds as $teacherId) {
                        $assignStmt->bind_param('iii', $class_id, $teacherId, $adminId);
                        $assignStmt->execute();
                    }
                    $assignStmt->close();
                }
                $previousStmt = $mysqli->prepare('SELECT staff_id, signature_path FROM class_report_signatories WHERE class_id=? ORDER BY position="principal" DESC LIMIT 1');
                $previousStmt->bind_param('i', $class_id);
                $previousStmt->execute();
                $previousSignatory = $previousStmt->get_result()->fetch_assoc();
                $previousStmt->close();
                $deleteSignatory = $mysqli->prepare('DELETE FROM class_report_signatories WHERE class_id=?');
                $deleteSignatory->bind_param('i', $class_id);
                $deleteSignatory->execute();
                $deleteSignatory->close();
                $signatoryId = (int)($_POST['signatory_id'] ?? 0);
                if (in_array($signatoryId, $validTeacherIds, true)) {
                    $signatoryTitle = trim(substr((string)($_POST['signatory_title'] ?? ''), 0, 80));
                    if ($signatoryTitle === '') $signatoryTitle = 'Head Teacher';
                    $position = stripos($signatoryTitle, 'principal') !== false ? 'principal' : 'head_teacher';
                    $signaturePath = $saveSignatoryUpload();
                    if ($signaturePath === null && (int)($previousSignatory['staff_id'] ?? 0) === $signatoryId) {
                        $signaturePath = $previousSignatory['signature_path'] ?? null;
                    }
                    $signatoryStmt = $mysqli->prepare('INSERT INTO class_report_signatories (class_id, position, staff_id, title, signature_path, assigned_by) VALUES (?, ?, ?, ?, ?, ?)');
                    $signatoryStmt->bind_param('isissi', $class_id, $position, $signatoryId, $signatoryTitle, $signaturePath, $adminId);
                    $signatoryStmt->execute();
                    $signatoryStmt->close();
                }
                $mysqli->commit();
                $transactionActive = false;
                $message = 'Class updated successfully';
                
                // Clear editing mode
                $editing_class = null;
                
                // Refresh classes list
                if (!empty($search_term)) {
                    $stmt = $mysqli->prepare("SELECT id, name, description, marks_per_question, created_at FROM classes WHERE name LIKE ? OR description LIKE ? ORDER BY name");
                    $search_param = "%$search_term%";
                    $stmt->bind_param("ss", $search_param, $search_param);
                } else {
                    $stmt = $mysqli->prepare("SELECT id, name, description, marks_per_question, created_at FROM classes ORDER BY name");
                }
                $stmt->execute();
                $result = $stmt->get_result();
                $classes = [];
                while ($row = $result->fetch_assoc()) {
                    $classes[] = $row;
                }
            }
        } catch (Exception $e) {
            if ($transactionActive) $mysqli->rollback();
            $message = 'Error updating class: ' . $e->getMessage();
        }
    } else {
        $message = 'Class name is required';
    }
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $class_id = $_POST['class_id'];
    
    try {
        $mysqli = getDBConnection();
        $stmt = $mysqli->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $message = 'Class deleted successfully';
        
        // Refresh classes list
        if (!empty($search_term)) {
            $stmt = $mysqli->prepare("SELECT id, name, description, marks_per_question, created_at FROM classes WHERE name LIKE ? OR description LIKE ? ORDER BY name");
            $search_param = "%$search_term%";
            $stmt->bind_param("ss", $search_param, $search_param);
        } else {
            $stmt = $mysqli->prepare("SELECT id, name, description, marks_per_question, created_at FROM classes ORDER BY name");
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $classes = [];
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
        
        // Clear editing mode if we were editing the deleted class
        if ($editing_class && $editing_class['id'] == $class_id) {
            $editing_class = null;
        }
    } catch (Exception $e) {
        $message = 'Error deleting class. It might be referenced by other records.';
    }
}

try {
    $teacherResult = $mysqli->query("SELECT cta.class_id, u.full_name FROM class_teacher_assignments cta JOIN users u ON u.id=cta.teacher_id ORDER BY u.full_name");
    while ($teacher = $teacherResult->fetch_assoc()) $classTeacherNames[(int)$teacher['class_id']][] = $teacher['full_name'];
    $signatoryResult = $mysqli->query("SELECT crs.class_id, crs.position, crs.title, u.full_name FROM class_report_signatories crs JOIN users u ON u.id=crs.staff_id");
    while ($signatory = $signatoryResult->fetch_assoc()) $classSignatoryNames[(int)$signatory['class_id']][] = $signatory['title'] . ': ' . $signatory['full_name'];
} catch (Throwable $e) {
    // The migration may not have been applied yet; the rest of class management remains usable.
}
?>

<h5><i class="fas fa-book me-2"></i>Class Management</h5>

<?php if ($message): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Search Form -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="d-flex">
            <input type="hidden" name="tab" value="classes">
            <div class="input-group">
                <input type="text" class="form-control" name="search" placeholder="Search classes..." value="<?php echo htmlspecialchars($search_term); ?>">
                <button class="btn btn-outline-secondary" type="submit">
                    <i class="fas fa-search"></i>
                </button>
                <?php if (!empty($search_term)): ?>
                    <a href="dashboard.php?tab=classes" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Add/Edit Class Form -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="fas fa-<?php echo $editing_class ? 'edit' : 'plus-circle'; ?> me-2"></i>
            <?php echo $editing_class ? 'Edit Class' : 'Add New Class'; ?>
        </h6>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?php echo $editing_class ? 'edit' : 'add'; ?>">
            <?php if ($editing_class): ?>
                <input type="hidden" name="class_id" value="<?php echo $editing_class['id']; ?>">
            <?php endif; ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="name" class="form-label">Class Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo $editing_class ? htmlspecialchars($editing_class['name']) : ''; ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="marks_per_question" class="form-label">Marks Per Question</label>
                        <input type="number" class="form-control" id="marks_per_question" name="marks_per_question" step="0.01" min="0.01" value="<?php echo $editing_class ? htmlspecialchars($editing_class['marks_per_question']) : '1.00'; ?>" required>
                        <div class="form-text">Enter the marks awarded for each correct answer (e.g., 1.00 or 0.50)</div>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <input type="text" class="form-control" id="description" name="description" value="<?php echo $editing_class ? htmlspecialchars($editing_class['description'] ?? '') : ''; ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <fieldset class="border rounded p-3 mb-3">
                        <legend class="float-none w-auto px-2 fs-6 mb-1">Report Card Signatory</legend>
                        <label class="form-label" for="signatory_id">Appointed staff</label>
                        <select class="form-select mb-2" id="signatory_id" name="signatory_id">
                            <option value="0">No signatory assigned</option>
                            <?php foreach ($teacherOptions as $staff): ?>
                                <option value="<?php echo (int)$staff['id']; ?>" <?php echo $selectedSignatory['staff_id'] === (int)$staff['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($staff['full_name'] . ' (' . ucfirst($staff['role']) . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="form-label" for="signatory_title">Report-card title</label>
                        <input type="text" class="form-control" id="signatory_title" name="signatory_title" maxlength="80" placeholder="e.g. Principal, Head Teacher, School Director" value="<?php echo htmlspecialchars($selectedSignatory['title']); ?>">
                        <div class="form-text">Enter one title for this class. For example, use “Principal” for JSS 1 or a custom title for another class.</div>
                        <?php if (!empty($selectedSignatory['signature_path'])): ?><img class="img-thumbnail mt-2" src="<?php echo htmlspecialchars($selectedSignatory['signature_path']); ?>" alt="Current report signatory signature" style="max-width:180px;max-height:58px;object-fit:contain"><?php endif; ?>
                        <label class="form-label mt-2" for="signatory_signature">Appointed staff member's signature</label>
                        <input type="file" class="form-control" id="signatory_signature" name="signatory_signature" accept=".jpg,.jpeg,.png,.gif,.webp">
                        <div class="form-text">Changing the appointed staff member will not carry over the previous person's signature.</div>
                    </fieldset>
                </div>
                <div class="col-md-12">
                    <div class="mb-3">
                        <fieldset>
                            <legend class="form-label fs-6">Class Teachers</legend>
                            <?php if ($teacherOptions): ?><div class="row row-cols-1 row-cols-md-2 g-2">
                                <?php foreach ($teacherOptions as $teacher): ?><div class="col"><label class="form-check border rounded p-2 ps-5"><input class="form-check-input" type="checkbox" name="teacher_ids[]" value="<?php echo (int)$teacher['id']; ?>" <?php echo in_array((int)$teacher['id'], $selectedTeacherIds, true) ? 'checked' : ''; ?>><span class="form-check-label"><?php echo htmlspecialchars($teacher['full_name']); ?> <span class="text-muted small">(<?php echo htmlspecialchars(ucfirst($teacher['role'])); ?>)</span></span></label></div><?php endforeach; ?>
                            </div><?php else: ?><p class="text-muted mb-0">No teachers registered yet.</p><?php endif; ?>
                            <div class="form-text">Select all teachers responsible for attendance and class remarks.</div>
                        </fieldset>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <?php echo $editing_class ? 'Update Class' : 'Add Class'; ?>
            </button>
            <?php if ($editing_class): ?>
                <a href="dashboard.php?tab=classes" class="btn btn-secondary">
                    Cancel
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Classes List -->
<div class="card admin-print-area" id="class-directory-print">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-list me-2"></i>Existing Classes (<?php echo count($classes); ?>)</h6>
        <button type="button" class="btn btn-outline-primary btn-sm no-print" onclick="window.print()"><i class="fas fa-print me-1"></i> Print / Save PDF</button>
    </div>
    <div class="card-body">
        <?php if (count($classes) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Class Name</th>
                            <th>Description</th>
                            <th>Marks/Question</th>
                            <th>Class Teachers</th>
                            <th>Report Card Signatory</th>
                            <th>Created At</th>
                            <th width="150" class="text-center no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes as $index => $class): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($class['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($class['description'] ?? ''); ?></td>
                                <td><span class="badge bg-secondary"><?php echo number_format($class['marks_per_question'], 2); ?></span></td>
                                <td><?php echo htmlspecialchars(implode(', ', $classTeacherNames[(int)$class['id']] ?? [] ) ?: 'Not assigned'); ?></td>
                                <td><?php echo htmlspecialchars(implode(' · ', $classSignatoryNames[(int)$class['id']] ?? []) ?: 'Not assigned'); ?></td>
                                <td><?php echo date('M j, Y', strtotime($class['created_at'])); ?></td>
                                <td class="text-center no-print">
                                    <div class="btn-group btn-group-sm">
                                        <a href="dashboard.php?tab=classes&edit=<?php echo $class['id']; ?>" class="btn btn-info text-white">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this class? This action cannot be undone.')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                            <button type="submit" class="btn btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                <p class="text-muted small">
                    <?php echo !empty($search_term) ? 'No classes match your search' : 'No classes found. Add a new class to get started.'; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>
