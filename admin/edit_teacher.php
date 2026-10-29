<?php
require_once '../config/session.php';
require_once '../config/database.php';

// Check if user is logged in and is admin
requireLogin();
if (getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$success = false;
$teacher = null;

// Get teacher ID from URL
$teacher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($teacher_id <= 0) {
    header('Location: dashboard.php?tab=teachers');
    exit();
}

try {
    $mysqli = getDBConnection();
    
    // Load the teacher
    $stmt = $mysqli->prepare("SELECT id, email, full_name, role FROM users WHERE id = ? AND role IN ('teacher','admin','admin_teacher')");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $teacher = $result->fetch_assoc();
    $stmt->close();
    
    if (!$teacher) {
        header('Location: dashboard.php?tab=teachers');
        exit();
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $submitted_roles = isset($_POST['roles']) && is_array($_POST['roles']) ? $_POST['roles'] : [];
        $selected_roles = array_values(array_unique(array_intersect(['teacher', 'admin'], $submitted_roles)));
        $role = count($selected_roles) === 2 ? 'admin_teacher' : ($selected_roles[0] ?? '');
        
        if (!empty($full_name) && !empty($email) && $role !== '') {
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Please enter a valid email address';
            } else {
                // Check if email is already used by another user
                $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->bind_param("si", $email, $teacher_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $message = 'This email is already used by another teacher';
                } else {
                    // Update teacher details
                    if ($role === 'admin_teacher' && !ensureCombinedStaffRoleSupport($mysqli)) {
                        throw new RuntimeException('Database role upgrade failed. Apply database/upgrade_multi_role.sql and try again.');
                    }
                    if (!empty($password)) {
                        // Update with new password
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $mysqli->prepare("UPDATE users SET full_name = ?, email = ?, password = ?, role = ? WHERE id = ?");
                        $stmt->bind_param("ssssi", $full_name, $email, $hashed_password, $role, $teacher_id);
                    } else {
                        // Update without changing password
                        $stmt = $mysqli->prepare("UPDATE users SET full_name = ?, email = ?, role = ? WHERE id = ?");
                        $stmt->bind_param("sssi", $full_name, $email, $role, $teacher_id);
                    }
                    
                    if ($stmt->execute()) {
                        $message = 'Teacher updated successfully';
                        $success = true;
                        
                        // Also update in users.json file
                        $users = [];
                        if (file_exists('../users.json')) {
                            $users = json_decode(file_get_contents('../users.json'), true);
                            
                            $old_email = $teacher['email'];
                            if (isset($users[$old_email])) {
                                // Get the password hash (either the new one or keep the existing one)
                                $password_hash = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : $users[$old_email]['password'];
                                
                                // If the email is changing, remove the old key
                                if ($old_email !== $email) {
                                    unset($users[$old_email]);
                                }
                                
                                // Add/Update the entry
                                $users[$email] = [
                                    'password' => $password_hash,
                                    'full_name' => $full_name,
                                    'role' => $role
                                ];
                                
                                file_put_contents('../users.json', json_encode($users, JSON_PRETTY_PRINT));
                            }
                        }
                        
                        // Update the teacher variable for display
                        $teacher['full_name'] = $full_name;
                        $teacher['email'] = $email;
                        $teacher['role'] = $role;
                    } else {
                        $message = 'Error updating teacher: ' . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        } else {
            $message = 'Please fill in all required fields';
        }
    }
} catch (Exception $e) {
    $message = 'Error loading teacher: ' . $e->getMessage();
}
?>

<?php include '../includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Edit Teacher</h5>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($teacher): ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($teacher['full_name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($teacher['email']); ?>" required>
                            <div class="form-text">This will be used as the username for login</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" class="form-control" id="password" name="password">
                            <div class="form-text">Enter a new password only if you want to change it</div>
                        </div>

                        <div class="mb-3">
                            <fieldset>
                                <legend class="form-label">Account roles</legend>
                                <div class="d-flex flex-wrap gap-4">
                                    <label class="form-check-label"><input class="form-check-input me-2" type="checkbox" name="roles[]" value="teacher" <?php echo in_array($teacher['role'], ['teacher','admin_teacher'], true) ? 'checked' : ''; ?>>Teacher</label>
                                    <label class="form-check-label"><input class="form-check-input me-2" type="checkbox" name="roles[]" value="admin" <?php echo in_array($teacher['role'], ['admin','admin_teacher'], true) ? 'checked' : ''; ?>>Admin</label>
                                </div>
                            </fieldset>
                            <div class="form-text">Select Teacher, Admin, or both.</div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="dashboard.php?tab=teachers" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Back to Teacher Allocation
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Update Teacher
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Teacher not found.
                    </div>
                    <a href="dashboard.php?tab=teachers" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Teacher Allocation
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
