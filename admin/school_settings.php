<?php
require_once '../config/database.php';
require_once '../config/session.php';

$message = '';
$school_config = null;

try {
    $mysqli = getDBConnection();
    if ($mysqli) {
        $stmt = $mysqli->prepare("SELECT id, school_name, school_address, school_phone, default_time_limit, logo_path, head_signature_path, principal_signature_path, school_stamp_path, updated_at, updated_by FROM school_config ORDER BY id DESC LIMIT 1");
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_name = trim($_POST['school_name'] ?? '');
    $school_address = trim($_POST['school_address'] ?? '');
    $school_phone = trim($_POST['school_phone'] ?? '');
    $default_time_limit = max(1, min(300, (int)($_POST['default_time_limit'] ?? 60)));
    $assets = [
        'logo' => $school_config['logo_path'] ?? null,
        'head_signature' => $school_config['head_signature_path'] ?? null,
        'principal_signature' => $school_config['principal_signature_path'] ?? null,
        'school_stamp' => $school_config['school_stamp_path'] ?? null,
    ];
    $uploadError = '';
    foreach (['logo' => 'school_logo', 'head_signature' => 'head_teacher_signature', 'principal_signature' => 'principal_signature', 'school_stamp' => 'school_stamp'] as $field => $prefix) {
        if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) continue;
        $file = $_FILES[$field];
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) {
            $uploadError = 'Each image must upload successfully and be no larger than 5 MB.';
            break;
        }
        $imageInfo = @getimagesize($file['tmp_name']);
        $mime = $imageInfo['mime'] ?? '';
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) {
            $uploadError = 'Logo, signature, and stamp must be JPG, PNG, GIF, or WebP images.';
            break;
        }
        $uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            $uploadError = 'The image upload folder could not be created.';
            break;
        }
        $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($file['tmp_name'], $uploadDirectory . DIRECTORY_SEPARATOR . $filename)) {
            $uploadError = 'An image could not be saved. Please try again.';
            break;
        }
        $assets[$field] = '../uploads/' . $filename;
    }

    if ($school_name === '') {
        $message = 'School name is required.';
    } elseif ($uploadError !== '') {
        $message = $uploadError;
    } else {
        try {
            $mysqli = getDBConnection();
            if (!$mysqli) throw new RuntimeException('Database connection failed.');
            $user_id = (int)(getUserId() ?: 0);
            if ($school_config) {
                $stmt = $mysqli->prepare('UPDATE school_config SET school_name=?, school_address=?, school_phone=?, default_time_limit=?, logo_path=?, head_signature_path=?, principal_signature_path=?, school_stamp_path=?, updated_by=? WHERE id=?');
                $config_id = (int)$school_config['id'];
                $stmt->bind_param('sssissssii', $school_name, $school_address, $school_phone, $default_time_limit, $assets['logo'], $assets['head_signature'], $assets['principal_signature'], $assets['school_stamp'], $user_id, $config_id);
            } else {
                $stmt = $mysqli->prepare('INSERT INTO school_config (school_name, school_address, school_phone, default_time_limit, logo_path, head_signature_path, principal_signature_path, school_stamp_path, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('sssissssi', $school_name, $school_address, $school_phone, $default_time_limit, $assets['logo'], $assets['head_signature'], $assets['principal_signature'], $assets['school_stamp'], $user_id);
            }
            $stmt->execute();
            $stmt->close();
            $_SESSION['school_name'] = $school_name;
            $message = 'School settings updated successfully.';
            $stmt = $mysqli->prepare('SELECT id, school_name, school_address, school_phone, default_time_limit, logo_path, head_signature_path, principal_signature_path, school_stamp_path, updated_at, updated_by FROM school_config ORDER BY id DESC LIMIT 1');
            $stmt->execute();
            $school_config = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } catch (Throwable $e) {
            $message = 'Error updating school settings: ' . $e->getMessage();
        }
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

                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="school_address" class="form-label">School Address</label>
                        <textarea class="form-control" id="school_address" name="school_address" rows="2" maxlength="500" placeholder="Street, town/city, state"><?php echo htmlspecialchars($school_config['school_address'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="school_phone" class="form-label">School Phone Details</label>
                        <input type="text" class="form-control" id="school_phone" name="school_phone" maxlength="150" value="<?php echo htmlspecialchars($school_config['school_phone'] ?? ''); ?>" placeholder="Phone numbers">
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
                <?php foreach (['head_signature' => ['Head Teacher Signature', 'head_signature_path'], 'principal_signature' => ['Principal Signature', 'principal_signature_path'], 'school_stamp' => ['School Stamp', 'school_stamp_path']] as $field => [$label, $configKey]): ?>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="<?php echo $field; ?>" class="form-label"><?php echo htmlspecialchars($label); ?></label>
                            <div class="d-flex align-items-center gap-3">
                                <?php if (!empty($school_config[$configKey])): ?>
                                    <img src="<?php echo htmlspecialchars($school_config[$configKey]); ?>" alt="<?php echo htmlspecialchars($label); ?> preview" class="img-thumbnail" style="max-width:130px;max-height:72px;object-fit:contain">
                                <?php endif; ?>
                                <input type="file" class="form-control" id="<?php echo $field; ?>" name="<?php echo $field; ?>" accept=".jpg,.jpeg,.png,.gif,.webp">
                            </div>
                            <div class="form-text">Optional image, up to 5 MB. The latest upload appears on report cards.</div>
                        </div>
                    </div>
                <?php endforeach; ?>
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
