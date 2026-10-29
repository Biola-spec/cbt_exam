<?php
require_once 'config/database.php';
require_once 'config/session.php';

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    redirectBasedOnRole();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];
    $passport_path = null;
    
    // Validate input
    if (empty($email) || empty($password) || empty($full_name) || empty($role)) {
        $error = 'All fields are required';
    } elseif (!in_array($role, ['admin', 'teacher', 'student'])) {
        $error = 'Invalid role selected';
    } elseif ($role === 'student' && (!isset($_FILES['passport']) || $_FILES['passport']['error'] === UPLOAD_ERR_NO_FILE)) {
        $error = 'Please upload a profile photo.';
    } else {
        try {
            $mysqli = getDBConnection();
            
            // Check if email already exists
            $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Email already registered';
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Profile photos are optional for staff and required for students.
                if (isset($_FILES['passport']) && $_FILES['passport']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $image_info = $_FILES['passport']['error'] === UPLOAD_ERR_OK ? @getimagesize($_FILES['passport']['tmp_name']) : false;
                    $file_ext = strtolower(pathinfo($_FILES['passport']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
                    if ($_FILES['passport']['error'] !== UPLOAD_ERR_OK || $_FILES['passport']['size'] > 5 * 1024 * 1024 || !$image_info || !isset($allowed_extensions[$file_ext]) || $image_info['mime'] !== $allowed_extensions[$file_ext]) {
                        $error = 'Choose a JPG, PNG, GIF, or WebP photo under 5 MB.';
                    } else {
                        $upload_dir = 'uploads/passports/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                        $file_name = 'user_' . bin2hex(random_bytes(12)) . '.' . $file_ext;
                        if (move_uploaded_file($_FILES['passport']['tmp_name'], $upload_dir . $file_name)) {
                            $passport_path = $upload_dir . $file_name;
                        } else {
                            $error = 'The photo could not be saved. Please try again.';
                        }
                    }
                }
                
                if (!$error) {
                    // Insert new user
                    $stmt = $mysqli->prepare("INSERT INTO users (email, password, full_name, role, passport) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $email, $hashed_password, $full_name, $role, $passport_path);
                    if ($stmt->execute()) {
                        saveUserToJson($email, $hashed_password, $full_name, $role);
                        $success = 'Registration successful! You can now login.';
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                }
            }
            $stmt->close();
        } catch (Exception $e) {
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="text-center">
                    <i class="fas fa-user-plus"></i> Register for CBT Exam System
                </h3>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" class="registration-form">
                    <div class="registration-section-title"><span>Account details</span><small>Create your sign-in information and profile.</small></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label for="full_name" class="form-label">Full name</label><input type="text" class="form-control" id="full_name" name="full_name" autocomplete="name" placeholder="e.g. Ada Okafor" required></div>
                        <div class="col-md-6"><label for="email" class="form-label">Email address</label><input type="email" class="form-control" id="email" name="email" autocomplete="email" placeholder="name@school.com" required></div>
                        <div class="col-md-6"><label for="password" class="form-label">Password</label><input type="password" class="form-control" id="password" name="password" autocomplete="new-password" placeholder="Create a password" required></div>
                        <div class="col-md-6"><label for="role" class="form-label">Account role</label><select class="form-select" id="role" name="role" required><option value="">Select a role</option><option value="admin">Admin</option><option value="teacher">Teacher</option><option value="student">Student</option></select></div>
                        <div class="col-12" id="passportField" hidden><label for="passport" class="form-label">Profile photo <span id="photoRequiredLabel" class="text-muted fw-normal">(optional)</span></label><div class="registration-photo-upload"><div class="registration-photo-preview" id="photoPlaceholder"><i class="fas fa-camera"></i></div><img class="registration-photo-preview d-none" id="photoPreview" alt="Selected profile photo preview"><div class="flex-grow-1"><input type="file" class="form-control" id="passport" name="passport" accept="image/jpeg,image/png,image/gif,image/webp"><div class="form-text">JPG, PNG, GIF or WebP, up to 5 MB.</div></div></div></div>
                        <div class="col-12 d-grid mt-4"><button type="submit" class="btn btn-primary"><i class="fas fa-user-plus me-2"></i>Create account</button></div>
                    </div>
                </form>
                
                <div class="text-center mt-3">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.registration-form .form-label { font-weight: 600; color: #243247; margin-bottom: .45rem; }
.registration-form .form-control, .registration-form .form-select { min-height: 46px; border-color: #d8e0e9; border-radius: 10px; }
.registration-form .form-control:focus, .registration-form .form-select:focus { border-color: #4f7df3; box-shadow: 0 0 0 .2rem rgba(79,125,243,.14); }
.registration-section-title { display: flex; flex-direction: column; margin-bottom: 1.1rem; }
.registration-section-title span { font-weight: 700; color: #243247; }
.registration-section-title small { color: #748196; margin-top: .15rem; }
.registration-photo-upload { display: flex; align-items: center; gap: 1rem; padding: 1rem; border: 1px dashed #cbd5e1; border-radius: 12px; background: #f8fafc; }
.registration-photo-preview { width: 68px; height: 68px; flex: 0 0 68px; display: grid; place-items: center; object-fit: cover; border-radius: 50%; background: #e8eef8; color: #58709b; font-size: 1.25rem; }
</style>
<script>
const roleSelect = document.getElementById('role');
const passportField = document.getElementById('passportField');
const passportInput = document.getElementById('passport');
const photoRequiredLabel = document.getElementById('photoRequiredLabel');
roleSelect.addEventListener('change', function () {
    const isStudent = this.value === 'student';
    passportField.hidden = !this.value;
    passportInput.required = isStudent;
    photoRequiredLabel.textContent = isStudent ? '(required for students)' : '(optional)';
});
passportInput.addEventListener('change', function () {
    if (!this.files || !this.files[0]) return;
    const preview = document.getElementById('photoPreview');
    preview.src = URL.createObjectURL(this.files[0]);
    preview.classList.remove('d-none');
    document.getElementById('photoPlaceholder').classList.add('d-none');
});
</script>

<?php include 'includes/footer.php'; ?>
