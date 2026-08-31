<?php
require_once '../config/database.php';
require_once '../config/session.php';

$message = '';
$message_type = 'info'; // Default message type
$subjects = [];
$classes = [];
$edit_subject = null; // For holding subject data when editing
$search_term = '';
$class_filter = isset($_GET['class_filter']) ? intval($_GET['class_filter']) : 0;

// Handle search
if (isset($_GET['search'])) {
    $search_term = trim($_GET['search']);
}

// Check for messages from redirects (do this early before any output)
if (isset($_GET['message'])) {
    $message = $_GET['message'];
    $message_type = isset($_GET['message_type']) ? $_GET['message_type'] : 'info';
}

// Load all classes
try {
    $mysqli = getDBConnection();
    if ($mysqli) {
        $stmt = $mysqli->prepare("SELECT id, name, description, created_at FROM classes ORDER BY name");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
    }
} catch (Exception $e) {
    $message = 'Error loading classes: ' . $e->getMessage();
    $message_type = 'danger';
}

// Load all subjects with their associated classes and search filter
try {
    $mysqli = getDBConnection();
    if ($mysqli) {
        // Get all subjects with search filter
        $query = "SELECT id, name, subject_code, description, created_at FROM subjects";
        $conditions = [];
        $params = [];
        $types = "";

        if (!empty($search_term)) {
            $conditions[] = "(name LIKE ? OR description LIKE ?)";
            $search_param = "%$search_term%";
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= "ss";
        }

        if ($class_filter > 0) {
            $conditions[] = "id IN (SELECT subject_id FROM subject_class_link WHERE class_id = ?)";
            $params[] = $class_filter;
            $types .= "i";
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        $query .= " ORDER BY name";

        $stmt = $mysqli->prepare($query);
        if (!empty($params)) {
             $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['classes'] = []; // Initialize classes array for each subject
            $subjects[] = $row;
        }
        
        // Get classes directly associated with each subject
        foreach ($subjects as &$subject) {
            $stmt = $mysqli->prepare("SELECT DISTINCT c.id, c.name FROM subject_class_link scl JOIN classes c ON scl.class_id = c.id WHERE scl.subject_id = ? ORDER BY c.name");
            $stmt->bind_param("i", $subject['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($class_row = $result->fetch_assoc()) {
                $subject['classes'][] = $class_row;
            }
        }
        unset($subject); // Important: unset the reference to avoid issues
    }
} catch (Exception $e) {
    $message = 'Error loading subjects: ' . $e->getMessage();
    $message_type = 'danger';
}

// Handle form submission for editing subject
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $subject_id = $_POST['subject_id'];
    $name = trim($_POST['name']);
    $subject_code = trim($_POST['subject_code']);
    $description = trim($_POST['description']);
    $class_ids = isset($_POST['class_ids']) ? $_POST['class_ids'] : [];
    
    if (!empty($name) && !empty($subject_code)) {
        try {
            $mysqli = getDBConnection();
            
            if ($mysqli) {
                // Start transaction
                $mysqli->begin_transaction();
                
                // Update subject
                $stmt = $mysqli->prepare("UPDATE subjects SET name = ?, subject_code = ?, description = ? WHERE id = ?");
                $stmt->bind_param("sssi", $name, $subject_code, $description, $subject_id);
                $stmt->execute();
                
                // Delete existing subject-class links
                $delete_stmt = $mysqli->prepare("DELETE FROM subject_class_link WHERE subject_id = ?");
                $delete_stmt->bind_param("i", $subject_id);
                $delete_stmt->execute();
                
                // Add new subject-class links
                if (!empty($class_ids)) {
                    foreach ($class_ids as $class_id) {
                        $link_stmt = $mysqli->prepare("INSERT INTO subject_class_link (subject_id, class_id) VALUES (?, ?)");
                        $link_stmt->bind_param("ii", $subject_id, $class_id);
                        $link_stmt->execute();
                        $link_stmt->close();
                    }
                }
                
                // Commit transaction
                $mysqli->commit();
                
                $message = 'Subject updated successfully.';
                $message_type = 'success';
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($mysqli && $mysqli->ping()) {
                $mysqli->rollback();
            }
            $message = 'Error updating subject: ' . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = 'Subject name and subject code are required';
        $message_type = 'danger';
    }
}

// Handle form submission for adding new subject
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $name = trim($_POST['name']);
    $subject_code = trim($_POST['subject_code']);
    $description = trim($_POST['description']);
    $class_ids = isset($_POST['class_ids']) ? $_POST['class_ids'] : [];
    
    if (!empty($name) && !empty($subject_code)) {
        try {
            $mysqli = getDBConnection();
            
            if ($mysqli) {
                // Check if subject with this code already exists
                $check_stmt = $mysqli->prepare("SELECT id FROM subjects WHERE subject_code = ?");
                $check_stmt->bind_param("s", $subject_code);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $message = 'A subject with the code "' . htmlspecialchars($subject_code) . '" already exists. Please choose a different code.';
                    $message_type = 'warning';
                } else {
                    // Check if subject with this name already exists for the selected classes
                    $subject_exists = false;
                    $conflicting_classes = [];
                    
                    if (!empty($class_ids)) {
                        foreach ($class_ids as $class_id) {
                            // Check if a subject with this name already exists for this specific class
                            $check_stmt = $mysqli->prepare("SELECT s.id, s.name FROM subjects s 
                                                           JOIN subject_class_link scl ON s.id = scl.subject_id 
                                                           WHERE s.name = ? AND scl.class_id = ?");
                            $check_stmt->bind_param("si", $name, $class_id);
                            $check_stmt->execute();
                            $check_result = $check_stmt->get_result();
                            
                            if ($check_result->num_rows > 0) {
                                $subject_exists = true;
                                $conflicting_subject = $check_result->fetch_assoc();
                                // Get class name for the error message
                                $class_stmt = $mysqli->prepare("SELECT name FROM classes WHERE id = ?");
                                $class_stmt->bind_param("i", $class_id);
                                $class_stmt->execute();
                                $class_result = $class_stmt->get_result();
                                $class_data = $class_result->fetch_assoc();
                                $conflicting_classes[] = $class_data['name'];
                                $class_stmt->close();
                            }
                            $check_stmt->close();
                        }
                    } else {
                        // If no classes selected, we allow creating the subject without class restrictions
                        // This allows creating a subject that can be associated with classes later
                        $message = 'Subject added successfully. No classes were selected. You can associate classes with this subject later when editing it.';
                    }
                    
                    if ($subject_exists) {
                        $message = 'A subject with the name "' . htmlspecialchars($name) . '" already exists for class(es): ' . implode(', ', $conflicting_classes) . '. Please choose a different name or select different classes.';
                        $message_type = 'warning';
                    } else {
                        // Start transaction
                        $mysqli->begin_transaction();
                        
                        // Insert subject
                        $stmt = $mysqli->prepare("INSERT INTO subjects (name, subject_code, description) VALUES (?, ?, ?)");
                        $stmt->bind_param("sss", $name, $subject_code, $description);
                        $stmt->execute();
                        $subject_id = $mysqli->insert_id;
                        
                        // Link selected classes to the new subject
                        if (!empty($class_ids)) {
                            foreach ($class_ids as $class_id) {
                                // Check if this combination already exists to avoid duplicates
                                $check_link_stmt = $mysqli->prepare("SELECT id FROM subject_class_link WHERE subject_id = ? AND class_id = ?");
                                $check_link_stmt->bind_param("ii", $subject_id, $class_id);
                                $check_link_stmt->execute();
                                $check_link_result = $check_link_stmt->get_result();
                                
                                if ($check_link_result->num_rows == 0) {
                                    $link_stmt = $mysqli->prepare("INSERT INTO subject_class_link (subject_id, class_id) VALUES (?, ?)");
                                    $link_stmt->bind_param("ii", $subject_id, $class_id);
                                    $link_stmt->execute();
                                    $link_stmt->close();
                                }
                                $check_link_stmt->close();
                            }
                            $message = 'Subject added successfully with selected classes.';
                        } else {
                            $message = 'Subject added successfully. No classes were selected.';
                        }
                        
                        // Commit transaction
                        $mysqli->commit();
                        
                        $message_type = 'success';
                        
                        // Reload subjects after adding to ensure it shows up
                        $query = "SELECT id, name, subject_code, description, created_at FROM subjects";
                        $conditions = [];
                        $params = [];
                        $types = "";
                        
                        if (!empty($search_term)) {
                            $conditions[] = "(name LIKE ? OR description LIKE ?)";
                            $search_param = "%$search_term%";
                            $params[] = $search_param;
                            $params[] = $search_param;
                            $types .= "ss";
                        }
                        
                        if ($class_filter > 0) {
                            $conditions[] = "id IN (SELECT subject_id FROM subject_class_link WHERE class_id = ?)";
                            $params[] = $class_filter;
                            $types .= "i";
                        }
                        
                        if (!empty($conditions)) {
                            $query .= " WHERE " . implode(" AND ", $conditions);
                        }
                        $query .= " ORDER BY name";
                        
                        $stmt = $mysqli->prepare($query);
                        if (!empty($params)) {
                             $stmt->bind_param($types, ...$params);
                        }
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $subjects = []; // Reset subjects array
                        while ($row = $result->fetch_assoc()) {
                            $row['classes'] = []; // Initialize classes array for each subject
                            $subjects[] = $row;
                        }
                        
                        // Get classes directly associated with each subject
                        foreach ($subjects as &$subject) {
                            $stmt = $mysqli->prepare("SELECT DISTINCT c.id, c.name FROM subject_class_link scl JOIN classes c ON scl.class_id = c.id WHERE scl.subject_id = ? ORDER BY c.name");
                            $stmt->bind_param("i", $subject['id']);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            while ($class_row = $result->fetch_assoc()) {
                                $subject['classes'][] = $class_row;
                            }
                        }
                        unset($subject); // Important: unset the reference to avoid issues
                    }
                }
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($mysqli && $mysqli->ping()) {
                $mysqli->rollback();
            }
            $message = 'Error adding subject: ' . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = 'Subject name and subject code are required';
        $message_type = 'danger';
    }
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $subject_id = $_POST['subject_id'];
    
    try {
        $mysqli = getDBConnection();
        
        if ($mysqli) {
            // Start transaction
            $mysqli->begin_transaction();
            
            // Delete subject
            $stmt = $mysqli->prepare("DELETE FROM subjects WHERE id = ?");
            $stmt->bind_param("i", $subject_id);
            $stmt->execute();
            
            // Commit transaction
            $mysqli->commit();
            
            $message = 'Subject deleted successfully';
            $message_type = 'success';
            
            // Reload subjects after deleting to ensure the list is updated
            $query = "SELECT id, name, subject_code, description, created_at FROM subjects";
            $conditions = [];
            $params = [];
            $types = "";
            
            if (!empty($search_term)) {
                $conditions[] = "(name LIKE ? OR description LIKE ?)";
                $search_param = "%$search_term%";
                $params[] = $search_param;
                $params[] = $search_param;
                $types .= "ss";
            }
            
            if ($class_filter > 0) {
                $conditions[] = "id IN (SELECT subject_id FROM subject_class_link WHERE class_id = ?)";
                $params[] = $class_filter;
                $types .= "i";
            }
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
            $query .= " ORDER BY name";
            
            $stmt = $mysqli->prepare($query);
            if (!empty($params)) {
                 $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $subjects = []; // Reset subjects array
            while ($row = $result->fetch_assoc()) {
                $row['classes'] = []; // Initialize classes array for each subject
                $subjects[] = $row;
            }
            
            // Get classes directly associated with each subject
            foreach ($subjects as &$subject) {
                $stmt = $mysqli->prepare("SELECT DISTINCT c.id, c.name FROM subject_class_link scl JOIN classes c ON scl.class_id = c.id WHERE scl.subject_id = ? ORDER BY c.name");
                $stmt->bind_param("i", $subject['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($class_row = $result->fetch_assoc()) {
                    $subject['classes'][] = $class_row;
                }
            }
            unset($subject); // Important: unset the reference to avoid issues
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($mysqli && $mysqli->ping()) {
            $mysqli->rollback();
        }
        $message = 'Error deleting subject. It might be referenced by other records.';
        $message_type = 'danger';
    }
}

// Handle edit request (to populate the edit form)
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $subject_id = $_GET['id'];
    
    // Find the subject to edit
    foreach ($subjects as $subject) {
        if ($subject['id'] == $subject_id) {
            $edit_subject = $subject;
            break;
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="fas fa-book-open me-2"></i>Subject Management</h4>
    </div>

    <!-- Information Panel -->
    <div class="card mb-4 bg-light">
        <div class="card-body">
            <h6><i class="fas fa-info-circle me-2"></i>Subject Management Guide</h6>
            <p class="mb-2">You can create different instances of the same subject for different classes if they need different content:</p>
            <ul class="mb-0">
                <li>Create "Mathematics - JSS1" and associate it with JSS1 class</li>
                <li>Create "Mathematics - JSS2" and associate it with JSS2 class</li>
                <li>Each subject instance can have different questions and content</li>
            </ul>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Search Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="d-flex flex-wrap flex-md-nowrap gap-2">
                <input type="hidden" name="tab" value="subjects">
                <div class="input-group" style="max-width: 250px;">
                    <select name="class_filter" class="form-select" onchange="this.form.submit()">
                        <option value="0">All Classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($class_filter == $c['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-group flex-grow-1">
                    <input type="text" class="form-control" name="search" placeholder="Search subjects..." value="<?php echo htmlspecialchars($search_term); ?>">
                    <button class="btn btn-outline-secondary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if (!empty($search_term) || $class_filter > 0): ?>
                        <a href="dashboard.php?tab=subjects" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <?php if ($edit_subject): ?>
                <!-- Edit Subject Form -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Subject</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="subject_id" value="<?php echo $edit_subject['id']; ?>">
                            <div class="mb-3">
                                <label for="name" class="form-label">Subject Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($edit_subject['name']); ?>" required>
                                <div class="form-text">Enter a unique name for this subject. You can create different instances of the same subject for different classes (e.g., "Mathematics - JSS1", "Mathematics - JSS2").</div>
                            </div>
                            <div class="mb-3">
                                <label for="subject_code" class="form-label">Subject Code</label>
                                <input type="text" class="form-control" id="subject_code" name="subject_code" value="<?php echo htmlspecialchars($edit_subject['subject_code']); ?>" required>
                                <div class="form-text">Enter a unique code for this subject. Students will use this code to access exams for this subject.</div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="2"><?php echo htmlspecialchars($edit_subject['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Associated Classes</label>
                                <div class="row">
                                    <?php foreach ($classes as $class): ?>
                                        <div class="col-md-6 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="class_ids[]" value="<?php echo $class['id']; ?>" id="edit_class_<?php echo $class['id']; ?>" <?php echo in_array($class['id'], array_column($edit_subject['classes'], 'id')) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="edit_class_<?php echo $class['id']; ?>">
                                                    <?php echo htmlspecialchars($class['name']); ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">Select classes to associate with this subject. If you want different content for the same subject in different classes, create separate subject instances (e.g., "Mathematics - JSS1" and "Mathematics - JSS2") and associate each with its respective class.</div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Update Subject
                                </button>
                                <a href="dashboard.php?tab=subjects" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Add Subject Form -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Subject</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            <div class="mb-3">
                                <label for="name" class="form-label">Subject Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                                <div class="form-text">Enter a name for this subject. You can create subjects with the same name for different classes.</div>
                            </div>
                            <div class="mb-3">
                                <label for="subject_code" class="form-label">Subject Code</label>
                                <input type="text" class="form-control" id="subject_code" name="subject_code" required>
                                <div class="form-text">Enter a unique code for this subject. Students will use this code to access exams for this subject.</div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus me-1"></i> Add Subject
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-6">
            <!-- Subjects List -->
            <div class="card">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Existing Subjects (<?php echo count($subjects); ?>)</h5>
                    <span class="badge bg-white text-dark"><?php echo count($subjects); ?> Total</span>
                </div>
                <div class="card-body p-0">
                    <?php if (count($subjects) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Subject Name</th>
                                        <th>Code</th>
                                        <th>Classes</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subjects as $subject): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($subject['name']); ?></strong>
                                                <?php if (!empty($subject['description'])): ?>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($subject['description']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><code><?php echo htmlspecialchars($subject['subject_code']); ?></code></td>
                                            <td>
                                                <?php if (!empty($subject['classes'])): ?>
                                                    <?php foreach ($subject['classes'] as $class): ?>
                                                        <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($class['name']); ?></span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="text-muted small italic">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="dashboard.php?tab=subjects&action=edit&id=<?php echo $subject['id']; ?>" class="btn btn-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this subject?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                        <button type="submit" class="btn btn-danger" title="Delete">
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
                            <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                            <p class="text-muted small">
                                <?php echo !empty($search_term) ? 'No subjects match your search' : 'No subjects found. Add a new subject to get started.'; ?>
                            </p>
                            <?php if (!empty($search_term)): ?>
                                <p class="small text-muted">Search term: '<?php echo htmlspecialchars($search_term); ?>'</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>