<?php
require_once '../config/session.php';

// Check if user is logged in and is admin
requireLogin();
if (getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$results = null;

// Run sync when requested
if (isset($_GET['action']) && $_GET['action'] == 'sync') {
    try {
        // Include the sync functionality
        $included_from_admin = true;
        include '../sync_users_to_db.php';
        $results = $sync_results ?? null;
        $message = 'Sync completed successfully!';
    } catch (Exception $e) {
        $message = 'Error during sync: ' . $e->getMessage();
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-sync-alt me-2"></i>Sync Users to Database</h5>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $results ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($results): ?>
                    <div class="alert alert-info">
                        <h6>Sync Results:</h6>
                        <ul>
                            <li>Total users processed: <strong><?php echo $results['total_synced']; ?></strong></li>
                            <li>Users added: <strong><?php echo $results['added']; ?></strong></li>
                            <li>Users updated: <strong><?php echo $results['updated']; ?></strong></li>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Why Sync Users?</h6>
                    <p>The CBT Exam System uses both file-based and database storage:</p>
                    <ul>
                        <li><strong>File-based</strong>: Used for authentication (users.json)</li>
                        <li><strong>Database</strong>: Used for administrative features</li>
                    </ul>
                    <p>When you add new users through the admin interface, they're added to both systems automatically. However, if you manually edit the users.json file, you need to sync to update the database.</p>
                </div>
                
                <div class="d-grid gap-2">
                    <a href="sync_users.php?action=sync" class="btn btn-primary btn-lg">
                        <i class="fas fa-sync-alt me-1"></i> Sync Users Now
                    </a>
                </div>
                
                <div class="mt-4">
                    <h6>Current Status:</h6>
                    <?php
                    // Show current status
                    $users_file = '../users.json';
                    if (file_exists($users_file)) {
                        $users = json_decode(file_get_contents($users_file), true);
                        $file_count = count($users);
                        
                        // Database count
                        require_once '../config/database.php';
                        $mysqli = getDBConnection();
                        if ($mysqli) {
                            $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM users");
                            if ($stmt) {
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $row = $result->fetch_assoc();
                                $db_count = $row['count'];
                                $stmt->close();
                            } else {
                                $db_count = 'Error';
                            }
                        } else {
                            $db_count = 'Connection failed';
                        }
                        
                        echo "<p>Users in users.json: <strong>" . $file_count . "</strong></p>";
                        echo "<p>Users in database: <strong>" . $db_count . "</strong></p>";
                        
                        if ($file_count == $db_count) {
                            echo "<p class='text-success'><i class='fas fa-check-circle me-1'></i> Systems are synchronized</p>";
                        } else {
                            echo "<p class='text-warning'><i class='fas fa-exclamation-triangle me-1'></i> Systems are NOT synchronized - please sync now</p>";
                        }
                    } else {
                        echo "<p class='text-danger'>users.json file not found</p>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>