<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(getSchoolName()); ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Source+Sans+3:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- CKEditor Math Plugin CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
    
    <?php
    // Determine the base URL dynamically to ensure assets load properly from subdirectories
    $base_url = '';
    $current_dir = dirname($_SERVER['PHP_SELF']);
    if (strpos($current_dir, '/admin') !== false || strpos($current_dir, '/teacher') !== false || strpos($current_dir, '/student') !== false) {
        $base_url = '../';
    }
    ?>
    <link href="<?php echo $base_url; ?>assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>" rel="stylesheet">
</head>
<body class="<?php echo isset($body_class) ? htmlspecialchars($body_class) : ''; ?>">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?php echo $base_url; ?>index.php">
                <?php 
                $school_logo = getSchoolLogo();
                if (!empty($school_logo)) {
                    $logo_src = $school_logo;
                    $current_dir = dirname($_SERVER['PHP_SELF']);
                    if (strpos($current_dir, '/admin') === false && strpos($current_dir, '/teacher') === false && strpos($current_dir, '/student') === false) {
                        if (strpos($logo_src, '../') === 0) $logo_src = substr($logo_src, 3);
                    }
                    if (file_exists($logo_src)) {
                        echo '<img src="' . htmlspecialchars($logo_src) . '" alt="Logo" style="height: 30px; margin-right: 10px;">';
                    }
                }
                echo '<span>' . htmlspecialchars(getSchoolName()) . '</span>';
                ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isLoggedIn()): ?>
                        <?php if (getUserRole() === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo $base_url; ?>admin/dashboard.php">Admin Dashboard</a>
                            </li>
                        <?php elseif (getUserRole() === 'teacher'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo $base_url; ?>teacher/dashboard.php">Teacher Dashboard</a>
                            </li>
                        <?php elseif (getUserRole() === 'student'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo $base_url; ?>student/dashboard.php">Student Dashboard</a>
                            </li>
                        <?php endif; ?>
                        
                        <li class="nav-item d-lg-none">
                            <span class="nav-link text-light border-bottom pb-2 mb-2"><i class="fas fa-user me-2"></i><?php echo htmlspecialchars(getUserName()); ?></span>
                        </li>
                        <li class="nav-item">
                            <span class="nav-link text-light border-start ms-2 ps-3 d-none d-lg-inline"><?php echo htmlspecialchars(getUserName()); ?></span>
                        </li>
                        <li class="nav-item">
                            <form method="POST" action="<?php echo $base_url; ?>logout.php" class="d-block d-lg-inline">
                                <button type="submit" class="btn btn-link nav-link text-danger border-0 w-100 text-start text-lg-center" style="text-decoration: none;">
                                    <i class="fas fa-sign-out-alt me-2 d-lg-none"></i>Logout
                                </button>
                            </form>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_url; ?>login.php">Login</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
