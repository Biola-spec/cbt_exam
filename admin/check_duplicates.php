<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Only allow admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$message = '';
$duplicates = [];
$all_allocations = [];

try {
    $mysqli = getDBConnection();
    
    // Check for duplicate allocations
    $stmt = $mysqli->prepare("SELECT teacher_id, class_id, subject_id, COUNT(*) as count 
                              FROM teacher_allocations 
                              GROUP BY teacher_id, class_id, subject_id 
                              HAVING COUNT(*) > 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $duplicates[] = $row;
    }
    
    // Show all allocations with explicit column names to avoid ambiguity
    $stmt = $mysqli->prepare("SELECT ta.id, ta.teacher_id, ta.class_id, ta.subject_id, ta.allocated_at, ta.allocated_by,
                              u.full_name as teacher_name, c.name as class_name, s.name as subject_name 
                              FROM teacher_allocations ta
                              JOIN users u ON ta.teacher_id = u.id
                              JOIN classes c ON ta.class_id = c.id
                              JOIN subjects s ON ta.subject_id = s.id
                              ORDER BY u.full_name, c.name, s.name");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $all_allocations[] = $row;
    }
    
    // Handle removal of duplicates
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'remove_duplicates') {
        $removed_count = 0;
        
        foreach ($duplicates as $duplicate) {
            // Keep the first allocation and remove the rest
            $stmt = $mysqli->prepare("SELECT id FROM teacher_allocations 
                                      WHERE teacher_id = ? AND class_id = ? AND subject_id = ? 
                                      ORDER BY id ASC LIMIT 1");
            $stmt->bind_param("iii", $duplicate['teacher_id'], $duplicate['class_id'], $duplicate['subject_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $first_allocation = $result->fetch_assoc();
            
            if ($first_allocation) {
                // Delete all except the first one
                $stmt = $mysqli->prepare("DELETE FROM teacher_allocations 
                                          WHERE teacher_id = ? AND class_id = ? AND subject_id = ? 
                                          AND id != ?");
                $stmt->bind_param("iiii", $duplicate['teacher_id'], $duplicate['class_id'], $duplicate['subject_id'], $first_allocation['id']);
                $stmt->execute();
                $removed_count += $stmt->affected_rows;
            }
        }
        
        $message = "Removed $removed_count duplicate allocations. Page will refresh in 3 seconds.";
        
        // Refresh the data
        header("Refresh: 3; url=check_duplicates.php");
    }
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Duplicates - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php?tab=school">
                                <i class="fas fa-cog me-2"></i>School Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php?tab=classes">
                                <i class="fas fa-book me-2"></i>Classes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php?tab=subjects">
                                <i class="fas fa-book-open me-2"></i>Subject Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="teacher_allocation.php">
                                <i class="fas fa-chalkboard-teacher me-2"></i>Teacher Allocation
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="exam_management.php">
                                <i class="fas fa-file-alt me-2"></i>Exam Management
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="check_duplicates.php">
                                <i class="fas fa-bug me-2"></i>Check Duplicates
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-bug me-2"></i>Check Duplicates</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Duplicate Allocations</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($duplicates) > 0): ?>
                                    <p class="text-danger">
                                        <i class="fas fa-exclamation-circle me-1"></i>
                                        Found <?php echo count($duplicates); ?> duplicate allocation(s) in the database.
                                    </p>
                                    
                                    <form method="POST" class="mb-3">
                                        <input type="hidden" name="action" value="remove_duplicates">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to remove duplicate allocations? This will keep only the first allocation and remove the rest.')">
                                            <i class="fas fa-trash-alt me-1"></i>Remove Duplicates
                                        </button>
                                    </form>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Teacher ID</th>
                                                    <th>Class ID</th>
                                                    <th>Subject ID</th>
                                                    <th>Count</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($duplicates as $duplicate): ?>
                                                    <tr>
                                                        <td><?php echo $duplicate['teacher_id']; ?></td>
                                                        <td><?php echo $duplicate['class_id']; ?></td>
                                                        <td><?php echo $duplicate['subject_id']; ?></td>
                                                        <td><?php echo $duplicate['count']; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-success">
                                        <i class="fas fa-check-circle me-1"></i>
                                        No duplicate allocations found in the database.
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Allocations</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($all_allocations) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Teacher</th>
                                                    <th>Class</th>
                                                    <th>Subject</th>
                                                    <th>Allocated At</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($all_allocations as $allocation): ?>
                                                    <tr>
                                                        <td><?php echo $allocation['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($allocation['teacher_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($allocation['class_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($allocation['subject_name']); ?></td>
                                                        <td><?php echo $allocation['allocated_at']; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p>No allocations found.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>