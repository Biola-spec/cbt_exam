<?php
require_once '../config/database.php';
require_once '../config/session.php'; // Added this to ensure session functions are available

// Check if user is logged in and is a teacher or admin
requireLogin();
if (!in_array(getUserRole(), ['teacher', 'admin'])) {
    header('Location: ../login.php');
    exit();
}

$message = '';
$questions = [];
$exam_subject = null;
$comprehension_passages = []; // Changed to array to store multiple passages

try {
    $mysqli = getDBConnection();
    ensureTheoryExamSchema($mysqli);
    
    // Get parameters
    $exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
    $class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
    $subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
    
    // Validate parameters
    if (!$exam_id || !$class_id || !$subject_id) {
        header('Location: my_exams.php');
        exit();
    }
    
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
        header('Location: my_exams.php');
        exit();
    }
    
    // Get all comprehension passages for this exam subject
    $stmt = $mysqli->prepare("SELECT id, passage_title, passage_content, start_question_number, end_question_number FROM comprehension_passages WHERE exam_subject_id = ? ORDER BY start_question_number");
    $stmt->bind_param("i", $exam_subject['exam_subject_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $comprehension_passages[] = $row;
    }
    
    // Get questions for this exam subject
    $stmt = $mysqli->prepare("SELECT id, exam_subject_id, question_text, question_type, option_a, option_b, option_c, option_d, option_e, option_f, correct_answer, theory_marks, marking_scheme, created_by, created_at 
                          FROM questions 
                          WHERE exam_subject_id = ? 
                          ORDER BY COALESCE(order_position, id)");
    $stmt->bind_param("i", $exam_subject['exam_subject_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }
    
    // Handle form submission for adding/updating comprehension passage
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] == 'save_passage') {
            $passage_id = !empty($_POST['passage_id']) ? (int)$_POST['passage_id'] : 0;
            $passage_title = !empty($_POST['passage_title']) ? trim($_POST['passage_title']) : null;
            $passage_content = !empty($_POST['passage_content']) ? cleanWpsContent($_POST['passage_content']) : '';
            $start_question = !empty($_POST['start_question']) ? (int)$_POST['start_question'] : 1;
            $end_question = !empty($_POST['end_question']) ? (int)$_POST['end_question'] : 1;
            
            // Validate question range
            if ($start_question < 1 || $end_question < $start_question) {
                $message = 'Invalid question range. Start question must be at least 1 and end question must be greater than or equal to start question.';
            } else {
                // Check for overlapping passages
                $stmt_check = $mysqli->prepare("SELECT id, passage_title, start_question_number, end_question_number FROM comprehension_passages WHERE exam_subject_id = ? AND ((start_question_number <= ? AND end_question_number >= ?) OR (start_question_number <= ? AND end_question_number >= ?))");
                $stmt_check->bind_param("iiiii", $exam_subject['exam_subject_id'], $start_question, $start_question, $end_question, $end_question);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                
                // If updating, exclude current passage from check
                $has_overlap = false;
                while ($row = $result_check->fetch_assoc()) {
                    if ($passage_id == 0 || $row['id'] != $passage_id) {
                        $has_overlap = true;
                        break;
                    }
                }
                $stmt_check->close();
                
                if ($has_overlap) {
                    $message = 'This question range overlaps with another passage. Please choose a different range.';
                } else if (!empty($passage_content)) {
                    try {
                        // Use the robust database function with automatic reconnection
                        if ($passage_id > 0) {
                            // Update existing passage
                            $stmt = executeQueryWithReconnect($mysqli, 
                                "UPDATE comprehension_passages SET passage_title = ?, passage_content = ?, start_question_number = ?, end_question_number = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND exam_subject_id = ?", 
                                [$passage_title, $passage_content, $start_question, $end_question, $passage_id, $exam_subject['exam_subject_id']]);
                        } else {
                            // Insert new passage
                            $user_id = getUserId();
                            $stmt = executeQueryWithReconnect($mysqli, 
                                "INSERT INTO comprehension_passages (exam_subject_id, passage_title, passage_content, start_question_number, end_question_number, created_by) VALUES (?, ?, ?, ?, ?, ?)", 
                                [$exam_subject['exam_subject_id'], $passage_title, $passage_content, $start_question, $end_question, $user_id]);
                        }
                        
                        $message = 'Comprehension passage saved successfully';
                        
                        // Refresh passages data using the robust function
                        $stmt = executeQueryWithReconnect($mysqli, 
                            "SELECT id, passage_title, passage_content, start_question_number, end_question_number FROM comprehension_passages WHERE exam_subject_id = ? ORDER BY start_question_number", 
                            [$exam_subject['exam_subject_id']]);
                        $result = $stmt->get_result();
                        $comprehension_passages = [];
                        while ($row = $result->fetch_assoc()) {
                            $comprehension_passages[] = $row;
                        }
                    } catch (Exception $e) {
                        $message = 'Error saving comprehension passage: ' . $e->getMessage();
                    }
                } else {
                    $message = 'Passage content cannot be empty';
                }
            }
        } else if ($_POST['action'] == 'delete_passage' && !empty($_POST['passage_id'])) {
            // Delete passage
            $passage_id = (int)$_POST['passage_id'];
            try {
                $stmt = $mysqli->prepare("DELETE FROM comprehension_passages WHERE id = ? AND exam_subject_id = ?");
                $stmt->bind_param("ii", $passage_id, $exam_subject['exam_subject_id']);
                $stmt->execute();
                $message = 'Comprehension passage removed successfully';
                
                // Refresh passages data
                $stmt = $mysqli->prepare("SELECT id, passage_title, passage_content, start_question_number, end_question_number FROM comprehension_passages WHERE exam_subject_id = ? ORDER BY start_question_number");
                $stmt->bind_param("i", $exam_subject['exam_subject_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $comprehension_passages = [];
                while ($row = $result->fetch_assoc()) {
                    $comprehension_passages[] = $row;
                }
            } catch (Exception $e) {
                $message = 'Error removing comprehension passage: ' . $e->getMessage();
            }
        }
    }
    
    // Handle form submission for adding new question
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
        $question_text = cleanWpsContent($_POST['question_text']);
        $question_type = ($_POST['question_type'] ?? 'objective') === 'theory' ? 'theory' : 'objective';
        // Preserve line breaks in options by not using trim() which removes them
        $option_a = $question_type === 'objective' ? cleanWpsContent($_POST['option_a'] ?? '') : null;
        $option_b = $question_type === 'objective' ? cleanWpsContent($_POST['option_b'] ?? '') : null;
        $option_c = $question_type === 'objective' ? cleanWpsContent($_POST['option_c'] ?? '') : null;
        $option_d = $question_type === 'objective' ? cleanWpsContent($_POST['option_d'] ?? '') : null;
        $option_e = !empty($_POST['option_e']) ? cleanWpsContent($_POST['option_e']) : null;
        $option_f = !empty($_POST['option_f']) ? cleanWpsContent($_POST['option_f']) : null;
        $correct_answer = $question_type === 'objective' ? ($_POST['correct_answer'] ?? null) : null;
        $theory_marks = $question_type === 'theory' ? max(0, (float)($_POST['theory_marks'] ?? 0)) : null;
        $marking_scheme = $question_type === 'theory' && !empty($_POST['marking_scheme']) ? cleanWpsContent($_POST['marking_scheme']) : null;
        
        $is_valid_objective = $question_type === 'objective' && !empty($question_text) && !empty($option_a) && !empty($option_b) && !empty($option_c) && !empty($option_d) && !empty($correct_answer);
        $is_valid_theory = $question_type === 'theory' && !empty($question_text) && $theory_marks > 0;
        if ($is_valid_objective || $is_valid_theory) {
            try {
                $user_id = getUserId();
                // Get the next order position
                $stmt_pos = $mysqli->prepare("SELECT COALESCE(MAX(order_position), 0) + 1 as next_position FROM questions WHERE exam_subject_id = ?");
                $stmt_pos->bind_param("i", $exam_subject['exam_subject_id']);
                $stmt_pos->execute();
                $result_pos = $stmt_pos->get_result();
                $row_pos = $result_pos->fetch_assoc();
                $next_position = $row_pos['next_position'];
                $stmt_pos->close();
                
                $stmt = $mysqli->prepare("INSERT INTO questions (exam_subject_id, order_position, question_type, question_text, option_a, option_b, option_c, option_d, option_e, option_f, correct_answer, theory_marks, marking_scheme, created_by) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iisssssssssdsi", $exam_subject['exam_subject_id'], $next_position, $question_type, $question_text, $option_a, $option_b, $option_c, $option_d, $option_e, $option_f, $correct_answer, $theory_marks, $marking_scheme, $user_id);
                $stmt->execute();
                $message = 'Question added successfully';
                
                // Refresh questions list
                $stmt = $mysqli->prepare("SELECT id, exam_subject_id, question_text, question_type, option_a, option_b, option_c, option_d, option_e, option_f, correct_answer, theory_marks, marking_scheme, created_by, created_at 
                                      FROM questions 
                                      WHERE exam_subject_id = ? 
                                      ORDER BY COALESCE(order_position, id)");
                $stmt->bind_param("i", $exam_subject['exam_subject_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $questions = [];
                while ($row = $result->fetch_assoc()) {
                    $questions[] = $row;
                }
            } catch (Exception $e) {
                $message = 'Error adding question: ' . $e->getMessage();
            }
        } else {
            $message = $question_type === 'theory' ? 'Question text and total theory marks are required' : 'All objective question fields are required';
        }
    } else if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
        $question_id = (int)$_POST['question_id'];
        $question_text = cleanWpsContent($_POST['question_text']);
        $question_type = ($_POST['question_type'] ?? 'objective') === 'theory' ? 'theory' : 'objective';
        $option_a = $question_type === 'objective' ? cleanWpsContent($_POST['option_a'] ?? '') : null;
        $option_b = $question_type === 'objective' ? cleanWpsContent($_POST['option_b'] ?? '') : null;
        $option_c = $question_type === 'objective' ? cleanWpsContent($_POST['option_c'] ?? '') : null;
        $option_d = $question_type === 'objective' ? cleanWpsContent($_POST['option_d'] ?? '') : null;
        $option_e = !empty($_POST['option_e']) ? cleanWpsContent($_POST['option_e']) : null;
        $option_f = !empty($_POST['option_f']) ? cleanWpsContent($_POST['option_f']) : null;
        $correct_answer = $question_type === 'objective' ? ($_POST['correct_answer'] ?? null) : null;
        $theory_marks = $question_type === 'theory' ? max(0, (float)($_POST['theory_marks'] ?? 0)) : null;
        $marking_scheme = $question_type === 'theory' && !empty($_POST['marking_scheme']) ? cleanWpsContent($_POST['marking_scheme']) : null;
        
        $is_valid_objective = $question_type === 'objective' && !empty($question_id) && !empty($question_text) && !empty($option_a) && !empty($option_b) && !empty($option_c) && !empty($option_d) && !empty($correct_answer);
        $is_valid_theory = $question_type === 'theory' && !empty($question_id) && !empty($question_text) && $theory_marks > 0;
        if ($is_valid_objective || $is_valid_theory) {
            try {
                $stmt = $mysqli->prepare("UPDATE questions SET question_type = ?, question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, option_e = ?, option_f = ?, correct_answer = ?, theory_marks = ?, marking_scheme = ? WHERE id = ? AND exam_subject_id = ?");
                $stmt->bind_param("sssssssssdsii", $question_type, $question_text, $option_a, $option_b, $option_c, $option_d, $option_e, $option_f, $correct_answer, $theory_marks, $marking_scheme, $question_id, $exam_subject['exam_subject_id']);
                
                if ($stmt->execute()) {
                    $message = 'Question updated successfully';
                } else {
                    $message = 'Error updating question: ' . $stmt->error;
                }
                $stmt->close();
                
                // Refresh questions list
                $stmt = $mysqli->prepare("SELECT id, exam_subject_id, question_text, question_type, option_a, option_b, option_c, option_d, option_e, option_f, correct_answer, theory_marks, marking_scheme, created_by, created_at 
                                      FROM questions 
                                      WHERE exam_subject_id = ? 
                                      ORDER BY COALESCE(order_position, id)");
                $stmt->bind_param("i", $exam_subject['exam_subject_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $questions = [];
                while ($row = $result->fetch_assoc()) {
                    $questions[] = $row;
                }
            } catch (Exception $e) {
                $message = 'Error updating question: ' . $e->getMessage();
            }
        } else {
            $message = $question_type === 'theory' ? 'Question text and total theory marks are required' : 'All required objective fields must be filled';
        }
    }
    
    // Handle deletion
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
        $question_id = $_POST['question_id'];
        
        try {
            $stmt = $mysqli->prepare("DELETE FROM questions WHERE id = ? AND exam_subject_id = ?");
            $stmt->bind_param("ii", $question_id, $exam_subject['exam_subject_id']);
            $stmt->execute();
            $message = 'Question deleted successfully';
            
            // Refresh questions list
            $stmt = $mysqli->prepare("SELECT id, exam_subject_id, question_text, question_type, option_a, option_b, option_c, option_d, option_e, option_f, correct_answer, theory_marks, marking_scheme, created_by, created_at 
                                  FROM questions 
                                  WHERE exam_subject_id = ? 
                                  ORDER BY COALESCE(order_position, id)");
            $stmt->bind_param("i", $exam_subject['exam_subject_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $questions = [];
            while ($row = $result->fetch_assoc()) {
                $questions[] = $row;
            }
        } catch (Exception $e) {
            $message = 'Error deleting question: ' . $e->getMessage();
        }
    }
    
    // Handle delete all questions
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_all') {
        try {
            $stmt = $mysqli->prepare("DELETE FROM questions WHERE exam_subject_id = ?");
            $stmt->bind_param("i", $exam_subject['exam_subject_id']);
            $stmt->execute();
            $message = 'All questions deleted successfully';
            
            // Refresh questions list (should be empty now)
            $questions = [];
        } catch (Exception $e) {
            $message = 'Error deleting all questions: ' . $e->getMessage();
        }
    }
    
    // Handle question import
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'import') {
        $import_type = ($_POST['import_type'] ?? 'objective') === 'theory' ? 'theory' : 'objective';
        // Checkbox values are only set if checked
        $prevent_duplicates = isset($_POST['prevent_duplicates']);
        $clear_existing = isset($_POST['clear_existing']);
        
        if (isset($_FILES['questions_file']) && $_FILES['questions_file']['error'] == 0) {
            $file_tmp = $_FILES['questions_file']['tmp_name'];
            $file_name = $_FILES['questions_file']['name'];
            $file_size = $_FILES['questions_file']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Check file size (limit to 5MB)
            if ($file_size > 5 * 1024 * 1024) {
                $message = 'File is too large. Maximum file size is 5MB.';
            } else {
                // Check file extension
                $allowed_extensions = ['txt', 'doc', 'docx'];
                if (!in_array($file_ext, $allowed_extensions)) {
                    $message = 'Invalid file format. Please upload a TXT, DOC, or DOCX file.';
                } else {
                    // Create a temporary copy of the file to prevent "file changed" errors
                    $temp_file = tempnam(sys_get_temp_dir(), 'question_import_');
                    if (!copy($file_tmp, $temp_file)) {
                        $message = 'Failed to process the uploaded file. Please try again.';
                    } else {
                        // Read file content from the temporary copy
                        if ($file_ext == 'txt') {
                            $content = file_get_contents($temp_file);
                        } else {
                            // For DOC/DOCX files, try to extract text content
                            $content = extractTextFromWordDocument($temp_file, $file_ext);
                            if ($content === false) {
                                $message = 'Unable to extract text from the Word document. Please save your questions as a plain text file (.txt) instead.';
                                // Clean up temporary file
                                unlink($temp_file);
                                // Continue to show the form again
                            }
                        }
                        
                        // If we have content, process it
                        // If clear existing is selected, delete all questions first
                        if ($clear_existing) {
                            try {
                                $stmt = $mysqli->prepare("DELETE FROM questions WHERE exam_subject_id = ?");
                                $stmt->bind_param("i", $exam_subject['exam_subject_id']);
                                if (!$stmt->execute()) {
                                    throw new Exception("Failed to delete questions: " . $stmt->error);
                                }
                                $stmt->close();
                                
                                // Also clear comprehension passages for a full reset
                                $stmt = $mysqli->prepare("DELETE FROM comprehension_passages WHERE exam_subject_id = ?");
                                $stmt->bind_param("i", $exam_subject['exam_subject_id']);
                                $stmt->execute();
                                $stmt->close();

                                // Clear all existing attempts for this exam subject
                                $stmt = $mysqli->prepare("DELETE FROM exam_attempts WHERE exam_subject_id = ?");
                                $stmt->bind_param("i", $exam_subject['exam_subject_id']);
                                $stmt->execute();
                                $stmt->close();
                                
                                // Refresh questions list (should be empty now)
                                $questions = [];
                            } catch (Exception $e) {
                                $message = 'Error clearing existing questions: ' . $e->getMessage();
                                // Clean up temporary file
                                unlink($temp_file);
                                // Continue to show the form again
                                return;
                            }
                        }
                        
                        if (isset($content) && $content !== false) {
                            // Ensure proper UTF-8 encoding for all content
                            $content = ensureUtf8Encoding($content);
                            
                            // Remove BOM if present
                            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
                            
                            // Clean content from WPS or other rich text editors
                            $content = cleanWpsContent($content);
                            
                            // Add debug information
                            file_put_contents('../scratch/raw_import_content.txt', $content);
                            error_log("Import file content length: " . strlen($content));
                            error_log("First 500 characters of content: " . substr($content, 0, 500));
                            
                            // Parse questions
                            $imported_questions = parseQuestionsFromFile($content, $import_type);
                            error_log("Imported questions count: " . count($imported_questions));
                            
                            if (!empty($imported_questions)) {
                                $imported_count = 0;
                                $user_id = getUserId();
                                $failed_count = 0;
                                $duplicate_count = 0;
                                
                                // Define null variables to avoid pass by reference error
                                $null_value = null;
                                
                                // Insert each question into the database
                                foreach ($imported_questions as $index => $q) {
                                    try {
                                        $question_type = $q['question_type'] ?? 'objective';

                                        // Check for duplicates if prevention is enabled
                                        if ($prevent_duplicates) {
                                            $duplicate_check_stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM questions WHERE exam_subject_id = ? AND question_type = ? AND question_text = ?");
                                            if ($duplicate_check_stmt) {
                                                $duplicate_check_stmt->bind_param("iss", $exam_subject['exam_subject_id'], $question_type, $q['question']);
                                                $duplicate_check_stmt->execute();
                                                $duplicate_result = $duplicate_check_stmt->get_result();
                                                $duplicate_row = $duplicate_result->fetch_assoc();
                                                $duplicate_check_stmt->close();
                                                
                                                // If duplicate found, skip this question
                                                if ($duplicate_row['count'] > 0) {
                                                    $duplicate_count++;
                                                    continue;
                                                }
                                            }
                                        }
                                        
                                        $stmt = $mysqli->prepare("INSERT INTO questions (exam_subject_id, order_position, question_type, question_text, option_a, option_b, option_c, option_d, option_e, option_f, correct_answer, theory_marks, marking_scheme, created_by) 
                                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                        if (!$stmt) {
                                            error_log("Failed to prepare statement for question " . ($index + 1) . ": " . $mysqli->error);
                                            $failed_count++;
                                            continue;
                                        }
                                        
                                        $opt_e = !empty($q['option_e']) ? $q['option_e'] : null;
                                        $opt_f = !empty($q['option_f']) ? $q['option_f'] : null;
                                        $option_a = $question_type === 'objective' ? ($q['option_a'] ?? null) : null;
                                        $option_b = $question_type === 'objective' ? ($q['option_b'] ?? null) : null;
                                        $option_c = $question_type === 'objective' ? ($q['option_c'] ?? null) : null;
                                        $option_d = $question_type === 'objective' ? ($q['option_d'] ?? null) : null;
                                        $correct_answer = $question_type === 'objective' ? ($q['answer'] ?? null) : null;
                                        $theory_marks = $question_type === 'theory' ? (float)($q['theory_marks'] ?? 0) : null;
                                        $marking_scheme = $question_type === 'theory' ? ($q['marking_scheme'] ?? null) : null;
                                        $stmt->bind_param("iisssssssssdsi", $exam_subject['exam_subject_id'], $index, $question_type, $q['question'], $option_a, $option_b, $option_c, $option_d, $opt_e, $opt_f, $correct_answer, $theory_marks, $marking_scheme, $user_id);
                                        
                                        if ($stmt->execute()) {
                                            $imported_count++;
                                        } else {
                                            error_log("Failed to execute statement for question " . ($index + 1) . ": " . $stmt->error);
                                            $failed_count++;
                                        }
                                        $stmt->close();
                                    } catch (Exception $e) {
                                        // Continue with other questions even if one fails
                                        error_log("Error importing question " . ($index + 1) . ": " . $e->getMessage());
                                        $failed_count++;
                                    }
                                }
                                
                                if ($failed_count > 0) {
                                    $message = $imported_count . ' questions imported successfully. ' . $failed_count . ' questions failed to import.';
                                } else if ($duplicate_count > 0) {
                                    $message = $imported_count . ' new questions imported successfully. ' . $duplicate_count . ' duplicate questions were skipped.';
                                } else {
                                    $message = $imported_count . ' ' . $import_type . ' questions imported successfully.';
                                    if ($clear_existing) {
                                        $message = 'Cleared all existing questions. ' . $message;
                                    }
                                }
                                
                                // Refresh questions list
                                $stmt = $mysqli->prepare("SELECT id, exam_subject_id, question_text, question_type, option_a, option_b, option_c, option_d, option_e, option_f, correct_answer, theory_marks, marking_scheme, created_by, created_at 
                                                      FROM questions 
                                                      WHERE exam_subject_id = ? 
                                                      ORDER BY COALESCE(order_position, id)");
                                if ($stmt) {
                                    $stmt->bind_param("i", $exam_subject['exam_subject_id']);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    $questions = [];
                                    while ($row = $result->fetch_assoc()) {
                                        $questions[] = $row;
                                    }
                                    $stmt->close();
                                }
                            } else {
                                $message = $import_type === 'theory'
                                    ? 'No valid theory questions found. Use numbered questions with a MARKS line for each question, and optionally add MARKING SCHEME.'
                                    : 'No valid objective questions found. Use numbered questions with A-D options and an ANSWER line for each question.';
                            }
                            
                            // Clean up temporary file
                            unlink($temp_file);
                        }
                    }
                }
            }
        } else {
            // Handle specific upload errors
            if (isset($_FILES['questions_file'])) {
                switch ($_FILES['questions_file']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $message = 'File is too large. Please upload a smaller file.';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $message = 'File was only partially uploaded. Please try again.';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $message = 'No file was uploaded. Please select a file to import.';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $message = 'Missing temporary folder. Please contact the system administrator.';
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $message = 'Failed to write file to disk. Please contact the system administrator.';
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $message = 'File upload stopped by extension. Please try a different file format.';
                        break;
                    default:
                        $message = 'An unknown error occurred during file upload. Please try again.';
                        break;
                }
            } else {
                $message = 'Please select a file to import.';
            }
        }
    }
} catch (Exception $e) {
    $message = 'Error loading data: ' . $e->getMessage();
}

// Function to parse questions from file content
function parseQuestionsFromFile($content, $import_type = 'objective') {
    $questions = [];
    $import_type = $import_type === 'theory' ? 'theory' : 'objective';
    
    // Add debug information
    error_log("Parsing content length: " . strlen($content));
    error_log("First 500 characters for parsing: " . substr($content, 0, 500));
    
    // Normalize line endings
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    
    // Split content into lines
    $lines = explode("\n", $content);
    
    $current_question = [];
    $in_question = false;
    $question_lines = [];
    $option_lines = [];
    $in_options = false;
    $potential_answer_line = '';
    $tag_gap = '(?:\s|<[^>]+>)*';
    $question_start_pattern = '/^(?:\[ANSWER_MARKER\])?' . $tag_gap . '\d+' . $tag_gap . '[\.\)]/u';
    // Fix: Change the third tag_gap to \s* to prevent stripping HTML tags like <b>, <i>, <u> that start the question text
    $question_prefix_pattern = '/^(?:\[ANSWER_MARKER\])?(' . $tag_gap . ')\d+' . $tag_gap . '[\.\)]\s*/u';
    $option_start_pattern = '/^(?:\[ANSWER_MARKER\])?' . $tag_gap . '([A-F])' . $tag_gap . '[\.\)]/iu';
    
    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        $trimmed_line = trim($line);
        
        // Skip completely empty lines
        if (empty($trimmed_line)) {
            // But keep track of potential answer lines that might be split across lines
            if (!empty($potential_answer_line)) {
                $potential_answer_line .= ' ';
            }
            continue;
        }
        
        // Check if this line starts a new question (allowing for leading HTML tags like <b> and [ANSWER_MARKER] and optional whitespace)
        if (preg_match($question_start_pattern, $trimmed_line)) {
            // If we were already processing a question, save it
            if ($in_question && !empty($current_question)) {
                // Process any remaining potential answer
                if (!empty($potential_answer_line) && empty($current_question['answer'])) {
                    if (stripos($potential_answer_line, 'answer:') !== false) {
                        $answer = trim(str_ireplace('answer:', '', $potential_answer_line));
                        $current_question['answer'] = strtoupper(trim(substr($answer, 0, 1)));
                    }
                }
                
                // Process accumulated question and option lines
                processQuestionLines($question_lines, $option_lines, $current_question);
                
                $parsed_question = buildImportedQuestion($current_question, $import_type);
                if ($parsed_question) {
                    $questions[] = $parsed_question;
                }
            }
            
            // Start a new question
            $in_question = true;
            $in_options = false;
            $potential_answer_line = '';
            $question_lines = [preg_replace($question_prefix_pattern, '$1', $trimmed_line)];
            $option_lines = [];
            $current_question = [
                'options' => [],
                'answer' => ''
            ];
            
            continue;
        }
        
        // If we're not in a question, skip this line
        if (!$in_question) {
            continue;
        }
        
        // Check if this is an explicit answer line (e.g., "ANSWER: B")
        $plain_line = strip_tags($trimmed_line);
        if (preg_match('/^\s*(?:ans(?:wer)?|correct(?:\s+ans(?:wer)?)?)\s*[:\-]?\s*(?:is\s+)?([A-F])\b/i', $plain_line, $ans_match)) {
            $current_question['answer'] = strtoupper($ans_match[1]);
            error_log("Detected answer via explicit line: " . $current_question['answer']);
            $in_options = false;
            $potential_answer_line = '';
            continue;
        }
        
        // If we have a partial "answer" word, store it for potential completion
        if (stripos($trimmed_line, 'ans') !== false || !empty($potential_answer_line)) {
            $potential_answer_line .= ' ' . $trimmed_line;
            // Limit the potential answer line to prevent it from growing too large
            if (strlen($potential_answer_line) > 100) {
                $potential_answer_line = '';
            }
        }
        
        // Check if this is the start of options (A, B, C, D, E, F) (allowing for leading HTML tags and [ANSWER_MARKER])
        if (preg_match($option_start_pattern, $trimmed_line)) {
            $in_options = true;
            $potential_answer_line = ''; // Reset potential answer line when we hit options
            $option_lines[] = $trimmed_line;
            continue;
        }
        
        // If we're in options section, continue collecting option lines
        if ($in_options) {
            $option_lines[] = $trimmed_line;
        } else {
            // Otherwise, it's part of the question text
            $question_lines[] = $line; // Keep original formatting
        }
    }
    
    // Don't forget the last question
    if ($in_question && !empty($current_question)) {
        // Process any remaining potential answer
        if (!empty($potential_answer_line) && empty($current_question['answer'])) {
            if (stripos($potential_answer_line, 'answer:') !== false) {
                $answer = trim(str_ireplace('answer:', '', $potential_answer_line));
                $current_question['answer'] = strtoupper(trim(substr($answer, 0, 1)));
            }
        }
        
        // Process accumulated question and option lines
        processQuestionLines($question_lines, $option_lines, $current_question);
        
        $parsed_question = buildImportedQuestion($current_question, $import_type);
        if ($parsed_question) {
            $questions[] = $parsed_question;
        }
    }
    
    // Add debug information
    error_log("Number of questions parsed: " . count($questions));
    
    return $questions;
}

function processQuestionLines($question_lines, $option_lines, &$current_question) {
    // Add debug information
    error_log("Processing question with " . count($question_lines) . " question lines and " . count($option_lines) . " option lines");
    
    // Process question text
    $current_question['question'] = trim(implode("\n", $question_lines));
    error_log("Question text: " . substr($current_question['question'], 0, 100) . "...");
    
    // First, flatten all option lines into one searchable text, preserving line breaks
    $full_options_text = implode("\n", $option_lines);
    
    // Pattern to find option markers (A-F followed by . or ))
    // We look for them globally. They should be preceded by start of string, newline, or whitespace
    // Updated pattern to consume trailing spaces after the marker to prevent "far right" content issues
    $tag_gap = '(?:\s|<[^>]+>)*';
    $option_marker_pattern = '/(?:\[ANSWER_MARKER\])?(?:\s|<[^>]+>)*?((?:<[^>]+>)*)\s*([A-F])' . $tag_gap . '[\.\)]\s*/iu';
    
    $options_data = [];
    
    // Find all matches of option markers
    if (preg_match_all($option_marker_pattern, $full_options_text, $matches, PREG_OFFSET_CAPTURE)) {
        for ($i = 0; $i < count($matches[0]); $i++) {
            $marker_full_match = $matches[0][$i][0];
            $marker_offset = $matches[0][$i][1];
            $marker_tags = $matches[1][$i][0];
            $option_letter = strtoupper($matches[2][$i][0]);
            
            // Determine where the content for this option ends
            $content_start = $marker_offset + strlen($marker_full_match);
            if ($i < count($matches[0]) - 1) {
                $content_end = $matches[0][$i+1][1];
            } else {
                $content_end = strlen($full_options_text);
            }
            
            $option_content = substr($full_options_text, $content_start, $content_end - $content_start);
            
            // 1. Check for explicit ANSWER_MARKER
            $has_answer_marker = false;
            if (strpos($marker_full_match, '[ANSWER_MARKER]') !== false || strpos($option_content, '[ANSWER_MARKER]') !== false) {
                $has_answer_marker = true;
                $option_content = str_replace('[ANSWER_MARKER]', '', $option_content);
            }
            
            // 2. Check for bold tag
            $has_bold = (preg_match('/<(b|strong)>/i', $marker_full_match) || preg_match('/<(b|strong)>/i', $option_content));
            
            // 3. Check for color tag
            $color = '';
            if (preg_match('/<span[^>]*color:\s*#?([a-zA-Z0-9]+)[^>]*>/i', $marker_full_match, $c_match) || 
                preg_match('/<span[^>]*color:\s*#?([a-zA-Z0-9]+)[^>]*>/i', $option_content, $c_match)) {
                $color = strtolower($c_match[1]);
            }
            
            // Clean up the content
            $cleaned_content = preg_replace('/^[\s\x{00A0}\x{200B}-\x{200D}\x{FEFF}]+|[\s\x{00A0}\x{200B}-\x{200D}\x{FEFF}]+$/u', '', $option_content);
            $cleaned_content = preg_replace('/[\r\n\x{00A0}\x{200B}-\x{200D}\x{FEFF}]+/u', ' ', $cleaned_content);
            $cleaned_content = preg_replace('/\s+/u', ' ', $cleaned_content);
            
            $options_data[$option_letter] = [
                'has_answer_marker' => $has_answer_marker,
                'has_bold' => $has_bold,
                'color' => $color,
                'raw' => $cleaned_content,
                'marker' => $marker_tags
            ];
        }
        
        // Intelligent Answer Determination
        $determined_answer = '';
        
        // Add debug logging
        error_log("Option detection summary: Markers: " . json_encode($options_data));

        // 1. Check for ANSWER_MARKER uniqueness
        $marker_counts = 0; $last_marked = '';
        foreach ($options_data as $let => $data) {
            if ($data['has_answer_marker']) { $marker_counts++; $last_marked = $let; }
        }
        
        error_log("Marker counts: $marker_counts");

        if ($marker_counts === 1) {
            $determined_answer = $last_marked;
        } else {
            // 2. Check for bold uniqueness
            $bold_counts = 0; $last_bold = '';
            foreach ($options_data as $let => $data) {
                if ($data['has_bold']) { $bold_counts++; $last_bold = $let; }
            }
            
            error_log("Bold counts: $bold_counts");

            if ($bold_counts === 1) {
                $determined_answer = $last_bold;
            } else {
                // 3. Check for color uniqueness
                $color_freq = [];
                foreach ($options_data as $let => $data) {
                    if ($data['color'] !== '') {
                        if (!isset($color_freq[$data['color']])) $color_freq[$data['color']] = [];
                        $color_freq[$data['color']][] = $let;
                    }
                }
                $unique_colors = [];
                foreach ($color_freq as $c => $letters) {
                    if (count($letters) === 1) $unique_colors[] = $letters[0];
                }
                
                error_log("Unique colors found: " . count($unique_colors));

                if (count($unique_colors) === 1) {
                    $determined_answer = $unique_colors[0];
                }
            }
        }
        
        error_log("Determined answer for question: " . ($determined_answer ?: 'NONE'));

        
        // Assign determined answer
        if ($determined_answer !== '') {
            $current_question['answer'] = $determined_answer;
        }
        
        // Finalize option texts (stripping answer formatting if applicable)
        foreach ($options_data as $let => $data) {
            $cleaned_content = $data['raw'];
            $marker_tags = $data['marker'];
            
            if ($let === $determined_answer) {
                // Aggressively strip ALL bold or strong tags and color styles from the true answer
                $cleaned_content = preg_replace('/<\/?(b|strong|span|em|i|u)[^>]*>/iu', '', $cleaned_content);
                $marker_tags = ''; // Do not prepend anything for the correct answer
            }
            
            $final_content = preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', $cleaned_content);
            $secondary_marker_pattern = '/^' . $let . '\s*[\.\)]\s*/iu';
            $final_content = preg_replace($secondary_marker_pattern, '', $final_content);
            
            // Prepend formatting tags (like <b>, <i>, <u>) for distractors to preserve original Word styles
            if (!empty($marker_tags)) {
                $final_content = $marker_tags . $final_content;
            }
            
            $current_question['options'][$let] = trim($final_content);
        }
        
    } else {
        // Fallback for unexpected format - try to assign whatever we have to option A
        error_log("Failed to parse options with global pattern. Fallback engaged.");
        $current_question['options']['A'] = trim(implode("\n", $option_lines));
    }
    
    // Add debug information
    error_log("Parsed options: " . json_encode($current_question['options']));
    
    // Ensure all options are set, even if empty
    $required_options = ['A', 'B', 'C', 'D'];
    foreach ($required_options as $option) {
        if (!isset($current_question['options'][$option])) {
            $current_question['options'][$option] = '';
        }
    }
}

function buildImportedQuestion($current_question, $import_type = 'objective') {
    if (empty($current_question['question'])) {
        return null;
    }

    $question_text = trim($current_question['question']);
    $import_type = $import_type === 'theory' ? 'theory' : 'objective';
    $question_type = $import_type;
    $theory_marks = 0;
    $marking_scheme = '';

    if (preg_match('/^\s*(?:type|question\s*type)\s*[:\-]\s*(theory|essay)\s*$/im', $question_text)) {
        $question_type = 'theory';
    }
    if (preg_match('/^\s*(?:type|question\s*type)\s*[:\-]\s*objective\s*$/im', $question_text)) {
        $question_type = 'objective';
    }
    if (preg_match('/^\s*(?:marks|mark|score|theory\s*marks)\s*[:\-]\s*([0-9]+(?:\.[0-9]+)?)\s*$/im', $question_text, $marks_match)) {
        $theory_marks = (float)$marks_match[1];
        if ($theory_marks > 0) {
            $question_type = 'theory';
        }
    }
    if (preg_match('/^\s*(?:marking\s*(?:scheme|guide)|scheme|guide)\s*[:\-]\s*(.*)$/ims', $question_text, $scheme_match)) {
        $marking_scheme = trim($scheme_match[1]);
        $question_type = 'theory';
    }

    $question_text = preg_replace('/^\s*(?:type|question\s*type)\s*[:\-]\s*(?:theory|essay|objective)\s*$/im', '', $question_text);
    $question_text = preg_replace('/^\s*(?:marks|mark|score|theory\s*marks)\s*[:\-]\s*[0-9]+(?:\.[0-9]+)?\s*$/im', '', $question_text);
    $question_text = preg_replace('/^\s*(?:marking\s*(?:scheme|guide)|scheme|guide)\s*[:\-].*$/ims', '', $question_text);
    $question_text = trim($question_text);

    $has_options = isset($current_question['options']['A'], $current_question['options']['B'], $current_question['options']['C'], $current_question['options']['D'])
        && trim(strip_tags($current_question['options']['A'])) !== ''
        && trim(strip_tags($current_question['options']['B'])) !== ''
        && trim(strip_tags($current_question['options']['C'])) !== ''
        && trim(strip_tags($current_question['options']['D'])) !== '';

    if ($question_type !== $import_type) {
        return null;
    }

    if ($question_type === 'theory') {
        if ($question_text === '' || $theory_marks <= 0) {
            return null;
        }

        return [
            'question_type' => 'theory',
            'question' => $question_text,
            'option_a' => '',
            'option_b' => '',
            'option_c' => '',
            'option_d' => '',
            'option_e' => '',
            'option_f' => '',
            'answer' => null,
            'theory_marks' => $theory_marks,
            'marking_scheme' => $marking_scheme
        ];
    }

    if ($question_text !== '' && $has_options && !empty($current_question['answer'])) {
        return [
            'question_type' => 'objective',
            'question' => $question_text,
            'option_a' => $current_question['options']['A'],
            'option_b' => $current_question['options']['B'],
            'option_c' => $current_question['options']['C'],
            'option_d' => $current_question['options']['D'],
            'option_e' => isset($current_question['options']['E']) ? $current_question['options']['E'] : '',
            'option_f' => isset($current_question['options']['F']) ? $current_question['options']['F'] : '',
            'answer' => $current_question['answer'],
            'theory_marks' => null,
            'marking_scheme' => null
        ];
    }

    return null;
}

// Enhanced function to format mathematical content with better Unicode support
function formatMathematicalContent($content) {
    // Use sanitizeHtml to allow safe tags like <img>, then convert newlines
    // Fully decode entities to handle cases where content was stored escaped (even multiple times)
    $content = fullyDecodeHtml($content);
    
    $content = sanitizeHtml($content);
    
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

// Function to clean content copied from WPS Office or other rich text editors
function cleanWpsContent($content) {
    // Remove null bytes and other control characters that might cause issues
    // Be careful not to remove characters that are part of base64 data
    $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
    
    // Normalize line endings
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    
    // Remove any invisible Unicode characters that WPS might insert
    $content = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $content);
    
    // For image content, we need to be more careful with HTML entity decoding
    // For image content, we need to be more careful with HTML entity decoding
    // Always decode entities to ensure we have actual HTML tags
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Trim whitespace but preserve line breaks
    $content = rtrim($content);
    
    return $content;
}

// Function to convert OMML (Office Math Markup Language) to MathML
function convertOMMLtoMathML($node) {
    if (!$node) return '';
    
    $mathml = '';
    
    // Mapping of OMML tags to MathML tags
    $tag_map = [
        'm:oMath' => 'math',
        'm:oMathPara' => 'math',
        'm:f' => 'mfrac',
        'm:num' => 'mrow',
        'm:den' => 'mrow',
        'm:sup' => 'msup',
        'm:sub' => 'msub',
        'm:sPre' => 'mmultiscripts',
        'm:sSub' => 'msub',
        'm:sSup' => 'msup',
        'm:sSubSup' => 'msubsup',
        'm:r' => 'mrow',
        'm:t' => 'mtext',
        'm:d' => 'mrow',
        'm:e' => 'mrow',
        'm:limLow' => 'munder',
        'm:limUpp' => 'mover',
        'm:m' => 'mtable',
        'm:mr' => 'mtr',
        'm:mtd' => 'mtd',
        'm:rad' => 'mroot',
        'm:deg' => 'mrow',
    ];

    $nodeName = $node->nodeName;
    
    // Handle text nodes specially
    if ($nodeName === 'm:t') {
        $text = $node->nodeValue;
        if (preg_match('/^\d+(\.\d+)?$/', trim($text))) {
            $targetTag = 'mn';
        } else if (preg_match('/^[a-zA-Z\x{0370}-\x{03FF}\x{1F00}-\x{1FFF}]$/u', trim($text))) {
            // Handle single letters and Greek characters as identifiers
            $targetTag = 'mi';
        } else if (preg_match('/^[\+\-\*\/\=\(\)\[\]\{\}\>\<]$/', trim($text))) {
            $targetTag = 'mo';
        } else {
            $targetTag = 'mtext';
        }
        return "<$targetTag>" . htmlspecialchars($text) . "</$targetTag>";
    }

    // Handle delimiters (m:d) - opening and closing chars
    if ($nodeName === 'm:d') {
        $open = '(';
        $close = ')';
        foreach ($node->childNodes as $child) {
            if ($child->nodeName === 'm:dPr') {
                foreach ($child->childNodes as $dPrChild) {
                    if ($dPrChild->nodeName === 'm:begChr') {
                        $open = $dPrChild->getAttribute('m:val');
                        if ($open === '') {
                            $open = $dPrChild->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/math', 'val');
                        }
                        if ($open === '') {
                            $open = $dPrChild->getAttribute('val');
                        }
                    }
                    if ($dPrChild->nodeName === 'm:endChr') {
                        $close = $dPrChild->getAttribute('m:val');
                        if ($close === '') {
                            $close = $dPrChild->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/math', 'val');
                        }
                        if ($close === '') {
                            $close = $dPrChild->getAttribute('val');
                        }
                    }
                }
            }
        }
        $mathml .= '<mrow><mo>' . htmlspecialchars($open) . '</mo>';
        foreach ($node->childNodes as $child) {
            if ($child->nodeName === 'm:e') {
                $mathml .= convertOMMLtoMathML($child);
            }
        }
        $mathml .= '<mo>' . htmlspecialchars($close) . '</mo></mrow>';
        return $mathml;
    }

    // Handle n-ary operators (m:nary) like summation, integration
    if ($nodeName === 'm:nary') {
        $chr = '∑'; // Default operator
        $naryPr = null;
        $subNode = null;
        $supNode = null;
        $eNode = null;
        
        foreach ($node->childNodes as $child) {
            if ($child->nodeName === 'm:naryPr') $naryPr = $child;
            if ($child->nodeName === 'm:sub') $subNode = $child;
            if ($child->nodeName === 'm:sup') $supNode = $child;
            if ($child->nodeName === 'm:e') $eNode = $child;
        }
        
        if ($naryPr) {
            foreach ($naryPr->childNodes as $prChild) {
                if ($prChild->nodeName === 'm:chr') {
                    $chr = $prChild->getAttribute('m:val');
                    if ($chr === '') {
                        $chr = $prChild->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/math', 'val');
                    }
                    if ($chr === '') {
                        $chr = $prChild->getAttribute('val');
                    }
                }
            }
        }
        
        $isUnderOver = true;
        if ($chr === '∫' || $chr === '⌠' || $chr === '⌡') {
            $isUnderOver = false;
        }
        
        $limLoc = '';
        if ($naryPr) {
            foreach ($naryPr->childNodes as $prChild) {
                if ($prChild->nodeName === 'm:limLoc') {
                    $limLoc = $prChild->getAttribute('m:val');
                    if ($limLoc === '') {
                        $limLoc = $prChild->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/math', 'val');
                    }
                    if ($limLoc === '') {
                        $limLoc = $prChild->getAttribute('val');
                    }
                }
            }
        }
        if ($limLoc === 'subSup') {
            $isUnderOver = false;
        }
        
        $mathml = '';
        if ($subNode && $supNode) {
            $wrapperTag = $isUnderOver ? 'munderover' : 'msubsup';
            $mathml .= "<$wrapperTag>";
            $mathml .= "<mo>" . htmlspecialchars($chr) . "</mo>";
            $mathml .= convertOMMLtoMathML($subNode);
            $mathml .= convertOMMLtoMathML($supNode);
            $mathml .= "</$wrapperTag>";
        } else if ($subNode) {
            $wrapperTag = $isUnderOver ? 'munder' : 'msub';
            $mathml .= "<$wrapperTag>";
            $mathml .= "<mo>" . htmlspecialchars($chr) . "</mo>";
            $mathml .= convertOMMLtoMathML($subNode);
            $mathml .= "</$wrapperTag>";
        } else if ($supNode) {
            $wrapperTag = $isUnderOver ? 'mover' : 'msup';
            $mathml .= "<$wrapperTag>";
            $mathml .= "<mo>" . htmlspecialchars($chr) . "</mo>";
            $mathml .= convertOMMLtoMathML($supNode);
            $mathml .= "</$wrapperTag>";
        } else {
            $mathml .= "<mo>" . htmlspecialchars($chr) . "</mo>";
        }
        
        if ($eNode) {
            $mathml .= convertOMMLtoMathML($eNode);
        }
        
        return $mathml;
    }

    // Handle roots (m:rad) - distinguish square roots (msqrt) from roots with degrees (mroot)
    if ($nodeName === 'm:rad') {
        $degNode = null;
        $eNode = null;
        foreach ($node->childNodes as $child) {
            if ($child->nodeName === 'm:deg') $degNode = $child;
            if ($child->nodeName === 'm:e') $eNode = $child;
        }
        
        // Check if degree has text content
        $hasDegree = false;
        if ($degNode) {
            $degText = trim($degNode->nodeValue);
            if ($degText !== '') {
                $hasDegree = true;
            }
        }
        
        $mathml = '';
        if ($hasDegree) {
            $mathml .= '<mroot>';
            if ($eNode) {
                $mathml .= convertOMMLtoMathML($eNode);
            } else {
                $mathml .= '<mrow></mrow>';
            }
            $mathml .= convertOMMLtoMathML($degNode);
            $mathml .= '</mroot>';
        } else {
            $mathml .= '<msqrt>';
            if ($eNode) {
                $mathml .= convertOMMLtoMathML($eNode);
            } else {
                $mathml .= '<mrow></mrow>';
            }
            $mathml .= '</msqrt>';
        }
        return $mathml;
    }

    // Fix for nested sup/sub tags in OMML (also handles sub/sup under m:nary to map to mrow)
    if (($nodeName === 'm:sup' || $nodeName === 'm:sub') && $node->parentNode && ($node->parentNode->nodeName === 'm:sup' || $node->parentNode->nodeName === 'm:sub' || $node->parentNode->nodeName === 'm:sSup' || $node->parentNode->nodeName === 'm:sSub' || $node->parentNode->nodeName === 'm:sSubSup' || $node->parentNode->nodeName === 'm:nary')) {
        $targetTag = 'mrow';
    } else {
        $targetTag = isset($tag_map[$nodeName]) ? $tag_map[$nodeName] : '';
    }
    
    if ($targetTag) {
        $attrs = '';
        if ($targetTag === 'math') {
            $attrs = ' xmlns="http://www.w3.org/1998/Math/MathML" display="inline"';
        }
        $mathml .= "<$targetTag$attrs>";
    }

    foreach ($node->childNodes as $child) {
        if ($child->nodeType === XML_ELEMENT_NODE && $child->nodeName !== 'm:dPr') {
            $mathml .= convertOMMLtoMathML($child);
        }
    }

    if ($targetTag) {
        $mathml .= "</$targetTag>";
    }

    return $mathml;
}

// Function to extract text from Word documents with formatting preservation
function extractTextFromWordDocument($file_path, $extension) {
    if ($extension === 'docx') {
        if (!class_exists('ZipArchive')) {
            error_log("ZipArchive class not available for DOCX processing");
            return file_get_contents($file_path);
        }
        
        $zip = new ZipArchive();
        if ($zip->open($file_path) === TRUE) {
            $xml_content = $zip->getFromName('word/document.xml');
            
            if ($xml_content === false) {
                $zip->close();
                return false;
            }

            $dom = new DOMDocument();
            // Suppress errors for malformed XML
            @$dom->loadXML($xml_content);
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/officeDocument/2006/math');
            $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $xpath->registerNamespace('wp', 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');
            $xpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');

            // Load relationships to map image IDs to filenames
            $rels_content = $zip->getFromName('word/_rels/document.xml.rels');
            $image_map = [];
            if ($rels_content) {
                $rels_dom = new DOMDocument();
                @$rels_dom->loadXML($rels_content);
                $rels_xpath = new DOMXPath($rels_dom);
                $rels_xpath->registerNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');
                $rels = $rels_xpath->query('//rel:Relationship');
                foreach ($rels as $rel) {
                    $image_map[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
                }
            }

            $html_content = "";
            // Query for both paragraphs and math containers (exclude raw inline m:oMath to avoid double-processing)
            $elements = $xpath->query('//w:p | //m:oMathPara');

            foreach ($elements as $element) {
                if ($element->nodeName === 'm:oMathPara') {
                    $html_content .= convertOMMLtoMathML($element) . "\n";
                    continue;
                }

                $p_text = "";
                // Get runs, drawings, and math blocks inside paragraph
                $runs = $xpath->query('.//w:r | .//m:oMath | .//w:drawing | .//w:pict', $element);
                $all_bold = true;
                $all_colored = true;
                $has_text = false;
                $unbolded_text = "";
                $uncolored_text = "";
                
                // Check paragraph-level run properties for inherited formatting
                $p_is_bold = false;
                $p_is_italic = false;
                $p_is_colored = false;
                $p_color_val = '';
                $pPr = $xpath->query('.//w:pPr', $element)->item(0);
                if ($pPr) {
                    $p_rPr = $xpath->query('.//w:rPr', $pPr)->item(0);
                    if ($p_rPr) {
                        $p_b = $xpath->query('.//w:b | .//w:bCs', $p_rPr)->item(0);
                        if ($p_b && (!$p_b->hasAttribute('w:val') || !in_array($p_b->getAttribute('w:val'), ['0', 'false', 'none']))) {
                            $p_is_bold = true;
                        }
                        $p_i = $xpath->query('.//w:i | .//w:iCs', $p_rPr)->item(0);
                        if ($p_i && (!$p_i->hasAttribute('w:val') || !in_array($p_i->getAttribute('w:val'), ['0', 'false', 'none']))) {
                            $p_is_italic = true;
                        }
                        $p_color = $xpath->query('.//w:color', $p_rPr)->item(0);
                        if ($p_color && $p_color->hasAttribute('w:val')) {
                            $c_val = strtolower($p_color->getAttribute('w:val'));
                            if ($c_val !== 'auto' && $c_val !== '000000' && $c_val !== 'black') {
                                $p_is_colored = true;
                                $p_color_val = $c_val;
                            }
                        }
                    }
                }

                foreach ($runs as $r) {
                    if ($r->nodeName === 'm:oMath') {
                        $p_text .= convertOMMLtoMathML($r);
                        continue;
                    }

                    // Handle Drawings/Images
                    if ($r->nodeName === 'w:drawing' || $r->nodeName === 'w:pict') {
                        $blip = $xpath->query('.//a:blip/@r:embed | .//v:imagedata/@r:id', $r)->item(0);
                        if ($blip) {
                            $rId = $blip->nodeValue;
                            if (isset($image_map[$rId])) {
                                $image_path = 'word/' . $image_map[$rId];
                                $image_data = $zip->getFromName($image_path);
                                if ($image_data) {
                                    $ext = pathinfo($image_map[$rId], PATHINFO_EXTENSION);
                                    // Only support web-safe images
                                    if (in_array(strtolower($ext), ['png', 'jpg', 'jpeg', 'gif', 'svg'])) {
                                        $filename = 'img_' . uniqid() . '.' . $ext;
                                        $upload_dir = '../uploads/questions/';
                                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                                        file_put_contents($upload_dir . $filename, $image_data);
                                        $p_text .= '<img src="../uploads/questions/' . $filename . '" class="img-fluid my-2 d-block" alt="Question Image">';
                                    }
                                }
                            }
                        }
                        continue;
                    }

                    $has_text_node = false;
                    $texts = $xpath->query('.//w:t', $r);
                    if ($texts->length > 0) {
                        $has_text_node = true;
                    }
                    
                    $r_text = "";
                    
                    // Handle tabs and line breaks inside runs, even if there's no text
                    $tabs = $xpath->query('.//w:tab', $r);
                    for ($i = 0; $i < $tabs->length; $i++) {
                        $r_text .= "\t";
                    }
                    
                    $brs = $xpath->query('.//w:br', $r);
                    for ($i = 0; $i < $brs->length; $i++) {
                        $r_text .= "\n";
                    }

                    if (!$has_text_node && $r_text === "") {
                        continue;
                    }
                    
                    $has_text = true;

                    foreach ($texts as $t) {
                        $r_text .= $t->nodeValue;
                    }

                    // Preserve leading/trailing spaces for the run but trim for tag insertion
                    $leading_spaces = "";
                    if (preg_match('/^(\s+)/', $r_text, $sp_matches)) {
                        $leading_spaces = $sp_matches[1];
                    }
                    $trailing_spaces = "";
                    if (preg_match('/(\s+)$/', $r_text, $sp_matches)) {
                        $trailing_spaces = $sp_matches[1];
                    }
                    $inner_text = trim($r_text);

                    $is_r_bold = $p_is_bold;
                    $is_r_colored = $p_is_colored;
                    $r_color_val = $p_color_val;

                    // Check for formatting in run properties (w:rPr)
                    $rPr = $xpath->query('.//w:rPr', $r)->item(0);
                    if ($rPr) {
                        // Color check
                        $color = $xpath->query('.//w:color', $rPr)->item(0);
                        if ($color && $color->hasAttribute('w:val')) {
                            $color_val = strtolower($color->getAttribute('w:val'));
                            if ($color_val !== 'auto' && $color_val !== '000000' && $color_val !== 'black') {
                                $is_r_colored = true;
                                $r_color_val = $color_val;
                            } else {
                                $is_r_colored = false; // Override inherited
                            }
                        }
                        
                        // Bold check (update inner_text)
                        $b = $xpath->query('.//w:b | .//w:bCs', $rPr)->item(0);
                        if ($b) {
                            if (!$b->hasAttribute('w:val') || !in_array($b->getAttribute('w:val'), ['0', 'false', 'none'])) {
                                $is_r_bold = true;
                            } else {
                                $is_r_bold = false; // Override inherited
                            }
                        }
                        
                        // Italic check (update inner_text)
                        $i = $xpath->query('.//w:i | .//w:iCs', $rPr)->item(0);
                        $is_r_italic = $p_is_italic;
                        if ($i) {
                            if (!$i->hasAttribute('w:val') || !in_array($i->getAttribute('w:val'), ['0', 'false', 'none'])) {
                                $is_r_italic = true;
                            } else {
                                $is_r_italic = false; // Override inherited
                            }
                        }
                        if ($is_r_italic && $inner_text !== '') {
                            $inner_text = "<i>" . $inner_text . "</i>";
                        }

                        // Underline check (update inner_text)
                        if ($xpath->query('.//w:u', $rPr)->length > 0) {
                            if ($inner_text !== '') $inner_text = "<u>" . $inner_text . "</u>";
                        }
                    } else {
                        // No run properties, use inherited italic
                        if ($p_is_italic && $inner_text !== '') {
                            $inner_text = "<i>" . $inner_text . "</i>";
                        }
                    }

                    if ($is_r_bold && $inner_text !== '') {
                        $inner_text = "<b>" . $inner_text . "</b>";
                    }
                    if ($is_r_colored && $inner_text !== '') {
                        $inner_text = '<span style="color:#' . $r_color_val . '">' . $inner_text . '</span>';
                    }

                    if (trim($r_text) !== '') {
                        if (!$is_r_bold) {
                            $all_bold = false;
                            $unbolded_text .= $r_text;
                        }
                        if (!$is_r_colored) {
                            $all_colored = false;
                            $uncolored_text .= $r_text;
                        }
                    }
                    
                    if ($inner_text !== '') {
                        $p_text .= $leading_spaces . $inner_text . $trailing_spaces;
                    } else {
                        $p_text .= $r_text; // just spaces
                    }
                }

                // Check for automatic numbering
                $pPr = $xpath->query('.//w:pPr', $element)->item(0);
                if ($pPr) {
                    $numPr = $xpath->query('.//w:numPr', $pPr)->item(0);
                    if ($numPr) {
                        $ilvl = $xpath->query('.//w:ilvl', $numPr)->item(0);
                        if ($ilvl) {
                            $level = (int)$ilvl->getAttribute('w:val');
                            $plain_text = trim(strip_tags($p_text));
                            if ($level === 0 && !preg_match('/^\d+[\.\)]/i', $plain_text) && !preg_match('/^Question\s*\d+/i', $plain_text)) {
                                $p_text = "1. " . $p_text; 
                            } else if ($level === 1 && !preg_match('/^[A-F]\s*[\.\)]/i', $plain_text)) {
                                $p_text = "A. " . $p_text;
                            }
                        }
                    }
                }

                $is_option = preg_match('/^(?:<[^>]+>|\s)*[A-F](?:<[^>]+>|\s)*[\.\)]/i', trim($p_text));
                $is_essentially_all_bold = $all_bold || preg_match('/^\s*[A-F]\s*[\.\)]\s*$/i', trim($unbolded_text));
                $is_essentially_all_colored = $all_colored || preg_match('/^\s*[A-F]\s*[\.\)]\s*$/i', trim($uncolored_text));

                if ($has_text && $is_option && ($is_essentially_all_bold || $is_essentially_all_colored)) {
                    if (strpos($p_text, '[ANSWER_MARKER]') === false) {
                        $p_text = "[ANSWER_MARKER]" . $p_text;
                    }
                }
                
                $html_content .= $p_text . "\n";
            }
            $zip->close();
            return $html_content;
        }
    } else if ($extension === 'doc') {
        // Basic extraction for old .doc files (best effort)
        $file_content = file_get_contents($file_path);
        $lines = explode(chr(13), $file_content);
        $outtext = "";
        foreach ($lines as $thisline) {
            $pos = strpos($thisline, chr(0));
            if (($pos !== false) || (strlen($thisline) == 0)) {
            } else {
                $outtext .= $thisline . " ";
            }
        }
        $outtext = preg_replace("/[^a-zA-Z0-9\s\,\.\-\n\r\t@\/\_\(\)]/", "", $outtext);
        return $outtext;
    }
    
    return file_get_contents($file_path);
}

// Function to ensure content is in UTF-8 encoding
function ensureUtf8Encoding($content) {
    // Check if content is already UTF-8
    if (mb_check_encoding($content, 'UTF-8')) {
        return $content;
    }
    
    // Try to detect and convert encoding
    $encodings = ['Windows-1252', 'ISO-8859-1'];
    foreach ($encodings as $encoding) {
        if (mb_check_encoding($content, $encoding)) {
            return mb_convert_encoding($content, 'UTF-8', $encoding);
        }
    }
    
    // Fallback to ISO-8859-1 if all else fails
    return mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
}

// Function to fully decode HTML entities (handles double/triple encoding)
function fullyDecodeHtml($content) {
    $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // If decoding changed nothing, we're done
    if ($decoded === $content) {
        return $content;
    }
    // If it still has encoded tags, decode again (limit to 3 levels to prevent infinite loops)
    $levels = 0;
    while ($decoded !== $content && $levels < 3) {
        $content = $decoded;
        $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $levels++;
    }
    return $decoded;
}

// Function to sanitize HTML content while allowing safe tags like images
function sanitizeHtml($text) {
    // Allow only safe HTML tags (like img, p, br, strong, em, b, i, u)
    // Added MathML tags support
    $allowed_tags = '<p><br><strong><em><b><i><u><img><span><div><ul><ol><li><h1><h2><h3><h4><h5><h6><math><mfrac><mi><mn><mo><msup><msub><mrow><msqrt><mroot><annotation><mstyle><merror><mpadded><mphantom><mfenced><menclose><mtext><munderover><munder><mover><msubsup><mmultiscripts><mtable><mtr><mtd>';
    $text = strip_tags($text, $allowed_tags);
    
    // Additional security: Remove any javascript or on* attributes
    $text = preg_replace('/(<[^>]+) on[a-zA-Z]*="[^"]*"/i', '$1', $text);
    $text = preg_replace("/(<[^>]+) on[a-zA-Z]*='[^']*'/i", '$1', $text);
    
    // Remove color styles that might reveal answers
    $text = preg_replace('/style="[^"]*color:[^;"]*;?[^"]*"/i', '', $text);
    $text = preg_replace("/style='[^']*color:[^;']* ;?[^']*'/i", '', $text);
    
    return $text;
}



?>

<?php include '../includes/header.php'; ?>

<!-- TinyMCE is loaded via tinymce_setup.php -->
<?php require_once '../includes/tinymce_setup.php'; ?>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><?php echo htmlspecialchars($exam_subject['exam_title']); ?> - Questions</h4>
                <a href="dashboard.php" class="btn btn-light btn-sm">Back to Dashboard</a>
            </div>
            <div class="card-body">
                <p><strong>Subject:</strong> <?php echo htmlspecialchars($exam_subject['subject_name']); ?> | <strong>Class:</strong> <?php echo htmlspecialchars($exam_subject['class_name']); ?></p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <ul class="nav nav-tabs mb-4" id="managementTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="list-tab" data-bs-toggle="tab" href="#list" role="tab">Questions List</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="add-tab" data-bs-toggle="tab" href="#add" role="tab">Add Question</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="passages-tab" data-bs-toggle="tab" href="#passages" role="tab">Comprehension Passages</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="import-tab" data-bs-toggle="tab" href="#import" role="tab">Import Questions</a>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Questions List Tab -->
            <div class="tab-pane fade show active" id="list" role="tabpanel">
                <?php if (count($questions) > 0): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5>Question Bank (<?php echo count($questions); ?>)</h5>
                        <form method="POST" onsubmit="return confirm('Warning: This will delete ALL questions in this subject. Continue?')">
                            <input type="hidden" name="action" value="delete_all">
                            <button type="submit" class="btn btn-sm btn-danger">Clear All Questions</button>
                        </form>
                    </div>
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="card mb-3 question-container">
                            <div class="card-header d-flex justify-content-between align-items-center py-2 bg-light">
                                <span class="fw-bold">
                                    Question <?php echo $index + 1; ?>
                                    <span class="badge bg-<?php echo (($question['question_type'] ?? 'objective') === 'theory') ? 'warning text-dark' : 'primary'; ?> ms-2">
                                        <?php echo ucfirst($question['question_type'] ?? 'objective'); ?>
                                    </span>
                                </span>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-question" 
                                            data-id="<?php echo $question['id']; ?>"
                                            data-text="<?php echo base64_encode($question['question_text']); ?>"
                                            data-a="<?php echo base64_encode($question['option_a']); ?>"
                                            data-b="<?php echo base64_encode($question['option_b']); ?>"
                                            data-c="<?php echo base64_encode($question['option_c']); ?>"
                                            data-d="<?php echo base64_encode($question['option_d']); ?>"
                                            data-e="<?php echo !empty($question['option_e']) ? base64_encode($question['option_e']) : ''; ?>"
                                            data-f="<?php echo !empty($question['option_f']) ? base64_encode($question['option_f']) : ''; ?>"
                                            data-correct="<?php echo $question['correct_answer']; ?>"
                                            data-type="<?php echo htmlspecialchars($question['question_type'] ?? 'objective'); ?>"
                                            data-marks="<?php echo htmlspecialchars($question['theory_marks'] ?? ''); ?>"
                                            data-scheme="<?php echo !empty($question['marking_scheme']) ? base64_encode($question['marking_scheme']) : ''; ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this question?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i> Delete</button>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="question-text mb-3">
                                    <?php echo formatMathematicalContent($question['question_text']); ?>
                                </div>
                                <?php if (($question['question_type'] ?? 'objective') === 'theory'): ?>
                                    <div class="alert alert-warning mb-0">
                                        <div class="fw-bold mb-2">Total Marks: <?php echo number_format((float)($question['theory_marks'] ?? 0), 2); ?></div>
                                        <?php if (!empty($question['marking_scheme'])): ?>
                                            <div class="small text-muted mb-1">Marking guide / steps</div>
                                            <div><?php echo formatMathematicalContent($question['marking_scheme']); ?></div>
                                        <?php else: ?>
                                            <div class="text-muted">No marking guide added.</div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                <div class="row g-2">
                                    <?php 
                                    $options = ['A' => $question['option_a'] ?? null, 'B' => $question['option_b'] ?? null, 'C' => $question['option_c'] ?? null, 'D' => $question['option_d'] ?? null, 'E' => $question['option_e'] ?? null, 'F' => $question['option_f'] ?? null];
                                    foreach ($options as $label => $val): 
                                        if (empty($val)) continue;
                                        $is_correct = ($question['correct_answer'] == $label);
                                    ?>
                                        <div class="col-md-6">
                                            <div class="p-2 border rounded <?php echo $is_correct ? 'bg-success text-white' : 'bg-light'; ?>">
                                                <strong><?php echo $label; ?>:</strong> <?php echo formatMathematicalContent($val); ?>
                                                <?php if ($is_correct): ?> <i class="fas fa-check-circle ms-1"></i> <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <p class="text-muted">No questions found. Add them manually or import from a file.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Add/Edit Question Tab -->
            <div class="tab-pane fade" id="add" role="tabpanel">
                <div class="card" id="questionFormCard">
                    <div class="card-header">
                        <h5 class="mb-0" id="questionFormTitle">Add New Question</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="questionForm">
                            <input type="hidden" name="action" id="question_action" value="add">
                            <input type="hidden" name="question_id" id="form_question_id" value="">
                            
                            <div class="mb-3">
                                <label class="form-label">Question Text</label>
                                <textarea class="form-control" name="question_text" id="question_text" rows="4" data-cke-auto-init></textarea>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Question Type</label>
                                    <select class="form-select" name="question_type" id="question_type">
                                        <option value="objective">Objective</option>
                                        <option value="theory">Theory / Essay</option>
                                    </select>
                                </div>
                                <div class="col-md-4 theory-only d-none">
                                    <label class="form-label">Total Marks</label>
                                    <input type="number" class="form-control" name="theory_marks" id="theory_marks" min="0.01" step="0.01" placeholder="e.g. 10">
                                </div>
                            </div>

                            <div class="row g-3 mb-3 objective-only">
                                <div class="col-md-6">
                                    <label class="form-label">Option A</label>
                                    <textarea class="form-control" name="option_a" id="option_a" rows="2" data-cke-auto-init></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Option B</label>
                                    <textarea class="form-control" name="option_b" id="option_b" rows="2" data-cke-auto-init></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Option C</label>
                                    <textarea class="form-control" name="option_c" id="option_c" rows="2" data-cke-auto-init></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Option D</label>
                                    <textarea class="form-control" name="option_d" id="option_d" rows="2" data-cke-auto-init></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Option E (Optional)</label>
                                    <textarea class="form-control" name="option_e" id="option_e" rows="2" data-cke-auto-init></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Option F (Optional)</label>
                                    <textarea class="form-control" name="option_f" id="option_f" rows="2" data-cke-auto-init></textarea>
                                </div>
                            </div>

                            <div class="mb-3 theory-only d-none">
                                <label class="form-label">Marking Guide / Steps (Optional)</label>
                                <textarea class="form-control" name="marking_scheme" id="marking_scheme" rows="4" data-cke-auto-init></textarea>
                            </div>

                            <div class="row align-items-end">
                                <div class="col-md-4 objective-only">
                                    <label class="form-label">Correct Answer</label>
                                    <select class="form-select" name="correct_answer" id="correct_answer" required>
                                        <option value="">Select Correct Option</option>
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="D">D</option>
                                        <option value="E">E</option>
                                        <option value="F">F</option>
                                    </select>
                                </div>
                                <div class="col-md-8 text-end">
                                    <button type="button" id="cancelQuestionEdit" class="btn btn-secondary d-none me-2">Cancel Edit</button>
                                    <button type="submit" name="submit_question" id="submitQuestionBtn" class="btn btn-primary">Add Question</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Passages Tab -->
            <div class="tab-pane fade" id="passages" role="tabpanel">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Manage Comprehension Passages</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="needs-validation" onsubmit="return saveCKEditorContent();">
                            <input type="hidden" name="action" value="save_passage">
                            <input type="hidden" name="passage_id" id="passage_id" value="">
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Passage Title</label>
                                    <input type="text" class="form-control" name="passage_title" id="passage_title" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Start Question #</label>
                                    <input type="number" class="form-control" name="start_question" id="start_question" value="1" min="1" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">End Question #</label>
                                    <input type="number" class="form-control" name="end_question" id="end_question" value="5" min="1" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Passage Content</label>
                                <textarea class="form-control" name="passage_content" id="passage_content" rows="6" data-cke-auto-init></textarea>
                            </div>

                            <div class="d-flex justify-content-between">
                                <button type="button" id="cancel_edit" class="btn btn-secondary">Clear Form</button>
                                <button type="submit" class="btn btn-primary">Save Passage</button>
                            </div>
                        </form>
                    </div>
                </div>

                <h6>Current Passages</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="passagesTable">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Range</th>
                                <th>Content Preview</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($comprehension_passages)): ?>
                                <?php foreach ($comprehension_passages as $passage): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($passage['passage_title']); ?></td>
                                        <td>Q<?php echo $passage['start_question_number']; ?> - Q<?php echo $passage['end_question_number']; ?></td>
                                        <td><?php echo substr(strip_tags(fullyDecodeHtml($passage['passage_content'])), 0, 100); ?>...</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info text-white edit-passage" 
                                                    data-id="<?php echo $passage['id']; ?>"
                                                    data-title="<?php echo htmlspecialchars($passage['passage_title']); ?>"
                                                    data-content="<?php echo base64_encode(fullyDecodeHtml($passage['passage_content'])); ?>"
                                                    data-start="<?php echo $passage['start_question_number']; ?>"
                                                    data-end="<?php echo $passage['end_question_number']; ?>">
                                                Edit
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this passage?')">
                                                <input type="hidden" name="action" value="delete_passage">
                                                <input type="hidden" name="passage_id" value="<?php echo $passage['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">No passages added yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Import Tab -->
            <div class="tab-pane fade" id="import" role="tabpanel">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Bulk Import Questions</h5>
                    </div>
                    <div class="card-body py-4">
                        <p class="text-muted">Import objective and theory questions separately. Objective questions use the class mark per question; theory questions use the MARKS value written on each imported question.</p>

                        <div class="row g-4">
                            <div class="col-lg-6">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="fw-bold mb-3"><i class="fas fa-list-check me-1"></i> Objective Import</h6>
                                    <form method="POST" enctype="multipart/form-data" class="mb-3">
                                        <input type="hidden" name="action" value="import">
                                        <input type="hidden" name="import_type" value="objective">
                                        <div class="input-group mb-3">
                                            <input type="file" class="form-control" name="questions_file" required>
                                            <button class="btn btn-primary" type="submit">Import Objectives</button>
                                        </div>
                                        <div class="d-flex flex-wrap gap-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="prevent_duplicates" checked>
                                                <label class="form-check-label small">Prevent Duplicates</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="clear_existing">
                                                <label class="form-check-label small text-danger">Clear Existing Before Import</label>
                                            </div>
                                        </div>
                                    </form>

                                    <div class="alert alert-info text-start mb-0">
                                        <h6>Objective Format</h6>
                                        <pre class="mb-0">1. What is the capital of France?
A. London
B. Paris
C. Berlin
D. Madrid
ANSWER: B</pre>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="fw-bold mb-3"><i class="fas fa-pen-nib me-1"></i> Theory Import</h6>
                                    <form method="POST" enctype="multipart/form-data" class="mb-3">
                                        <input type="hidden" name="action" value="import">
                                        <input type="hidden" name="import_type" value="theory">
                                        <div class="input-group mb-3">
                                            <input type="file" class="form-control" name="questions_file" required>
                                            <button class="btn btn-warning" type="submit">Import Theory</button>
                                        </div>
                                        <div class="d-flex flex-wrap gap-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="prevent_duplicates" checked>
                                                <label class="form-check-label small">Prevent Duplicates</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="clear_existing">
                                                <label class="form-check-label small text-danger">Clear Existing Before Import</label>
                                            </div>
                                        </div>
                                    </form>

                                    <div class="alert alert-warning text-start mb-0">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0">Theory Format</h6>
                                            <a href="../sample_theory_questions.txt" class="btn btn-sm btn-outline-dark" download>
                                                <i class="fas fa-download me-1"></i>Sample
                                            </a>
                                        </div>
                                        <pre class="mb-0">1. Explain photosynthesis.
MARKS: 10
MARKING SCHEME: Award 2 marks for sunlight, 2 for chlorophyll, 2 for carbon dioxide and water, 2 for glucose, and 2 for oxygen.

2. State three causes of soil erosion.
MARKS: 6
MARKING SCHEME: Award 2 marks for each correct cause explained.</pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Preview Modal -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Image Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="previewImage" src="" alt="Preview" style="max-width: 100%; height: auto;">
                <div class="mt-3 text-muted small">
                    Click "Insert Image" to add this picture to your passage.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmInsertImageBtn">
                    <i class="fas fa-check me-1"></i> Insert Image
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Shared Robust Base64 Decoding for UTF-8
    const decodeBase64 = (str) => {
        if (!str) return '';
        try {
            const binary = atob(str);
            const bytes = new Uint8Array(binary.length);
            for (let i = 0; i < binary.length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }
            return new TextDecoder('utf-8').decode(bytes);
        } catch (e) {
            try { return decodeURIComponent(escape(atob(str))); } catch (err) {
                try { return atob(str); } catch (f) { return ''; }
            }
        }
    };

    // Helper to set editor content (TinyMCE or CKEditor)
    const setEditorContent = (fieldId, value) => {
        const textarea = document.getElementById(fieldId);
        if (textarea) textarea.value = value;
        
        if (window.tinymce && tinymce.get(fieldId)) {
            tinymce.get(fieldId).setContent(value);
        }
        if (window.ckEditors && window.ckEditors[fieldId]) {
            window.ckEditors[fieldId].setData(value);
        }
        // Legacy window.editor support for passages
        if (fieldId === 'passage_content' && window.editor) {
            window.editor.setData(value);
        }
    };

    // --- Question Management Logic ---
    const questionForm = document.getElementById('questionForm');
    const questionAction = document.getElementById('question_action');
    const formQuestionId = document.getElementById('form_question_id');
    const questionFormTitle = document.getElementById('questionFormTitle');
    const submitQuestionBtn = document.getElementById('submitQuestionBtn');
    const cancelQuestionEdit = document.getElementById('cancelQuestionEdit');
    const questionTypeSelect = document.getElementById('question_type');
    const correctAnswerSelect = document.getElementById('correct_answer');
    const theoryMarksInput = document.getElementById('theory_marks');

    function syncQuestionTypeFields() {
        const isTheory = questionTypeSelect && questionTypeSelect.value === 'theory';
        document.querySelectorAll('.objective-only').forEach(el => el.classList.toggle('d-none', isTheory));
        document.querySelectorAll('.theory-only').forEach(el => el.classList.toggle('d-none', !isTheory));
        if (correctAnswerSelect) correctAnswerSelect.required = !isTheory;
        if (theoryMarksInput) theoryMarksInput.required = isTheory;
    }

    if (questionTypeSelect) {
        questionTypeSelect.addEventListener('change', syncQuestionTypeFields);
        syncQuestionTypeFields();
    }

    // Use Event Delegation for Edit Question Buttons
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.edit-question');
        if (!btn) return;

        const id = btn.dataset.id;
        const text = decodeBase64(btn.dataset.text);
        const a = decodeBase64(btn.dataset.a);
        const b = decodeBase64(btn.dataset.b);
        const c = decodeBase64(btn.dataset.c);
        const d = decodeBase64(btn.dataset.d);
        const e_val = btn.dataset.e ? decodeBase64(btn.dataset.e) : '';
        const f_val = btn.dataset.f ? decodeBase64(btn.dataset.f) : '';
        const correct = btn.dataset.correct;
        const type = btn.dataset.type || 'objective';
        const marks = btn.dataset.marks || '';
        const scheme = btn.dataset.scheme ? decodeBase64(btn.dataset.scheme) : '';

        // Switch to the Add/Edit tab IMMEDIATELY
        const addTabLink = document.getElementById('add-tab');
        if (addTabLink) {
            if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                bootstrap.Tab.getOrCreateInstance(addTabLink).show();
            } else {
                addTabLink.click();
            }
        }

        // Set form to edit mode
        setTimeout(() => {
            questionAction.value = 'edit';
            formQuestionId.value = id;
            questionFormTitle.innerHTML = '<i class="fas fa-edit text-primary me-2"></i>Edit Question';
            submitQuestionBtn.innerHTML = '<i class="fas fa-save me-1"></i> Update Question';
            if (cancelQuestionEdit) cancelQuestionEdit.classList.remove('d-none');

            // Populate fields
            setEditorContent('question_text', text);
            setEditorContent('option_a', a);
            setEditorContent('option_b', b);
            setEditorContent('option_c', c);
            setEditorContent('option_d', d);
            setEditorContent('option_e', e_val);
            setEditorContent('option_f', f_val);
            setEditorContent('marking_scheme', scheme);

            if (questionTypeSelect) questionTypeSelect.value = type;
            if (theoryMarksInput) theoryMarksInput.value = marks;
            if (correctAnswerSelect) correctAnswerSelect.value = correct || '';
            syncQuestionTypeFields();

            const formCard = document.getElementById('questionFormCard');
            if (formCard) formCard.scrollIntoView({ behavior: 'smooth' });
        }, 50);
    });

    if (cancelQuestionEdit) {
        cancelQuestionEdit.addEventListener('click', function() {
            questionAction.value = 'add';
            formQuestionId.value = '';
            questionFormTitle.innerHTML = '<i class="fas fa-plus-circle text-primary me-2"></i>Add New Question';
            submitQuestionBtn.innerHTML = '<i class="fas fa-plus me-1"></i> Add Question';
            this.classList.add('d-none');
            questionForm.reset();
            ['question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'option_f', 'marking_scheme'].forEach(id => setEditorContent(id, ''));
            if (questionTypeSelect) questionTypeSelect.value = 'objective';
            syncQuestionTypeFields();
        });
    }

    // --- Passage Management Logic ---
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.edit-passage');
        if (!btn) return;

        const id = btn.dataset.id;
        const title = btn.dataset.title;
        const content = decodeBase64(btn.dataset.content);
        const start = btn.dataset.start;
        const end = btn.dataset.end;

        document.getElementById('passage_id').value = id;
        document.getElementById('passage_title').value = title;
        setEditorContent('passage_content', content);
        document.getElementById('start_question').value = start;
        document.getElementById('end_question').value = end;

        document.querySelector('#passage_id').closest('form').scrollIntoView({ behavior: 'smooth' });
    });

    const cancelPassageBtn = document.getElementById('cancel_edit');
    if (cancelPassageBtn) {
        cancelPassageBtn.addEventListener('click', function() {
            document.getElementById('passage_id').value = '';
            document.getElementById('passage_title').value = '';
            setEditorContent('passage_content', '');
            document.getElementById('start_question').value = '1';
            document.getElementById('end_question').value = '1';
        });
    }

    // --- AJAX Passage Saving ---
    const passageForm = document.querySelector('form.needs-validation');
    if (passageForm) {
        passageForm.addEventListener('submit', function(e) {
            const actionInput = this.querySelector('input[name="action"]');
            if (actionInput && actionInput.value === 'save_passage') {
                e.preventDefault();
                
                // Final sync for editors
                if (window.tinymce && tinymce.get('passage_content')) tinymce.get('passage_content').save();
                
                const formData = new FormData(this);
                formData.append('exam_subject_id', <?php echo $exam_subject['exam_subject_id'] ?? 0; ?>);
                
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';
                submitBtn.disabled = true;
                
                fetch('../fix_passage_save.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        cancelPassageBtn.click();
                        if (data.passages) updatePassagesList(data.passages);
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(err => alert('Error saving passage: ' + err.message))
                .finally(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            }
        });
    }

    window.updatePassagesList = function(passages) {
        const tbody = document.querySelector('#passagesTable tbody');
        if (!tbody) return;
        tbody.innerHTML = '';
        passages.forEach(p => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${p.passage_title || 'Untitled'}</td>
                <td>Q${p.start_question_number} - Q${p.end_question_number}</td>
                <td>${p.passage_content.replace(/<[^>]+>/g, '').substring(0, 100)}...</td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-primary edit-passage" 
                            data-id="${p.id}" data-title="${p.passage_title || ''}"
                            data-content="${btoa(p.passage_content)}"
                            data-start="${p.start_question_number}" data-end="${p.end_question_number}">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Remove this passage?')">
                        <input type="hidden" name="action" value="delete_passage">
                        <input type="hidden" name="passage_id" value="${p.id}">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            `;
            tbody.appendChild(row);
        });
    };
});
</script>

<?php include '../includes/footer.php'; ?>
