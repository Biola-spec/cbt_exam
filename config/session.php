<?php
// Start session only if not already started
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Simple file-based user system
$users_file = __DIR__ . '/../users.json';

// Simple in-memory cache for frequently accessed data
static $data_cache = [];
static $cache_expiry = [];

// Function to create default users
function createDefaultUsers() {
    global $users_file;
    
    $users = [
        'admin@cbtexam.com' => [
            'password' => password_hash('Admin@2024', PASSWORD_DEFAULT),
            'full_name' => 'System Administrator',
            'role' => 'admin'
        ],
        'teacher@cbtexam.com' => [
            'password' => password_hash('Teacher@2024', PASSWORD_DEFAULT),
            'full_name' => 'Teacher User',
            'role' => 'teacher'
        ],
        'student@cbtexam.com' => [
            'password' => password_hash('Student@2024', PASSWORD_DEFAULT),
            'full_name' => 'Student User',
            'role' => 'student'
        ]
    ];
    
    file_put_contents($users_file, json_encode($users, JSON_PRETTY_PRINT));
    return $users;
}

// Create default users file if it doesn't exist
if (!file_exists($users_file)) {
    createDefaultUsers();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Get current user role
function getUserRole() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

// Get current user name
function getUserName() {
    return isset($_SESSION['full_name']) ? $_SESSION['full_name'] : null;
}

// Get current user ID
function getUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

// Get school name with caching
function getSchoolName() {
    global $data_cache, $cache_expiry;
    
    if (isset($_SESSION['school_name'])) {
        return $_SESSION['school_name'];
    }
    
    // Check cache first
    $cache_key = 'school_name';
    $current_time = time();
    
    if (isset($data_cache[$cache_key]) && isset($cache_expiry[$cache_key]) && $current_time < $cache_expiry[$cache_key]) {
        return $data_cache[$cache_key];
    }
    
    // Try to get school name from database
    require_once __DIR__ . '/database.php';
    $mysqli = getDBConnection();
    
    if ($mysqli) {
        try {
            // Ensure connection is still alive
            try {
                if (!$mysqli->ping()) {
                    // Try to reconnect
                    $mysqli = getDBConnection();
                }
            } catch (mysqli_sql_exception $e) {
                // Try to reconnect
                $mysqli = getDBConnection();
            }
            
            if ($mysqli) {
                $stmt = $mysqli->prepare("SELECT school_name FROM school_config LIMIT 1");
                if ($stmt) {
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        // Cache the result for 5 minutes
                        $data_cache[$cache_key] = $row['school_name'];
                        $cache_expiry[$cache_key] = $current_time + 300; // 5 minutes
                        return $row['school_name'];
                    }
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            // Log error but don't crash
            error_log("Error getting school name: " . $e->getMessage());
        }
    }
    
    // Fallback to default
    return 'CBT Exam System';
}

// Get school logo with caching
function getSchoolLogo() {
    global $data_cache, $cache_expiry;
    
    if (isset($_SESSION['school_logo'])) {
        return $_SESSION['school_logo'];
    }
    
    // Check cache first
    $cache_key = 'school_logo';
    $current_time = time();
    
    if (isset($data_cache[$cache_key]) && isset($cache_expiry[$cache_key]) && $current_time < $cache_expiry[$cache_key]) {
        return $data_cache[$cache_key];
    }
    
    // Try to get school logo from database
    require_once __DIR__ . '/database.php';
    $mysqli = getDBConnection();
    
    if ($mysqli) {
        try {
            // Ensure connection is still alive
            try {
                if (!$mysqli->ping()) {
                    // Try to reconnect
                    $mysqli = getDBConnection();
                }
            } catch (mysqli_sql_exception $e) {
                // Try to reconnect
                $mysqli = getDBConnection();
            }
            
            if ($mysqli) {
                $stmt = $mysqli->prepare("SELECT logo_path FROM school_config LIMIT 1");
                if ($stmt) {
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        // Cache the result for 5 minutes
                        $data_cache[$cache_key] = $row['logo_path'];
                        $cache_expiry[$cache_key] = $current_time + 300; // 5 minutes
                        return $row['logo_path'];
                    }
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            // Log error but don't crash
            error_log("Error getting school logo: " . $e->getMessage());
        }
    }
    
    // Fallback to null
    return null;
}

// Redirect to login if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Redirect based on role
function redirectBasedOnRole() {
    if (isLoggedIn()) {
        $role = getUserRole();
        switch ($role) {
            case 'admin':
                header('Location: admin/dashboard.php');
                break;
            case 'teacher':
                header('Location: teacher/dashboard.php');
                break;
            case 'student':
                header('Location: student/dashboard.php');
                break;
            default:
                header('Location: login.php');
        }
        exit();
    }
}

// Save or update user in users.json
function saveUserToJson($email, $password_hash, $full_name, $role) {
    global $users_file;
    $users = [];
    if (file_exists($users_file)) {
        $users = json_decode(file_get_contents($users_file), true);
    }
    $users[$email] = [
        'password' => $password_hash,
        'full_name' => $full_name,
        'role' => $role
    ];
    return file_put_contents($users_file, json_encode($users, JSON_PRETTY_PRINT)) !== false;
}

// Logout function
function logout() {
    // If user is a student, mark any active exam attempts as submitted
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'student' && isset($_SESSION['user_id'])) {
        require_once __DIR__ . '/database.php';
        $mysqli = getDBConnection();
        if ($mysqli) {
            try {
                // Find any unsubmitted attempts for this student
                $student_id = $_SESSION['user_id'];
                
                // We should also calculate the score before marking as submitted, 
                // but for a simple "block resume" policy, marking submitted_at is enough.
                // The score can be 0 or calculated based on existing answers.
                // For now, let's just mark them as submitted to fulfill "can't take the exam except admin make them retake"
                $stmt = $mysqli->prepare("UPDATE exam_attempts SET submitted_at = NOW() WHERE student_id = ? AND submitted_at IS NULL");
                if ($stmt) {
                    $stmt->bind_param("i", $student_id);
                    $stmt->execute();
                    $stmt->close();
                }
            } catch (Exception $e) {
                // Log error silently
                error_log("Error marking active exams on logout: " . $e->getMessage());
            }
        }
    }

    // Unset all session variables
    $_SESSION = array();
    
    // Delete session cookie if it exists
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login
    header('Location: login.php');
    exit();
}

// Simple in-memory cache for user data
static $users_cache = null;
static $users_cache_time = 0;
static $users_cache_expiry = 300; // 5 minutes

// Authenticate user (file-based as workaround) with caching
function authenticateUser($email, $password) {
    global $users_file, $users_cache, $users_cache_time, $users_cache_expiry;
    
    // Check if we have a cached version of users data
    $current_time = time();
    if ($users_cache === null || ($current_time - $users_cache_time) > $users_cache_expiry) {
        // Ensure users file exists with all default users
        if (!file_exists($users_file)) {
            createDefaultUsers();
        }
        
        if (file_exists($users_file)) {
            $users_cache = json_decode(file_get_contents($users_file), true);
            $users_cache_time = $current_time;
        } else {
            $users_cache = [];
        }
    }
    
    $users = $users_cache;
    
    if (isset($users[$email]) && password_verify($password, $users[$email]['password'])) {
        // Get the actual database user ID
        require_once __DIR__ . '/database.php';
        $mysqli = getDBConnection();
        
        if ($mysqli) {
            try {
                // Ensure connection is still alive
                try {
                    if (!$mysqli->ping()) {
                        // Try to reconnect
                        $mysqli = getDBConnection();
                    }
                } catch (mysqli_sql_exception $e) {
                    // Try to reconnect
                    $mysqli = getDBConnection();
                }
                
                if ($mysqli) {
                    $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($row = $result->fetch_assoc()) {
                        // Use the actual database user ID
                        $user_id = $row['id'];
                    } else {
                        // If user not found in database, fallback to MD5 (for backward compatibility)
                        $user_id = md5($email);
                    }
                    $stmt->close();
                } else {
                    // If database connection fails, fallback to MD5
                    $user_id = md5($email);
                }
            } catch (Exception $e) {
                // Log error but don't crash, fallback to MD5
                error_log("Error authenticating user: " . $e->getMessage());
                $user_id = md5($email);
            }
        } else {
            // If database connection fails, fallback to MD5
            $user_id = md5($email);
        }
        
        return [
            'id' => $user_id, // Use actual database ID instead of MD5
            'email' => $email,
            'full_name' => $users[$email]['full_name'],
            'role' => $users[$email]['role']
        ];
    }
    
    return false;
}
?>