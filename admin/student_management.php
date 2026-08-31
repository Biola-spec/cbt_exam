<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();
if (getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$mysqli = getDBConnection();
if (!$mysqli) {
    die('<div class="alert alert-danger">Database connection failed.</div>');
}

// Utility: check if users.class_id exists
function usersHasClassId(mysqli $mysqli): bool {
    $exists = false;
    if ($res = $mysqli->query("SHOW COLUMNS FROM users LIKE 'class_id'")) {
        $exists = $res->num_rows > 0;
        $res->close();
    }
    return $exists;
}

// CSRF helpers
function ensureCsrfToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
function verifyCsrfToken($token): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}



ensureCsrfToken();

$message = '';
$has_class_id = usersHasClassId($mysqli);

// Load classes for dropdown
$classes = [];
try {
    $stmt = $mysqli->prepare("SELECT id, name FROM classes ORDER BY name");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $classes[] = $row;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    // Handle exception silently
}

// Handle add student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_student') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid session token. Please refresh and try again.';
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $class_id = intval($_POST['class_id'] ?? 0);

        // Basic validations
        if ($full_name === '' || $email === '' || $class_id <= 0) {
            $message = 'Please fill in full name, Gmail and select a class.';
        } elseif (!preg_match('/@gmail\.com$/i', $email)) {
            $message = 'Email must be a valid Gmail address ending with @gmail.com';
        } elseif (!$has_class_id) {
            $message = 'Database missing users.class_id. See migration instructions below.';
        } else {
            // Check duplicate email
            $stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ?');
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    $message = 'A user with this email already exists.';
                } else {
                    // Generate and hash password
                    $password = bin2hex(random_bytes(8));
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Handle passport upload
                    $passport_path = null;
                    if (isset($_FILES['passport']) && $_FILES['passport']['error'] == UPLOAD_ERR_OK) {
                        $upload_dir = '../uploads/passports/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                        $file_ext = strtolower(pathinfo($_FILES['passport']['name'], PATHINFO_EXTENSION));
                        $file_name = uniqid() . '.' . $file_ext;
                        $target_file = $upload_dir . $file_name;
                        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif']) && move_uploaded_file($_FILES['passport']['tmp_name'], $target_file)) {
                            // Adjust path for database (so it works relatively anywhere)
                            $passport_path = 'uploads/passports/' . $file_name;
                        }
                    }

                    // Insert student
                    $ins = $mysqli->prepare("INSERT INTO users (email, password, full_name, role, class_id, passport) VALUES (?, ?, ?, 'student', ?, ?)");
                    if ($ins) {
                        $ins->bind_param('sssis', $email, $hash, $full_name, $class_id, $passport_path);
                        if ($ins->execute()) {
                            $message = 'Student added successfully. Passkey generated below. Copy it now; it will not be shown again on refresh.';
                        } else {
                            $message = 'Error adding student: ' . $mysqli->error;
                        }
                        $ins->close();
                    } else {
                        $message = 'Error preparing insert: ' . $mysqli->error;
                    }
                }
                $stmt->close();
            } else {
                $message = 'Error preparing email check: ' . $mysqli->error;
            }
        }
    }
}

// Handle update student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_student') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid session token. Please refresh and try again.';
    } else {
        $sid = intval($_POST['student_id'] ?? 0);
        $full_name = trim($_POST['edit_full_name'] ?? '');
        $email = trim($_POST['edit_email'] ?? '');
        $class_id = intval($_POST['edit_class_id'] ?? 0);
        $reset_pass = isset($_POST['reset_password']) ? true : false;

        if ($sid <= 0) {
            $message = 'Invalid student selected.';
        } elseif ($full_name === '' || $email === '' || ($has_class_id && $class_id <= 0)) {
            $message = 'Please provide valid name, Gmail and class.';
        } elseif (!preg_match('/@gmail\.com$/i', $email)) {
            $message = 'Email must be a valid Gmail address ending with @gmail.com';
        } else {
            // Ensure email uniqueness (excluding current student)
            $stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            if ($stmt) {
                $stmt->bind_param('si', $email, $sid);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    $message = 'Another user with this email already exists.';
                } else {
                    // Handle passport upload for edit
                    $passport_path = null;
                    if (isset($_FILES['edit_passport']) && $_FILES['edit_passport']['error'] == UPLOAD_ERR_OK) {
                        $upload_dir = '../uploads/passports/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                        $file_ext = strtolower(pathinfo($_FILES['edit_passport']['name'], PATHINFO_EXTENSION));
                        $file_name = uniqid() . '.' . $file_ext;
                        $target_file = $upload_dir . $file_name;
                        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif']) && move_uploaded_file($_FILES['edit_passport']['tmp_name'], $target_file)) {
                            $passport_path = 'uploads/passports/' . $file_name;
                        }
                    }

                    $mysqli->begin_transaction();
                    try {
                        if ($has_class_id) {
                            if ($passport_path) {
                                $upd = $mysqli->prepare("UPDATE users SET full_name = ?, email = ?, class_id = ?, passport = ? WHERE id = ? AND role = 'student'");
                                $upd->bind_param('ssisi', $full_name, $email, $class_id, $passport_path, $sid);
                            } else {
                                $upd = $mysqli->prepare("UPDATE users SET full_name = ?, email = ?, class_id = ? WHERE id = ? AND role = 'student'");
                                $upd->bind_param('ssii', $full_name, $email, $class_id, $sid);
                            }
                        } else {
                            if ($passport_path) {
                                $upd = $mysqli->prepare("UPDATE users SET full_name = ?, email = ?, passport = ? WHERE id = ? AND role = 'student'");
                                $upd->bind_param('sssi', $full_name, $email, $passport_path, $sid);
                            } else {
                                $upd = $mysqli->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ? AND role = 'student'");
                                $upd->bind_param('ssi', $full_name, $email, $sid);
                            }
                        }
                        if (!$upd || !$upd->execute()) {
                            throw new Exception('Failed to update student.');
                        }
                        if ($reset_pass) {
                            $password = bin2hex(random_bytes(8));
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $rp = $mysqli->prepare('UPDATE users SET password = ? WHERE id = ?');
                            if (!$rp) throw new Exception('Failed to prepare password reset.');
                            $rp->bind_param('si', $hash, $sid);
                            if (!$rp->execute()) throw new Exception('Failed to reset password.');
                            $rp->close();
                        }
                        $upd->close();
                        $mysqli->commit();
                        $message = 'Student updated successfully.';
                    } catch (Exception $ex) {
                        $mysqli->rollback();
                        $message = 'Error updating student: ' . $ex->getMessage();
                    }
                }
                $stmt->close();
            } else {
                $message = 'Error preparing email uniqueness check.';
            }
        }
    }
}

// Handle delete student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_student') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid session token. Please refresh and try again.';
    } else {
        $sid = intval($_POST['student_id'] ?? 0);
        if ($sid <= 0) {
            $message = 'Invalid student selected.';
        } else {
            // Hard delete; if you prefer soft delete, replace with status column update
            $del = $mysqli->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
            if ($del) {
                $del->bind_param('i', $sid);
                if ($del->execute()) {
                    if ($del->affected_rows > 0) {
                        $message = 'Student deleted successfully.';
                    } else {
                        $message = 'Student not found or could not be deleted.';
                    }
                } else {
                    $message = 'Error deleting student: ' . $mysqli->error;
                }
                $del->close();
            } else {
                $message = 'Error preparing delete: ' . $mysqli->error;
            }
        }
    }
}

// Handle retake exam
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'retake_exam') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid session token. Please refresh and try again.';
    } else {
        $student_id = intval($_POST['student_id'] ?? 0);
        $exam_subject_id = intval($_POST['exam_subject_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        
        if ($student_id <= 0 || $exam_subject_id <= 0) {
            $message = 'Please select a student and subject.';
        } elseif (empty($reason)) {
            $message = 'Please provide a reason for allowing retake.';
        } else {
            try {
                // Get exam and subject details
                $stmt = $mysqli->prepare("SELECT es.exam_id, es.subject_id, e.title as exam_title, s.name as subject_name 
                                         FROM exam_subjects es 
                                         JOIN exams e ON es.exam_id = e.id 
                                         JOIN subjects s ON es.subject_id = s.id 
                                         WHERE es.id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $exam_subject_id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $exam_data = $res->fetch_assoc();
                    $stmt->close();
                    
                    if (!$exam_data) {
                        $message = 'Invalid exam subject selected.';
                    } else {
                        // Log the republish action
                        $admin_id = getUserId(); // Assuming this function exists
                        $stmt = $mysqli->prepare("INSERT INTO exam_republish_log (student_id, exam_id, exam_subject_id, republished_by, reason) VALUES (?, ?, ?, ?, ?)");
                        if ($stmt) {
                            $stmt->bind_param('iiiii', $student_id, $exam_data['exam_id'], $exam_subject_id, $admin_id, $reason);
                            if ($stmt->execute()) {
                                $message = "Student is now allowed to retake {$exam_data['subject_name']} in {$exam_data['exam_title']}.";
                            } else {
                                $message = 'Error logging retake permission: ' . $mysqli->error;
                            }
                            $stmt->close();
                        } else {
                            $message = 'Error preparing log statement: ' . $mysqli->error;
                        }
                    }
                } else {
                    $message = 'Error preparing exam data query: ' . $mysqli->error;
                }
            } catch (Exception $e) {
                $message = 'Error processing retake request: ' . $e->getMessage();
            }
        }
    }
}

// Fetch existing students list
$students = [];
$class_filter = isset($_GET['class_filter']) ? intval($_GET['class_filter']) : 0;
try {
    if ($has_class_id) {
        $sql = "SELECT u.id, u.full_name, u.email, u.passport, c.name AS class_name, u.class_id FROM users u LEFT JOIN classes c ON u.class_id = c.id WHERE u.role = 'student'";
        if ($class_filter > 0) {
            $sql .= " AND u.class_id = ?";
        }
        $sql .= " ORDER BY u.full_name";
    } else {
        $sql = "SELECT id, full_name, email, passport FROM users WHERE role = 'student' ORDER BY full_name";
    }
    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        if ($has_class_id && $class_filter > 0) {
            $stmt->bind_param('i', $class_filter);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();
    }
} catch (Exception $e) {}
?>

<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Student Enrollment</h5>
        <span class="badge bg-primary"><?php echo count($students); ?> Registered Students</span>
    </div>
    <div class="card-body">
        <?php if (!$has_class_id): ?>
        <div class="alert alert-warning mb-4">
            <h6 class="alert-heading">Database Sync Required</h6>
            <p class="mb-2">The users table requires a class_id column. Run the following SQL:</p>
            <code class="bg-dark text-light p-2 rounded d-block small">ALTER TABLE users ADD COLUMN class_id INT NULL AFTER role;</code>
        </div>
        <?php endif; ?>

        <?php if ($message): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Add Student Form -->
        <div class="card mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-user-plus me-2"></i>Quick Registration</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_student">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Full Name</label>
                                <input type="text" class="form-control form-control-lg" name="full_name" required placeholder="Enter student's full name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Gmail Address</label>
                                <input type="email" class="form-control form-control-lg" name="email" required placeholder="student.name@gmail.com">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Class</label>
                                <select class="form-select form-select-lg" name="class_id" <?php echo !$has_class_id || empty($classes) ? 'disabled' : ''; ?> required>
                                    <option value="">Select Class...</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Passport Photograph</label>
                                <input type="file" class="form-control form-control-lg" id="add_student_passport" name="passport" accept="image/*" required>
                                <div id="add_passport_preview_container" class="mt-2" style="display: none;">
                                    <img id="add_passport_preview" src="" alt="Passport Preview" style="max-height: 120px; border-radius: 8px; border: 1px solid #ddd; padding: 4px;">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="mb-3 w-100">
                                <button type="submit" class="btn btn-primary btn-lg w-100" <?php echo !$has_class_id ? 'disabled' : ''; ?> style="height: calc(3.5rem + 2px);">
                                    <i class="fas fa-user-plus me-2"></i>Enroll Student
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Student Directory -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Student Directory</h6>
                <?php if ($has_class_id && !empty($classes)): ?>
                <form method="GET" class="d-flex align-items-center mb-0">
                    <input type="hidden" name="tab" value="students">
                    <select name="class_filter" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="0">All Classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($class_filter == $c['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (count($students) === 0): ?>
                    <div class="alert alert-info">No students found for the selected criteria.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th width="50">#</th>
                                    <th width="70">Photo</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Class</th>
                                    <th width="150" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $index => $s): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td class="text-center">
                                            <?php if (!empty($s['passport'])): ?>
                                                <img src="../<?php echo htmlspecialchars($s['passport']); ?>" width="40" height="40" class="rounded-circle" style="object-fit: cover;">
                                            <?php else: ?>
                                                <i class="fas fa-user-circle fa-2x text-muted"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($s['full_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($s['email']); ?></td>
                                        <td><?php echo htmlspecialchars($s['class_name'] ?? 'Unassigned'); ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-info text-white" 
                                                data-bs-toggle="modal" data-bs-target="#editStudentModal" 
                                                data-id="<?php echo (int)$s['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($s['full_name'], ENT_QUOTES); ?>"
                                                data-email="<?php echo htmlspecialchars($s['email'], ENT_QUOTES); ?>"
                                                data-passport="<?php echo htmlspecialchars($s['passport'] ?? '', ENT_QUOTES); ?>"
                                                <?php if ($has_class_id): ?>
                                                data-class-id="<?php echo isset($s['class_id']) ? (int)$s['class_id'] : 0; ?>"
                                                <?php endif; ?>>
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete student?');">
                                                <input type="hidden" name="action" value="delete_student">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="student_id" value="<?php echo (int)$s['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editStudentModalLabel">Edit Student</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_student">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="student_id" id="edit_student_id">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="edit_full_name">Full Name</label>
            <input type="text" class="form-control" name="edit_full_name" id="edit_full_name" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="edit_email">Gmail</label>
            <input type="email" class="form-control" name="edit_email" id="edit_email" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="edit_passport">Passport Photograph (Optional)</label>
            <div class="mb-2">
                <img id="current_passport_preview" src="" alt="Current Passport" style="max-width: 100px; max-height: 100px; border-radius: 5px; display: none;">
            </div>
            <input type="file" class="form-control" id="edit_passport" name="edit_passport" accept="image/*">
            <div class="form-text">Leave blank to keep the current passport.</div>
          </div>
          <?php if ($has_class_id): ?>
          <div class="mb-3">
            <label class="form-label" for="edit_class_id">Class</label>
            <select class="form-select" name="edit_class_id" id="edit_class_id" required>
                <option value="">Select Class</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" value="1" id="reset_password" name="reset_password">
            <label class="form-check-label" for="reset_password">
              Reset password
            </label>
          </div>
          <div class="form-text">If password is reset, a new password will be generated.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  // Add Student Passport Preview
  var addPassport = document.getElementById('add_student_passport');
  if (addPassport) {
    addPassport.addEventListener('change', function(e) {
      var reader = new FileReader();
      reader.onload = function(e) {
        var preview = document.getElementById('add_passport_preview');
        if (preview) {
          preview.src = e.target.result;
          var container = document.getElementById('add_passport_preview_container');
          if (container) container.style.display = 'block';
        }
      }
      if (this.files && this.files[0]) {
        reader.readAsDataURL(this.files[0]);
      }
    });
  }

  // Edit Student Passport Preview
  var editPassport = document.getElementById('edit_passport');
  if (editPassport) {
    editPassport.addEventListener('change', function(e) {
      var reader = new FileReader();
      reader.onload = function(e) {
        var preview = document.getElementById('current_passport_preview');
        if (preview) {
          preview.src = e.target.result;
          preview.style.display = 'block';
        }
      }
      if (this.files && this.files[0]) {
        reader.readAsDataURL(this.files[0]);
      }
    });
  }

  var modal = document.getElementById('editStudentModal');
  if (!modal) return;
  
  // Move modal to the body to prevent stacking context/backdrop issues
  document.body.appendChild(modal);

  modal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var sid = button.getAttribute('data-id');
    var name = button.getAttribute('data-name');
    var email = button.getAttribute('data-email');
    var classId = button.getAttribute('data-class-id');
    var passport = button.getAttribute('data-passport');

    document.getElementById('edit_student_id').value = sid || '';
    document.getElementById('edit_full_name').value = name || '';
    document.getElementById('edit_email').value = email || '';
    
    var passportPreview = document.getElementById('current_passport_preview');
    if (passport) {
        // Adjust for root vs relative paths if needed, assuming relative to site root or admin folder
        var path = passport.startsWith('../') ? passport : '../' + passport;
        passportPreview.src = path;
        passportPreview.style.display = 'block';
    } else {
        passportPreview.src = '';
        passportPreview.style.display = 'none';
    }

    var classSelect = document.getElementById('edit_class_id');
    if (classSelect && classId) {
        classSelect.value = classId;
    }
    document.getElementById('reset_password').checked = false;
  });
})();

</script>

<?php
// Add this after the existing content, just before the closing PHP tag
// Fetch classes for the retake form
$retake_classes = [];
try {
    $stmt = $mysqli->prepare("SELECT id, name FROM classes ORDER BY name");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $retake_classes[] = $row;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    // Handle error silently
}
?>

<!-- Retake Exam Section -->
<div class="row mt-5">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">Examination Override</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Authorize students to retake specific examination subjects</p>
                <form method="POST" id="retakeForm">
                    <input type="hidden" name="action" value="retake_exam">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Target Class</label>
                                <select class="form-select" id="retake_class_id" name="class_id" required>
                                    <option value="">Select Class...</option>
                                    <?php foreach ($retake_classes as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Select Student</label>
                                <select class="form-select" id="retake_student_id" name="student_id" required>
                                    <option value="">Waiting for Class...</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Exam Subject</label>
                                <select class="form-select" id="exam_subject_id" name="exam_subject_id" required>
                                    <option value="">Waiting for Class...</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Authorization Reason</label>
                        <textarea class="form-control" id="reason" name="reason" rows="2" required placeholder="Reason for authorization..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning">
                        Grant Retake Permission
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Guidelines Sidebar -->
    <div class="col-md-4">
        <div class="card bg-light">
            <div class="card-header bg-secondary text-white">
                <h6 class="mb-0">Security Protocol</h6>
            </div>
            <div class="card-body small">
                <ul class="mb-0">
                    <li class="mb-2">Retake permissions are logged for auditing purposes.</li>
                    <li class="mb-2">Previous scores will be archived, not overwritten.</li>
                    <li>The student must re-login to see the activated exam.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Handle class selection to load students and subjects
document.getElementById('retake_class_id').addEventListener('change', function() {
    var classId = this.value;
    var studentSelect = document.getElementById('retake_student_id');
    var subjectSelect = document.getElementById('exam_subject_id');
    
    // Clear existing options
    studentSelect.innerHTML = '<option value="">Loading...</option>';
    subjectSelect.innerHTML = '<option value="">Loading...</option>';
    
    if (classId) {
        // Fetch students for this class
        fetch('get_students_by_class.php?class_id=' + encodeURIComponent(classId))
            .then(response => response.json())
            .then(students => {
                studentSelect.innerHTML = '<option value="">Select Student</option>';
                
                students.forEach(function(student) {
                    var option = document.createElement('option');
                    option.value = student.id;
                    option.textContent = student.full_name;
                    studentSelect.appendChild(option);
                });
                
                if (students.length === 0) {
                    studentSelect.innerHTML = '<option value="">No students in this class</option>';
                }
            })
            .catch(error => {
                console.error('Error loading students:', error);
                studentSelect.innerHTML = '<option value="">Error loading students</option>';
            });
            
        // Fetch exam subjects for this class
        fetch('get_exam_subjects.php?class_id=' + encodeURIComponent(classId))
            .then(response => response.json())
            .then(examSubjects => {
                subjectSelect.innerHTML = '<option value="">Select Exam Subject</option>';
                
                examSubjects.forEach(function(es) {
                    var option = document.createElement('option');
                    option.value = es.id;
                    option.textContent = es.display_name;
                    subjectSelect.appendChild(option);
                });
                
                if (examSubjects.length === 0) {
                    subjectSelect.innerHTML = '<option value="">No published exams for this class</option>';
                }
            })
            .catch(error => {
                console.error('Error loading exam subjects:', error);
                subjectSelect.innerHTML = '<option value="">Error loading subjects</option>';
            });
    } else {
        studentSelect.innerHTML = '<option value="">Select Class First</option>';
        subjectSelect.innerHTML = '<option value="">Select Class First</option>';
    }
});
</script>
