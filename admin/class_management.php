<?php
require_once '../config/database.php';
require_once '../config/session.php';

$message = '';
$classes = [];
$search_term = '';
$editing_class = null;

// Handle search
if (isset($_GET['search'])) {
    $search_term = trim($_GET['search']);
}

// Check if we're editing a class
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    try {
        $mysqli = getDBConnection();
        $stmt = $mysqli->prepare("SELECT id, name, description, marks_per_question FROM classes WHERE id = ?");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $editing_class = $result->fetch_assoc();
    } catch (Exception $e) {
        $message = 'Error loading class for editing: ' . $e->getMessage();
    }
}

// Load classes with search filter
try {
    $mysqli = getDBConnection();
    
    if (!empty($search_term)) {
        $stmt = $mysqli->prepare("SELECT id, name, description, marks_per_question, created_at FROM classes WHERE name LIKE ? OR description LIKE ? ORDER BY name");
        $search_param = "%$search_term%";
        $stmt->bind_param("ss", $search_param, $search_param);
    } else {
        $stmt = $mysqli->prepare("SELECT id, name, description, marks_per_question, created_at FROM classes ORDER BY name");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
} catch (Exception $e) {
    $message = 'Error loading classes: ' . $e->getMessage();
}

// Handle form submission for adding new class
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $marks_per_question = isset($_POST['marks_per_question']) ? floatval($_POST['marks_per_question']) : 1.0;
    
    if (!empty($name)) {
        try {
            // Check if class already exists
            $mysqli = getDBConnection();
            $stmt = $mysqli->prepare("SELECT id FROM classes WHERE name = ?");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $message = 'A class with this name already exists';
            } else {
                $stmt = $mysqli->prepare("INSERT INTO classes (name, description, marks_per_question) VALUES (?, ?, ?)");
                $stmt->bind_param("ssd", $name, $description, $marks_per_question);
                $stmt->execute();
                $message = 'Class added successfully';
                
                // Refresh classes list
                if (!empty($search_term)) {
                    $stmt = $mysqli->prepare("SELECT id, name, description, marks_per_question, created_at FROM classes WHERE name LIKE ? OR description LIKE ? ORDER BY name");
                    $search_param = "%$search_term%";
                    $stmt->bind_param("ss", $search_param, $search_param);
                } else {
                    $stmt = $mysqli->prepare("SELECT id, name, description, marks_per_question, created_at FROM classes ORDER BY name");
                }
                $stmt->execute();
                $result = $stmt->get_result();
                $classes = [];
                while ($row = $result->fetch_assoc()) {
                    $classes[] = $row;
                }
            }
        } catch (Exception $e) {
            $message = 'Error adding class: ' . $e->getMessage();
        }
    } else {
        $message = 'Class name is required';
    }
}

// Handle form submission for editing class
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $class_id = (int)$_POST['class_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $marks_per_question = isset($_POST['marks_per_question']) ? floatval($_POST['marks_per_question']) : 1.0;
    
    if (!empty($name)) {
        try {
            $mysqli = getDBConnection();
            
            // Check if another class already has this name
            $stmt = $mysqli->prepare("SELECT id FROM classes WHERE name = ? AND id != ?");
            $stmt->bind_param("si", $name, $class_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $message = 'Another class with this name already exists';
            } else {
                $stmt = $mysqli->prepare("UPDATE classes SET name = ?, description = ?, marks_per_question = ? WHERE id = ?");
                $stmt->bind_param("ssdi", $name, $description, $marks_per_question, $class_id);
                $stmt->execute();
                $message = 'Class updated successfully';
                
                // Clear editing mode
                $editing_class = null;
                
                // Refresh classes list
                if (!empty($search_term)) {
                    $stmt = $mysqli->prepare("SELECT id, name, description, marks_per_question, created_at FROM classes WHERE name LIKE ? OR description LIKE ? ORDER BY name");
                    $search_param = "%$search_term%";
                    $stmt->bind_param("ss", $search_param, $search_param);
                } else {
                    $stmt = $mysqli->prepare("SELECT id, name, description, marks_per_question, created_at FROM classes ORDER BY name");
                }
                $stmt->execute();
                $result = $stmt->get_result();
                $classes = [];
                while ($row = $result->fetch_assoc()) {
                    $classes[] = $row;
                }
            }
        } catch (Exception $e) {
            $message = 'Error updating class: ' . $e->getMessage();
        }
    } else {
        $message = 'Class name is required';
    }
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $class_id = $_POST['class_id'];
    
    try {
        $mysqli = getDBConnection();
        $stmt = $mysqli->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $message = 'Class deleted successfully';
        
        // Refresh classes list
        if (!empty($search_term)) {
            $stmt = $mysqli->prepare("SELECT id, name, description, marks_per_question, created_at FROM classes WHERE name LIKE ? OR description LIKE ? ORDER BY name");
            $search_param = "%$search_term%";
            $stmt->bind_param("ss", $search_param, $search_param);
        } else {
            $stmt = $mysqli->prepare("SELECT id, name, description, marks_per_question, created_at FROM classes ORDER BY name");
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $classes = [];
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
        
        // Clear editing mode if we were editing the deleted class
        if ($editing_class && $editing_class['id'] == $class_id) {
            $editing_class = null;
        }
    } catch (Exception $e) {
        $message = 'Error deleting class. It might be referenced by other records.';
    }
}
?>

<h5><i class="fas fa-book me-2"></i>Class Management</h5>

<?php if ($message): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Search Form -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="d-flex">
            <input type="hidden" name="tab" value="classes">
            <div class="input-group">
                <input type="text" class="form-control" name="search" placeholder="Search classes..." value="<?php echo htmlspecialchars($search_term); ?>">
                <button class="btn btn-outline-secondary" type="submit">
                    <i class="fas fa-search"></i>
                </button>
                <?php if (!empty($search_term)): ?>
                    <a href="dashboard.php?tab=classes" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Add/Edit Class Form -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="fas fa-<?php echo $editing_class ? 'edit' : 'plus-circle'; ?> me-2"></i>
            <?php echo $editing_class ? 'Edit Class' : 'Add New Class'; ?>
        </h6>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="<?php echo $editing_class ? 'edit' : 'add'; ?>">
            <?php if ($editing_class): ?>
                <input type="hidden" name="class_id" value="<?php echo $editing_class['id']; ?>">
            <?php endif; ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="name" class="form-label">Class Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo $editing_class ? htmlspecialchars($editing_class['name']) : ''; ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="marks_per_question" class="form-label">Marks Per Question</label>
                        <input type="number" class="form-control" id="marks_per_question" name="marks_per_question" step="0.01" min="0.01" value="<?php echo $editing_class ? htmlspecialchars($editing_class['marks_per_question']) : '1.00'; ?>" required>
                        <div class="form-text">Enter the marks awarded for each correct answer (e.g., 1.00 or 0.50)</div>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <input type="text" class="form-control" id="description" name="description" value="<?php echo $editing_class ? htmlspecialchars($editing_class['description'] ?? '') : ''; ?>">
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <?php echo $editing_class ? 'Update Class' : 'Add Class'; ?>
            </button>
            <?php if ($editing_class): ?>
                <a href="dashboard.php?tab=classes" class="btn btn-secondary">
                    Cancel
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Classes List -->
<div class="card">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-list me-2"></i>Existing Classes (<?php echo count($classes); ?>)</h6>
    </div>
    <div class="card-body">
        <?php if (count($classes) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Class Name</th>
                            <th>Description</th>
                            <th>Marks/Question</th>
                            <th>Created At</th>
                            <th width="150" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes as $index => $class): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($class['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($class['description'] ?? ''); ?></td>
                                <td><span class="badge bg-secondary"><?php echo number_format($class['marks_per_question'], 2); ?></span></td>
                                <td><?php echo date('M j, Y', strtotime($class['created_at'])); ?></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="dashboard.php?tab=classes&edit=<?php echo $class['id']; ?>" class="btn btn-info text-white">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this class? This action cannot be undone.')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                            <button type="submit" class="btn btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                <p class="text-muted small">
                    <?php echo !empty($search_term) ? 'No classes match your search' : 'No classes found. Add a new class to get started.'; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>