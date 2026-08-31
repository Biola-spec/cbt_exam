<?php
session_start();

// Simple file-based user system for testing
$users_file = 'users.json';

// Create default admin user if file doesn't exist
if (!file_exists($users_file)) {
    $users = [
        'admin@cbtexam.com' => [
            'password' => password_hash('Admin@2024', PASSWORD_DEFAULT),
            'full_name' => 'System Administrator',
            'role' => 'admin'
        ]
    ];
    file_put_contents($users_file, json_encode($users, JSON_PRETTY_PRINT));
}

// Handle login
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        // Load users
        $users = json_decode(file_get_contents($users_file), true);
        
        // Check if user exists and password is correct
        if (isset($users[$email]) && password_verify($password, $users[$email]['password'])) {
            // Set session variables
            $_SESSION['user_id'] = md5($email); // Simple ID generation
            $_SESSION['email'] = $email;
            $_SESSION['full_name'] = $users[$email]['full_name'];
            $_SESSION['role'] = $users[$email]['role'];
            
            // Redirect based on role
            if ($users[$email]['role'] == 'admin') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: login.php'); // For testing, just redirect back
            }
            exit();
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Login Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">
                            <i class="fas fa-sign-in-alt"></i> Simple Login Test
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required value="admin@cbtexam.com">
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required value="Admin@2024">
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt"></i> Sign In
                                </button>
                            </div>
                        </form>
                        
                        <div class="mt-3">
                            <p><strong>Test Credentials:</strong></p>
                            <ul>
                                <li>Email: admin@cbtexam.com</li>
                                <li>Password: Admin@2024</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>