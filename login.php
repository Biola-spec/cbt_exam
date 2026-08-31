<?php
require_once 'config/session.php';
require_once 'config/database.php';

// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    redirectBasedOnRole();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $is_student = isset($_POST['is_student']) && $_POST['is_student'] == '1';
    
    if ($is_student) {
        // Student login with email and subject code
        $email = trim($_POST['email']);
        $subject_code = trim($_POST['subject_code']);
        
        if (empty($email) || empty($subject_code)) {
            $error = 'Please enter both email and subject code';
        } else {
            // Try to authenticate student with email and subject code
            $user = authenticateStudentWithSubjectCode($email, $subject_code);
            
            if ($user) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['subject_code'] = $subject_code; // Store subject code in session
                
                // Get school name and logo from database
                $mysqli = getDBConnection();
                if ($mysqli) {
                    $stmt = $mysqli->prepare("SELECT school_name, logo_path FROM school_config LIMIT 1");
                    if ($stmt) {
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($row = $result->fetch_assoc()) {
                            $_SESSION['school_name'] = $row['school_name'];
                            $_SESSION['school_logo'] = $row['logo_path'];
                        } else {
                            $_SESSION['school_name'] = 'CBT Exam System'; // Fallback
                            $_SESSION['school_logo'] = null;
                        }
                        $stmt->close();
                    } else {
                        $_SESSION['school_name'] = 'CBT Exam System'; // Fallback
                        $_SESSION['school_logo'] = null;
                    }
                } else {
                    $_SESSION['school_name'] = 'CBT Exam System'; // Fallback
                    $_SESSION['school_logo'] = null;
                }
                
                // Redirect to student dashboard
                header('Location: student/dashboard.php');
                exit();
            } else {
                $error = 'Invalid email or subject code';
            }
        }
    } else {
        // Staff login
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password';
        } else {
            $user = authenticateUser($email, $password);
            
            if ($user && ($user['role'] == 'admin' || $user['role'] == 'teacher')) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                
                // Get school name and logo from database
                $mysqli = getDBConnection();
                if ($mysqli) {
                    $stmt = $mysqli->prepare("SELECT school_name, logo_path FROM school_config LIMIT 1");
                    if ($stmt) {
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($row = $result->fetch_assoc()) {
                            $_SESSION['school_name'] = $row['school_name'];
                            $_SESSION['school_logo'] = $row['logo_path'];
                        } else {
                            $_SESSION['school_name'] = 'CBT Exam System'; // Fallback
                            $_SESSION['school_logo'] = null;
                        }
                        $stmt->close();
                    } else {
                        $_SESSION['school_name'] = 'CBT Exam System'; // Fallback
                        $_SESSION['school_logo'] = null;
                    }
                } else {
                    $_SESSION['school_name'] = 'CBT Exam System'; // Fallback
                    $_SESSION['school_logo'] = null;
                }
                
                // Redirect based on role
                redirectBasedOnRole();
            } else {
                $error = 'Invalid email or password';
            }
        }
    }
}

// Function to authenticate student with subject code against database
function authenticateStudentWithSubjectCode($email, $subject_code) {
    // Simple cache for subject code validation
    static $subject_cache = [];
    static $subject_cache_time = [];
    static $subject_cache_expiry = 300; // 5 minutes
    $mysqli = getDBConnection();
    
    if (!$mysqli) {
        // Database not available, return false
        return false;
    }
    
    try {
        // Check if the student exists in the users table
        $stmt = $mysqli->prepare("SELECT id, full_name, role FROM users WHERE email = ? AND role = 'student' LIMIT 1");
        if (!$stmt) {
            error_log("Prepare failed: " . $mysqli->error);
            return false;
        }
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            
            // Check cache for subject code validation
            $cache_key = 'subject_' . $subject_code;
            $current_time = time();
            
            $subject_valid = false;
            
            if (isset($subject_cache[$cache_key]) && 
                isset($subject_cache_time[$cache_key]) && 
                ($current_time - $subject_cache_time[$cache_key]) < 300) {
                // Use cached result
                $subject_valid = $subject_cache[$cache_key];
            } else {
                // Check if the subject code is valid
                $stmt = $mysqli->prepare("SELECT id FROM subjects WHERE subject_code = ? LIMIT 1");
                if (!$stmt) {
                    error_log("Prepare failed: " . $mysqli->error);
                    return false;
                }
                
                $stmt->bind_param("s", $subject_code);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $subject_valid = $result->fetch_assoc() !== false;
                $stmt->close();
                
                // Cache the result
                $subject_cache[$cache_key] = $subject_valid;
                $subject_cache_time[$cache_key] = $current_time;
            }
            
            if ($subject_valid) {
                // Valid student and subject code
                return [
                    'id' => $row['id'],
                    'email' => $email,
                    'full_name' => $row['full_name'],
                    'role' => $row['role']
                ];
            }
        }
        
        $stmt->close();
    } catch (Exception $e) {
        // Database error
        error_log("Subject code authentication error: " . $e->getMessage());
        return false;
    }
    
    return false;
}
$body_class = 'login-page-bg';
?>

<?php include 'includes/header.php'; ?>

<div class="row justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="col-md-8 col-lg-7 col-xl-6 col-sm-11">
        <!-- Modernized Login Box -->
        <div class="card glass-card fade-in" style="border-radius: var(--radius-xl); overflow: hidden;">
            <div class="card-header text-center bg-primary text-white border-0 py-4">
                <h2 class="mb-0 fw-bold header-title">
                    <?php 
                    // Use the same logic as in header.php for consistency
                    $school_logo = getSchoolLogo();
                    
                    // Display logo if exists, otherwise show icon
                    if (!empty($school_logo)) {
                        // Handle relative paths - if path starts with ../, adjust it for root-level pages
                        $logo_src = $school_logo;
                        if (strpos($logo_src, '../') === 0) {
                            // Remove the ../ prefix for root-level pages
                            $logo_src = substr($logo_src, 3);
                        }
                        
                        if (file_exists($logo_src)) {
                            echo '<img src="' . htmlspecialchars($logo_src) . '" alt="School Logo" style="height: 40px; margin-right: 10px;">';
                        } else {
                            echo '<i class="fas fa-graduation-cap me-2"></i>';
                        }
                    } else {
                        echo '<i class="fas fa-graduation-cap me-2"></i>';
                    }
                    
                    echo isset($_SESSION['school_name']) ? htmlspecialchars($_SESSION['school_name']) : 'CBT Exam System';
                    ?>
                </h2>
                <p class="mb-0 mt-2 text-white-50">Sign in to access your account</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <ul class="nav nav-tabs" id="loginTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="staff-tab" data-bs-toggle="tab" data-bs-target="#staff" type="button" role="tab">
                            <i class="fas fa-user-tie me-1"></i> Staff Login
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="student-tab" data-bs-toggle="tab" data-bs-target="#student" type="button" role="tab">
                            <i class="fas fa-user-graduate me-1"></i> Student Login
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content mt-4" id="loginTabsContent">
                    <!-- Staff Login Form -->
                    <div class="tab-pane fade show active" id="staff" role="tabpanel">
                        <form method="POST">
                            <input type="hidden" name="is_student" value="0">
                            <div class="mb-3">
                                <label for="staff_email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="staff_email" name="email" placeholder="Enter your email" required autocomplete="off">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="staff_password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="staff_password" name="password" placeholder="Enter your password" required autocomplete="off">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('staff_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-1"></i> Sign In as Staff
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Student Login Form -->
                    <div class="tab-pane fade" id="student" role="tabpanel">
                        <form method="POST">
                            <input type="hidden" name="is_student" value="1">
                            <div class="mb-3">
                                <label for="student_email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="student_email" name="email" placeholder="Enter your email" required autocomplete="off">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="subject_code" class="form-label">Subject Code</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-book"></i></span>
                                    <input type="text" class="form-control" id="subject_code" name="subject_code" placeholder="Enter subject code" required autocomplete="off">
                                </div>
                                <div class="form-text">Enter the unique code for the subject you want to access exams for.</div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-sign-in-alt me-1"></i> Sign In as Student
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility(fieldId) {
    const passwordField = document.getElementById(fieldId);
    const toggleButton = passwordField.nextElementSibling;
    const icon = toggleButton.querySelector('i');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passwordField.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<?php include 'includes/footer.php'; ?>