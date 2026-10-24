<?php
require_once '../config/database.php';
require_once '../config/session.php';

$message = '';
$teachers = [];
$classes = [];
$subjects = [];
$allocations = [];
$editing_allocation = null;

try {
    $mysqli = getDBConnection();
    
    // Handle AJAX request for subjects based on class
    if (isset($_GET['action']) && $_GET['action'] == 'get_subjects' && isset($_GET['class_id'])) {
        header('Content-Type: application/json');
        $class_id = (int)$_GET['class_id'];
        $subjects = [];
        
        if ($class_id > 0) {
            $stmt = $mysqli->prepare("SELECT s.id, s.name, s.description, s.created_at 
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
        
        echo json_encode($subjects);
        exit();
    }
    
    // Check if we're editing an allocation
    if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
        $edit_id = (int)$_GET['id'];
        $stmt = $mysqli->prepare("SELECT id, teacher_id, class_id, subject_id FROM teacher_allocations WHERE id = ?");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $editing_allocation = $result->fetch_assoc();
        $stmt->close();
    }
    
    // Handle form submission for adding allocation
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
        $teacher_id = (int)$_POST['teacher_id'];
        $class_id = (int)$_POST['class_id'];
        $subject_id = (int)$_POST['subject_id'];
        
        if ($teacher_id && $class_id && $subject_id) {
            // Check if allocation already exists
            $stmt = $mysqli->prepare("SELECT id FROM teacher_allocations WHERE teacher_id = ? AND class_id = ? AND subject_id = ?");
            $stmt->bind_param("iii", $teacher_id, $class_id, $subject_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $message = 'This allocation already exists.';
            } else {
                // Get current user ID for allocated_by
                $current_user_id = getUserId();
                
                // Insert new allocation
                $stmt = $mysqli->prepare("INSERT INTO teacher_allocations (teacher_id, class_id, subject_id, allocated_by) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiii", $teacher_id, $class_id, $subject_id, $current_user_id);
                
                if ($stmt->execute()) {
                    $message = 'Teacher allocated successfully.';
                } else {
                    $message = 'Error allocating teacher: ' . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            $message = 'Please select all fields.';
        }
    }
    
    // Handle form submission for editing allocation
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
        $allocation_id = (int)$_POST['allocation_id'];
        $teacher_id = (int)$_POST['teacher_id'];
        $class_id = (int)$_POST['class_id'];
        $subject_id = (int)$_POST['subject_id'];
        
        if ($allocation_id && $teacher_id && $class_id && $subject_id) {
            // Check if another allocation with the same combination already exists
            $stmt = $mysqli->prepare("SELECT id FROM teacher_allocations WHERE teacher_id = ? AND class_id = ? AND subject_id = ? AND id != ?");
            $stmt->bind_param("iiii", $teacher_id, $class_id, $subject_id, $allocation_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $message = 'This allocation already exists.';
            } else {
                // Update allocation
                $stmt = $mysqli->prepare("UPDATE teacher_allocations SET teacher_id = ?, class_id = ?, subject_id = ? WHERE id = ?");
                $stmt->bind_param("iiii", $teacher_id, $class_id, $subject_id, $allocation_id);
                
                if ($stmt->execute()) {
                    $message = 'Teacher allocation updated successfully.';
                    $editing_allocation = null; // Clear editing mode
                } else {
                    $message = 'Error updating allocation: ' . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            $message = 'Please select all fields.';
        }
    }
    
    // Handle form submission for deleting allocation
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
        $allocation_id = (int)$_POST['allocation_id'];
        
        if ($allocation_id) {
            $stmt = $mysqli->prepare("DELETE FROM teacher_allocations WHERE id = ?");
            $stmt->bind_param("i", $allocation_id);
            
            if ($stmt->execute()) {
                $message = 'Allocation removed successfully.';
            } else {
                $message = 'Error removing allocation: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
    
    // Handle form submission for deleting teacher
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_teacher') {
        $teacher_id = (int)$_POST['teacher_id'];
        
        if ($teacher_id) {
            // Start transaction
            $mysqli->begin_transaction();
            
            try {
                // Find the email for this teacher ID first, before deleting the user
                $teacher_email = null;
                $email_stmt = $mysqli->prepare("SELECT email FROM users WHERE id = ?");
                if ($email_stmt) {
                    $email_stmt->bind_param("i", $teacher_id);
                    $email_stmt->execute();
                    $result = $email_stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $teacher_email = $row['email'];
                    }
                    $email_stmt->close();
                }

                // Delete all allocations for this teacher
                $stmt = $mysqli->prepare("DELETE FROM teacher_allocations WHERE teacher_id = ?");
                $stmt->bind_param("i", $teacher_id);
                $stmt->execute();
                $stmt->close();
                
                // Clear user references in dependent tables before deleting user record to avoid foreign key failures
                $tables_to_nullify = [
                    'comprehension_passages' => 'created_by',
                    'questions'              => 'created_by',
                    'exams'                  => 'created_by',
                    'teacher_allocations'   => 'allocated_by',
                    'exam_republish_log'     => 'republished_by',
                    'student_answers'        => 'marked_by',
                    'school_config'          => 'updated_by',
                    'calendar_events'        => 'created_by',
                    'passkeys'               => 'created_by'
                ];

                foreach ($tables_to_nullify as $table => $col) {
                    $tbl_check = $mysqli->query("SHOW TABLES LIKE '$table'");
                    if ($tbl_check && $tbl_check->num_rows > 0) {
                        $col_check = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
                        if ($col_check && $col_check->num_rows > 0) {
                            if ($null_stmt = $mysqli->prepare("UPDATE `$table` SET `$col` = NULL WHERE `$col` = ?")) {
                                $null_stmt->bind_param("i", $teacher_id);
                                $null_stmt->execute();
                                $null_stmt->close();
                            }
                        }
                    }
                }

                // Delete the user from users table (allow admin or teacher)
                $stmt = $mysqli->prepare("DELETE FROM users WHERE id = ? AND role IN ('teacher', 'admin')");
                $stmt->bind_param("i", $teacher_id);
                
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        // Also remove from users.json file
                        $users = [];
                        if ($teacher_email && file_exists('../users.json')) {
                            $users = json_decode(file_get_contents('../users.json'), true);
                            
                            // Remove the teacher from users.json
                            if (isset($users[$teacher_email])) {
                                unset($users[$teacher_email]);
                                file_put_contents('../users.json', json_encode($users, JSON_PRETTY_PRINT));
                            }
                        }
                        
                        $message = 'Teacher deleted successfully.';
                    } else {
                        $message = 'Teacher not found or could not be deleted.';
                    }
                } else {
                    throw new Exception('Error deleting teacher: ' . $stmt->error);
                }
                $stmt->close();
                
                // Commit transaction
                $mysqli->commit();
            } catch (Exception $e) {
                // Rollback transaction
                $mysqli->rollback();
                $message = 'Error deleting teacher: ' . $e->getMessage();
            }
        }
    }
    
    // Load teachers/admins with filtering
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $letter = isset($_GET['letter']) ? trim($_GET['letter']) : '';
    
    $teachers_query = "SELECT id, email, full_name, role FROM users WHERE role IN ('teacher', 'admin')";
    $teachers_params = [];
    $teachers_types = "";
    
    if (!empty($search)) {
        $teachers_query .= " AND (full_name LIKE ? OR email LIKE ?)";
        $search_val = "%$search%";
        $teachers_params[] = $search_val;
        $teachers_params[] = $search_val;
        $teachers_types .= "ss";
    }
    
    if (!empty($letter)) {
        $teachers_query .= " AND full_name LIKE ?";
        $letter_val = "$letter%";
        $teachers_params[] = $letter_val;
        $teachers_types .= "s";
    }
    
    $teachers_query .= " ORDER BY full_name";
    
    $stmt = $mysqli->prepare($teachers_query);
    if ($stmt) {
        if (!empty($teachers_types)) {
            $stmt->bind_param($teachers_types, ...$teachers_params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $teachers[] = $row;
        }
        $stmt->close();
    } else {
        throw new Exception("Failed to prepare statement for loading teachers: " . $mysqli->error);
    }
    
    // Load classes
    $stmt = $mysqli->prepare("SELECT id, name, description, created_at FROM classes ORDER BY name");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
        $stmt->close();
    } else {
        throw new Exception("Failed to prepare statement for loading classes: " . $mysqli->error);
    }
    
    // Load all subjects (initial load)
    $stmt = $mysqli->prepare("SELECT id, name, description, created_at FROM subjects ORDER BY name");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        $stmt->close();
    } else {
        throw new Exception("Failed to prepare statement for loading subjects: " . $mysqli->error);
    }
    
    // Load allocations with related data - use DISTINCT to prevent duplicates
    $stmt = $mysqli->prepare("SELECT DISTINCT ta.id, ta.teacher_id, ta.class_id, ta.subject_id, ta.allocated_at, ta.allocated_by,
                              u.full_name as teacher_name, c.name as class_name, s.name as subject_name 
                          FROM teacher_allocations ta
                          JOIN users u ON ta.teacher_id = u.id
                          JOIN classes c ON ta.class_id = c.id
                          JOIN subjects s ON ta.subject_id = s.id
                          ORDER BY u.full_name, c.name, s.name");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $allocations[] = $row;
        }
        $stmt->close();
    } else {
        throw new Exception("Failed to prepare statement for loading allocations: " . $mysqli->error);
    }
} catch (Exception $e) {
    $message = 'Error loading data: ' . $e->getMessage();
}
?>



                <?php if ($message): ?>
                    <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <div class="no-print d-flex justify-content-end mb-3">
                    <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-1"></i> Print / Save Teacher Information</button>
                </div>
                <div class="row">
                    <!-- Allocation Form -->
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5><?php echo $editing_allocation ? 'Edit Allocation' : 'New Allocation'; ?></h5>
                                <form method="POST">
                                    <input type="hidden" name="action" value="<?php echo $editing_allocation ? 'edit' : 'add'; ?>">
                                    <?php if ($editing_allocation): ?>
                                        <input type="hidden" name="allocation_id" value="<?php echo $editing_allocation['id']; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Teacher</label>
                                        <select class="form-select" name="teacher_id" required>
                                            <option value="">Select Teacher</option>
                                            <?php foreach ($teachers as $teacher): ?>
                                                <option value="<?php echo $teacher['id']; ?>" <?php echo ($editing_allocation && $editing_allocation['teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($teacher['full_name']); ?> (<?php echo ucfirst($teacher['role']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Class</label>
                                        <select class="form-select" id="class_id" name="class_id" required>
                                            <option value="">Select Class</option>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?php echo $class['id']; ?>" <?php echo ($editing_allocation && $editing_allocation['class_id'] == $class['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($class['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Subject</label>
                                        <select class="form-select" id="subject_id" name="subject_id" required>
                                            <option value="">Select Subject</option>
                                            <?php foreach ($subjects as $subject): ?>
                                                <option value="<?php echo $subject['id']; ?>" <?php echo ($editing_allocation && $editing_allocation['subject_id'] == $subject['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($subject['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <?php echo $editing_allocation ? 'Update Allocation' : 'Add Allocation'; ?>
                                        </button>
                                        <?php if ($editing_allocation): ?>
                                            <a href="teacher_allocation.php" class="btn btn-secondary">Cancel</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Current Allocations Table -->
                    <div class="col-md-8 admin-print-area" id="teacher-information-print">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="fas fa-tasks me-2"></i>Current Subject Allocations</h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Teacher</th>
                                                <th>Class</th>
                                                <th>Subject</th>
                                                <th class="text-center no-print">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($allocations) > 0): ?>
                                                <?php foreach ($allocations as $allocation): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($allocation['teacher_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($allocation['class_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($allocation['subject_name']); ?></td>
                                                        <td class="text-center no-print">
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="dashboard.php?tab=teachers&action=edit&id=<?php echo $allocation['id']; ?>" class="btn btn-info text-white" title="Edit Allocation">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this allocation?');">
                                                                    <input type="hidden" name="action" value="delete">
                                                                    <input type="hidden" name="allocation_id" value="<?php echo $allocation['id']; ?>">
                                                                    <button type="submit" class="btn btn-danger" title="Delete Allocation">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-3">No allocations found.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Staff Management Table -->
                        <div class="card">
                            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="fas fa-users-cog me-2"></i>Staff Management (Teachers & Admins)</h6>
                                <a href="add_teacher.php" class="btn btn-sm btn-outline-light no-print">
                                    <i class="fas fa-user-plus me-1"></i>Add New Staff
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Full Name</th>
                                                <th>Email/Username</th>
                                                <th>Role</th>
                                                <th class="text-center no-print">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($teachers) > 0): ?>
                                                <?php foreach ($teachers as $staff): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($staff['full_name']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $staff['role'] === 'admin' ? 'bg-danger' : 'bg-success'; ?>">
                                                                <?php echo ucfirst($staff['role']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center no-print">
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="edit_teacher.php?id=<?php echo $staff['id']; ?>" class="btn btn-primary" title="Edit Staff Member">
                                                                    <i class="fas fa-user-edit"></i> Edit
                                                                </a>
                                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this staff member? All their allocations will be removed.');">
                                                                    <input type="hidden" name="action" value="delete_teacher">
                                                                    <input type="hidden" name="teacher_id" value="<?php echo $staff['id']; ?>">
                                                                    <button type="submit" class="btn btn-outline-danger" title="Delete Staff Member">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-3">No staff members found.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const classSelect = document.getElementById('class_id');
    const subjectSelect = document.getElementById('subject_id');
    
    // Store the editing subject ID if present
    let editingSubjectId = <?php echo $editing_allocation ? (int)$editing_allocation['subject_id'] : 'null'; ?>;

    classSelect.addEventListener('change', function() {
        const classId = this.value;
        
        if (classId) {
            subjectSelect.innerHTML = '<option value="">Loading subjects...</option>';
            subjectSelect.disabled = true;
            
            fetch('teacher_allocation.php?action=get_subjects&class_id=' + encodeURIComponent(classId))
                .then(response => response.json())
                .then(subjects => {
                    subjectSelect.innerHTML = '<option value="">Select Subject</option>';
                    subjects.forEach(subject => {
                        const option = document.createElement('option');
                        option.value = subject.id;
                        option.textContent = subject.name;
                        if (editingSubjectId !== null && subject.id == editingSubjectId) {
                            option.selected = true;
                        }
                        subjectSelect.appendChild(option);
                    });
                    subjectSelect.disabled = false;
                    
                    // Clear the editing subject ID after first load to allow normal changes
                    editingSubjectId = null;
                })
                .catch(error => {
                    console.error('Error fetching subjects:', error);
                    subjectSelect.innerHTML = '<option value="">Error loading subjects</option>';
                    subjectSelect.disabled = false;
                });
        } else {
            subjectSelect.innerHTML = '<option value="">Select Class First</option>';
            subjectSelect.disabled = true;
        }
    });

    <?php if ($editing_allocation): ?>
    // Trigger the change event to fetch and populate class subjects on load
    const event = new Event('change');
    classSelect.dispatchEvent(event);
    <?php endif; ?>
});
</script>