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
    
    if (!empty($email) && !empty($full_name) && !empty($password)) {
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
                    'role' => $_POST['role']
                ];
                
                // Save updated users
                if (file_put_contents('../users.json', json_encode($users, JSON_PRETTY_PRINT))) {
                    $role_name = $_POST['role'] === 'admin' ? 'Admin' : 'Teacher';
                    
                    // Try to add to database
                    $db_success = false;
                    try {
                        $mysqli = getDBConnection();
                        if ($mysqli) {
                            $stmt = $mysqli->prepare("INSERT INTO users (email, password, full_name, role) VALUES (?, ?, ?, ?)");
                            if ($stmt) {
                                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                $role = $_POST['role'];  // Assign to variable to avoid pass by reference error
                                $stmt->bind_param("ssss", $email, $hashed_password, $full_name, $role);
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
                
                <form method="POST">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                        <div class="form-text">This will be used as the username for login</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="form-text">Make sure to use a strong password</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Admin</option>
                        </select>
                        <div class="form-text">Select the user role</div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
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

<?php include '../includes/footer.php'; ?>