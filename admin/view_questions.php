<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is logged in and is an admin
requireLogin();
if (getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle AJAX request for subjects based on class
if (isset($_GET['action']) && $_GET['action'] == 'get_subjects' && isset($_GET['class_id'])) {
    header('Content-Type: application/json');
    $class_id = (int)$_GET['class_id'];
    $subjects = [];
    
    if ($class_id > 0) {
        try {
            $mysqli = getDBConnection();
            if ($mysqli) {
                $stmt = $mysqli->prepare("SELECT s.id, s.name 
                                         FROM subjects s
                                         JOIN subject_class_link scl ON s.id = scl.subject_id
                                         WHERE scl.class_id = ?
                                         ORDER BY s.name");
                $stmt->bind_param("i", $class_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $subjects[] = $row;
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            // Return empty array on error
        }
    }
    
    echo json_encode($subjects);
    exit();
}

$message = '';
$questions = [];
$exam_subject = null;
$comprehension_passages = [];

try {
    $mysqli = getDBConnection();
    
    // Get parameters
    $exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
    $class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
    $subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
    
    // Validate parameters
    if ($exam_id || $class_id || $subject_id) {
        if (!$exam_id || !$class_id || !$subject_id) {
            $message = 'Invalid parameters. Please select a valid exam, class, and subject.';
        } else {
            // Get exam subject with explicit column names to avoid ambiguity
            $stmt = $mysqli->prepare("SELECT es.id as exam_subject_id, es.exam_id, es.subject_id, es.class_id, es.time_limit, es.created_at,
                                  e.title as exam_title, c.name as class_name, s.name as subject_name 
                                  FROM exam_subjects es
                                  JOIN exams e ON es.exam_id = e.id
                                  JOIN classes c ON es.class_id = c.id
                                  JOIN subjects s ON es.subject_id = s.id
                                  WHERE es.exam_id = ? AND es.class_id = ? AND es.subject_id = ?");
            $stmt->bind_param("iii", $exam_id, $class_id, $subject_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $exam_subject = $result->fetch_assoc();
            
            if (!$exam_subject) {
                $message = 'No exam found for the selected class and subject.';
            } else {
                // Get all comprehension passages for this exam subject
                $stmt = $mysqli->prepare("SELECT id, passage_title, passage_content, start_question_number, end_question_number FROM comprehension_passages WHERE exam_subject_id = ? ORDER BY start_question_number");
                $stmt->bind_param("i", $exam_subject['exam_subject_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $comprehension_passages[] = $row;
                }
                
                // Get questions for this exam subject
                $stmt = $mysqli->prepare("SELECT id, exam_subject_id, question_text, option_a, option_b, option_c, option_d, option_e, option_f, correct_answer, created_by, created_at 
                                      FROM questions 
                                      WHERE exam_subject_id = ? 
                                      ORDER BY COALESCE(order_position, id)");
                $stmt->bind_param("i", $exam_subject['exam_subject_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $questions[] = $row;
                }
            }
        }
    }
    
    // Get all exams for the dropdown
    $exams = [];
    $stmt = $mysqli->prepare("SELECT id, title FROM exams ORDER BY title");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
    
    // Get all classes for the dropdown
    $classes = [];
    $stmt = $mysqli->prepare("SELECT id, name FROM classes ORDER BY name");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
    
    // Get all subjects for the dropdown
    $subjects = [];
    $stmt = $mysqli->prepare("SELECT id, name FROM subjects ORDER BY name");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
} catch (Exception $e) {
    $message = 'Error loading data: ' . $e->getMessage();
}

// Function to format mathematical content including fractions using MathML
function formatMathematicalContent($content) {
    // First, preserve line breaks and ensure proper UTF-8 encoding
    // Allow safe HTML tags including img for images
    $allowed_tags = '<p><br><strong><em><img><span><div><ul><ol><li><h1><h2><h3><h4><h5><h6>';
    $content = strip_tags($content, $allowed_tags);
    
    // Additional security: Remove any javascript or on* attributes except for image src
    $content = preg_replace('/(<[^>]+) on[a-zA-Z]*="[^"]*"/i', '$1', $content);
    $content = preg_replace("/(<[^>]+) on[a-zA-Z]*='[^']*'/i", '$1', $content);
    
    // Apply nl2br after sanitization
    $content = nl2br($content);
    
    // Handle mixed numbers like "2 1/2" - only simple cases with integer parts
    $content = preg_replace_callback(
        '/(?<!\d)(\d+)\s+(\d+)\/(\d+)(?!\d)/', 
        function($matches) {
            // Only convert if the fraction is simple (small numbers)
            if (strlen($matches[2]) <= 3 && strlen($matches[3]) <= 3) {
                return $matches[1] . '<math xmlns="http://www.w3.org/1998/Math/MathML" display="inline">' .
                       '<mfrac><mn>' . $matches[2] . '</mn><mn>' . $matches[3] . '</mn></mfrac>' .
                       '</math>';
            } else {
                // Keep original if numbers are too large (likely part of a larger expression)
                return $matches[0];
            }
        }, 
        $content
    );
    
    // Handle fractions in parentheses like "(a/b)" - only simple cases
    $content = preg_replace_callback(
        '/\((\d+(?:\.\d*)?)\/(\d+(?:\.\d*)?)\)/', 
        function($matches) {
            // Only convert if the fraction is simple (small numbers)
            if (strlen($matches[1]) <= 4 && strlen($matches[2]) <= 4) {
                return '(<math xmlns="http://www.w3.org/1998/Math/MathML" display="inline">' .
                       '<mfrac><mn>' . $matches[1] . '</mn><mn>' . $matches[2] . '</mn></mfrac>' .
                       '</math>)';
            } else {
                // Keep original if numbers are too large
                return $matches[0];
            }
        }, 
        $content
    );
    
    // Convert simple fractions in the format "a/b" to MathML fraction format
    // Only apply to simple fractions, not complex expressions
    $content = preg_replace_callback(
        '/(?<![\d\w\/\(\[\{\|\=\:])(\d+(?:\.\d*)?)\/(\d+(?:\.\d*)?)(?![\d\w\/\)\]\}\|\=\:])/', 
        function($matches) {
            // Only convert if the fraction is simple (small numbers)
            if (strlen($matches[1]) <= 4 && strlen($matches[2]) <= 4) {
                return '<math xmlns="http://www.w3.org/1998/Math/MathML" display="inline">' .
                       '<mfrac><mn>' . $matches[1] . '</mn><mn>' . $matches[2] . '</mn></mfrac>' .
                       '</math>';
            } else {
                // Keep original if numbers are too large (likely part of a larger expression)
                return $matches[0];
            }
        }, 
        $content
    );
    
    return $content;
}

// Function to ensure proper UTF-8 encoding for database content
function ensureProperEncoding($content) {
    // If content is already UTF-8, return as is
    if (mb_check_encoding($content, 'UTF-8')) {
        return $content;
    }
    
    // Try to convert from common encodings
    $encodings = ['Windows-1252', 'ISO-8859-1'];
    foreach ($encodings as $encoding) {
        if (mb_check_encoding($content, $encoding)) {
            return mb_convert_encoding($content, 'UTF-8', $encoding);
        }
    }
    
    // Fallback
    return mb_convert_encoding($content, 'UTF-8', 'auto');
}
?>

<?php include '../includes/header.php'; ?>


<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-cogs me-2"></i> Navigation</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="dashboard.php?tab=school" class="list-group-item list-group-item-action">
                    <i class="fas fa-cog me-2"></i> School Settings
                </a>
                <a href="dashboard.php?tab=classes" class="list-group-item list-group-item-action">
                    <i class="fas fa-book me-2"></i> Classes
                </a>
                <a href="dashboard.php?tab=subjects" class="list-group-item list-group-item-action">
                    <i class="fas fa-book-open me-2"></i> Subjects
                </a>
                <a href="dashboard.php?tab=teachers" class="list-group-item list-group-item-action">
                    <i class="fas fa-chalkboard-teacher me-2"></i> Teacher Allocation
                </a>
                <a href="dashboard.php?tab=students" class="list-group-item list-group-item-action">
                    <i class="fas fa-user-graduate me-2"></i> Students
                </a>
                <a href="add_teacher.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-user-plus me-2"></i> Add Teacher
                </a>
                <a href="dashboard.php?tab=exams" class="list-group-item list-group-item-action">
                    <i class="fas fa-file-alt me-2"></i> Exam Management
                </a>
                <a href="view_questions.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-question-circle me-2"></i> View Questions
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-eye me-2"></i> View Teacher Questions</h4>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Filter Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-filter text-primary me-2"></i>Select Exam, Class, and Subject</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="exam_id" class="form-label">Exam</label>
                                <select class="form-select" id="exam_id" name="exam_id" required>
                                    <option value="">Select Exam</option>
                                    <?php foreach ($exams as $exam): ?>
                                        <option value="<?php echo $exam['id']; ?>" <?php echo ($exam_id == $exam['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($exam['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="class_id" class="form-label">Class</label>
                                <select class="form-select class-select" id="class_id" name="class_id" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>" <?php echo ($class_id == $class['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="subject_id" class="form-label">Subject</label>
                                <select class="form-select subject-select" id="subject_id" name="subject_id" required>
                                    <option value="">Select Subject</option>
                                    <!-- Subjects will be populated dynamically based on class selection -->
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i> View Questions
                                </button>
                                <a href="view_questions.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i> Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($exam_subject): ?>
                    <div class="card mb-4 bg-light">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <small class="text-muted">Exam:</small><br>
                                    <strong><?php echo htmlspecialchars($exam_subject['exam_title']); ?></strong>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Class:</small><br>
                                    <strong><?php echo htmlspecialchars($exam_subject['class_name']); ?></strong>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Subject:</small><br>
                                    <strong><?php echo htmlspecialchars($exam_subject['subject_name']); ?></strong>
                                </div>
                            </div>
                            <div class="mt-3">
                                <span class="badge bg-primary">Total Questions: <?php echo count($questions); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Comprehension Passages Section -->
                    <?php if (!empty($comprehension_passages)): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-book-reader text-primary me-2"></i>Comprehension Passages</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Question Range</th>
                                                <th>Passage Content</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($comprehension_passages as $passage): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo $passage['passage_title'] ? htmlspecialchars($passage['passage_title']) : '<em>Untitled</em>'; ?>
                                                    </td>
                                                    <td>
                                                        Questions <?php echo $passage['start_question_number']; ?> - <?php echo $passage['end_question_number']; ?>
                                                    </td>
                                                    <td>
                                                        <div class="bg-light p-2 rounded">
                                                            <?php echo formatMathematicalContent($passage['passage_content']); ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Questions List -->
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-list text-primary me-2"></i>Questions</h5>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (count($questions) > 0): ?>
                                <div class="accordion accordion-flush" id="questionsAccordion">
                                    <?php foreach ($questions as $index => $question): ?>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="heading<?php echo $question['id']; ?>">
                                                <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button"
                                                        data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $question['id']; ?>">
                                                    <div class="text-truncate w-100">
                                                        <span class="badge bg-primary me-2">Q<?php echo $index + 1; ?></span>
                                                        <?php echo htmlspecialchars(substr($question['question_text'], 0, 100)); ?>...
                                                    </div>
                                                </button>
                                            </h2>
                                            <div id="collapse<?php echo $question['id']; ?>" class="accordion-collapse collapse <?php echo $index == 0 ? 'show' : ''; ?>"
                                                 data-bs-parent="#questionsAccordion">
                                                <div class="accordion-body">
                                                    <div class="mb-3">
                                                        <h6>Question:</h6>
                                                        <div class="bg-light p-3 rounded">
                                                            <?php echo formatMathematicalContent(ensureProperEncoding($question['question_text'])); ?>
                                                        </div>
                                                    </div>
                                                    <div class="question-option">
                                                        <span class="badge bg-secondary me-2">A</span> <?php echo formatMathematicalContent(ensureProperEncoding($question['option_a'])); ?>
                                                    </div>
                                                    <div class="question-option">
                                                        <span class="badge bg-secondary me-2">B</span> <?php echo formatMathematicalContent(ensureProperEncoding($question['option_b'])); ?>
                                                    </div>
                                                    <div class="question-option">
                                                        <span class="badge bg-secondary me-2">C</span> <?php echo formatMathematicalContent(ensureProperEncoding($question['option_c'])); ?>
                                                    </div>
                                                    <div class="question-option">
                                                        <span class="badge bg-secondary me-2">D</span> <?php echo formatMathematicalContent(ensureProperEncoding($question['option_d'])); ?>
                                                    </div>

                                                    <?php if (!empty($question['option_e'])): ?>
                                                    <div class="question-option">
                                                        <span class="badge bg-secondary me-2">E</span> <?php echo formatMathematicalContent(ensureProperEncoding($question['option_e'])); ?>
                                                    </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($question['option_f'])): ?>
                                                    <div class="question-option">
                                                        <span class="badge bg-secondary me-2">F</span> <?php echo formatMathematicalContent(ensureProperEncoding($question['option_f'])); ?>
                                                    </div>
                                                    <?php endif; ?>

                                                    <div class="alert alert-success py-2 mt-3">
                                                        <strong><i class="fas fa-check-circle me-1"></i>Correct Answer:</strong> <?php echo htmlspecialchars($question['correct_answer']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-info-circle fa-2x mb-3"></i>
                                    <h5>No Questions Found</h5>
                                    <p class="mb-0">There are no questions for this exam subject.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($exam_id && $class_id && $subject_id): ?>
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i> No Data Found</h5>
                        <p>No questions found for the selected exam, class, and subject combination.</p>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                        <h5>Select an Exam, Class, and Subject</h5>
                        <p class="text-muted">Please use the filters above to view questions created by teachers.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle class selection to load subjects
    const classSelect = document.querySelector('.class-select');
    const subjectSelect = document.querySelector('.subject-select');
    
    // Load subjects for the initially selected class (if any)
    if (classSelect && subjectSelect) {
        classSelect.addEventListener('change', function() {
            const classId = this.value;
            
            // Clear existing options
            subjectSelect.innerHTML = '<option value="">Select Subject</option>';
            
            if (classId) {
                // Fetch subjects for this class
                fetch('view_questions.php?action=get_subjects&class_id=' + encodeURIComponent(classId))
                    .then(response => response.json())
                    .then(subjects => {
                        subjects.forEach(function(subject) {
                            const option = document.createElement('option');
                            option.value = subject.id;
                            option.textContent = subject.name;
                            subjectSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Error loading subjects:', error);
                    });
            }
        });
        
        // If a class is already selected, load its subjects
        if (classSelect.value) {
            // Trigger the change event to load subjects
            const event = new Event('change');
            classSelect.dispatchEvent(event);
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>