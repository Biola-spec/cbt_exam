<?php
require_once 'config/session.php';
require_once 'config/database.php';

// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

$student_exam_access_mode = isset($_GET['exam_access']) && $_GET['exam_access'] === '1'
    && isLoggedIn() && getUserRole() === 'student';

// Keep dashboard users signed in when they open the subject-code exam entry screen.
if (isLoggedIn() && !$student_exam_access_mode) {
    redirectBasedOnRole();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $is_student = isset($_POST['is_student']) && $_POST['is_student'] == '1';
    
    if ($is_student) {
        $email = trim($_POST['email'] ?? '');
        $subject_code = trim($_POST['subject_code'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($email === '') {
            $error = 'Enter your Gmail address.';
        } elseif ($subject_code !== '' && $password !== '') {
            $error = 'Choose one option: use a subject code for a published exam, or use your admin-issued password for the dashboard.';
        } elseif ($subject_code !== '') {
            $user = authenticateStudentWithSubjectCode($email, $subject_code);
            if ($user) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = 'student';
                $_SESSION['subject_code'] = $subject_code;
                $_SESSION['public_exam_access'] = true;
                $_SESSION['public_exam_access_expires_at'] = time() + (8 * 60 * 60);
                unset($_SESSION['school_name'], $_SESSION['school_logo']);
                header('Location: student/public_exams.php');
                exit();
            }
            $error = 'We could not find a published exam for that Gmail address and subject code.';
        } elseif ($password !== '') {
            $user = authenticateStudentWithPassword($email, $password);
            if ($user) {
                session_regenerate_id(true);
                unset($_SESSION['public_exam_access'], $_SESSION['public_exam_access_expires_at'], $_SESSION['subject_code']);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = 'student';
                header('Location: student/dashboard.php');
                exit();
            }
            $error = 'Invalid Gmail address or password.';
        } else {
            $error = 'Enter a subject code for a published exam or your admin-issued password for the dashboard.';
        }
    } else {
        // Staff login
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password';
        } else {
            $user = authenticateUser($email, $password);
            
            if ($user && in_array($user['role'], ['admin', 'teacher', 'admin_teacher'], true)) {
                // Set session variables
                session_regenerate_id(true);
                unset($_SESSION['public_exam_access'], $_SESSION['public_exam_access_expires_at'], $_SESSION['subject_code']);
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

// A subject code only opens published exams assigned to this student's class.
function authenticateStudentWithSubjectCode($email, $subject_code) {
    $mysqli = getDBConnection();
    if (!$mysqli) return false;
    $stmt = $mysqli->prepare("SELECT DISTINCT u.id, u.email, u.full_name, u.role
        FROM users u
        JOIN subjects s ON s.subject_code = ?
        JOIN exam_subjects es ON es.subject_id = s.id AND es.class_id = u.class_id AND es.is_published = 1
        JOIN exams e ON e.id = es.exam_id AND e.is_published = 1
        WHERE u.email = ? AND u.role = 'student' AND u.class_id IS NOT NULL
          AND NOT EXISTS (SELECT 1 FROM entrance_exams ee WHERE ee.exam_subject_id=es.id)
        LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('ss', $subject_code, $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc() ?: false;
    $stmt->close();
    return $user;
}

function authenticateStudentWithPassword(string $email, string $password) {
    $mysqli = getDBConnection();
    if (!$mysqli) return false;
    $stmt = $mysqli->prepare("SELECT id, email, full_name, role, password FROM users WHERE email = ? AND role = 'student' LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user || !password_verify($password, $user['password'])) return false;
    unset($user['password']);
    return $user;
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
                    
                    echo '<span class="login-school-name">' . htmlspecialchars(getSchoolName()) . '</span>';
                    ?>
                </h2>
                <p class="mb-0 mt-2 text-white-50"><?php echo $student_exam_access_mode ? 'Enter the code for the published subject exam' : 'Sign in to access your account'; ?></p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <ul class="nav nav-tabs" id="loginTabs" role="tablist">
                    <?php if (!$student_exam_access_mode): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="staff-tab" data-bs-toggle="tab" data-bs-target="#staff" type="button" role="tab">
                            <i class="fas fa-user-tie me-1"></i> Staff Login
                        </button>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $student_exam_access_mode ? 'active' : ''; ?>" id="student-tab" data-bs-toggle="tab" data-bs-target="#student" type="button" role="tab">
                            <i class="fas fa-user-graduate me-1"></i> Student Login
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content mt-4" id="loginTabsContent">
                    <!-- Staff Login Form -->
                    <?php if (!$student_exam_access_mode): ?>
                    <div class="tab-pane fade show active" id="staff" role="tabpanel">
                        <form method="POST" autocomplete="off">
                            <input type="hidden" name="is_student" value="0">
                            <div class="mb-3">
                                <label for="staff_email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="staff_email" name="email" placeholder="Enter your email" required autocomplete="off" readonly>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="staff_password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="staff_password" name="password" placeholder="Enter your password" required autocomplete="off" readonly>
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
                    <?php endif; ?>
                    
                    <!-- Student Login Form -->
                    <div class="tab-pane fade <?php echo $student_exam_access_mode ? 'show active' : ''; ?>" id="student" role="tabpanel">
                        <form method="POST" autocomplete="off">
                            <input type="hidden" name="is_student" value="1">
                            <div class="mb-3">
                                <label for="student_email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="student_email" name="email" placeholder="Enter your email" required autocomplete="off" readonly>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="subject_code" class="form-label">Subject Code (exam access only)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-book"></i></span>
                                    <input type="text" class="form-control" id="subject_code" name="subject_code" placeholder="Optional: enter subject code" autocomplete="off" readonly>
                                </div>
                                <div class="form-text"><?php echo $student_exam_access_mode ? 'This opens only the published exam assigned to your class for this subject.' : 'Use a subject code to access its published exam. Leave it blank and enter your admin-issued password to open your dashboard.'; ?></div>
                            </div>
                            <?php if (!$student_exam_access_mode): ?>
                            <div class="mb-3">
                                <label for="student_password" class="form-label">Admin-issued Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="student_password" name="password" placeholder="Enter password for dashboard access" autocomplete="off" readonly>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-sign-in-alt me-1"></i> Sign In as Student
                                </button>
                            </div>
                            <?php if ($student_exam_access_mode): ?>
                            <div class="text-center mt-3"><a href="student/dashboard.php">Return to dashboard</a></div>
                            <?php endif; ?>
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

// Browsers may restore previously entered credentials when returning to this page.
window.addEventListener('pageshow', function () {
    ['staff_email', 'staff_password', 'student_email', 'subject_code', 'student_password'].forEach(function (id) {
        const field = document.getElementById(id);
        if (field) {
            field.value = '';
            field.readOnly = true;
            field.addEventListener('focus', function unlockField() {
                field.readOnly = false;
                field.removeEventListener('focus', unlockField);
            }, { once: true });
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
