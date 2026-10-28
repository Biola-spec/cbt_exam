<?php
require_once '../config/database.php';
require_once '../config/session.php'; // Added this to ensure session functions are available

$public_exam_access = isPublicExamSession();
if ((!$public_exam_access && !isLoggedIn()) || getUserRole() !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Prevent browser caching to handle back button after submission
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

// Session-level block to prevent back button immediately after submission
$exam_subject_id = isset($_GET['exam_subject_id']) ? (int)$_GET['exam_subject_id'] : 0;
if ($exam_subject_id && isset($_SESSION['submitted_exam_id']) && $_SESSION['submitted_exam_id'] == $exam_subject_id) {
    header("Location: exam_completion.php?attempt_id=" . ($_SESSION['last_attempt_id'] ?? 0));
    exit();
}

// Simple cache for exam data
static $exam_cache = [];
static $exam_cache_time = [];
static $cache_expiry = 300; // 5 minutes

$error = '';
$exam_subject = null;
$questions = [];
$answers = [];
$current_question_index = isset($_GET['q']) ? (int)$_GET['q'] : 0;
$time_limit = 60; // This will be updated with the actual time limit from exam_subject
$time_remaining = 0;
$comprehension_passages = []; // Changed to array to store multiple passages
$current_passage = null; // Store the current passage for this question

// Function to sanitize HTML content while allowing safe tags like images
function sanitizeHtml($text) {
    // Allow only safe HTML tags (like img, p, br, strong, em, b, i, u)
    $allowed_tags = '<p><br><strong><em><b><i><u><img><span><div><ul><ol><li><h1><h2><h3><h4><h5><h6>';
    $text = strip_tags($text, $allowed_tags);
    
    // Additional security: Remove any javascript or on* attributes
    $text = preg_replace('/(<[^>]+) on[a-zA-Z]*="[^"]*"/i', '$1', $text);
    $text = preg_replace("/(<[^>]+) on[a-zA-Z]*='[^']*'/i", '$1', $text);
    
    return $text;
}

// Function to ensure proper UTF-8 encoding for exam content
function ensureUtf8Encoding($content) {
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

// Function to fully decode HTML entities (multiple levels if needed)
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

// Function to aggressively clean options for display
function cleanOptionDisplay($text) {
    $text = fullyDecodeHtml($text);
    $text = ensureUtf8Encoding($text);
    $text = sanitizeHtml($text);
    // Aggressive Unicode-aware trim and whitespace collapse
    $text = preg_replace('/[\r\n\x{00A0}\x{200B}-\x{200D}\x{FEFF}]+/u', ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', $text);
    return $text;
}

try {
    $mysqli = getDBConnection();
    ensureTheoryExamSchema($mysqli);
    
    // Get current user ID and store in a variable to avoid pass by reference error
    $current_user_id = getUserId();
    
    // Get user details for sidebar
    $stmt = $mysqli->prepare("SELECT full_name, passport, class_id FROM users WHERE id = ? AND role = 'student'");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $current_user = $stmt->get_result()->fetch_assoc();
    $student_class_id = (int)($current_user['class_id'] ?? 0);

    // Validate exam_subject_id
    $exam_subject_id = isset($_GET['exam_subject_id']) ? (int)$_GET['exam_subject_id'] : 0;
    if (!$exam_subject_id) {
        header('Location: ' . ($public_exam_access ? 'public_exams.php' : 'dashboard.php?tab=available'));
        exit();
    }
    
    // Create cache key
    $cache_key = "exam_data_{$exam_subject_id}_" . ($public_exam_access ? ($_SESSION['subject_code'] ?? '') : 'dashboard');
    $current_time = time();
    
    // Check cache first
    if (isset($exam_cache[$cache_key]) && 
        isset($exam_cache_time[$cache_key]) && 
        ($current_time - $exam_cache_time[$cache_key]) < $cache_expiry) {
        
        // Use cached data
        $cached_data = $exam_cache[$cache_key];
        $exam_subject = $cached_data['exam_subject'];
        $comprehension_passages = $cached_data['comprehension_passages'];
        
    } else {
        // Verify exam subject exists and user has access through subject code
        $resit_column_check = $mysqli->query("SHOW COLUMNS FROM exam_subjects LIKE 'resit_request_id'");
        $resit_schema_available = $resit_column_check && $resit_column_check->num_rows > 0;
        $resit_request_select = $resit_schema_available ? 'es.resit_request_id' : 'NULL';
        $regular_exam_filter = "es.class_id = {$student_class_id} AND e.is_published = 1 AND es.is_published = 1";
        if (!$public_exam_access) {
            $safeStudentId = (int)$current_user_id;
            $regular_exam_filter = "(({$regular_exam_filter} AND NOT EXISTS (SELECT 1 FROM entrance_exams x WHERE x.exam_subject_id=es.id)) OR (e.is_published=1 AND es.is_published=1 AND EXISTS (SELECT 1 FROM entrance_applications ea JOIN entrance_exams root_ee ON root_ee.id=ea.entrance_exam_id JOIN exam_subjects root_es ON root_es.id=root_ee.exam_subject_id JOIN entrance_exams target_ee ON target_ee.exam_subject_id=es.id JOIN exam_subjects target_es ON target_es.id=target_ee.exam_subject_id WHERE ea.user_id={$safeStudentId} AND ea.status IN ('pending','admitted') AND root_es.exam_id=target_es.exam_id)))";
        }
        if ($public_exam_access) {
            $regular_exam_filter .= " AND s.subject_code = ?";
        }
        $student_access_filter = $resit_schema_available ? "
                              AND (
                                  (es.resit_request_id IS NULL AND {$regular_exam_filter})
                                  OR (
                                      es.resit_request_id IS NOT NULL
                                      AND EXISTS (
                                          SELECT 1 FROM resit_requests rr
                                          WHERE rr.id = es.resit_request_id
                                            AND rr.student_id = ?
                                            AND rr.status = 'approved'
                                      )
                                  )
                              )" : "AND {$regular_exam_filter}";
        $stmt = $mysqli->prepare("SELECT es.id as exam_subject_id, es.exam_id, es.subject_id, es.class_id, {$resit_request_select} as resit_request_id, es.time_limit, es.show_calculator, es.created_at,
                              e.title as exam_title, c.name as class_name, s.name as subject_name
                              FROM exam_subjects es
                              JOIN exams e ON es.exam_id = e.id
                              JOIN classes c ON es.class_id = c.id
                              JOIN subjects s ON es.subject_id = s.id
                              WHERE es.id = ? {$student_access_filter}");
        if ($public_exam_access && $resit_schema_available) {
            $stmt->bind_param("isi", $exam_subject_id, $_SESSION['subject_code'], $current_user_id);
        } elseif ($public_exam_access) {
            $stmt->bind_param("is", $exam_subject_id, $_SESSION['subject_code']);
        } elseif ($resit_schema_available) {
            $stmt->bind_param("ii", $exam_subject_id, $current_user_id);
        } else {
            $stmt->bind_param("i", $exam_subject_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $exam_subject = $result->fetch_assoc();
        
        if (!$exam_subject) {
            header('Location: ' . ($public_exam_access ? 'public_exams.php' : 'dashboard.php?tab=available'));
            exit();
        }

        if ($resit_schema_available && !empty($exam_subject['resit_request_id'])) {
            $stmt = $mysqli->prepare("SELECT id FROM resit_requests
                                      WHERE id = ? AND student_id = ? AND status = 'approved'
                                        AND resit_exam_subject_id = ?");
            $stmt->bind_param('iii', $exam_subject['resit_request_id'], $current_user_id, $exam_subject_id);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                header('Location: ' . ($public_exam_access ? 'public_exams.php' : 'dashboard.php?tab=available'));
                exit();
            }
            $stmt->close();
        }
        
        // Get all comprehension passages for this exam subject
        // Get all comprehension passages for this exam subject
        $stmt = $mysqli->prepare("SELECT id, passage_title, passage_content, start_question_number, end_question_number FROM comprehension_passages WHERE exam_subject_id = ? ORDER BY start_question_number");
        $stmt->bind_param("i", $exam_subject_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $comprehension_passages[] = $row;
        }
        
        // Cache the data
        $exam_cache[$cache_key] = [
            'exam_subject' => $exam_subject,
            'comprehension_passages' => $comprehension_passages
        ];
        $exam_cache_time[$cache_key] = $current_time;
    }

    // Get time limit
    $time_limit = $exam_subject['time_limit'];

    // --- GET ATTEMPT LOGIC ---
    // Check for submitted attempt
    $stmt = $mysqli->prepare("SELECT id, student_id, exam_id, exam_subject_id, started_at, submitted_at, score, total_questions FROM exam_attempts 
                          WHERE student_id = ? AND exam_subject_id = ? AND submitted_at IS NOT NULL");
    $stmt->bind_param("ii", $current_user_id, $exam_subject_id);
    $stmt->execute();
    $submitted_attempt = $stmt->get_result()->fetch_assoc();
    
    if ($submitted_attempt) {
        $stmt = $mysqli->prepare("SELECT id FROM exam_republish_log WHERE student_id = ? AND exam_subject_id = ? ORDER BY republished_at DESC LIMIT 1");
        $stmt->bind_param("ii", $current_user_id, $exam_subject_id);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            header("Location: exam_completion.php?attempt_id=" . $submitted_attempt['id']);
            exit();
        }
    }

    // Check for active attempt
    $stmt = $mysqli->prepare("SELECT id, student_id, exam_id, exam_subject_id, started_at, submitted_at, score, total_questions FROM exam_attempts 
                          WHERE student_id = ? AND exam_subject_id = ? AND submitted_at IS NULL");
    $stmt->bind_param("ii", $current_user_id, $exam_subject_id);
    $stmt->execute();
    $attempt = $stmt->get_result()->fetch_assoc();
    
    if (!$attempt) {
        $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM questions WHERE exam_subject_id = ?");
        $stmt->bind_param("i", $exam_subject_id);
        $stmt->execute();
        $question_count = $stmt->get_result()->fetch_assoc()['count'];
        
        $stmt = $mysqli->prepare("INSERT INTO exam_attempts (student_id, exam_id, exam_subject_id, total_questions, started_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiii", $current_user_id, $exam_subject['exam_id'], $exam_subject_id, $question_count);
        $stmt->execute();
        
        $attempt_id = $mysqli->insert_id;
        $stmt = $mysqli->prepare("SELECT id, student_id, exam_id, exam_subject_id, started_at, submitted_at, score, total_questions FROM exam_attempts WHERE id = ?");
        $stmt->bind_param("i", $attempt_id);
        $stmt->execute();
        $attempt = $stmt->get_result()->fetch_assoc();
    } else if ($attempt['started_at'] == null || $attempt['started_at'] == '0000-00-00 00:00:00') {
        $mysqli->query("UPDATE exam_attempts SET started_at = NOW() WHERE id = " . $attempt['id']);
        $attempt['started_at'] = date('Y-m-d H:i:s');
    }

    // --- GET QUESTIONS AND RANDOMIZE ---
    $all_questions = [];
    // Order by order_position to ensure original_number matches the teacher's intended sequence
    $stmt = $mysqli->prepare("SELECT id, exam_subject_id, question_text, question_type, option_a, option_b, option_c, option_d, option_e, option_f, correct_answer, theory_marks, marking_scheme, created_by, created_at, order_position FROM questions WHERE exam_subject_id = ? ORDER BY order_position ASC, id ASC");
    $stmt->bind_param("i", $exam_subject_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        // Use order_position + 1 as the original question number (since order_position is 0-indexed)
        $row['original_number'] = (int)$row['order_position'] + 1;
        $all_questions[] = $row;
    }

    // Seed with attempt ID for consistency
    mt_srand($attempt['id']);
    $questions = $all_questions;
    if (!empty($questions)) {
        for ($i = count($questions) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            $tmp = $questions[$i];
            $questions[$i] = $questions[$j];
            $questions[$j] = $tmp;
        }
    }

    // --- GET ANSWERS ---
    $stmt = $mysqli->prepare("SELECT question_id, selected_answer, answer_text FROM student_answers WHERE attempt_id = ?");
    $stmt->bind_param("i", $attempt['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $answers[$row['question_id']] = ($row['answer_text'] !== null && $row['answer_text'] !== '') ? $row['answer_text'] : $row['selected_answer'];
    }

    // Calculate time remaining
    $stmt = $mysqli->prepare("SELECT TIMESTAMPDIFF(SECOND, started_at, NOW()) as elapsed FROM exam_attempts WHERE id = ?");
    $stmt->bind_param("i", $attempt['id']);
    $stmt->execute();
    $elapsed = $stmt->get_result()->fetch_assoc()['elapsed'];
    $time_remaining = max(0, ($time_limit * 60) - (int)$elapsed);

    // Passage detection
    if (!empty($comprehension_passages) && isset($questions[$current_question_index])) {
        $orig_num = $questions[$current_question_index]['original_number'];
        foreach ($comprehension_passages as $passage) {
            if ($orig_num >= $passage['start_question_number'] && $orig_num <= $passage['end_question_number']) {
                $current_passage = $passage;
                break;
            }
        }
    }
    
    // Check if time is up
    if ($time_remaining <= 0 && empty($_POST['submit_exam'])) {
        // Auto-submit exam if time is up
        // Calculate score
        $objective_score = 0;
        $total_answered = 0;
        
        // Prepare batch update statement for better performance
        $update_correct_stmt = $mysqli->prepare("UPDATE student_answers SET is_correct = 1 WHERE attempt_id = ? AND question_id = ?");
        $update_incorrect_stmt = $mysqli->prepare("UPDATE student_answers SET is_correct = 0 WHERE attempt_id = ? AND question_id = ?");
        
        foreach ($questions as $question) {
            if (isset($answers[$question['id']])) {
                $total_answered++;
                if (($question['question_type'] ?? 'objective') === 'theory') {
                    continue;
                }
                if ($answers[$question['id']] == $question['correct_answer']) {
                    // Update is_correct field in student_answers table
                    $update_correct_stmt->bind_param("ii", $attempt['id'], $question['id']);
                    $update_correct_stmt->execute();
                    $objective_score += 1;
                } else {
                    // Update is_correct field to 0 for incorrect answers
                    $update_incorrect_stmt->bind_param("ii", $attempt['id'], $question['id']);
                    $update_incorrect_stmt->execute();
                }
            }
        }
        
        // Close prepared statements
        $update_correct_stmt->close();
        $update_incorrect_stmt->close();
        
        // Get marks per question for this class with caching
        $marks_cache_key = "marks_{$exam_subject['class_id']}";
        
        if (isset($exam_cache[$marks_cache_key]) && 
            isset($exam_cache_time[$marks_cache_key]) && 
            ($current_time - $exam_cache_time[$marks_cache_key]) < $cache_expiry) {
            
            // Use cached marks
            $marks_per_question = $exam_cache[$marks_cache_key];
            
        } else {
            $marks_per_question = 1.00; // Default value
            $stmt = $mysqli->prepare("SELECT marks_per_question FROM classes WHERE id = ?");
            $stmt->bind_param("i", $exam_subject['class_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $marks_per_question = (float)$row['marks_per_question'];
            }
            $stmt->close();
            
            // Cache the marks
            $exam_cache[$marks_cache_key] = $marks_per_question;
            $exam_cache_time[$marks_cache_key] = $current_time;
        }
        
        // Calculate total score based on marks per question
        $total_score = $objective_score * $marks_per_question;
        
        // Update attempt with score and submission time
        $stmt = $mysqli->prepare("UPDATE exam_attempts SET score = ?, submitted_at = NOW() WHERE id = ?");
        $stmt->bind_param("di", $total_score, $attempt['id']);
        $stmt->execute();
        
        // Clear cache for this attempt
        $answers_cache_key = "answers_{$attempt['id']}";
        unset($exam_cache[$answers_cache_key]);
        unset($exam_cache_time[$answers_cache_key]);
        
        // Mark as submitted in session for immediate back-button blocking
        $_SESSION['submitted_exam_id'] = $exam_subject_id;
        $_SESSION['last_attempt_id'] = $attempt['id'];
        
        // Redirect to completion page
        header("Location: exam_completion.php?attempt_id=" . $attempt['id'] . "&time_up=1");
        exit();
    }
    
    // Handle answer submission - Save current answer whether submitting exam or not
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['answer']) || isset($_POST['answer_text']) || isset($_POST['next']) || isset($_POST['previous']) || !empty($_POST['submit_exam']) || isset($_POST['navigate_to']))) {
        
        // SECURITY CHECK: Ensure attempt is NOT already submitted
        if ($attempt && $attempt['submitted_at'] !== null && $attempt['submitted_at'] !== '0000-00-00 00:00:00') {
            header("Location: exam_completion.php?attempt_id=" . $attempt['id']);
            exit();
        }

        // Make sure we have a question_id
        if (!isset($_POST['question_id'])) {
            // If no question_id, redirect to current question
            header("Location: take_exam.php?exam_subject_id=$exam_subject_id&q=$current_question_index");
            exit();
        }
        
        $question_id = (int)$_POST['question_id'];
        $posted_question_type = 'objective';
        foreach ($questions as $posted_question) {
            if ((int)$posted_question['id'] === $question_id) {
                $posted_question_type = $posted_question['question_type'] ?? 'objective';
                break;
            }
        }
        $selected_answer = $_POST['answer'] ?? null;
        $answer_text = isset($_POST['answer_text']) ? trim($_POST['answer_text']) : null;
        $answer_to_save = $posted_question_type === 'theory' ? $answer_text : $selected_answer;
        
        // If we have an answer to save (either from form or already existing)
        if ($answer_to_save !== null && $answer_to_save !== '') {
            // Check if answer already exists in cache first
            $answer_cache_key = "answers_{$attempt['id']}";
            $answer_exists = isset($answers[$question_id]);
            
            if ($posted_question_type === 'theory') {
                if ($answer_exists) {
                    $stmt = $mysqli->prepare("UPDATE student_answers SET answer_text = ? WHERE attempt_id = ? AND question_id = ?");
                    $stmt->bind_param("sii", $answer_text, $attempt['id'], $question_id);
                } else {
                    $stmt = $mysqli->prepare("INSERT INTO student_answers (attempt_id, question_id, answer_text) VALUES (?, ?, ?)");
                    $stmt->bind_param("iis", $attempt['id'], $question_id, $answer_text);
                }
                $stmt->execute();
            } else {
                if ($answer_exists) {
                    // Update existing answer
                    $stmt = $mysqli->prepare("UPDATE student_answers SET selected_answer = ? WHERE attempt_id = ? AND question_id = ?");
                    $stmt->bind_param("sii", $selected_answer, $attempt['id'], $question_id);
                } else {
                    // Insert new answer
                    $stmt = $mysqli->prepare("INSERT INTO student_answers (attempt_id, question_id, selected_answer) 
                                          VALUES (?, ?, ?)");
                    $stmt->bind_param("iis", $attempt['id'], $question_id, $selected_answer);
                }
                $stmt->execute();
            }
            
            // Update answers array and cache
            $answers[$question_id] = $answer_to_save;
            
            // Update cache
            if (isset($exam_cache[$answer_cache_key])) {
                $exam_cache[$answer_cache_key][$question_id] = $answer_to_save;
            }
        } else {
            // If no answer was submitted but we're navigating, check if there's already an answer saved
            // We already have the answers loaded, so just use the existing value
            // No database query needed here
        }
        
        // Handle navigation to specific question
        if (isset($_POST['navigate_to'])) {
            $target_question = (int)$_POST['navigate_to'];
            // Ensure target is within valid range
            if ($target_question >= 0 && $target_question < count($questions)) {
                header("Location: take_exam.php?exam_subject_id=$exam_subject_id&q=$target_question");
                exit();
            }
        }
        
        // If we're submitting the exam, continue to exam submission logic (don't redirect)
        if (empty($_POST['submit_exam']) && !isset($_POST['navigate_to'])) {
            // Redirect to next question or same question
            if (isset($_POST['next']) && $current_question_index < count($questions) - 1) {
                header("Location: take_exam.php?exam_subject_id=$exam_subject_id&q=" . ($current_question_index + 1));
                exit();
            } elseif (isset($_POST['previous']) && $current_question_index > 0) {
                header("Location: take_exam.php?exam_subject_id=$exam_subject_id&q=" . ($current_question_index - 1));
                exit();
            } else {
                header("Location: take_exam.php?exam_subject_id=$exam_subject_id&q=$current_question_index");
                exit();
            }
        }
    }
    
    // Handle exam submission - Check this AFTER handling answer submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['submit_exam'])) {
        // We already have the answers in our cache, no need to refresh from database
        
        // Calculate score
        $objective_score = 0;
        $total_answered = 0;
        
        // Prepare batch update statement for better performance
        $update_correct_stmt = $mysqli->prepare("UPDATE student_answers SET is_correct = 1 WHERE attempt_id = ? AND question_id = ?");
        $update_incorrect_stmt = $mysqli->prepare("UPDATE student_answers SET is_correct = 0 WHERE attempt_id = ? AND question_id = ?");
        
        foreach ($questions as $question) {
            if (isset($answers[$question['id']])) {
                $total_answered++;
                if (($question['question_type'] ?? 'objective') === 'theory') {
                    continue;
                }
                if ($answers[$question['id']] == $question['correct_answer']) {
                    // Update is_correct field in student_answers table
                    $update_correct_stmt->bind_param("ii", $attempt['id'], $question['id']);
                    $update_correct_stmt->execute();
                    $objective_score += 1;
                } else {
                    // Update is_correct field to 0 for incorrect answers
                    $update_incorrect_stmt->bind_param("ii", $attempt['id'], $question['id']);
                    $update_incorrect_stmt->execute();
                }
            }
        }
        
        // Close prepared statements
        $update_correct_stmt->close();
        $update_incorrect_stmt->close();
        
        // Get marks per question for this class with caching
        $marks_cache_key = "marks_{$exam_subject['class_id']}";
        
        if (isset($exam_cache[$marks_cache_key]) && 
            isset($exam_cache_time[$marks_cache_key]) && 
            ($current_time - $exam_cache_time[$marks_cache_key]) < $cache_expiry) {
            
            // Use cached marks
            $marks_per_question = $exam_cache[$marks_cache_key];
            
        } else {
            $marks_per_question = 1.00; // Default value
            $stmt = $mysqli->prepare("SELECT marks_per_question FROM classes WHERE id = ?");
            $stmt->bind_param("i", $exam_subject['class_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $marks_per_question = (float)$row['marks_per_question'];
            }
            $stmt->close();
            
            // Cache the marks
            $exam_cache[$marks_cache_key] = $marks_per_question;
            $exam_cache_time[$marks_cache_key] = $current_time;
        }
        
        // Calculate total score based on marks per question
        $total_score = $objective_score * $marks_per_question;
        
        // Update attempt with score and submission time
        $stmt = $mysqli->prepare("UPDATE exam_attempts SET score = ?, submitted_at = NOW() WHERE id = ?");
        $stmt->bind_param("di", $total_score, $attempt['id']);
        $stmt->execute();
        
        // Clear cache for this attempt
        $answers_cache_key = "answers_{$attempt['id']}";
        unset($exam_cache[$answers_cache_key]);
        unset($exam_cache_time[$answers_cache_key]);
        
        // Mark as submitted in session for immediate back-button blocking
        $_SESSION['submitted_exam_id'] = $exam_subject_id;
        $_SESSION['last_attempt_id'] = $attempt['id'];
        
        // Redirect to completion page
        header("Location: exam_completion.php?attempt_id=" . $attempt['id']);
        exit();
    }
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
}

// Count unanswered questions - moved to global scope so it's always available
$unanswered_count = 0;
if (!empty($questions)) {
    foreach ($questions as $index => $q) {
        if (!isset($answers[$q['id']]) || empty($answers[$q['id']])) {
            $unanswered_count++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Exam - CBT Exam System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <style>
        body {
            background-color: #f4f7f6;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .exam-header {
            background-color: #fff;
            padding: 15px 0;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 30px;
        }
        .question-card {
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .option-container {
            margin-bottom: 15px;
        }
        .option-label {
            display: block;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .option-label:hover {
            background-color: #f8f9fa;
        }
        .option-label.active {
            background-color: #e9ecef;
            border-color: #0d6efd;
            font-weight: bold;
        }
        .timer-box {
            font-size: 24px;
            font-weight: bold;
            color: #d9534f;
        }
        .q-nav-btn {
            width: 40px;
            height: 40px;
            margin: 2px;
            border-radius: 4px;
            font-weight: bold;
        }
        .q-nav-btn.answered {
            background-color: #5cb85c;
            color: white;
            border-color: #4cae4c;
        }
        .q-nav-btn.current {
            background-color: #0275d8;
            color: white;
            border-color: #025aa5;
        }
        #calcContainer {
            display: none;
            position: fixed;
            top: 50px;
            right: 20px;
            width: 320px;
            z-index: 9999;
            background: rgba(30, 34, 45, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        #calcContainer .card {
            background: transparent;
            border: none;
        }
        #calcHeader {
            background: rgba(20, 24, 33, 0.8) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding: 12px 16px;
            color: #fff !important;
            font-weight: 600;
        }
        #calcDisplay {
            font-family: 'Courier New', Courier, monospace;
            font-size: 22px;
            font-weight: bold;
            height: 60px;
            background: #141821 !important;
            color: #00ffcc !important;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            text-shadow: 0 0 10px rgba(0, 255, 204, 0.5);
            padding: 10px 15px;
            margin-bottom: 15px !important;
        }
        .calc-keys button {
            height: 42px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            border: none;
            transition: all 0.2s ease;
        }
        /* scientific keys */
        .calc-keys button.btn-sci {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        .calc-keys button.btn-sci:hover {
            background: rgba(59, 130, 246, 0.4);
            color: #fff;
        }
        /* operator keys */
        .calc-keys button.btn-op {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .calc-keys button.btn-op:hover {
            background: rgba(245, 158, 11, 0.4);
            color: #fff;
        }
        /* number keys */
        .calc-keys button.btn-num {
            background: rgba(255, 255, 255, 0.08);
            color: #f3f4f6;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .calc-keys button.btn-num:hover {
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
        }
        /* action keys */
        .calc-keys button.btn-ac {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .calc-keys button.btn-ac:hover {
            background: rgba(239, 68, 68, 0.4);
            color: #fff;
            transform: scale(1.05);
        }
        .calc-keys button.btn-del {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .calc-keys button.btn-del:hover {
            background: rgba(239, 68, 68, 0.4);
            color: #fff;
        }
        .calc-keys button.btn-eq {
            background: #16835e;
            color: #fff;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .calc-keys button.btn-eq:hover {
            background: #126b4d;
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.5);
        }
        #toggleCalc {
            background: var(--brand-navy);
            border: none;
            width: 60px;
            height: 60px;
            font-size: 24px;
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.4);
            transition: background-color 0.16s ease, box-shadow 0.16s ease;
        }
        #toggleCalc:hover {
            box-shadow: 0 6px 24px rgba(59, 130, 246, 0.6);
        }
    </style>
    <link href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>" rel="stylesheet">
    
    <script>
        // Prevent back button after submission by forcing a reload if loaded from cache
        window.onpageshow = function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        };
        
        // Disable right click and some keyboard shortcuts for exam security
        document.addEventListener('contextmenu', event => event.preventDefault());

        // Prevent Backspace from acting as a "Back" button
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace') {
                var activeElement = document.activeElement;
                var isInput = activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA' || activeElement.isContentEditable;
                if (!isInput) {
                    e.preventDefault();
                }
            }
        });
    </script>
</head>
<body>
    <div class="exam-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-0"><?php echo htmlspecialchars($exam_subject['exam_title']); ?></h4>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($exam_subject['subject_name']); ?> | Question <?php echo $current_question_index + 1; ?> of <?php echo count($questions); ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <div id="timerDisplay" class="timer-box">
                        <i class="fas fa-clock"></i> <span id="timeRemaining">--:--:--</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <div class="row">
            <!-- Left Sidebar -->
            <div class="col-md-3 order-2 order-md-1">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Candidate Info</h5>
                    </div>
                    <div class="card-body text-center">
                        <img src="<?php echo !empty($current_user['passport']) ? '../' . $current_user['passport'] : '../assets/img/default-avatar.png'; ?>" 
                             class="img-thumbnail rounded-circle mb-3" 
                             style="width: 100px; height: 100px; object-fit: cover;">
                        <h6><?php echo htmlspecialchars($current_user['full_name']); ?></h6>
                        <hr>
                        <div class="question-nav">
                            <?php foreach ($questions as $index => $q): 
                                $is_answered = isset($answers[$q['id']]) && !empty($answers[$q['id']]);
                                $is_current = $index == $current_question_index;
                                $btn_class = 'btn-outline-secondary';
                                if ($is_current) $btn_class = 'btn-primary';
                                elseif ($is_answered) $btn_class = 'btn-success';
                            ?>
                                <button type="button" 
                                        class="btn btn-sm <?php echo $btn_class; ?> q-nav-btn"
                                        onclick="navigateToQuestion(<?php echo $index; ?>)">
                                    <?php echo $index + 1; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <hr>
                        <button type="button" class="btn btn-danger w-100" onclick="confirmFinishExam()">
                            Submit Exam
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="col-md-9 order-1 order-md-2">
                <?php if ($exam_subject && count($questions) > 0): 
                    $current_question = $questions[$current_question_index] ?? null;
                    if ($current_question): ?>
                    
                    <?php if ($current_passage): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><?php echo htmlspecialchars($current_passage['passage_title'] ?: 'Comprehension Passage'); ?></h5>
                            </div>
                            <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                <?php echo fullyDecodeHtml($current_passage['passage_content']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card question-card">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between mb-3">
                                <h5 class="text-primary">Question <?php echo $current_question_index + 1; ?></h5>
                            </div>

                            <div class="question-text mb-4">
                                <?php echo fullyDecodeHtml($current_question['question_text']); ?>
                            </div>

                            <form id="questionForm" method="POST">
                                <input type="hidden" name="question_id" value="<?php echo $current_question['id']; ?>">
                                
                                <?php if (($current_question['question_type'] ?? 'objective') === 'theory'): ?>
                                    <div class="mb-3">
                                        <label for="answer_text" class="form-label fw-bold">Your Answer</label>
                                        <textarea class="form-control" id="answer_text" name="answer_text" rows="10"><?php echo htmlspecialchars($answers[$current_question['id']] ?? ''); ?></textarea>
                                        <div class="form-text">This theory answer will be marked by your teacher after submission.</div>
                                    </div>
                                <?php else: ?>
                                    <div class="options-list">
                                        <?php 
                                        $options = [
                                            'A' => $current_question['option_a'],
                                            'B' => $current_question['option_b'],
                                            'C' => $current_question['option_c'],
                                            'D' => $current_question['option_d']
                                        ];
                                        if (!empty($current_question['option_e'])) $options['E'] = $current_question['option_e'];
                                        if (!empty($current_question['option_f'])) $options['F'] = $current_question['option_f'];

                                        foreach ($options as $key => $val): 
                                            if (empty(trim(strip_tags($val)))) continue;
                                            $is_selected = (isset($answers[$current_question['id']]) && $answers[$current_question['id']] == $key);
                                        ?>
                                            <div class="option-container">
                                                <label class="option-label <?php echo $is_selected ? 'active' : ''; ?>">
                                                    <input type="radio" name="answer" value="<?php echo $key; ?>" <?php echo $is_selected ? 'checked' : ''; ?> onchange="this.form.submit()">
                                                    <span class="ms-2"><strong><?php echo $key; ?>.</strong> <?php echo cleanOptionDisplay($val); ?></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex justify-content-between mt-4 border-top pt-3">
                                    <?php if ($current_question_index > 0): ?>
                                        <button type="submit" name="previous" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left"></i> Previous
                                        </button>
                                    <?php else: ?>
                                        <div></div>
                                    <?php endif; ?>
                                    
                                    <?php if ($current_question_index < count($questions) - 1): ?>
                                        <button type="submit" name="next" class="btn btn-primary">
                                            Next <i class="fas fa-arrow-right"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-success" onclick="confirmFinishExam()">
                                            Submit Exam <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" name="submit_exam" id="finalSubmitInput" value="0">
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scientific Calculator UI -->
    <?php if ($exam_subject && isset($exam_subject['show_calculator']) && $exam_subject['show_calculator']): ?>
    <div style="position: fixed; bottom: 20px; right: 20px; z-index: 1000;">
        <button id="toggleCalc" class="btn btn-primary btn-lg rounded-circle shadow">
            <i class="fas fa-calculator"></i>
        </button>
    </div>

    <div id="calcContainer">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" id="calcHeader" style="cursor: move;">
                <h6 class="mb-0 text-white"><i class="fas fa-calculator me-2"></i>Calculator</h6>
                <button type="button" class="btn-close btn-close-white" id="closeCalc"></button>
            </div>
            <div class="card-body p-3">
                <input type="text" id="calcDisplay" class="form-control mb-3 text-end" value="0" readonly>
                <div class="calc-keys d-grid" style="grid-template-columns: repeat(4, 1fr); gap: 5px;">
                    <button type="button" class="btn-sci" value="sin">sin</button>
                    <button type="button" class="btn-sci" value="cos">cos</button>
                    <button type="button" class="btn-sci" value="tan">tan</button>
                    <button type="button" class="btn-sci" value="log">log</button>
                    
                    <button type="button" class="btn-sci" value="sqrt">√</button>
                    <button type="button" class="btn-sci" value="pow">x²</button>
                    <button type="button" class="btn-sci" value="pi">π</button>
                    <button type="button" class="btn-sci" value="exp">exp</button>

                    <button type="button" class="btn-ac clear-btn" value="all-clear">AC</button>
                    <button type="button" class="btn-op" value="/">÷</button>
                    <button type="button" class="btn-op" value="*">×</button>
                    <button type="button" class="btn-op" value="-">-</button>

                    <button type="button" class="btn-num" value="7">7</button>
                    <button type="button" class="btn-num" value="8">8</button>
                    <button type="button" class="btn-num" value="9">9</button>
                    <button type="button" class="btn-op" value="+">+</button>

                    <button type="button" class="btn-num" value="4">4</button>
                    <button type="button" class="btn-num" value="5">5</button>
                    <button type="button" class="btn-num" value="6">6</button>
                    <button type="button" class="btn-sci" value="(">(</button>

                    <button type="button" class="btn-num" value="1">1</button>
                    <button type="button" class="btn-num" value="2">2</button>
                    <button type="button" class="btn-num" value="3">3</button>
                    <button type="button" class="btn-sci" value=")">)</button>

                    <button type="button" class="btn-num" value="0">0</button>
                    <button type="button" class="btn-num" value=".">.</button>
                    <button type="button" class="btn-del" value="backspace">DEL</button>
                    <button type="button" class="btn-eq" value="=">=</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mathjs/10.6.4/math.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Time limit countdown
var timeRemaining = <?php echo $time_remaining; ?>;
var timerElement = document.getElementById('timeRemaining');
var unansweredCount = <?php echo $unanswered_count ?? 0; ?>;

function updateTimer() {
    var hours = Math.floor(timeRemaining / 3600);
    var minutes = Math.floor((timeRemaining % 3600) / 60);
    var seconds = timeRemaining % 60;
    
    // Format time as HH:MM:SS
    var formattedTime = 
        (hours < 10 ? '0' : '') + hours + ':' +
        (minutes < 10 ? '0' : '') + minutes + ':' +
        (seconds < 10 ? '0' : '') + seconds;
    
    timerElement.textContent = 'Time: ' + formattedTime;
    
    if (timeRemaining <= 0) {
        // Time is up, auto-submit
        document.getElementById('questionForm').submit();
    } else {
        timeRemaining--;
        setTimeout(updateTimer, 1000);
    }
}

// Start the timer
updateTimer();

function confirmFinishExam() {
    var currentUnansweredCount = unansweredCount;
    var isCurrentAnsweredInDB = <?php echo (isset($current_question) && isset($answers[$current_question['id']]) && !empty($answers[$current_question['id']])) ? 'true' : 'false'; ?>;
    var isCurrentAnsweredNow = document.querySelector('input[name="answer"]:checked') !== null;
    
    if (!isCurrentAnsweredInDB && isCurrentAnsweredNow) {
        currentUnansweredCount--;
    }
    
    var message = 'Are you sure you want to submit this exam?';
    
    if (currentUnansweredCount > 0) {
        message = 'You have ' + currentUnansweredCount + ' unanswered questions. Are you sure you want to submit the exam?';
    }
    
    var proceed = confirm(message);
    if (proceed) {
        document.getElementById('finalSubmitInput').value = '1';
        document.getElementById('questionForm').submit();
    }
}

// Calculator Logic
<?php if ($exam_subject && isset($exam_subject['show_calculator']) && $exam_subject['show_calculator']): ?>
// Override mathjs trig functions to use degrees and log to use base 10 globally
if (typeof math !== 'undefined') {
    math.import({
        sin: function(x) {
            if (typeof x === 'number') {
                return Math.sin(x * Math.PI / 180);
            }
            if (x && x.isUnit) {
                return math.sin(x);
            }
            return Math.sin(x * Math.PI / 180);
        },
        cos: function(x) {
            if (typeof x === 'number') {
                return Math.cos(x * Math.PI / 180);
            }
            if (x && x.isUnit) {
                return math.cos(x);
            }
            return Math.cos(x * Math.PI / 180);
        },
        tan: function(x) {
            if (typeof x === 'number') {
                return Math.tan(x * Math.PI / 180);
            }
            if (x && x.isUnit) {
                return math.tan(x);
            }
            return Math.tan(x * Math.PI / 180);
        },
        log: function(x) {
            if (typeof x === 'number') {
                return Math.log10(x);
            }
            return math.log10(x);
        }
    }, {override: true});
}

const calculator = {
    expression: '',
    result: null,
};

function updateDisplay() {
    const display = document.querySelector('#calcDisplay');
    if (display) {
        display.value = calculator.expression || '0';
    }
}

function handleInput(val) {
    if (calculator.result !== null) {
        // If we just got a result and start typing a number, start fresh
        if (!isNaN(val) || val === '.' || val === '(' || ['sin', 'cos', 'tan', 'log', 'sqrt', 'exp'].includes(val)) {
            calculator.expression = '';
        } else {
            // If it's an operator, continue from result
            calculator.expression = calculator.result.toString();
        }
        calculator.result = null;
    }

    if (['sin', 'cos', 'tan', 'log', 'sqrt', 'exp'].includes(val)) {
        calculator.expression += val + '(';
    } else if (val === 'pi') {
        calculator.expression += 'PI';
    } else if (val === 'pow') {
        calculator.expression += '^2';
    } else if (val === '*') {
        calculator.expression += '*';
    } else if (val === '/') {
        calculator.expression += '/';
    } else {
        calculator.expression += val;
    }
    updateDisplay();
}

function handleBackspace() {
    if (calculator.result !== null) {
        resetCalculator();
        return;
    }
    
    let expr = calculator.expression;
    if (expr.length > 0) {
        // Smart backspace: delete trig/log functions with their opening parenthesis entirely
        const funcEnds = ['sin(', 'cos(', 'tan(', 'log(', 'sqrt(', 'exp('];
        let foundFunc = false;
        for (const func of funcEnds) {
            if (expr.endsWith(func)) {
                calculator.expression = expr.substring(0, expr.length - func.length);
                foundFunc = true;
                break;
            }
        }
        
        if (!foundFunc) {
            calculator.expression = expr.substring(0, expr.length - 1);
        }
    }
    updateDisplay();
}

function calculateResult() {
    if (!calculator.expression) return;
    try {
        // Clean expression for mathjs
        let expr = calculator.expression;
        const res = math.evaluate(expr);
        calculator.result = parseFloat(res.toFixed(8));
        calculator.expression = calculator.result.toString();
        updateDisplay();
    } catch (error) {
        alert('Invalid Expression');
        resetCalculator();
    }
}

function resetCalculator() {
    calculator.expression = '';
    calculator.result = null;
    updateDisplay();
}

const keys = document.querySelector('.calc-keys');
if (keys) {
    keys.addEventListener('click', (event) => {
        const { target } = event;
        if (!target.matches('button')) return;

        const val = target.value;

        if (val === 'all-clear' || target.classList.contains('clear-btn')) {
            resetCalculator();
            return;
        }

        if (val === 'backspace' || val === 'delete') {
            handleBackspace();
            return;
        }

        if (val === '=') {
            calculateResult();
            return;
        }

        handleInput(val);
    });
}

// Drag and Drop Logic for Calculator
const calcContainer = document.getElementById('calcContainer');
const calcHeader = document.getElementById('calcHeader');
const toggleCalc = document.getElementById('toggleCalc');
const closeCalc = document.getElementById('closeCalc');

if (toggleCalc) {
    toggleCalc.addEventListener('click', () => {
        calcContainer.style.display = calcContainer.style.display === 'block' ? 'none' : 'block';
    });
}

if (closeCalc) {
    closeCalc.addEventListener('click', () => {
        calcContainer.style.display = 'none';
    });
}

let isDragging = false;
let currentX;
let currentY;
let initialX;
let initialY;
let xOffset = 0;
let yOffset = 0;

if (calcHeader) {
    calcHeader.addEventListener('mousedown', dragStart);
    document.addEventListener('mousemove', drag);
    document.addEventListener('mouseup', dragEnd);
}

function dragStart(e) {
    initialX = e.clientX - xOffset;
    initialY = e.clientY - yOffset;
    if (e.target === calcHeader || e.target.parentNode === calcHeader) {
        isDragging = true;
    }
}

function drag(e) {
    if (isDragging) {
        e.preventDefault();
        currentX = e.clientX - initialX;
        currentY = e.clientY - initialY;
        xOffset = currentX;
        yOffset = currentY;
        setTranslate(currentX, currentY, calcContainer);
    }
}

function setTranslate(xPos, yPos, el) {
    if (el) el.style.transform = `translate3d(${xPos}px, ${yPos}px, 0)`;
}

function dragEnd(e) {
    initialX = currentX;
    initialY = currentY;
    isDragging = false;
}
<?php endif; ?>

// Function to navigate to a specific question
function navigateToQuestion(index) {
    // Create a hidden form to submit the navigation request
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    
    // Add question index input
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'navigate_to';
    input.value = index;
    form.appendChild(input);
    
    // Add current question input to maintain state
    var currentInput = document.createElement('input');
    currentInput.type = 'hidden';
    currentInput.name = 'question_id';
    currentInput.value = document.querySelector('input[name="question_id"]').value;
    form.appendChild(currentInput);
    
    // Add answer if one is selected
    var selectedAnswer = document.querySelector('input[name="answer"]:checked');
    if (selectedAnswer) {
        var answerInput = document.createElement('input');
        answerInput.type = 'hidden';
        answerInput.name = 'answer';
        answerInput.value = selectedAnswer.value;
        form.appendChild(answerInput);
    }
    
    document.body.appendChild(form);
    form.submit();
}

// Add event listeners to navigation buttons
document.addEventListener('DOMContentLoaded', function() {
    var nextButton = document.querySelector('button[name="next"]');
    var previousButton = document.querySelector('button[name="previous"]');
    var submitButton = document.querySelector('button[name="submit_exam"]');
    var form = document.getElementById('questionForm');
    
    if (nextButton) {
        nextButton.addEventListener('click', function(e) {
            // Always allow navigation to next question, no confirmation needed
            // The form will submit normally
        });
    }
    
    if (previousButton) {
        previousButton.addEventListener('click', function(e) {
            // Always allow navigation to previous question, no confirmation needed
            // The form will submit normally
        });
    }
});
</script>
</body>
</html>
