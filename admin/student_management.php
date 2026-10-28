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

$message = $_SESSION['student_management_message'] ?? '';
unset($_SESSION['student_management_message']);
$bulk_password_results = $_SESSION['bulk_generated_passwords'] ?? [];
unset($_SESSION['bulk_generated_passwords']);
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

$student_count_by_class = [];
$all_student_count = 0;
if ($has_class_id) {
    $count_result = $mysqli->query("SELECT class_id, COUNT(*) AS student_count FROM users WHERE role = 'student' GROUP BY class_id");
    if ($count_result) {
        while ($count_row = $count_result->fetch_assoc()) {
            $student_count = (int)$count_row['student_count'];
            $all_student_count += $student_count;
            if ($count_row['class_id'] !== null) {
                $student_count_by_class[(int)$count_row['class_id']] = $student_count;
            }
        }
        $count_result->close();
    }
}

// Handle add student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_student') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid session token. Please refresh and try again.';
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $admission_number = trim($_POST['admission_number'] ?? '');
        if ($admission_number === '') $admission_number = null;
        $class_id = intval($_POST['class_id'] ?? 0);
        $password = (string)($_POST['student_password'] ?? '');

        // Basic validations
        if ($full_name === '' || $email === '' || $class_id <= 0 || $password === '') {
            $message = 'Please fill in full name, Gmail, password and select a class.';
        } elseif (strlen($password) < 8) {
            $message = 'Student password must be at least 8 characters.';
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
                    // Store only a password hash; the admin shares the chosen password with the student.
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
                    $ins = $mysqli->prepare("INSERT INTO users (email, password, full_name, admission_number, role, class_id, passport) VALUES (?, ?, ?, ?, 'student', ?, ?)");
                    if ($ins) {
                        $ins->bind_param('ssssis', $email, $hash, $full_name, $admission_number, $class_id, $passport_path);
                        if ($ins->execute()) {
                            $message = 'Student added successfully. Share the password you set with the student.';
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
        $admission_number = trim($_POST['edit_admission_number'] ?? '');
        if ($admission_number === '') $admission_number = null;
        $class_id = intval($_POST['edit_class_id'] ?? 0);
        $new_password = (string)($_POST['new_password'] ?? '');

        if ($sid <= 0) {
            $message = 'Invalid student selected.';
        } elseif ($full_name === '' || $email === '' || ($has_class_id && $class_id <= 0)) {
            $message = 'Please provide valid name, Gmail and class.';
        } elseif (!preg_match('/@gmail\.com$/i', $email)) {
            $message = 'Email must be a valid Gmail address ending with @gmail.com';
        } elseif ($new_password !== '' && strlen($new_password) < 8) {
            $message = 'Student password must be at least 8 characters.';
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
                                $upd = $mysqli->prepare("UPDATE users SET full_name = ?, email = ?, admission_number = ?, class_id = ?, passport = ? WHERE id = ? AND role = 'student'");
                                $upd->bind_param('sssisi', $full_name, $email, $admission_number, $class_id, $passport_path, $sid);
                            } else {
                                $upd = $mysqli->prepare("UPDATE users SET full_name = ?, email = ?, admission_number = ?, class_id = ? WHERE id = ? AND role = 'student'");
                                $upd->bind_param('sssii', $full_name, $email, $admission_number, $class_id, $sid);
                            }
                        } else {
                            if ($passport_path) {
                                $upd = $mysqli->prepare("UPDATE users SET full_name = ?, email = ?, admission_number = ?, passport = ? WHERE id = ? AND role = 'student'");
                                $upd->bind_param('ssssi', $full_name, $email, $admission_number, $passport_path, $sid);
                            } else {
                                $upd = $mysqli->prepare("UPDATE users SET full_name = ?, email = ?, admission_number = ? WHERE id = ? AND role = 'student'");
                                $upd->bind_param('sssi', $full_name, $email, $admission_number, $sid);
                            }
                        }
                        if (!$upd || !$upd->execute()) {
                            throw new Exception('Failed to update student.');
                        }
                        if ($new_password !== '') {
                            $hash = password_hash($new_password, PASSWORD_DEFAULT);
                            $rp = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'student'");
                            if (!$rp) throw new Exception('Failed to prepare password update.');
                            $rp->bind_param('si', $hash, $sid);
                            if (!$rp->execute()) throw new Exception('Failed to update password.');
                            $rp->close();
                        }
                        $upd->close();
                        $mysqli->commit();
                        $message = $new_password !== '' ? 'Student updated successfully. Share the new password with the student.' : 'Student updated successfully.';
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
        $sql = "SELECT u.id, u.full_name, u.admission_number, u.email, u.passport, c.name AS class_name, u.class_id FROM users u LEFT JOIN classes c ON u.class_id = c.id WHERE u.role = 'student'";
        if ($class_filter > 0) {
            $sql .= " AND u.class_id = ?";
        }
        $sql .= " ORDER BY u.full_name";
    } else {
        $sql = "SELECT id, full_name, admission_number, email, passport FROM users WHERE role = 'student' ORDER BY full_name";
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

        <?php if (!empty($bulk_password_results)): ?>
        <div class="card admin-print-area mb-4" id="bulk-password-print-area">
            <div class="card-header d-flex justify-content-between align-items-center bg-white">
                <div>
                    <h5 class="mb-1">Generated Student Passwords</h5>
                    <p class="text-muted mb-0 no-print">Copy or print this list and share each password with its student. It is shown once.</p>
                </div>
                <button type="button" class="btn btn-primary no-print" onclick="window.print()"><i class="fas fa-print me-1"></i>Print Password List</button>
            </div>
            <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered bg-white mb-0">
                    <thead><tr><th>#</th><th>Student</th><th>Gmail</th><th>Generated Password</th></tr></thead>
                    <tbody>
                    <?php foreach ($bulk_password_results as $credential_index => $credential): ?>
                        <tr>
                            <td><?php echo $credential_index + 1; ?></td>
                            <td><?php echo htmlspecialchars($credential['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($credential['email']); ?></td>
                            <td><strong class="generated-password-value"><?php echo htmlspecialchars($credential['password']); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mt-3 mb-0 no-print">Refreshing or leaving this page clears the password list.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Bulk password generation -->
        <div class="card mb-4 border-warning">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-warning-emphasis fw-bold"><i class="fas fa-key me-2"></i>Generate Passwords for a Class or All Students</h5>
            </div>
            <div class="card-body">
                        <form method="POST" action="generate_student_passwords.php" id="bulkPasswordGenerationForm" class="row g-3 align-items-end">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="col-md-6">
                        <label class="form-label fw-bold" for="target_class_id">Students to update</label>
                        <select class="form-select" id="target_class_id" name="target_class_id" <?php echo !$has_class_id ? 'disabled' : ''; ?> required>
                            <option value="0" data-scope="All students" data-count="<?php echo $all_student_count; ?>">All students (<?php echo $all_student_count; ?>)</option>
                            <?php foreach ($classes as $c): ?>
                                <?php $class_student_count = $student_count_by_class[(int)$c['id']] ?? 0; ?>
                                <option value="<?php echo (int)$c['id']; ?>" data-scope="<?php echo htmlspecialchars($c['name'], ENT_QUOTES); ?>" data-count="<?php echo $class_student_count; ?>"><?php echo htmlspecialchars($c['name']); ?> (<?php echo $class_student_count; ?> students)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Each student receives a different password. Existing passwords for the selected students will be replaced.</div>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-warning" <?php echo !$has_class_id ? 'disabled' : ''; ?>>
                            <i class="fas fa-wand-magic-sparkles me-1"></i>Generate Passwords
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="bulkPasswordConfirmModal" tabindex="-1" aria-labelledby="bulkPasswordConfirmTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg overflow-hidden">
                    <div class="text-center px-4 pt-4 pb-3" style="background: #fff;">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 76px; height: 76px; background: #fff0c2; color: #9a6700;">
                            <i class="fas fa-key fa-2x"></i>
                        </div>
                        <h4 class="modal-title fw-bold mb-1" id="bulkPasswordConfirmTitle">Replace student passwords?</h4>
                        <p class="text-muted mb-0">Please review the password update before continuing.</p>
                    </div>
                    <div class="modal-body px-4 pt-3 pb-4">
                        <div class="rounded-3 border bg-light p-3 mb-3">
                            <div class="small text-muted mb-1">Selected group</div>
                            <div class="fw-bold fs-5" id="bulkPasswordConfirmScope">All students</div>
                            <div class="text-muted" id="bulkPasswordConfirmCount">0 student passwords will change</div>
                        </div>
                        <div class="d-flex gap-2 align-items-start text-danger small">
                            <i class="fas fa-triangle-exclamation mt-1"></i>
                            <span>Existing passwords for this group will stop working. Each student will receive a unique generated password, shown to you once after generation.</span>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4 pt-0">
                        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-warning px-4 fw-semibold" id="confirmBulkPasswordButton" onclick="submitBulkPasswordGeneration()">
                            <i class="fas fa-wand-magic-sparkles me-1"></i>Generate Passwords
                        </button>
                    </div>
                </div>
            </div>
        </div>

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
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Full Name</label>
                                <input type="text" class="form-control form-control-lg" name="full_name" required placeholder="Enter student's full name">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Admission Number</label>
                                <input type="text" class="form-control form-control-lg" name="admission_number" maxlength="100" placeholder="Optional school ID">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Gmail Address</label>
                                <input type="email" class="form-control form-control-lg" name="email" required placeholder="student.name@gmail.com">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Student Password</label>
                                <input type="password" class="form-control form-control-lg" name="student_password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters">
                                <div class="form-text">Share this password with the student for dashboard access.</div>
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

        <style>
        @media print {
            @page { size: A4 portrait; margin: 6mm; }
            body:has(#bulk-password-print-area) #student-directory-print { display: none !important; visibility: hidden !important; }
            #student-directory-print { width: 100% !important; margin: 0 !important; border: 0 !important; }
            #student-directory-print .card-header { min-height: 7mm; padding: 1mm 0 !important; }
            #student-directory-print .card-body { padding: 0 !important; }
            #student-directory-print table { table-layout: fixed !important; width: 100% !important; font-size: 7pt !important; }
            #student-directory-print col.no-print { display: none !important; }
            #student-directory-print th, #student-directory-print td { padding: 1px 2px !important; line-height: 1.1 !important; vertical-align: middle !important; overflow-wrap: anywhere !important; }
            #student-directory-print tbody tr { height: 17mm !important; break-inside: avoid !important; page-break-inside: avoid !important; }
            #student-directory-print img.student-export-photo { width: 12mm !important; height: 15mm !important; max-width: 12mm !important; max-height: 15mm !important; object-fit: contain !important; }
            #student-directory-print .table-responsive { overflow: visible !important; }
            #bulk-password-print-area { width: 100% !important; margin: 0 !important; border: 0 !important; box-shadow: none !important; }
            #bulk-password-print-area .card-header { padding: 0 0 4mm !important; border-bottom: 1px solid #777 !important; }
            #bulk-password-print-area .card-body { padding: 3mm 0 !important; }
            #bulk-password-print-area table { width: 100% !important; font-size: 11pt !important; border-collapse: collapse !important; }
            #bulk-password-print-area th, #bulk-password-print-area td { padding: 2.5mm !important; color: #111 !important; background: #fff !important; vertical-align: middle !important; }
            #bulk-password-print-area .generated-password-value { font-family: monospace; font-size: 12pt; letter-spacing: .3px; }
            #bulk-password-print-area tbody tr { break-inside: avoid !important; page-break-inside: avoid !important; }
        }
        </style>
        <!-- Student Directory -->
        <div class="card admin-print-area" id="student-directory-print">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Student Directory</h6>
                <button type="button" class="btn btn-outline-primary btn-sm no-print" onclick="window.print()"><i class="fas fa-print me-1"></i> Print / Save PDF</button>
                <?php if ($has_class_id && !empty($classes)): ?>
                <form method="GET" class="d-flex align-items-center mb-0 no-print">
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
                            <colgroup>
                                <col style="width:5%"><col style="width:10%"><col style="width:24%">
                                <col style="width:16%"><col style="width:29%"><col style="width:16%">
                                <col class="no-print" style="width:120px">
                            </colgroup>
                            <thead class="table-light">
                                <tr>
                                    <th width="50">#</th>
                                    <th width="70">Photo</th>
                                    <th>Full Name</th>
                                    <th>Admission No.</th>
                                    <th>Email</th>
                                    <th>Class</th>
                                    <th width="150" class="text-center no-print">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $index => $s): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td class="text-center">
                                            <?php if (!empty($s['passport'])): ?>
                                                <img src="../<?php echo htmlspecialchars($s['passport']); ?>" width="72" height="88" class="student-export-photo" loading="eager" alt="Student photograph">
                                            <?php else: ?>
                                                <i class="fas fa-user-circle fa-2x text-muted"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($s['full_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($s['admission_number'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($s['email']); ?></td>
                                        <td><?php echo htmlspecialchars($s['class_name'] ?? 'Unassigned'); ?></td>
                                        <td class="text-center no-print">
                                            <button type="button" class="btn btn-sm btn-info text-white" 
                                                data-bs-toggle="modal" data-bs-target="#editStudentModal" 
                                                data-id="<?php echo (int)$s['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($s['full_name'], ENT_QUOTES); ?>"
                                                data-admission-number="<?php echo htmlspecialchars($s['admission_number'] ?? '', ENT_QUOTES); ?>"
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
            <label class="form-label" for="edit_admission_number">Admission Number</label>
            <input type="text" class="form-control" name="edit_admission_number" id="edit_admission_number" maxlength="100">
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
          <div class="mb-3">
            <label class="form-label" for="edit_new_password">Set / change student password</label>
            <div class="input-group">
              <input type="password" class="form-control" id="edit_new_password" name="new_password" minlength="8" autocomplete="new-password">
              <button type="button" class="btn btn-outline-primary" onclick="generateIndividualStudentPassword()">Generate</button>
            </div>
            <div class="form-text">Leave blank to keep the current password. Generated passwords use the simple format Famaiis123.</div>
          </div>
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
let pendingBulkPasswordForm = null;

function showBulkPasswordConfirmDialog(form) {
    const classChoice = form.elements.target_class_id;
    const selectedOption = classChoice.options[classChoice.selectedIndex];
    const scope = selectedOption.dataset.scope || 'Selected students';
    const count = Number(selectedOption.dataset.count || 0);
    document.getElementById('bulkPasswordConfirmScope').textContent = scope;
    document.getElementById('bulkPasswordConfirmCount').textContent = count + (count === 1 ? ' student password will change' : ' student passwords will change');
    pendingBulkPasswordForm = form;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('bulkPasswordConfirmModal')).show();
    return false;
}

const bulkPasswordForm = document.getElementById('bulkPasswordGenerationForm');
if (bulkPasswordForm) {
    bulkPasswordForm.addEventListener('submit', function (event) {
        event.preventDefault();
        showBulkPasswordConfirmDialog(this);
    });
}

function submitBulkPasswordGeneration() {
    if (!pendingBulkPasswordForm) return;
    const button = document.getElementById('confirmBulkPasswordButton');
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Generating…';
    pendingBulkPasswordForm.submit();
}

function generateIndividualStudentPassword() {
    const bytes = new Uint32Array(1);
    window.crypto.getRandomValues(bytes);
    const password = 'Famaiis' + String(100 + (bytes[0] % 900));
    const field = document.getElementById('edit_new_password');
    field.type = 'text';
    field.value = password;
}

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
    var admissionNumber = button.getAttribute('data-admission-number');
    var email = button.getAttribute('data-email');
    var classId = button.getAttribute('data-class-id');
    var passport = button.getAttribute('data-passport');

    document.getElementById('edit_student_id').value = sid || '';
    document.getElementById('edit_full_name').value = name || '';
    document.getElementById('edit_admission_number').value = admissionNumber || '';
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
    document.getElementById('edit_new_password').value = '';
    document.getElementById('edit_new_password').type = 'password';
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
