<?php
require_once '../config/database.php';
require_once '../config/session.php';

$message = '';
$school_config = null;

try {
    $mysqli = getDBConnection();
    if ($mysqli) {
        // Updated to include default_time_limit and logo_path
        $stmt = $mysqli->prepare("SELECT id, school_name, default_time_limit, logo_path, updated_at, updated_by FROM school_config LIMIT 1");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                $row = $result->fetch_assoc();
                if ($row) {
                    $school_config = $row;
                }
            }
            $stmt->close();
        }
    }
} catch (Exception $e) {
    $message = 'Error loading school configuration: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $school_name = trim($_POST['school_name']);
    $default_time_limit = isset($_POST['default_time_limit']) ? (int)$_POST['default_time_limit'] : 60;
    
    // Handle logo upload
    $logo_path = $school_config['logo_path'] ?? null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = 'school_logo_' . time() . '.' . $file_extension;
            $target_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_path)) {
                // Delete old logo if exists
                if ($logo_path && file_exists($logo_path)) {
                    unlink($logo_path);
                }
                $logo_path = $target_path;
            } else {
                $message = 'Error uploading logo file.';
            }
        } else {
            $message = 'Invalid file type. Only JPG, PNG, GIF, and WebP files are allowed.';
        }
    }
    
    if (!empty($school_name)) {
        try {
            $mysqli = getDBConnection();
            if ($mysqli) {
                if ($school_config) {
                    // Update existing config with default_time_limit and logo_path
                    $stmt = $mysqli->prepare("UPDATE school_config SET school_name = ?, default_time_limit = ?, logo_path = ?, updated_by = ? WHERE id = ?");
                    if ($stmt) {
                        $config_id = $school_config['id'];
                        $user_id = getUserId() ?: 0; // Use 0 as default if user ID is null
                        $stmt->bind_param("sisis", $school_name, $default_time_limit, $logo_path, $user_id, $config_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                } else {
                    // Insert new config with default_time_limit and logo_path
                    $stmt = $mysqli->prepare("INSERT INTO school_config (school_name, default_time_limit, logo_path, updated_by) VALUES (?, ?, ?, ?)");
                    if ($stmt) {
                        $user_id = getUserId() ?: 0; // Use 0 as default if user ID is null
                        $stmt->bind_param("siss", $school_name, $default_time_limit, $logo_path, $user_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                
                $message = 'School settings updated successfully';
                
                // Refresh config
                $stmt = $mysqli->prepare("SELECT id, school_name, default_time_limit, logo_path, updated_at, updated_by FROM school_config LIMIT 1");
                if ($stmt) {
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result) {
                        $row = $result->fetch_assoc();
                        if ($row) {
                            $school_config = $row;
                            // Update session with new school name
                            $_SESSION['school_name'] = $school_name;
                        }
                    }
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            $message = 'Error updating school settings: ' . $e->getMessage();
        }
    } else {
        $message = 'School name is required';
    }
}
?>


<h5>School Configuration</h5>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-university me-2"></i>School Configuration</h5>
    </div>
    <div class="card-body">
        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="school_name" class="form-label">School Name</label>
                        <input type="text" class="form-control" id="school_name" name="school_name" 
                               value="<?php echo $school_config && isset($school_config['school_name']) ? htmlspecialchars($school_config['school_name']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="default_time_limit" class="form-label">Default Exam Duration (Minutes)</label>
                        <input type="number" class="form-control" id="default_time_limit" name="default_time_limit" 
                               value="<?php echo $school_config && isset($school_config['default_time_limit']) ? htmlspecialchars($school_config['default_time_limit']) : '60'; ?>" 
                               min="1" max="300" required>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="mb-3">
                        <label for="logo" class="form-label">School Logo</label>
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <?php if (!empty($school_config['logo_path']) && file_exists($school_config['logo_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($school_config['logo_path']); ?>" alt="Logo" class="img-thumbnail" style="max-height: 80px;">
                                <?php else: ?>
                                    <div class="bg-light d-flex align-items-center justify-content-center border" style="height: 80px; width: 80px;">
                                        <i class="fas fa-image text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col">
                                <input type="file" class="form-control" id="logo" name="logo" accept=".jpg,.jpeg,.png,.gif,.webp">
                                <div class="form-text small">Recommended: Square PNG/JPG file.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Update Configuration
                </button>
            </div>
        </form>
    </div>
</div>
<?php include '../includes/footer.php'; ?>