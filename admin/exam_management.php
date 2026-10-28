<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/term_results.php';
requireLogin();
if (getUserRole() !== 'admin') { http_response_code(403); exit('Forbidden'); }

$mysqli = getDBConnection();
$resultSessions = [];
if ($mysqli) {
    $sessionResult = $mysqli->query('SELECT id, name, is_current FROM academic_sessions ORDER BY is_current DESC, name DESC');
    if ($sessionResult) while ($sessionRow = $sessionResult->fetch_assoc()) $resultSessions[] = $sessionRow;
}

if (isset($_GET['action']) && $_GET['action'] === 'get_result_components') {
    header('Content-Type: application/json');
    $sessionId = (int)($_GET['session_id'] ?? 0);
    $classId = (int)($_GET['class_id'] ?? 0);
    $components = [];
    if ($mysqli && $sessionId > 0) {
        if ($classId > 0) {
            $stmt = $mysqli->prepare('SELECT ca_count, exam_count FROM class_result_settings WHERE class_id=? AND session_id=?');
            $stmt->bind_param('ii', $classId, $sessionId);
            $stmt->execute();
            $settings = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$settings) $settings = ['ca_count' => 3, 'exam_count' => 1];
            $customLabels = [];
            $stmt = $mysqli->prepare('SELECT component_type, component_number, label FROM class_result_component_names WHERE class_id=? AND session_id=?');
            $stmt->bind_param('ii', $classId, $sessionId);
            $stmt->execute();
            $labelRows = $stmt->get_result();
            while ($labelRow = $labelRows->fetch_assoc()) $customLabels[$labelRow['component_type']][(int)$labelRow['component_number']] = $labelRow['label'];
            $stmt->close();
        } else {
            $stmt = $mysqli->prepare('SELECT MAX(crs.ca_count) AS ca_count, MAX(crs.exam_count) AS exam_count FROM academic_sessions a LEFT JOIN class_result_settings crs ON crs.session_id=a.id WHERE a.id=? GROUP BY a.id');
            $stmt->bind_param('i', $sessionId);
            $stmt->execute();
            $settings = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $customLabels = [];
        }
        if ($settings) {
            $counts = ['ca' => $settings['ca_count'] === null ? 3 : (int)$settings['ca_count'], 'exam' => $settings['exam_count'] === null ? 1 : (int)$settings['exam_count']];
            foreach ($counts as $type => $count) {
                for ($number = 1; $number <= $count; $number++) {
                    $defaultLabel = ($type === 'ca' ? 'CA ' : 'Exam ') . $number;
                    $components[] = [
                        'value' => $type . ':' . $number,
                        'label' => $customLabels[$type][$number] ?? $defaultLabel,
                    ];
                }
            }
        }
    }
    echo json_encode($components);
    exit();
}

function examResultComponentAvailableForClass(mysqli $mysqli, int $classId, int $sessionId, string $type, int $number): bool {
    if ($classId < 1 || $sessionId < 1 || !in_array($type, ['ca', 'exam'], true)) return false;
    $stmt = $mysqli->prepare('SELECT ca_count, exam_count FROM class_result_settings WHERE class_id=? AND session_id=?');
    $stmt->bind_param('ii', $classId, $sessionId);
    $stmt->execute();
    $settings = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $default = $type === 'ca' ? 3 : 1;
    $count = $settings ? (int)$settings[$type . '_count'] : $default;
    return $number >= 1 && $number <= $count;
}

function parseExamResultComponent($value): array {
    $parts = explode(':', (string)$value, 2);
    return [isset($parts[0]) ? (string)$parts[0] : '', isset($parts[1]) ? (int)$parts[1] : 0];
}

// Handle AJAX request for subjects based on class
if (isset($_GET['action']) && $_GET['action'] == 'get_subjects' && isset($_GET['class_id'])) {
    header('Content-Type: application/json');
    $class_id = (int)$_GET['class_id'];
    $subjects = [];
    
    if ($class_id > 0) {
        try {
            if ($mysqli) {
                $stmt = $mysqli->prepare("SELECT s.id, s.name 
                                         FROM subjects s
                                         JOIN subject_class_link scl ON s.id = scl.subject_id
                                         WHERE scl.class_id = ?
                                         ORDER BY s.name");
                $stmt->bind_param("i", $class_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $subjects[] = $row;
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            // Return empty array on error
        }
    }
    
    echo json_encode($subjects);
    exit();
}

$message = '';
$exams = [];
$classes = [];
$subjects = [];

try {
    $mysqli = getDBConnection();
    if ($mysqli) {
            // Refresh exams list
            $stmt = $mysqli->prepare("SELECT e.id, e.title, e.academic_session, e.result_session_id, e.result_term, e.result_component_type, e.result_component_number, e.description, e.is_published, e.published_at, e.created_by, e.created_at FROM exams e WHERE NOT EXISTS (SELECT 1 FROM entrance_exams ee JOIN exam_subjects es ON es.id=ee.exam_subject_id WHERE es.exam_id=e.id) ORDER BY e.created_at DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $exams[] = $row;
        }
        $stmt->close();
        
        // Load classes
        $stmt = $mysqli->prepare("SELECT id, name FROM classes ORDER BY name");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
        $stmt->close();
        
        // Load subjects that are linked to at least one class
        $stmt = $mysqli->prepare("SELECT DISTINCT s.id, s.name 
                                 FROM subjects s
                                 JOIN subject_class_link scl ON s.id = scl.subject_id
                                 ORDER BY s.name");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    $message = 'Error loading data: ' . $e->getMessage();
}


// Handle creation of new exam
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $academic_session = trim($_POST['academic_session'] ?? '');
    if ($academic_session === '') $academic_session = null;
    if (!empty($title)) {
        $createTransaction = false;
        try {
            if ($mysqli) {
                $user_id = getUserId();
                if ($user_id) {
                    $mysqli->begin_transaction();
                    $createTransaction = true;
                    $stmt = $mysqli->prepare('INSERT INTO exams (title, academic_session, description, created_by) VALUES (?, ?, ?, ?)');
                    $stmt->bind_param('sssi', $title, $academic_session, $description, $user_id);
                    $stmt->execute();
                    $stmt->close();
                    $mysqli->commit();
                    $createTransaction = false;
                    $message = 'Exam created. Add classes and subjects, then set a report-card component for each class.';
                } else {
                    $message = 'Error: User not found in database';
                }
                
                // Refresh exams list
                $stmt = $mysqli->prepare("SELECT e.id, e.title, e.academic_session, e.result_session_id, e.result_term, e.result_component_type, e.result_component_number, e.description, e.is_published, e.published_at, e.created_by, e.created_at FROM exams e WHERE NOT EXISTS (SELECT 1 FROM entrance_exams ee JOIN exam_subjects es ON es.id=ee.exam_subject_id WHERE es.exam_id=e.id) ORDER BY e.created_at DESC");
                $stmt->execute();
                $result = $stmt->get_result();
                $exams = [];
                while ($row = $result->fetch_assoc()) {
                    $exams[] = $row;
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            if ($mysqli && $createTransaction) $mysqli->rollback();
            $message = 'Error creating exam: ' . $e->getMessage();
        }
    } else {
        $message = 'Exam title is required';
    }
}

// Handle updating of exam title and description
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_exam') {
    $exam_id = (int)$_POST['exam_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $academic_session = trim($_POST['academic_session'] ?? '');
    if ($academic_session === '') $academic_session = null;
    
    if ($exam_id > 0 && !empty($title)) {
        try {
            if ($mysqli) {
                $stmt = $mysqli->prepare("UPDATE exams SET title = ?, academic_session = ?, description = ? WHERE id = ?");
                $stmt->bind_param("sssi", $title, $academic_session, $description, $exam_id);
                $stmt->execute();
                $stmt->close();
                $message = 'Exam updated successfully';
                
                // Refresh exams list
                $stmt = $mysqli->prepare("SELECT e.id, e.title, e.academic_session, e.result_session_id, e.result_term, e.result_component_type, e.result_component_number, e.description, e.is_published, e.published_at, e.created_by, e.created_at FROM exams e WHERE NOT EXISTS (SELECT 1 FROM entrance_exams ee JOIN exam_subjects es ON es.id=ee.exam_subject_id WHERE es.exam_id=e.id) ORDER BY e.created_at DESC");
                $stmt->execute();
                $result = $stmt->get_result();
                $exams = [];
                while ($row = $result->fetch_assoc()) {
                    $exams[] = $row;
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            $message = 'Error updating exam: ' . $e->getMessage();
        }
    } else {
        $message = 'Exam title is required';
    }
}

// Handle adding subject to exam
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_subject') {
    $exam_id = $_POST['exam_id'];
    $class_id = $_POST['class_id'];
    $subject_id = $_POST['subject_id'];
    // Use default time limit from school configuration if not provided
    $time_limit = isset($_POST['time_limit']) ? (int)$_POST['time_limit'] : 60;
    
    if ($mysqli && !empty($exam_id) && !empty($class_id) && !empty($subject_id)) {
        try {
            $mysqli = getDBConnection();
            if ($mysqli) {
                // Check if this combination already exists
                $stmt = $mysqli->prepare("SELECT id FROM exam_subjects WHERE exam_id = ? AND class_id = ? AND subject_id = ?");
                $stmt->bind_param("iii", $exam_id, $class_id, $subject_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $message = 'This subject is already added to the exam for the selected class.';
                } else {
                    // Add the subject to the exam with time limit
                    $stmt = $mysqli->prepare("INSERT INTO exam_subjects (exam_id, class_id, subject_id, time_limit) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("iiii", $exam_id, $class_id, $subject_id, $time_limit);
                    $stmt->execute();
                    $message = 'Subject added. Set the result component for this class in the class links below.';
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            $message = 'Error adding subject to exam: ' . $e->getMessage();
        }
    } else {
        $message = 'Please select class and subject';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'link_class_result_component' && !isset($_POST['clear_class_result_component'])) {
    $examId = (int)($_POST['exam_id'] ?? 0);
    $classId = (int)($_POST['class_id'] ?? 0);
    $sessionId = (int)($_POST['result_session_id'] ?? 0);
    $termValue = (int)($_POST['result_term'] ?? 0);
    [$componentType, $componentNumber] = parseExamResultComponent($_POST['result_component'] ?? '');
    if ($mysqli && $examId > 0 && $classId > 0 && $termValue >= 1 && $termValue <= 3 && examResultComponentAvailableForClass($mysqli, $classId, $sessionId, $componentType, $componentNumber)) {
        $stmt = $mysqli->prepare('SELECT 1 FROM exam_subjects WHERE exam_id=? AND class_id=? LIMIT 1');
        $stmt->bind_param('ii', $examId, $classId);
        $stmt->execute();
        $classIsInExam = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($classIsInExam) {
            $stmt = $mysqli->prepare('INSERT INTO exam_class_result_links (exam_id,class_id,result_session_id,result_term,result_component_type,result_component_number) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE result_session_id=VALUES(result_session_id),result_term=VALUES(result_term),result_component_type=VALUES(result_component_type),result_component_number=VALUES(result_component_number)');
            $stmt->bind_param('iiiisi', $examId, $classId, $sessionId, $termValue, $componentType, $componentNumber);
            $stmt->execute();
            $stmt->close();
            $message = 'Report-card component saved for every subject in this class under the exam.';
        } else {
            $message = 'Add a subject for this class to the exam before setting a class-specific component.';
        }
    } else {
        $message = 'That component is not configured for the selected class and session.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['clear_class_result_component'] ?? '') === '1') {
    $examId = (int)($_POST['exam_id'] ?? 0);
    $classId = (int)($_POST['class_id'] ?? 0);
    if ($mysqli && $examId > 0 && $classId > 0) {
        $stmt = $mysqli->prepare('DELETE FROM exam_class_result_links WHERE exam_id=? AND class_id=?');
        $stmt->bind_param('ii', $examId, $classId);
        $stmt->execute();
        $stmt->close();
        $message = 'The class report-card link was cleared. CBT scores will not import to term results until a class link is set.';
    }
}

// Handle updating time limit for existing exam subject
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_time_limit') {
    $exam_subject_id = $_POST['exam_subject_id'];
    // Use default time limit from school configuration if not provided
    $time_limit = isset($_POST['time_limit']) ? (int)$_POST['time_limit'] : 60;
    
    if (!empty($exam_subject_id) && !empty($time_limit)) {
        try {
            $mysqli = getDBConnection();
            if ($mysqli) {
                $stmt = $mysqli->prepare("UPDATE exam_subjects SET time_limit = ? WHERE id = ?");
                $stmt->bind_param("ii", $time_limit, $exam_subject_id);
                $stmt->execute();
                $stmt->close();
                $message = 'Time limit updated successfully to ' . $time_limit . ' minutes';
            }
        } catch (Exception $e) {
            $message = 'Error updating time limit: ' . $e->getMessage();
        }
    } else {
        $message = 'Invalid time limit value';
    }
}

// Handle publish/unpublish exam
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'publish') {
    $exam_id = $_POST['exam_id'];
    $is_published = $_POST['is_published'];
    
    try {
        $mysqli = getDBConnection();
        if ($mysqli) {
            if ($is_published) {
                // Unpublish exam
                $stmt = $mysqli->prepare("UPDATE exams SET is_published = 0, published_at = NULL WHERE id = ?");
                $stmt->bind_param("i", $exam_id);
                $stmt->execute();
                $stmt->close();
                $message = 'Exam unpublished successfully';
            } else {
                // Publish exam
                $stmt = $mysqli->prepare("UPDATE exams SET is_published = 1, published_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $exam_id);
                $stmt->execute();
                $stmt->close();
                $message = 'Exam published successfully';
            }
            
            // Refresh exams list
            $stmt = $mysqli->prepare("SELECT e.id, e.title, e.academic_session, e.result_session_id, e.result_term, e.result_component_type, e.result_component_number, e.description, e.is_published, e.published_at, e.created_by, e.created_at FROM exams e WHERE NOT EXISTS (SELECT 1 FROM entrance_exams ee JOIN exam_subjects es ON es.id=ee.exam_subject_id WHERE es.exam_id=e.id) ORDER BY e.created_at DESC");
            $stmt->execute();
            $result = $stmt->get_result();
            $exams = [];
            while ($row = $result->fetch_assoc()) {
                $exams[] = $row;
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        $message = 'Error updating exam status: ' . $e->getMessage();
    }
}

// Handle publish/unpublish exam subject
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'publish_subject') {
    $exam_subject_id = $_POST['exam_subject_id'];
    $is_published = $_POST['is_published'];
    
    try {
        $mysqli = getDBConnection();
        if ($mysqli) {
            if ($is_published) {
                // Unpublish exam subject
                $stmt = $mysqli->prepare("UPDATE exam_subjects SET is_published = 0, published_at = NULL WHERE id = ?");
                $stmt->bind_param("i", $exam_subject_id);
                $stmt->execute();
                $stmt->close();
                $message = 'Subject unpublished successfully';
            } else {
                // Publish exam subject
                $stmt = $mysqli->prepare("UPDATE exam_subjects SET is_published = 1, published_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $exam_subject_id);
                $stmt->execute();
                $stmt->close();
                $message = 'Subject published successfully';
            }
        }
    } catch (Exception $e) {
        $message = 'Error updating subject status: ' . $e->getMessage();
    }
}

// Handle deletion of exam subject
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_subject') {
    $exam_subject_id = $_POST['exam_subject_id'];
    
    try {
        $mysqli = getDBConnection();
        if ($mysqli) {
            $stmt = $mysqli->prepare("DELETE FROM exam_subjects WHERE id = ?");
            $stmt->bind_param("i", $exam_subject_id);
            $stmt->execute();
            $stmt->close();
            $message = 'Subject removed from exam successfully';
        }
    } catch (Exception $e) {
        $message = 'Error removing subject from exam: ' . $e->getMessage();
    }
}

// Handle deletion of exam
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $exam_id = $_POST['exam_id'];
    
    try {
        $mysqli = getDBConnection();
        if ($mysqli) {
            // First delete all exam_subjects for this exam
            $stmt = $mysqli->prepare("DELETE FROM exam_subjects WHERE exam_id = ?");
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            $stmt->close();
            
            // Then delete the exam
            $stmt = $mysqli->prepare("DELETE FROM exams WHERE id = ?");
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            $stmt->close();
            
            $message = 'Exam deleted successfully';
            
            // Refresh exams list
            $stmt = $mysqli->prepare("SELECT e.id, e.title, e.academic_session, e.result_session_id, e.result_term, e.result_component_type, e.result_component_number, e.description, e.is_published, e.published_at, e.created_by, e.created_at FROM exams e WHERE NOT EXISTS (SELECT 1 FROM entrance_exams ee JOIN exam_subjects es ON es.id=ee.exam_subject_id WHERE es.exam_id=e.id) ORDER BY e.created_at DESC");
            $stmt->execute();
            $result = $stmt->get_result();
            $exams = [];
            while ($row = $result->fetch_assoc()) {
                $exams[] = $row;
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        $message = 'Error deleting exam: ' . $e->getMessage();
    }
}

// Handle toggle subject calculator
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'toggle_subject_calculator') {
    $exam_subject_id = (int)$_POST['exam_subject_id'];
    $show_calculator = (int)$_POST['show_calculator'];
    
    try {
        $mysqli = getDBConnection();
        if ($mysqli) {
            $stmt = $mysqli->prepare("UPDATE exam_subjects SET show_calculator = ? WHERE id = ?");
            $stmt->bind_param("ii", $show_calculator, $exam_subject_id);
            $stmt->execute();
            $stmt->close();
            $message = 'Subject calculator setting updated successfully';
        }
    } catch (Exception $e) {
        $message = 'Error updating subject calculator setting: ' . $e->getMessage();
    }
}
?>
<h5><i class="fas fa-file-alt me-2"></i>Exam Management</h5>

<?php if ($message): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Information Panel -->
<div class="card mb-4 bg-light">
    <div class="card-body">
        <h6><i class="fas fa-info-circle me-2"></i>How Exam Management Works</h6>
        <p class="mb-2">To make exams available to teachers:</p>
        <ol class="mb-0">
            <li>Create the exam</li>
            <li>Add its classes and subjects, then link a report-card component for each class</li>
            <li>Publish the exam subjects so teachers can see them</li>
        </ol>
        <p class="mb-0 mt-2"><strong>Note:</strong> Teachers will only see exams that have been published and are linked to their class/subject allocations.</p>
    </div>
</div>

<!-- Add Exam Form -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Create New Exam</h6>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="title" class="form-label">Exam Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <input type="text" class="form-control" id="description" name="description">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="academic_session" class="form-label">Academic Session</label>
                        <input type="text" class="form-control" id="academic_session" name="academic_session" maxlength="50" placeholder="e.g. 2026/2027">
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Create Exam
            </button>
        </form>
    </div>
</div>

<!-- Exams List -->
<div class="card">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-list me-2"></i>Existing Exams (<?php echo count($exams); ?>)</h6>
    </div>
    <div class="card-body">
        <?php if (count($exams) > 0): ?>
            <div class="accordion" id="examsAccordion">
                <?php foreach ($exams as $exam): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading<?php echo $exam['id']; ?>">
                            <button class="accordion-button <?php echo $exam === reset($exams) ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $exam['id']; ?>" aria-expanded="<?php echo $exam === reset($exams) ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $exam['id']; ?>">
                                <div class="d-flex justify-content-between align-items-center w-100">
                                    <span>
                                        <strong><?php echo htmlspecialchars($exam['title']); ?></strong>
                                        <?php if ($exam['description']): ?>
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($exam['description']); ?></small>
                                        <?php endif; ?>
                                    </span>
                                    <span>
                                        <?php if ($exam['is_published']): ?>
                                            <span class="badge bg-success">Published</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Draft</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse<?php echo $exam['id']; ?>" class="accordion-collapse collapse <?php echo $exam === reset($exams) ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $exam['id']; ?>" data-bs-parent="#examsAccordion">
                            <div class="accordion-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex align-items-center">
                                        <small class="text-muted">
                                            <?php if ($exam['is_published']): ?>
                                                Published: <?php echo date('M j, Y g:i A', strtotime($exam['published_at'])); ?>
                                            <?php else: ?>
                                                Created: <?php echo date('M j, Y g:i A', strtotime($exam['created_at'])); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="publish">
                                            <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                            <input type="hidden" name="is_published" value="<?php echo $exam['is_published'] ? '1' : '0'; ?>">
                                            <button type="submit" class="btn btn-<?php echo $exam['is_published'] ? 'warning' : 'success'; ?> btn-sm">
                                                <i class="fas fa-<?php echo $exam['is_published'] ? 'eye-slash' : 'eye'; ?> me-1"></i>
                                                <?php echo $exam['is_published'] ? 'Unpublish' : 'Publish'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this exam and all its subjects?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash me-1"></i> Delete
                                            </button>
                                        </form>
                                        <!-- Edit Exam Button -->
                                        <button type="button" class="btn btn-primary btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#editExamModal<?php echo $exam['id']; ?>">
                                            <i class="fas fa-edit me-1"></i> Edit
                                        </button>
                                    </div>
                                </div>

                                <?php
                                $examClassMappings = [];
                                $classMapStmt = $mysqli->prepare('SELECT DISTINCT c.id AS class_id, c.name AS class_name, ecl.result_session_id AS override_session_id, ecl.result_term AS override_term, ecl.result_component_type AS override_component_type, ecl.result_component_number AS override_component_number FROM exam_subjects es JOIN classes c ON c.id=es.class_id LEFT JOIN exam_class_result_links ecl ON ecl.exam_id=es.exam_id AND ecl.class_id=es.class_id WHERE es.exam_id=? ORDER BY c.name');
                                $classMapStmt->bind_param('i', $exam['id']);
                                $classMapStmt->execute();
                                $classMapResult = $classMapStmt->get_result();
                                while ($classMapRow = $classMapResult->fetch_assoc()) $examClassMappings[] = $classMapRow;
                                $classMapStmt->close();
                                ?>
                                <?php if ($examClassMappings): ?>
                                    <div class="mb-3">
                                        <div class="fw-semibold mb-1">Report-card component by class</div>
                                        <div class="form-text mb-2">Set the session, term and component separately for each class. A class link applies to all subjects in that class. Custom component names and maximum scores come from the class's result settings.</div>
                                        <div class="row row-cols-1 row-cols-xl-2 g-2">
                                            <?php foreach ($examClassMappings as $classMapping):
                                                $classLinked = $classMapping['override_session_id'] !== null;
                                                $classSession = $classLinked ? (int)$classMapping['override_session_id'] : 0;
                                                $classTerm = $classLinked ? (int)$classMapping['override_term'] : 1;
                                                $classComponent = $classLinked ? ($classMapping['override_component_type'] . ':' . $classMapping['override_component_number']) : '';
                                            ?>
                                                <div class="col">
                                                    <form method="POST" class="result-component-form border rounded p-2 h-100" data-component-select="class_result_component_<?php echo (int)$exam['id']; ?>_<?php echo (int)$classMapping['class_id']; ?>" data-current-component="<?php echo htmlspecialchars($classComponent, ENT_QUOTES); ?>">
                                                        <input type="hidden" name="action" value="link_class_result_component">
                                                        <input type="hidden" name="exam_id" value="<?php echo (int)$exam['id']; ?>">
                                                        <input type="hidden" name="class_id" value="<?php echo (int)$classMapping['class_id']; ?>">
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <strong><?php echo htmlspecialchars($classMapping['class_name']); ?></strong>
                                                            <span class="badge bg-<?php echo $classLinked ? 'success' : 'secondary'; ?>"><?php echo $classLinked ? 'Linked' : 'Not linked'; ?></span>
                                                        </div>
                                                        <div class="row g-2">
                                                            <div class="col-5">
                                                                <label class="form-label small mb-1" for="class_result_session_<?php echo (int)$exam['id']; ?>_<?php echo (int)$classMapping['class_id']; ?>">Session</label>
                                                                <select class="form-select form-select-sm result-session-select" id="class_result_session_<?php echo (int)$exam['id']; ?>_<?php echo (int)$classMapping['class_id']; ?>" name="result_session_id" required>
                                                                    <option value="">Select session</option>
                                                                    <?php foreach ($resultSessions as $resultSession): ?>
                                                                        <option value="<?php echo (int)$resultSession['id']; ?>" <?php echo $classSession === (int)$resultSession['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($resultSession['name']); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-3">
                                                                <label class="form-label small mb-1" for="class_result_term_<?php echo (int)$exam['id']; ?>_<?php echo (int)$classMapping['class_id']; ?>">Term</label>
                                                                <select class="form-select form-select-sm" id="class_result_term_<?php echo (int)$exam['id']; ?>_<?php echo (int)$classMapping['class_id']; ?>" name="result_term" required>
                                                                    <?php for ($termOption = 1; $termOption <= 3; $termOption++): ?><option value="<?php echo $termOption; ?>" <?php echo $classTerm === $termOption ? 'selected' : ''; ?>><?php echo htmlspecialchars(termLabel($termOption)); ?></option><?php endfor; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-4">
                                                                <label class="form-label small mb-1" for="class_result_component_<?php echo (int)$exam['id']; ?>_<?php echo (int)$classMapping['class_id']; ?>">Component</label>
                                                                <select class="form-select form-select-sm result-component-select" id="class_result_component_<?php echo (int)$exam['id']; ?>_<?php echo (int)$classMapping['class_id']; ?>" name="result_component" required disabled><option value="">Loading…</option></select>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex gap-2 mt-2">
                                                            <button type="submit" class="btn btn-sm btn-outline-primary">Save class link</button>
                                                            <?php if ($classLinked): ?><button type="submit" name="clear_class_result_component" value="1" formnovalidate class="btn btn-sm btn-outline-secondary">Clear class link</button><?php endif; ?>
                                                        </div>
                                                    </form>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Add Subject to Exam Form -->
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add Subject to Exam</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="add_subject">
                                            <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                            <div class="row">
                                                <div class="col-md-5">
                                                    <div class="mb-3">
                                                        <label for="class_id_<?php echo $exam['id']; ?>" class="form-label">Class</label>
                                                        <select class="form-select class-select" id="class_id_<?php echo $exam['id']; ?>" name="class_id" data-exam-id="<?php echo $exam['id']; ?>" required>
                                                            <option value="">Select Class</option>
                                                            <?php foreach ($classes as $class): ?>
                                                                <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-5">
                                                    <div class="mb-3">
                                                        <label for="subject_id_<?php echo $exam['id']; ?>" class="form-label">Subject</label>
                                                        <select class="form-select subject-select" id="subject_id_<?php echo $exam['id']; ?>" name="subject_id" required>
                                                            <option value="">Select Subject</option>
                                                            <!-- Subjects will be loaded dynamically -->
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="mb-3">
                                                        <label for="time_limit_<?php echo $exam['id']; ?>" class="form-label">Time (min)</label>
                                                        <input type="number" class="form-control" id="time_limit_<?php echo $exam['id']; ?>" name="time_limit" value="60" min="1" max="300">
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-plus me-1"></i> Add Subject
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <!-- Exam Subjects List -->
                                <?php
                                // Get subjects for this exam
                                $exam_subjects = [];
                                if ($mysqli) {
                                    $stmt = $mysqli->prepare("SELECT es.id, es.class_id, es.subject_id, es.time_limit, es.show_calculator, es.is_published, es.published_at, es.created_at,
                                                              c.name as class_name, s.name as subject_name
                                                              FROM exam_subjects es
                                                              JOIN classes c ON es.class_id = c.id
                                                              JOIN subjects s ON es.subject_id = s.id
                                                              WHERE es.exam_id = ?
                                                              ORDER BY c.name, s.name");
                                    $stmt->bind_param("i", $exam['id']);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    while ($row = $result->fetch_assoc()) {
                                        $exam_subjects[] = $row;
                                    }
                                    $stmt->close();
                                }
                                ?>
                                
                                <?php if (count($exam_subjects) > 0): ?>
                                    <div class="row row-cols-1 row-cols-md-2 g-3">
                                        <?php foreach ($exam_subjects as $exam_subject): ?>
                                            <div class="col">
                                                <div class="card h-100">
                                                    <div class="card-body">
                                                        <h6 class="card-title mb-1"><?php echo htmlspecialchars($exam_subject['class_name']); ?></h6>
                                                        <p class="card-text small mb-2"><?php echo htmlspecialchars($exam_subject['subject_name']); ?></p>
                                                        <p class="card-text small mb-2">
                                                            <span class="badge bg-info small"><?php echo $exam_subject['time_limit']; ?> minutes</span>
                                                        </p>
                                                        <div class="mb-2">
                                                            <?php if ($exam_subject['is_published']): ?>
                                                                <span class="badge bg-success small">Published</span>
                                                                <small class="text-muted d-block"><?php echo date('M j, Y', strtotime($exam_subject['published_at'])); ?></small>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning small">Draft</span>
                                                                <small class="text-muted d-block"><?php echo date('M j, Y', strtotime($exam_subject['created_at'])); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="btn-group btn-group-sm w-100" role="group">
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="action" value="publish_subject">
                                                                <input type="hidden" name="exam_subject_id" value="<?php echo $exam_subject['id']; ?>">
                                                                <input type="hidden" name="is_published" value="<?php echo $exam_subject['is_published'] ? '1' : '0'; ?>">
                                                                <button type="submit" class="btn btn-<?php echo $exam_subject['is_published'] ? 'warning' : 'success'; ?>">
                                                                    <i class="fas fa-<?php echo $exam_subject['is_published'] ? 'eye-slash' : 'eye'; ?> me-1"></i>
                                                                    <?php echo $exam_subject['is_published'] ? 'Unpublish' : 'Publish'; ?>
                                                                </button>
                                                            </form>
                                                            <!-- Edit Time Limit Button -->
                                                            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#editTimeLimitModal<?php echo $exam_subject['id']; ?>">
                                                                <i class="fas fa-clock me-1"></i> Edit Time
                                                            </button>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to remove this subject from the exam?')">
                                                                <input type="hidden" name="action" value="delete_subject">
                                                                <input type="hidden" name="exam_subject_id" value="<?php echo $exam_subject['id']; ?>">
                                                                <button type="submit" class="btn btn-danger">
                                                                    <i class="fas fa-trash me-1"></i> Remove
                                                                </button>
                                                            </form>
                                                            <form method="POST" class="d-inline ms-1">
                                                                <input type="hidden" name="action" value="toggle_subject_calculator">
                                                                <input type="hidden" name="exam_subject_id" value="<?php echo $exam_subject['id']; ?>">
                                                                <input type="hidden" name="show_calculator" value="<?php echo $exam_subject['show_calculator'] ? '0' : '1'; ?>">
                                                                <button type="submit" class="btn btn-<?php echo $exam_subject['show_calculator'] ? 'success' : 'outline-info'; ?> btn-sm" title="<?php echo $exam_subject['show_calculator'] ? 'Disable' : 'Enable'; ?> Calculator">
                                                                    <i class="fas fa-calculator"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info small">
                                        <i class="fas fa-info-circle me-2"></i> No subjects added to this exam yet. Add subjects to make this exam available to teachers.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                <p class="text-muted">No exams found. Create a new exam to get started.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Time Limit Modals -->
<?php foreach ($exams as $exam): ?>
    <?php
    // Get subjects for this exam
    $exam_subjects = [];
    if ($mysqli) {
        $stmt = $mysqli->prepare("SELECT es.id, es.class_id, es.subject_id, es.time_limit, es.is_published, es.published_at, es.created_at,
                                  c.name as class_name, s.name as subject_name
                                  FROM exam_subjects es
                                  JOIN classes c ON es.class_id = c.id
                                  JOIN subjects s ON es.subject_id = s.id
                                  WHERE es.exam_id = ?
                                  ORDER BY c.name, s.name");
        $stmt->bind_param("i", $exam['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $exam_subjects[] = $row;
        }
        $stmt->close();
    }
    ?>
    <?php foreach ($exam_subjects as $exam_subject): ?>
        <div class="modal fade" id="editTimeLimitModal<?php echo $exam_subject['id']; ?>" tabindex="-1" aria-labelledby="editTimeLimitModalLabel<?php echo $exam_subject['id']; ?>" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_time_limit">
                        <input type="hidden" name="exam_subject_id" value="<?php echo $exam_subject['id']; ?>">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editTimeLimitModalLabel<?php echo $exam_subject['id']; ?>">Edit Time Limit</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="time_limit_<?php echo $exam_subject['id']; ?>" class="form-label">
                                    Time Limit for <?php echo htmlspecialchars($exam_subject['class_name']); ?> - <?php echo htmlspecialchars($exam_subject['subject_name']); ?>
                                </label>
                                <input type="number" class="form-control" id="time_limit_<?php echo $exam_subject['id']; ?>" name="time_limit" 
                                       value="<?php echo $exam_subject['time_limit']; ?>" min="1" max="300" required>
                                <div class="form-text">Enter time limit in minutes (1-300)</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Time Limit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endforeach; ?>

<!-- Edit Exam Modals -->
<?php foreach ($exams as $exam): ?>
    <div class="modal fade" id="editExamModal<?php echo $exam['id']; ?>" tabindex="-1" aria-labelledby="editExamModalLabel<?php echo $exam['id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="edit_exam">
                    <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editExamModalLabel<?php echo $exam['id']; ?>">Edit Exam Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_title_<?php echo $exam['id']; ?>" class="form-label">Exam Title</label>
                            <input type="text" class="form-control" id="edit_title_<?php echo $exam['id']; ?>" name="title" value="<?php echo htmlspecialchars($exam['title']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description_<?php echo $exam['id']; ?>" class="form-label">Description</label>
                            <input type="text" class="form-control" id="edit_description_<?php echo $exam['id']; ?>" name="description" value="<?php echo htmlspecialchars($exam['description']); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="edit_session_<?php echo $exam['id']; ?>" class="form-label">Academic Session</label>
                            <input type="text" class="form-control" id="edit_session_<?php echo $exam['id']; ?>" name="academic_session" maxlength="50" value="<?php echo htmlspecialchars($exam['academic_session'] ?? ''); ?>" placeholder="e.g. 2026/2027">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // TELEPORT MODALS: Move all modals to the end of body to prevent backdrop/z-index issues
    // This fixes the "frozen screen" issue where the backdrop appears but the modal is trapped inside a card
    var modals = document.querySelectorAll('.modal');
    modals.forEach(function(modal) {
        document.body.appendChild(modal);
    });

    // Handle class selection to load subjects
    document.querySelectorAll('.class-select').forEach(function(select) {
        select.addEventListener('change', function() {
            var classId = this.value;
            var examId = this.getAttribute('data-exam-id');
            var subjectSelect = document.getElementById('subject_id_' + examId);
            
            // Clear existing options
            subjectSelect.innerHTML = '<option value="">Select Subject</option>';
            
            if (classId) {
                // Fetch subjects for this class
                fetch('exam_management.php?action=get_subjects&class_id=' + encodeURIComponent(classId))
                    .then(response => response.json())
                    .then(subjects => {
                        subjects.forEach(function(subject) {
                            var option = document.createElement('option');
                            option.value = subject.id;
                            option.textContent = subject.name;
                            subjectSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Error loading subjects:', error);
                    });
            }
            var form = this.closest('.result-component-form');
            if (form) loadResultComponents(form);
        });
    });

    function loadResultComponents(form) {
        var sessionSelect = form.querySelector('.result-session-select');
        var componentSelect = document.getElementById(form.getAttribute('data-component-select'));
        var sessionId = sessionSelect ? sessionSelect.value : '';
        var currentComponent = form.getAttribute('data-current-component') || componentSelect.value || '';
        componentSelect.innerHTML = '<option value="">Select result session first</option>';
        componentSelect.disabled = true;
        if (!sessionId) return;
        var classSelect = form.querySelector('[name="class_id"]');
        var classId = classSelect ? classSelect.value : '';
        var url = 'exam_management.php?action=get_result_components&session_id=' + encodeURIComponent(sessionId);
        if (classId) url += '&class_id=' + encodeURIComponent(classId);
        fetch(url)
            .then(function(response) { return response.json(); })
            .then(function(components) {
                componentSelect.innerHTML = '<option value="">Select component</option>';
                components.forEach(function(component) {
                    var option = document.createElement('option');
                    option.value = component.value;
                    option.textContent = component.label;
                    componentSelect.appendChild(option);
                });
                if (currentComponent) componentSelect.value = currentComponent;
                componentSelect.disabled = components.length === 0;
                if (components.length === 0) componentSelect.innerHTML = '<option value="">No components configured</option>';
                form.removeAttribute('data-current-component');
            })
            .catch(function() {
                componentSelect.innerHTML = '<option value="">Could not load components</option>';
            });
    }

    document.querySelectorAll('.result-component-form').forEach(function(form) {
        var sessionSelect = form.querySelector('.result-session-select');
        if (sessionSelect) sessionSelect.addEventListener('change', function() { loadResultComponents(form); });
        loadResultComponents(form);
    });
});
</script>

