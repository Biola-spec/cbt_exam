<?php
require_once '../config/database.php';
require_once '../config/session.php';

$message = '';
$message_type = 'info'; // Default message type
$batch_errors = [];
$batch_form_rows = [];
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

// Handle adding multiple subjects in one submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_multiple') {
    $subject_rows = is_array($_POST['subjects'] ?? null) ? $_POST['subjects'] : [];
    $batch_form_rows = $subject_rows;
    $batch_added = 0;
    $batch_errors = [];
    $seen_codes = [];

    try {
        $mysqli = getDBConnection();
        if (!$mysqli) throw new Exception('Database connection is unavailable.');

        foreach ($subject_rows as $row_index => $subject_row) {
            if (!is_array($subject_row)) continue;
            $row_number = (int)$row_index + 1;
            $name = trim($subject_row['name'] ?? '');
            $subject_code = trim($subject_row['subject_code'] ?? '');
            $description = trim($subject_row['description'] ?? '');
            $class_ids = array_values(array_unique(array_filter(array_map('intval', $subject_row['class_ids'] ?? []), function ($id) { return $id > 0; })));

            if ($name === '' && $subject_code === '' && $description === '' && !$class_ids) continue;
            if ($name === '' || $subject_code === '') {
                $batch_errors[] = 'Row ' . $row_number . ': subject name and code are required.';
                continue;
            }

            $normalized_code = strtolower($subject_code);
            if (isset($seen_codes[$normalized_code])) {
                $batch_errors[] = 'Row ' . $row_number . ': subject code "' . $subject_code . '" is repeated in this batch.';
                continue;
            }
            $seen_codes[$normalized_code] = true;

            try {
                $mysqli->begin_transaction();

                $check_code = $mysqli->prepare('SELECT id FROM subjects WHERE subject_code = ? LIMIT 1');
                $check_code->bind_param('s', $subject_code);
                $check_code->execute();
                $code_exists = $check_code->get_result()->num_rows > 0;
                $check_code->close();
                if ($code_exists) {
                    $mysqli->rollback();
                    $batch_errors[] = 'Row ' . $row_number . ': subject code "' . $subject_code . '" already exists.';
                    continue;
                }

                $conflicting_class = null;
                foreach ($class_ids as $class_id) {
                    $check_name = $mysqli->prepare('SELECT s.id FROM subjects s JOIN subject_class_link scl ON s.id = scl.subject_id WHERE s.name = ? AND scl.class_id = ? LIMIT 1');
                    $check_name->bind_param('si', $name, $class_id);
                    $check_name->execute();
                    $name_exists = $check_name->get_result()->num_rows > 0;
                    $check_name->close();
                    if ($name_exists) {
                        $conflicting_class = $class_id;
                        break;
                    }
                }
                if ($conflicting_class !== null) {
                    $mysqli->rollback();
                    $class_name = 'selected class';
                    foreach ($classes as $class) {
                        if ((int)$class['id'] === (int)$conflicting_class) { $class_name = $class['name']; break; }
                    }
                    $batch_errors[] = 'Row ' . $row_number . ': "' . $name . '" already exists for ' . $class_name . '.';
                    continue;
                }

                $insert_subject = $mysqli->prepare('INSERT INTO subjects (name, subject_code, description) VALUES (?, ?, ?)');
                if (!$insert_subject) throw new Exception($mysqli->error);
                $insert_subject->bind_param('sss', $name, $subject_code, $description);
                $insert_subject->execute();
                $new_subject_id = $mysqli->insert_id;
                $insert_subject->close();

                if ($class_ids) {
                    $insert_link = $mysqli->prepare('INSERT INTO subject_class_link (subject_id, class_id) VALUES (?, ?)');
                    if (!$insert_link) throw new Exception($mysqli->error);
                    foreach ($class_ids as $class_id) {
                        $insert_link->bind_param('ii', $new_subject_id, $class_id);
                        $insert_link->execute();
                    }
                    $insert_link->close();
                }

                $mysqli->commit();
                $batch_added++;
                unset($batch_form_rows[$row_index]);
            } catch (Exception $row_error) {
                try { $mysqli->rollback(); } catch (Exception $rollback_error) {}
                $batch_errors[] = 'Row ' . $row_number . ': ' . $row_error->getMessage();
            }
        }
    } catch (Exception $e) {
        $batch_errors[] = $e->getMessage();
    }

    if ($batch_added > 0) {
        $message = $batch_added . ' subject' . ($batch_added === 1 ? '' : 's') . ' added successfully.';
        $message_type = $batch_errors ? 'warning' : 'success';
        if ($batch_errors) $message .= ' ' . count($batch_errors) . ' row' . (count($batch_errors) === 1 ? ' needs' : 's need') . ' attention.';

        // Refresh the existing-subject cards so newly created subjects appear immediately.
        $subjects = [];
        $query = 'SELECT id, name, subject_code, description, created_at FROM subjects';
        $conditions = [];
        $params = [];
        $types = '';
        if ($search_term !== '') {
            $conditions[] = '(name LIKE ? OR description LIKE ?)';
            $params[] = '%' . $search_term . '%';
            $params[] = '%' . $search_term . '%';
            $types .= 'ss';
        }
        if ($class_filter > 0) {
            $conditions[] = 'id IN (SELECT subject_id FROM subject_class_link WHERE class_id = ?)';
            $params[] = $class_filter;
            $types .= 'i';
        }
        if ($conditions) $query .= ' WHERE ' . implode(' AND ', $conditions);
        $query .= ' ORDER BY name';
        $list_stmt = $mysqli->prepare($query);
        if ($params) $list_stmt->bind_param($types, ...$params);
        $list_stmt->execute();
        $list_result = $list_stmt->get_result();
        while ($subject = $list_result->fetch_assoc()) {
            $subject['classes'] = [];
            $class_stmt = $mysqli->prepare('SELECT DISTINCT c.id, c.name FROM subject_class_link scl JOIN classes c ON scl.class_id = c.id WHERE scl.subject_id = ? ORDER BY c.name');
            $class_stmt->bind_param('i', $subject['id']);
            $class_stmt->execute();
            $class_result = $class_stmt->get_result();
            while ($class_row = $class_result->fetch_assoc()) $subject['classes'][] = $class_row;
            $class_stmt->close();
            $subjects[] = $subject;
        }
        $list_stmt->close();
    } elseif (!$batch_errors) {
        $message = 'Enter at least one subject to add.';
        $message_type = 'warning';
    } else {
        $message = 'No subjects were added. Correct the row details below and submit again.';
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

// Handle bulk deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_multiple') {
    $delete_subject_ids = array_values(array_unique(array_filter(array_map('intval', $_POST['subject_ids'] ?? []), function ($id) { return $id > 0; })));

    if (!$delete_subject_ids) {
        $message = 'Select at least one subject to delete.';
        $message_type = 'warning';
    } else {
        try {
            $mysqli = getDBConnection();
            if (!$mysqli) throw new Exception('Database connection is unavailable.');

            $mysqli->begin_transaction();
            $delete_stmt = $mysqli->prepare('DELETE FROM subjects WHERE id = ?');
            if (!$delete_stmt) throw new Exception('Could not prepare the delete operation.');

            $deleted_count = 0;
            foreach ($delete_subject_ids as $delete_subject_id) {
                $delete_stmt->bind_param('i', $delete_subject_id);
                $delete_stmt->execute();
                $deleted_count += $delete_stmt->affected_rows;
            }
            $delete_stmt->close();
            $mysqli->commit();

            $deleted_lookup = array_fill_keys($delete_subject_ids, true);
            $subjects = array_values(array_filter($subjects, function ($subject) use ($deleted_lookup) {
                return !isset($deleted_lookup[(int)$subject['id']]);
            }));
            $message = $deleted_count . ' subject' . ($deleted_count === 1 ? '' : 's') . ' deleted successfully.';
            $message_type = 'success';
        } catch (Exception $e) {
            if (isset($mysqli) && $mysqli && $mysqli->ping()) $mysqli->rollback();
            $message = 'Could not delete the selected subjects. One or more may still be in use. ' . $e->getMessage();
            $message_type = 'danger';
        }
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

<?php $subject_form_panel_active = $edit_subject || (($_POST['subject_view'] ?? $_GET['subject_view'] ?? '') === 'add'); ?>
<style>
    .subject-tabs .nav-link { border-radius: .75rem .75rem 0 0; font-weight: 600; padding: .75rem 1.15rem; }
    .subject-tabs .nav-link.active { color: #174a7e; }
    .subject-card { border: 1px solid #e7edf4; border-radius: 14px; transition: transform .16s ease, box-shadow .16s ease; }
    .subject-card:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1.25rem rgba(24, 56, 88, .1) !important; }
    .subject-card .card-body { padding: .9rem 1rem; }
    .subject-card .min-w-0 { min-width: 0; }
    .subject-card .btn-group { flex: 0 0 auto; }
    .subject-card-name { font-size: .98rem; font-weight: 700; line-height: 1.25; }
    .subject-code-chip { font-size: .72rem; letter-spacing: .035em; }
    .subject-class-chip { font-size: .68rem; font-weight: 500; }
    .subject-description { display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; min-height: 1.25rem; }
</style>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="fas fa-book-open me-2"></i>Subject Management</h4>
    </div>

    <div class="small text-muted mb-3"><i class="fas fa-circle-info me-1"></i>Subjects can be linked to one or more classes and managed independently.</div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($batch_errors): ?>
        <div class="alert alert-warning py-2" role="status">
            <ul class="mb-0 small"><?php foreach ($batch_errors as $batch_error): ?><li><?php echo htmlspecialchars($batch_error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs subject-tabs mb-0" id="subjectManagementTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo !$subject_form_panel_active ? 'active' : ''; ?>" id="existing-subjects-tab" data-bs-toggle="tab" data-bs-target="#existing-subjects-panel" type="button" role="tab" aria-controls="existing-subjects-panel" aria-selected="<?php echo !$subject_form_panel_active ? 'true' : 'false'; ?>">
                <i class="fas fa-layer-group me-2"></i>Existing Subjects <span class="badge rounded-pill text-bg-light ms-1"><?php echo count($subjects); ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $subject_form_panel_active ? 'active' : ''; ?>" id="add-subject-tab" data-bs-toggle="tab" data-bs-target="#add-subject-panel" type="button" role="tab" aria-controls="add-subject-panel" aria-selected="<?php echo $subject_form_panel_active ? 'true' : 'false'; ?>">
                <i class="fas <?php echo $edit_subject ? 'fa-pen-to-square' : 'fa-plus'; ?> me-2"></i><?php echo $edit_subject ? 'Edit Subject' : 'Add New Subject'; ?>
            </button>
        </li>
    </ul>

    <div class="tab-content border border-top-0 bg-white rounded-bottom p-3 p-lg-4 shadow-sm">
        <section class="tab-pane fade <?php echo !$subject_form_panel_active ? 'show active' : ''; ?>" id="existing-subjects-panel" role="tabpanel" aria-labelledby="existing-subjects-tab" tabindex="0">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h5 class="mb-1">Your Subjects</h5>
                    <div class="small text-muted">Browse and manage subjects by class.</div>
                </div>
                <form method="GET" class="d-flex flex-wrap gap-2">
                    <input type="hidden" name="tab" value="subjects">
                    <input type="hidden" name="subject_view" value="existing">
                    <select name="class_filter" class="form-select form-select-sm" aria-label="Filter by class" onchange="this.form.submit()">
                        <option value="0">All classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>" <?php echo ($class_filter == $c['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="input-group input-group-sm" style="width:min(100%,280px)">
                        <input type="search" class="form-control" name="search" placeholder="Search subjects" value="<?php echo htmlspecialchars($search_term); ?>" aria-label="Search subjects">
                        <button class="btn btn-outline-primary" type="submit" aria-label="Search"><i class="fas fa-search"></i></button>
                    </div>
                    <?php if (!empty($search_term) || $class_filter > 0): ?>
                        <a href="dashboard.php?tab=subjects" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times me-1"></i>Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if ($subjects): ?>
                <form method="POST" id="bulkSubjectDeleteForm" class="mb-3">
                    <input type="hidden" name="action" value="delete_multiple">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 rounded-3 border p-2 px-3 mb-3" style="background:#f8fafc">
                        <div class="d-flex align-items-center gap-3">
                            <label class="form-check mb-0 d-flex align-items-center gap-2">
                                <input class="form-check-input m-0" type="checkbox" id="selectAllSubjects">
                                <span class="small fw-semibold">Select all shown</span>
                            </label>
                            <span class="small text-muted" id="selectedSubjectCount" aria-live="polite">0 selected</span>
                        </div>
                        <button type="submit" class="btn btn-sm btn-danger" id="deleteSelectedSubjects" disabled><i class="fas fa-trash me-1"></i>Delete Selected</button>
                    </div>
                    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                    <?php foreach ($subjects as $subject): ?>
                        <div class="col">
                            <article class="card subject-card h-100 shadow-sm">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex align-items-start gap-2">
                                        <span class="d-inline-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="width:38px;height:38px;background:#eaf3ff;color:#2467a5"><i class="fas fa-book"></i></span>
                                        <div class="min-w-0 flex-grow-1">
                                            <div class="subject-card-name text-truncate" title="<?php echo htmlspecialchars($subject['name']); ?>"><?php echo htmlspecialchars($subject['name']); ?></div>
                                            <span class="badge rounded-pill bg-light text-primary border subject-code-chip mt-1"><?php echo htmlspecialchars($subject['subject_code']); ?></span>
                                        </div>
                                        <div class="btn-group btn-group-sm flex-shrink-0">
                                            <label class="btn btn-outline-secondary d-inline-flex align-items-center" title="Select subject for bulk deletion">
                                                <input class="form-check-input m-0 subject-select-checkbox" type="checkbox" name="subject_ids[]" value="<?php echo (int)$subject['id']; ?>" aria-label="Select <?php echo htmlspecialchars($subject['name']); ?>">
                                            </label>
                                            <a href="dashboard.php?tab=subjects&action=edit&id=<?php echo (int)$subject['id']; ?>" class="btn btn-outline-primary" title="Edit subject" aria-label="Edit <?php echo htmlspecialchars($subject['name']); ?>"><i class="fas fa-pen"></i></a>
                                        </div>
                                    </div>
                                    <div class="subject-description small text-muted mt-2" title="<?php echo htmlspecialchars($subject['description'] ?? ''); ?>"><?php echo !empty($subject['description']) ? htmlspecialchars($subject['description']) : '&nbsp;'; ?></div>
                                    <div class="d-flex align-items-center flex-wrap gap-1 mt-auto pt-2">
                                        <?php if ($subject['classes']): ?>
                                            <?php foreach (array_slice($subject['classes'], 0, 2) as $class): ?>
                                                <span class="badge rounded-pill bg-light text-dark border subject-class-chip"><?php echo htmlspecialchars($class['name']); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($subject['classes']) > 2): ?><span class="badge rounded-pill text-bg-secondary subject-class-chip">+<?php echo count($subject['classes']) - 2; ?></span><?php endif; ?>
                                        <?php else: ?>
                                            <span class="small text-muted"><i class="fas fa-circle-minus me-1"></i>No class assigned</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center rounded-4 py-5 px-3" style="background:#f6f9fc">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width:58px;height:58px;background:#eaf3ff;color:#2467a5"><i class="fas fa-book-open fa-lg"></i></span>
                    <h6 class="mb-1">No subjects found</h6>
                    <p class="text-muted small mb-3"><?php echo !empty($search_term) ? 'Try another search or clear the filters.' : 'Add a subject to start building your curriculum.'; ?></p>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="tab" data-bs-target="#add-subject-panel"><i class="fas fa-plus me-1"></i>Add a subject</button>
                </div>
            <?php endif; ?>
        </section>

        <section class="tab-pane fade <?php echo $subject_form_panel_active ? 'show active' : ''; ?>" id="add-subject-panel" role="tabpanel" aria-labelledby="add-subject-tab" tabindex="0">
            <div class="row justify-content-center">
                <div class="col-xl-8 col-xxl-7">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:44px;height:44px;background:<?php echo $edit_subject ? '#eaf3ff' : '#eaf8f0'; ?>;color:<?php echo $edit_subject ? '#2467a5' : '#198754'; ?>"><i class="fas <?php echo $edit_subject ? 'fa-pen-to-square' : 'fa-plus'; ?>"></i></span>
                        <div><h5 class="mb-1"><?php echo $edit_subject ? 'Edit Subject' : 'Add New Subject'; ?></h5><div class="small text-muted">Enter the subject details below.</div></div>
                    </div>
                    <?php if ($edit_subject): ?>
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="subject_view" value="add">
                            <input type="hidden" name="subject_id" value="<?php echo (int)$edit_subject['id']; ?>">
                            <div class="col-md-6">
                                <label for="subject_name" class="form-label">Subject Name</label>
                                <input type="text" class="form-control" id="subject_name" name="name" value="<?php echo htmlspecialchars($edit_subject['name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="subject_code" class="form-label">Subject Code</label>
                                <input type="text" class="form-control" id="subject_code" name="subject_code" value="<?php echo htmlspecialchars($edit_subject['subject_code']); ?>" required>
                            </div>
                            <div class="col-12">
                                <label for="subject_description" class="form-label">Description <span class="text-muted fw-normal">(optional)</span></label>
                                <textarea class="form-control" id="subject_description" name="description" rows="3"><?php echo htmlspecialchars($edit_subject['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label mb-1">Associated Classes</label>
                                <div class="row g-2 p-3 border rounded-3 mx-0" style="max-height:220px;overflow:auto;background:#f9fbfd">
                                    <?php foreach ($classes as $class): ?>
                                        <div class="col-sm-6 col-lg-4"><label class="d-flex align-items-center gap-2 small p-2 border rounded-2 bg-white h-100"><input class="form-check-input m-0" type="checkbox" name="class_ids[]" value="<?php echo (int)$class['id']; ?>" <?php echo in_array($class['id'], array_column($edit_subject['classes'], 'id')) ? 'checked' : ''; ?>><span><?php echo htmlspecialchars($class['name']); ?></span></label></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                                <a href="dashboard.php?tab=subjects" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <form method="POST" id="batchSubjectForm">
                            <input type="hidden" name="action" value="add_multiple">
                            <input type="hidden" name="subject_view" value="add">
                            <p class="small text-muted mb-3">Enter several subjects below. You can link different classes to each subject.</p>
                            <div id="subjectBatchRows">
                                <?php foreach ($batch_form_rows as $row_index => $row): ?>
                                    <?php $row_classes = array_map('intval', $row['class_ids'] ?? []); ?>
                                    <div class="subject-batch-row border rounded-3 p-3 mb-3" data-subject-row data-index="<?php echo (int)$row_index; ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-3"><h6 class="mb-0 text-primary">Subject <span data-row-number><?php echo (int)$row_index + 1; ?></span></h6><button type="button" class="btn btn-sm btn-outline-danger remove-subject-row" aria-label="Remove subject row"><i class="fas fa-times"></i></button></div>
                                        <div class="row g-3">
                                            <div class="col-md-6"><label class="form-label">Subject Name</label><input class="form-control" type="text" name="subjects[<?php echo (int)$row_index; ?>][name]" value="<?php echo htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Mathematics" required></div>
                                            <div class="col-md-6"><label class="form-label">Subject Code</label><input class="form-control" type="text" name="subjects[<?php echo (int)$row_index; ?>][subject_code]" value="<?php echo htmlspecialchars($row['subject_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. MTH-JSS1" required></div>
                                            <div class="col-12"><label class="form-label">Description <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" type="text" name="subjects[<?php echo (int)$row_index; ?>][description]" value="<?php echo htmlspecialchars($row['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Short description"></div>
                                            <div class="col-12">
                                                <details class="border rounded-3 p-2">
                                                    <summary class="small fw-semibold text-secondary">Link classes <span class="text-muted fw-normal">(optional)</span></summary>
                                                    <?php if ($classes): ?><div class="row g-2 mt-1">
                                                        <?php foreach ($classes as $class): ?>
                                                            <div class="col-sm-6 col-lg-4"><label class="d-flex align-items-center gap-2 small p-2 border rounded-2 bg-white h-100"><input class="form-check-input m-0" type="checkbox" name="subjects[<?php echo (int)$row_index; ?>][class_ids][]" value="<?php echo (int)$class['id']; ?>" <?php echo in_array((int)$class['id'], $row_classes, true) ? 'checked' : ''; ?>><span><?php echo htmlspecialchars($class['name']); ?></span></label></div>
                                                        <?php endforeach; ?>
                                                    </div><?php else: ?><div class="small text-warning mt-2">No classes are available yet. Create a class first.</div><?php endif; ?>
                                                </details>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <template id="subjectBatchRowTemplate">
                                <div class="subject-batch-row border rounded-3 p-3 mb-3" data-subject-row data-index="__INDEX__">
                                    <div class="d-flex justify-content-between align-items-center mb-3"><h6 class="mb-0 text-primary">Subject <span data-row-number>__ROW__</span></h6><button type="button" class="btn btn-sm btn-outline-danger remove-subject-row" aria-label="Remove subject row"><i class="fas fa-times"></i></button></div>
                                    <div class="row g-3">
                                        <div class="col-md-6"><label class="form-label">Subject Name</label><input class="form-control" type="text" name="subjects[__INDEX__][name]" placeholder="e.g. Mathematics" required></div>
                                        <div class="col-md-6"><label class="form-label">Subject Code</label><input class="form-control" type="text" name="subjects[__INDEX__][subject_code]" placeholder="e.g. MTH-JSS1" required></div>
                                        <div class="col-12"><label class="form-label">Description <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" type="text" name="subjects[__INDEX__][description]" placeholder="Short description"></div>
                                        <div class="col-12"><details class="border rounded-3 p-2"><summary class="small fw-semibold text-secondary">Link classes <span class="text-muted fw-normal">(optional)</span></summary>
                                            <?php if ($classes): ?><div class="row g-2 mt-1"><?php foreach ($classes as $class): ?><div class="col-sm-6 col-lg-4"><label class="d-flex align-items-center gap-2 small p-2 border rounded-2 bg-white h-100"><input class="form-check-input m-0" type="checkbox" name="subjects[__INDEX__][class_ids][]" value="<?php echo (int)$class['id']; ?>"><span><?php echo htmlspecialchars($class['name']); ?></span></label></div><?php endforeach; ?></div><?php else: ?><div class="small text-warning mt-2">No classes are available yet. Create a class first.</div><?php endif; ?>
                                        </details></div>
                                    </div>
                                </div>
                            </template>
                            <div class="d-flex justify-content-start mb-3">
                                <button type="button" class="btn btn-outline-primary" id="addSubjectRow"><i class="fas fa-plus me-1"></i>Add another subject</button>
                            </div>
                            <div class="d-flex flex-wrap gap-2 pt-2"><button type="submit" class="btn btn-success"><i class="fas fa-layer-group me-1"></i>Add Subjects</button></div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</div>
<script>
(() => {
    const form = document.getElementById('bulkSubjectDeleteForm');
    if (!form) return;
    const checks = Array.from(form.querySelectorAll('.subject-select-checkbox'));
    const selectAll = document.getElementById('selectAllSubjects');
    const deleteButton = document.getElementById('deleteSelectedSubjects');
    const countLabel = document.getElementById('selectedSubjectCount');
    const updateSelection = () => {
        const selected = checks.filter((check) => check.checked).length;
        deleteButton.disabled = selected === 0;
        countLabel.textContent = selected + ' selected';
        selectAll.checked = selected === checks.length;
        selectAll.indeterminate = selected > 0 && selected < checks.length;
    };
    selectAll.addEventListener('change', () => {
        checks.forEach((check) => { check.checked = selectAll.checked; });
        updateSelection();
    });
    checks.forEach((check) => check.addEventListener('change', updateSelection));
    form.addEventListener('submit', (event) => {
        const selected = checks.filter((check) => check.checked).length;
        if (!selected || !window.confirm('Delete ' + selected + ' selected subject' + (selected === 1 ? '' : 's') + '? This cannot be undone.')) {
            event.preventDefault();
        }
    });
})();
</script>
<script>
(() => {
    const form = document.getElementById('batchSubjectForm');
    if (!form) return;
    const rows = document.getElementById('subjectBatchRows');
    const template = document.getElementById('subjectBatchRowTemplate');
    const addButton = document.getElementById('addSubjectRow');
    let nextIndex = Array.from(rows.querySelectorAll('[data-subject-row]'))
        .reduce((next, row) => Math.max(next, Number(row.dataset.index) + 1), 0);

    const renumberRows = () => {
        Array.from(rows.querySelectorAll('[data-subject-row]')).forEach((row, index) => {
            const label = row.querySelector('[data-row-number]');
            if (label) label.textContent = String(index + 1);
        });
    };
    const addRow = () => {
        const index = nextIndex++;
        const markup = template.innerHTML
            .split('__INDEX__').join(String(index))
            .split('__ROW__').join(String(rows.querySelectorAll('[data-subject-row]').length + 1));
        rows.insertAdjacentHTML('beforeend', markup);
        renumberRows();
        rows.lastElementChild.querySelector('input[name$="[name]"]').focus();
    };

    addButton.addEventListener('click', addRow);
    rows.addEventListener('click', (event) => {
        const removeButton = event.target.closest('.remove-subject-row');
        if (!removeButton) return;
        removeButton.closest('[data-subject-row]').remove();
        if (!rows.querySelector('[data-subject-row]')) addRow();
        renumberRows();
    });
    if (!rows.querySelector('[data-subject-row]')) addRow();
})();
</script>
