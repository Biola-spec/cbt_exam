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
    
    // Validate input
    if (empty($email) || empty($password) || empty($full_name) || empty($role)) {
        $error = 'All fields are required';
    } elseif (!in_array($role, ['admin', 'teacher', 'student'])) {
        $error = 'Invalid role selected';
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
                
                // Handle passport upload
                $passport_path = null;
                if (isset($_FILES['passport']) && $_FILES['passport']['error'] == UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/passports/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                    $file_ext = strtolower(pathinfo($_FILES['passport']['name'], PATHINFO_EXTENSION));
                    $file_name = uniqid() . '.' . $file_ext;
                    $target_file = $upload_dir . $file_name;
                    if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif']) && move_uploaded_file($_FILES['passport']['tmp_name'], $target_file)) {
                        $passport_path = $target_file;
                    }
                }
                
                // Insert new user
                $stmt = $mysqli->prepare("INSERT INTO users (email, password, full_name, role, passport) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $email, $hashed_password, $full_name, $role, $passport_path);
                
                if ($stmt->execute()) {
                    // Sync to users.json
                    saveUserToJson($email, $hashed_password, $full_name, $role);
                    $success = 'Registration successful! You can now login.';
                } else {
                    $error = 'Registration failed. Please try again.';
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
    <div class="col-md-6">
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
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-control" id="role" name="role" required onchange="togglePassportField()">
                            <option value="">Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="teacher">Teacher</option>
                            <option value="student">Student</option>
                        </select>
                    </div>
                    <div class="mb-3" id="passportField" style="display: none;">
                        <label for="passport" class="form-label">Passport Photograph (Student ONLY)</label>
                        <input type="file" class="form-control" id="passport" name="passport" accept="image/*">
                    </div>
                <script>
                    function togglePassportField() {
                        var role = document.getElementById('role').value;
                        var passportField = document.getElementById('passportField');
                        var passportInput = document.getElementById('passport');
                        if (role === 'student') {
                            passportField.style.display = 'block';
                            passportInput.required = true;
                        } else {
                            passportField.style.display = 'none';
                            passportInput.required = false;
                        }
                    }
                </script>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Register
                        </button>
                    </div>
                </form>
                
                <div class="text-center mt-3">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>