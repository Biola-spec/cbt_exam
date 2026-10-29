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
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $password = $_POST['password'];
    $submitted_roles = isset($_POST['roles']) && is_array($_POST['roles']) ? $_POST['roles'] : [];
    $selected_roles = $submitted_roles
        ? array_values(array_unique(array_intersect(['teacher', 'admin'], $submitted_roles)))
        : [];
    $role = count($selected_roles) === 2 ? 'admin_teacher' : ($selected_roles[0] ?? '');
    $passport_path = null;
    $photo_error = '';
    if (isset($_FILES['passport']) && $_FILES['passport']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['passport']['error'] !== UPLOAD_ERR_OK) {
            $photo_error = 'The photo could not be uploaded. Please try again.';
        } else {
            $image_info = @getimagesize($_FILES['passport']['tmp_name']);
            $extension = strtolower(pathinfo($_FILES['passport']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
            if ($_FILES['passport']['size'] > 5 * 1024 * 1024 || !$image_info || !isset($allowed_extensions[$extension]) || $image_info['mime'] !== $allowed_extensions[$extension]) {
                $photo_error = 'Choose a JPG, PNG, GIF, or WebP image under 5 MB.';
            } else {
                $upload_dir = __DIR__ . '/../uploads/passports/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $file_name = 'staff_' . bin2hex(random_bytes(12)) . '.' . $extension;
                if (move_uploaded_file($_FILES['passport']['tmp_name'], $upload_dir . $file_name)) {
                    $passport_path = 'uploads/passports/' . $file_name;
                } else {
                    $photo_error = 'The photo could not be saved. Please try again.';
                }
            }
        }
    }
    
    if (!$selected_roles || count($selected_roles) !== count($submitted_roles)) {
        $message = 'Select Teacher, Admin, or both account roles.';
    } elseif ($photo_error) {
        $message = $photo_error;
    } elseif (!empty($email) && !empty($full_name) && !empty($password)) {
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address';
        } else {
            // Load existing users
            $users = [];
            if (file_exists('../users.json')) {
                $users = json_decode(file_get_contents('../users.json'), true);
            }
            
            // Check if user already exists
            if (isset($users[$email])) {
                $message = 'A user with this email already exists';
            } else {
                // Add new teacher/admin
                $users[$email] = [
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'full_name' => $full_name,
                    'role' => $role
                ];
                
                // Save updated users
                if (file_put_contents('../users.json', json_encode($users, JSON_PRETTY_PRINT))) {
                    $role_name = $role === 'admin_teacher' ? 'Teacher and Admin' : ($role === 'admin' ? 'Admin' : 'Teacher');
                    
                    // Try to add to database
                    $db_success = false;
                    try {
                        $mysqli = getDBConnection();
                        if ($mysqli) {
                            if ($role === 'admin_teacher' && !ensureCombinedStaffRoleSupport($mysqli)) {
                                throw new RuntimeException('Database role upgrade failed. Apply database/upgrade_multi_role.sql and try again.');
                            }
                            $stmt = $mysqli->prepare("INSERT INTO users (email, password, full_name, role, passport) VALUES (?, ?, ?, ?, ?)");
                            if ($stmt) {
                                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                $stmt->bind_param("sssss", $email, $hashed_password, $full_name, $role, $passport_path);
                                if ($stmt->execute()) {
                                    $db_success = true;
                                } else {
                                    $message = 'Failed to add user to database: ' . htmlspecialchars($stmt->error);
                                }
                                $stmt->close();
                            }
                        }
                    } catch (Exception $e) {
                        $message = 'Failed to add user to database: ' . htmlspecialchars($e->getMessage());
                    }
                    
                    if ($db_success) {
                        $message = $role_name . ' added successfully.';
                        $success = true;
                    } else {
                        // Rollback users.json if database insertion failed
                        unset($users[$email]);
                        file_put_contents('../users.json', json_encode($users, JSON_PRETTY_PRINT));
                        $success = false;
                    }
                } else {
                    $message = 'Error saving user data';
                }
            }
        }
    } else {
        $message = 'Please fill in all fields';
    }
    
    // If successful, redirect back to teacher allocation
    if ($success) {
        header('Location: dashboard.php?tab=teachers&message=' . urlencode($message));
        exit();
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" class="registration-form">
                    <div class="registration-section-title"><span>Account details</span><small>Enter the staff member’s information</small></div>
                    <div class="row g-3">
                    <div class="col-md-6">
                        <label for="full_name" class="form-label">Full name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required autocomplete="name" placeholder="e.g. Ada Okafor">
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" required autocomplete="email" placeholder="name@school.com">
                        <div class="form-text">Used to sign in.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password" placeholder="Create a password">
                    </div>
                    <div class="col-md-6">
                        <fieldset>
                                <legend class="form-label">Account roles</legend>
                            <div class="role-choice-group">
                                <label class="role-choice"><input type="checkbox" name="roles[]" value="teacher"><span><i class="fas fa-chalkboard-teacher"></i><strong>Teacher</strong><small>Manage classes and exams</small></span></label>
                                <label class="role-choice"><input type="checkbox" name="roles[]" value="admin"><span><i class="fas fa-user-shield"></i><strong>Admin</strong><small>Manage school settings</small></span></label>
                            </div>
                            <div class="form-text">Select one role or both.</div>
                        </fieldset>
                    </div>
                    <div class="col-12">
                        <label for="passport" class="form-label">Profile photo <span class="text-muted fw-normal">(optional)</span></label>
                        <div class="staff-photo-upload">
                            <div class="staff-photo-preview" id="staff_photo_placeholder"><i class="fas fa-camera"></i></div>
                            <img class="staff-photo-preview d-none" id="staff_photo_preview" alt="Selected profile photo preview">
                            <div class="flex-grow-1"><input type="file" class="form-control" id="passport" name="passport" accept="image/jpeg,image/png,image/gif,image/webp"><div class="form-text">JPG, PNG, GIF or WebP, up to 5 MB.</div></div>
                        </div>
                    </div>
                    </div>
                    <div class="d-flex flex-column-reverse flex-md-row gap-2 justify-content-end mt-4">
                        <a href="dashboard.php?tab=teachers" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus me-1"></i> Add User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.registration-form .form-label { font-weight: 600; color: #243247; margin-bottom: .45rem; }
.registration-form .form-control, .registration-form .form-select { min-height: 46px; border-color: #d8e0e9; border-radius: 10px; }
.registration-form .form-control:focus, .registration-form .form-select:focus { border-color: #4f7df3; box-shadow: 0 0 0 .2rem rgba(79,125,243,.14); }
.role-choice-group { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
.role-choice { position: relative; cursor: pointer; }
.role-choice input { position: absolute; opacity: 0; }
.role-choice span { min-height: 82px; padding: .75rem; display: grid; grid-template-columns: 24px 1fr; column-gap: .55rem; align-content: center; border: 1px solid #d8e0e9; border-radius: 10px; background: #fff; transition: border-color .15s, background .15s, box-shadow .15s; }
.role-choice span i { grid-row: span 2; align-self: center; color: #64748b; }
.role-choice span strong { color: #243247; }
.role-choice span small { color: #748196; }
.role-choice input:checked + span { border-color: #4f7df3; background: #f4f7ff; box-shadow: 0 0 0 2px rgba(79,125,243,.12); }
.role-choice input:focus-visible + span { outline: 3px solid rgba(79,125,243,.35); outline-offset: 2px; }
.registration-section-title { display: flex; flex-direction: column; margin-bottom: 1.1rem; }
.registration-section-title span { font-weight: 700; color: #243247; }
.registration-section-title small { color: #748196; margin-top: .15rem; }
.staff-photo-upload { display: flex; align-items: center; gap: 1rem; padding: 1rem; border: 1px dashed #cbd5e1; border-radius: 12px; background: #f8fafc; }
.staff-photo-preview { width: 68px; height: 68px; flex: 0 0 68px; display: grid; place-items: center; object-fit: cover; border-radius: 50%; background: #e8eef8; color: #58709b; font-size: 1.25rem; }
</style>
<script>
document.getElementById('passport')?.addEventListener('change', function () {
    const preview = document.getElementById('staff_photo_preview');
    const placeholder = document.getElementById('staff_photo_placeholder');
    if (this.files && this.files[0]) {
        preview.src = URL.createObjectURL(this.files[0]);
        preview.classList.remove('d-none');
        placeholder.classList.add('d-none');
    }
});
</script>

<?php include '../includes/footer.php'; ?>
