<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is logged in and is an admin
requireLogin();
if (getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$results = [];
$classes = [];
$students = [];
$exams = [];
$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selected_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$selected_exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

// Get students based on selected class
function getStudentsByClass($mysqli, $class_id) {
    $students = [];
    
    try {
        if ($class_id > 0) {
            // Get students who have taken exams in this class
            $stmt = $mysqli->prepare("SELECT DISTINCT u.id, u.full_name 
                                     FROM users u 
                                     JOIN exam_attempts ea ON u.id = ea.student_id
                                     JOIN exam_subjects es ON ea.exam_subject_id = es.id
                                     WHERE u.role = 'student' AND es.class_id = ? AND NOT EXISTS (SELECT 1 FROM entrance_exams ee WHERE ee.exam_subject_id=es.id)
                                     ORDER BY u.full_name");
            $stmt->bind_param("i", $class_id);
        } else {
            // Get all students
            $stmt = $mysqli->prepare("SELECT id, full_name FROM users WHERE role = 'student' ORDER BY full_name");
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    } catch (Exception $e) {
        // Handle error silently
    }
    
    return $students;
}

try {
    $mysqli = getDBConnection();
    
    // Get all classes
    $stmt = $mysqli->prepare("SELECT id, name FROM classes ORDER BY name");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
    
    // Get students based on selected class
    $students = getStudentsByClass($mysqli, $selected_class_id);
    
    // Get all exams
    $stmt = $mysqli->prepare("SELECT e.id, e.title FROM exams e WHERE NOT EXISTS (SELECT 1 FROM entrance_exams ee JOIN exam_subjects es ON es.id=ee.exam_subject_id WHERE es.exam_id=e.id) ORDER BY e.title");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
    
    // Build the query based on filters
    $sql = "SELECT ea.*, e.title as exam_title, c.name as class_name, s.name as subject_name, u.full_name as student_name, COALESCE(c.marks_per_question, 1) as marks_per_question,
            (SELECT COUNT(*) FROM questions q WHERE q.exam_subject_id = ea.exam_subject_id AND COALESCE(q.question_type, 'objective') = 'objective') AS objective_questions,
            (SELECT COALESCE(SUM(q.theory_marks), 0) FROM questions q WHERE q.exam_subject_id = ea.exam_subject_id AND q.question_type = 'theory') AS theory_total_marks
            FROM exam_attempts ea
            JOIN exams e ON ea.exam_id = e.id
            JOIN exam_subjects es ON ea.exam_subject_id = es.id
            JOIN classes c ON es.class_id = c.id
            JOIN subjects s ON es.subject_id = s.id
            JOIN users u ON ea.student_id = u.id
            WHERE ea.submitted_at IS NOT NULL AND NOT EXISTS (SELECT 1 FROM entrance_exams ee WHERE ee.exam_subject_id=es.id)";
    
    $params = array();
    $types = "";
    
    if ($selected_class_id > 0) {
        $sql .= " AND c.id = ?";
        $params[] = $selected_class_id;
        $types .= "i";
    }
    
    if ($selected_student_id > 0) {
        $sql .= " AND u.id = ?";
        $params[] = $selected_student_id;
        $types .= "i";
    }
    
    if ($selected_exam_id > 0) {
        $sql .= " AND e.id = ?";
        $params[] = $selected_exam_id;
        $types .= "i";
    }
    
    $sql .= " ORDER BY ea.submitted_at DESC";
    
    $stmt = $mysqli->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
} catch (Exception $e) {
    $message = 'Error loading results: ' . $e->getMessage();
}
?>

<h5>All Students' Exam Results</h5>

<!-- Filter Form -->
<form method="GET" class="mb-4">
    <input type="hidden" name="tab" value="results">
    <div class="row">
        <div class="col-md-3">
            <label for="class_id" class="form-label">Filter by Class:</label>
            <select name="class_id" id="class_id" class="form-select">
                <option value="0">All Classes</option>
                <?php foreach ($classes as $class): ?>
                    <option value="<?php echo $class['id']; ?>" <?php echo ($selected_class_id == $class['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($class['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="student_id" class="form-label">Filter by Student:</label>
            <select name="student_id" id="student_id" class="form-select">
                <option value="0">All Students</option>
                <?php foreach ($students as $student): ?>
                    <option value="<?php echo $student['id']; ?>" <?php echo ($selected_student_id == $student['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($student['full_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="exam_id" class="form-label">Filter by Exam:</label>
            <select name="exam_id" id="exam_id" class="form-select">
                <option value="0">All Exams</option>
                <?php foreach ($exams as $exam): ?>
                    <option value="<?php echo $exam['id']; ?>" <?php echo ($selected_exam_id == $exam['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($exam['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($selected_class_id > 0 || $selected_student_id > 0 || $selected_exam_id > 0): ?>
                <a href="?tab=results" class="btn btn-secondary ms-2">Clear</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if ($message): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if (count($results) > 0): ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Exam</th>
                    <th>Class - Subject</th>
                    <th>Questions</th>
                    <th>Marks Per Q</th>
                    <th>Score / Max Marks</th>
                    <th>Percentage</th>
                    <th>Submitted At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $result): ?>
                    <?php 
                    $marks_per_question = (float)$result['marks_per_question'];
                    $objective_questions = (int)($result['objective_questions'] ?? $result['total_questions']);
                    $theory_total_marks = (float)($result['theory_total_marks'] ?? 0);
                    $max_possible_score = ($objective_questions * $marks_per_question) + $theory_total_marks;
                    if ($max_possible_score <= 0) {
                        $max_possible_score = (float)$result['total_questions'] * $marks_per_question;
                    }
                    $percentage = $max_possible_score > 0 ? ($result['score'] / $max_possible_score) * 100 : 0;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($result['student_name']); ?></td>
                        <td><?php echo htmlspecialchars($result['exam_title']); ?></td>
                        <td><?php echo htmlspecialchars($result['class_name']); ?> - <?php echo htmlspecialchars($result['subject_name']); ?></td>
                        <td><?php echo $result['total_questions']; ?></td>
                        <td><?php echo number_format($marks_per_question, 2); ?></td>
                        <td><strong><?php echo number_format((float)$result['score'], 2); ?></strong> / <?php echo number_format($max_possible_score, 2); ?></td>
                        <td>
                            <strong class="<?php echo $percentage >= 50 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo round($percentage, 2) . '%'; ?>
                            </strong>
                        </td>
                        <td><?php echo date('M j, Y g:i A', strtotime($result['submitted_at'])); ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="view_student_result.php?attempt_id=<?php echo $result['id']; ?>" class="btn btn-sm btn-outline-primary" title="View Results" target="_blank">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-result" 
                                        data-attempt-id="<?php echo $result['id']; ?>" 
                                        data-student="<?php echo htmlspecialchars($result['student_name']); ?>"
                                        data-subject="<?php echo htmlspecialchars($result['subject_name']); ?>"
                                        title="Delete Result">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="d-flex justify-content-between align-items-center mt-3">
        <div>
            Showing <?php echo count($results); ?> result(s)
            <?php if ($selected_student_id > 0 && $selected_exam_id > 0): ?>
                <button type="button" class="btn btn-danger ms-3" id="btn-delete-all-filtered">
                    <i class="fas fa-trash-alt"></i> Delete All Related Results
                </button>
            <?php endif; ?>
        </div>
        <div>
            <a href="export_results.php?class_id=<?php echo $selected_class_id; ?>&student_id=<?php echo $selected_student_id; ?>&exam_id=<?php echo $selected_exam_id; ?>" class="btn btn-success">
                <i class="fas fa-download"></i> Export Results
            </a>
            <?php if ($selected_class_id > 0): ?>
                <a href="../teacher/export_class_excel.php?class_id=<?php echo $selected_class_id; ?>&exam_id=<?php echo $selected_exam_id; ?>" class="btn btn-outline-success ms-2" title="Export Broadsheet with all subjects and scores per student">
                    <i class="fas fa-file-excel me-1"></i> Class Broadsheet (Excel)
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <p>No exam results available yet.</p>
        <p>Students need to complete exams before results will appear here.</p>
    </div>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="delete_result.php" method="POST">
                <div class="modal-body">
                    <p id="deleteMessage">Are you sure you want to delete this result?</p>
                    <input type="hidden" name="attempt_id" id="delete_attempt_id" value="">
                    <input type="hidden" name="student_id" id="delete_student_id" value="">
                    <input type="hidden" name="exam_id" id="delete_exam_id" value="">
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Permanently</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Function to update student dropdown based on selected class
document.addEventListener('DOMContentLoaded', function() {
    const classSelect = document.getElementById('class_id');
    const studentSelect = document.getElementById('student_id');
    
    if (classSelect && studentSelect) {
        classSelect.addEventListener('change', function() {
            const classId = this.value;
            updateStudentDropdown(classId);
        });
    }
});

function updateStudentDropdown(classId) {
    const studentSelect = document.getElementById('student_id');
    
    // Show loading state
    const originalHTML = studentSelect.innerHTML;
    studentSelect.innerHTML = '<option value="0">Loading...</option>';
    studentSelect.disabled = true;
    
    // Make AJAX request
    fetch('get_students_by_class_ajax.php?class_id=' + classId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Clear current options except the first one
                studentSelect.innerHTML = '<option value="0">All Students</option>';
                
                // Add new options
                data.students.forEach(student => {
                    const option = document.createElement('option');
                    option.value = student.id;
                    option.textContent = student.full_name;
                    studentSelect.appendChild(option);
                });
            } else {
                console.error('Error fetching students:', data.error);
                studentSelect.innerHTML = originalHTML;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            studentSelect.innerHTML = originalHTML;
        })
        .finally(() => {
            studentSelect.disabled = false;
        });
}

// Result deletion handling
document.addEventListener('DOMContentLoaded', function() {
    // Check if bootstrap is available (it should be via the main layout)
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap is not loaded, modals will not work');
        // Fallback for direct deletion if bootstrap fails
        attachSimpleDeletions();
        return;
    }

    const deleteModalElem = document.getElementById('deleteModal');
    if (!deleteModalElem) return;

    const deleteModal = new bootstrap.Modal(deleteModalElem);
    const deleteForm = document.querySelector('#deleteModal form');
    const deleteMessage = document.getElementById('deleteMessage');
    const deleteAttemptId = document.getElementById('delete_attempt_id');
    const deleteStudentId = document.getElementById('delete_student_id');
    const deleteExamId = document.getElementById('delete_exam_id');

    // Individual delete
    document.querySelectorAll('.btn-delete-result').forEach(button => {
        button.addEventListener('click', function() {
            const attemptId = this.getAttribute('data-attempt-id');
            const student = this.getAttribute('data-student');
            const subject = this.getAttribute('data-subject');
            
            deleteAttemptId.value = attemptId;
            deleteStudentId.value = '';
            deleteExamId.value = '';
            deleteMessage.textContent = `Are you sure you want to delete the result for ${student} in ${subject}? This action cannot be undone.`;
            deleteModal.show();
        });
    });

    // Bulk delete (related results)
    const btnDeleteAll = document.getElementById('btn-delete-all-filtered');
    if (btnDeleteAll) {
        btnDeleteAll.addEventListener('click', function() {
            const studentId = '<?php echo $selected_student_id; ?>';
            const examId = '<?php echo $selected_exam_id; ?>';
            
            deleteAttemptId.value = '';
            deleteStudentId.value = studentId;
            deleteExamId.value = examId;
            deleteMessage.textContent = 'Are you sure you want to delete ALL subject results for this student in this exam? This action cannot be undone.';
            deleteModal.show();
        });
    }

    // Check for status messages in URL
    const urlParams = new URLSearchParams(window.location.search);
    const message = urlParams.get('message');
    const status = urlParams.get('status');
    
    if (message) {
        // Find existing message or create one
        let alertDiv = document.querySelector('.alert-info, .alert-success, .alert-danger');
        if (!alertDiv || alertDiv.closest('.modal')) {
            alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${status === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
            alertDiv.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>`;
            document.querySelector('h5').insertAdjacentElement('afterend', alertDiv);
        } else {
            alertDiv.className = `alert alert-${status === 'success' ? 'success' : 'danger'}`;
            alertDiv.textContent = message;
        }
    }
});

function attachSimpleDeletions() {
    document.querySelectorAll('.btn-delete-result').forEach(button => {
        button.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete this result?')) {
                const attemptId = this.getAttribute('data-attempt-id');
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete_result.php';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'attempt_id';
                input.value = attemptId;
                form.appendChild(input);
                
                const redirect = document.createElement('input');
                redirect.type = 'hidden';
                redirect.name = 'redirect';
                redirect.value = window.location.href;
                form.appendChild(redirect);
                
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
    
    // Dynamic student filtering by class
    document.addEventListener('DOMContentLoaded', function() {
        const classSelect = document.querySelector('select[name="class_id"]');
        const studentSelect = document.querySelector('select[name="student_id"]');
        
        if (classSelect && studentSelect) {
            classSelect.addEventListener('change', function() {
                const classId = this.value;
                
                // Clear current options except "All Students"
                studentSelect.innerHTML = '<option value="">All Students</option>';
                
                if (classId) {
                    // Fetch students for selected class
                    fetch(`get_students_by_class.php?class_id=${classId}`)
                        .then(response => response.json())
                        .then(students => {
                            students.forEach(student => {
                                const option = document.createElement('option');
                                option.value = student.id;
                                option.textContent = student.full_name;
                                studentSelect.appendChild(option);
                            });
                        })
                        .catch(error => console.error('Error loading students:', error));
                }
            });
        }
    });
}
</script>
