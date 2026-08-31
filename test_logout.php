<?php
require_once 'config/session.php';

// Start HTML
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Test Logout</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel='stylesheet'>
</head>
<body>
    <div class='container mt-5'>
        <div class='row justify-content-center'>
            <div class='col-md-6'>
                <div class='card shadow'>
                    <div class='card-header bg-primary text-white'>
                        <h4><i class='fas fa-sign-out-alt me-2'></i>Test Logout Functionality</h4>
                    </div>
                    <div class='card-body'>";

// Check if user is logged in
if (isLoggedIn()) {
    echo "<h5>Current Session Information</h5>";
    echo "<p><strong>User ID:</strong> " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not set') . "</p>";
    echo "<p><strong>User Name:</strong> " . (isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Not set') . "</p>";
    echo "<p><strong>User Role:</strong> " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'Not set') . "</p>";
    echo "<div class='d-grid gap-2'>";
    echo "<a href='logout.php' class='btn btn-danger btn-lg'><i class='fas fa-sign-out-alt me-1'></i> Logout</a>";
    echo "</div>";
} else {
    echo "<h5>No Active Session</h5>";
    echo "<p>You are not currently logged in.</p>";
    echo "<div class='d-grid gap-2'>";
    echo "<a href='login.php' class='btn btn-primary btn-lg'><i class='fas fa-sign-in-alt me-1'></i> Login</a>";
    echo "</div>";
}

echo "                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>";
?>