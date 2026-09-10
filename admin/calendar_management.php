<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();
if (getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$mysqli = getDBConnection();
$message = '';
$message_type = 'info';

$action = $_GET['action'] ?? 'list';
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sub_action = $_POST['sub_action'] ?? '';

    if ($sub_action === 'save') {
        $title = trim($_POST['event_title']);
        $description = trim($_POST['event_description'] ?? '');
        $event_date = $_POST['event_date'];
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $event_type = $_POST['event_type'] ?? 'other';
        $event_color = $_POST['event_color'] ?? '#007bff';
        $class_id = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
        $is_public = isset($_POST['is_public']) ? 1 : 0;
        $created_by = getUserId();

        if (empty($title) || empty($event_date)) {
            $message = 'Title and date are required.';
            $message_type = 'danger';
        } else {
            if ($event_id > 0) {
                $stmt = $mysqli->prepare("UPDATE calendar_events SET event_title=?, event_description=?, event_date=?, end_date=?, event_type=?, event_color=?, class_id=?, is_public=? WHERE id=?");
                $stmt->bind_param("ssssssiii", $title, $description, $event_date, $end_date, $event_type, $event_color, $class_id, $is_public, $event_id);
                $ok = $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $mysqli->prepare("INSERT INTO calendar_events (event_title, event_description, event_date, end_date, event_type, event_color, class_id, is_public, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssiii", $title, $description, $event_date, $end_date, $event_type, $event_color, $class_id, $is_public, $created_by);
                $ok = $stmt->execute();
                $stmt->close();
            }
            if ($ok) {
                header('Location: calendar_management.php?tab=events&message=' . urlencode('Event saved successfully.'));
                exit;
            } else {
                $message = 'Error saving event: ' . $mysqli->error;
                $message_type = 'danger';
            }
        }
    } elseif ($sub_action === 'delete') {
        $del_id = (int)($_POST['delete_id'] ?? 0);
        if ($del_id > 0) {
            $stmt = $mysqli->prepare("DELETE FROM calendar_events WHERE id = ?");
            $stmt->bind_param("i", $del_id);
            if ($stmt->execute()) {
                $message = 'Event deleted successfully.';
                $message_type = 'success';
            } else {
                $message = 'Error deleting event: ' . $mysqli->error;
                $message_type = 'danger';
            }
            $stmt->close();
        }
    }
}

$event_to_edit = null;
if ($action === 'edit' && $event_id > 0) {
    $stmt = $mysqli->prepare("SELECT * FROM calendar_events WHERE id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $event_to_edit = $res->fetch_assoc();
    $stmt->close();
}

$classes = [];
$res = $mysqli->query("SELECT id, name FROM classes ORDER BY name");
while ($r = $res->fetch_assoc()) $classes[] = $r;

$all_events = [];
$res = $mysqli->query("SELECT ce.*, c.name as class_name FROM calendar_events ce LEFT JOIN classes c ON ce.class_id = c.id ORDER BY ce.event_date DESC, ce.id DESC");
while ($r = $res->fetch_assoc()) $all_events[] = $r;

$tab = $_GET['tab'] ?? 'events';
?>

<?php include '../includes/header.php'; ?>

<div class="dashboard-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="dashboard-title"><i class="fas fa-calendar-alt me-2"></i> Calendar Management</h1>
                <p class="mb-0">Manage your school calendar events</p>
            </div>
            <div class="col-md-6 text-md-end">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Admin</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Calendar Management</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<div class="container mt-3">
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_GET['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?php echo $tab === 'events' ? 'active' : ''; ?>" href="calendar_management.php?tab=events">
                <i class="fas fa-list me-1"></i> Events
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $tab === 'add' || $tab === 'edit' ? 'active' : ''; ?>" href="calendar_management.php?tab=add">
                <i class="fas fa-plus me-1"></i> <?php echo $event_to_edit ? 'Edit Event' : 'Add Event'; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $tab === 'calendar' ? 'active' : ''; ?>" href="calendar_management.php?tab=calendar">
                <i class="fas fa-calendar-week me-1"></i> Preview Calendar
            </a>
        </li>
    </ul>

    <?php if ($tab === 'events'): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-calendar-check me-2 text-primary"></i> All Events (<?php echo count($all_events); ?>)</h5>
                <a href="calendar_management.php?tab=add" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus me-1"></i> Add Event
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date(s)</th>
                                <th>Type</th>
                                <th>Title</th>
                                <th>Class</th>
                                <th>Visibility</th>
                                <th style="width: 140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($all_events) > 0): ?>
                                <?php foreach ($all_events as $ev):
                                    $type_map = ['exam'=>'Exam','holiday'=>'Holiday','meeting'=>'Meeting','activity'=>'Activity','other'=>'Other'];
                                    $type_badge_map = [
                                        'exam'=>'bg-danger',
                                        'holiday'=>'bg-purple',
                                        'meeting'=>'bg-warning text-dark',
                                        'activity'=>'bg-success',
                                        'other'=>'bg-primary',
                                    ];
                                    $dt = date('M j, Y', strtotime($ev['event_date']));
                                    if (!empty($ev['end_date']) && $ev['end_date'] !== $ev['event_date']) {
                                        $dt .= ' → ' . date('M j, Y', strtotime($ev['end_date']));
                                    }
                                ?>
                                    <tr>
                                        <td class="small fw-semibold"><?php echo $dt; ?></td>
                                        <td>
                                            <span class="badge <?php echo $type_badge_map[$ev['event_type']] ?? 'bg-secondary'; ?>">
                                                <?php echo $type_map[$ev['event_type']] ?? 'Other'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong style="border-left: 3px solid <?php echo htmlspecialchars($ev['event_color']); ?>; padding-left: 8px;">
                                                <?php echo htmlspecialchars($ev['event_title']); ?>
                                            </strong>
                                            <?php if (!empty($ev['event_description'])): ?>
                                                <div class="text-muted small mt-1"><?php echo htmlspecialchars(mb_substr($ev['event_description'], 0, 80)) . (mb_strlen($ev['event_description']) > 80 ? '…' : ''); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small"><?php echo $ev['class_name'] ? htmlspecialchars($ev['class_name']) : '<span class="text-muted">All</span>'; ?></td>
                                        <td>
                                            <?php if ($ev['is_public']): ?>
                                                <span class="badge bg-success"><i class="fas fa-eye me-1"></i> Public</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><i class="fas fa-lock me-1"></i> Staff</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="calendar_management.php?tab=add&action=edit&id=<?php echo $ev['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this event? This cannot be undone.');">
                                                    <input type="hidden" name="sub_action" value="delete">
                                                    <input type="hidden" name="delete_id" value="<?php echo $ev['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fas fa-calendar-day fa-3x mb-3"></i>
                                        <p class="mb-0">No events yet. Click "Add Event" to create your first event.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif ($tab === 'add' || $tab === 'edit'):
        $ev = $event_to_edit ?? [
            'event_title' => '',
            'event_description' => '',
            'event_date' => date('Y-m-d'),
            'end_date' => '',
            'event_type' => 'other',
            'event_color' => '#007bff',
            'class_id' => null,
            'is_public' => 1,
        ];
    ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas <?php echo $event_to_edit ? 'fa-edit' : 'fa-plus-circle'; ?> me-2 text-primary"></i>
                    <?php echo $event_to_edit ? 'Edit Event' : 'Add New Event'; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="calendar_management.php?tab=add<?php echo $event_to_edit ? '&action=edit&id=' . $event_to_edit['id'] : ''; ?>">
                    <input type="hidden" name="sub_action" value="save">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Event Title *</label>
                            <input type="text" class="form-control" name="event_title" required maxlength="255"
                                   value="<?php echo htmlspecialchars($ev['event_title']); ?>" placeholder="e.g. Midterm Exams">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Event Type</label>
                            <select class="form-select" name="event_type" id="event_type_select">
                                <option value="exam" <?php echo $ev['event_type'] === 'exam' ? 'selected' : ''; ?>>Exam</option>
                                <option value="holiday" <?php echo $ev['event_type'] === 'holiday' ? 'selected' : ''; ?>>Holiday</option>
                                <option value="meeting" <?php echo $ev['event_type'] === 'meeting' ? 'selected' : ''; ?>>Meeting</option>
                                <option value="activity" <?php echo $ev['event_type'] === 'activity' ? 'selected' : ''; ?>>Activity</option>
                                <option value="other" <?php echo $ev['event_type'] === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Start Date *</label>
                            <input type="date" class="form-control" name="event_date" required
                                   value="<?php echo htmlspecialchars($ev['event_date']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">End Date (optional)</label>
                            <input type="date" class="form-control" name="end_date"
                                   value="<?php echo htmlspecialchars($ev['end_date'] ?? ''); ?>"
                                   placeholder="For multi-day events">
                            <div class="form-text small">Leave blank for single-day events</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Display Color</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" name="event_color" id="event_color_input"
                                       value="<?php echo htmlspecialchars($ev['event_color']); ?>" style="height: 38px; padding: 2px;">
                                <input type="text" class="form-control" id="event_color_text"
                                       value="<?php echo htmlspecialchars($ev['event_color']); ?>" maxlength="9"
                                       onchange="document.getElementById('event_color_input').value = this.value;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Target Class</label>
                            <select class="form-select" name="class_id">
                                <option value="">All Classes / School-wide</option>
                                <?php foreach ($classes as $cl): ?>
                                    <option value="<?php echo $cl['id']; ?>"
                                        <?php echo $ev['class_id'] == $cl['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cl['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">Restrict event visibility to a specific class</div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch ps-0">
                                <label class="form-check-label me-2 fw-semibold cursor-pointer" for="is_public_toggle">
                                    Make Public (visible to students)
                                </label>
                                <input class="form-check-input ms-2" type="checkbox" role="switch" id="is_public_toggle"
                                       name="is_public" value="1" <?php echo !empty($ev['is_public']) ? 'checked' : ''; ?>>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description (optional)</label>
                            <textarea class="form-control" name="event_description" rows="3" maxlength="1000"
                                      placeholder="Additional details about the event..."><?php echo htmlspecialchars($ev['event_description'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <div class="d-flex gap-2 justify-content-end">
                                <a href="calendar_management.php?tab=events" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> <?php echo $event_to_edit ? 'Update Event' : 'Create Event'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <script>
        (function() {
            const colors = { exam: '#dc3545', holiday: '#6f42c1', meeting: '#ffc107', activity: '#28a745', other: '#007bff' };
            const sel = document.getElementById('event_type_select');
            const ci = document.getElementById('event_color_input');
            const ct = document.getElementById('event_color_text');
            sel.addEventListener('change', function() {
                const c = colors[sel.value];
                if (c) {
                    ci.value = c;
                    ct.value = c;
                }
            });
            ci.addEventListener('input', function() { ct.value = ci.value; });
        })();
        </script>
    <?php elseif ($tab === 'calendar'): ?>
        <?php
        $calendar_widget_id = 'admin_preview_calendar';
        include '../includes/school_calendar.php';
        ?>
    <?php endif; ?>
</div>

<style>
.bg-purple { background-color: #6f42c1 !important; }
</style>

<?php include '../includes/footer.php'; ?>
