<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is logged in and is an admin
requireLogin();
if (getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mysqli = getDBConnection();
    $success = false;
    $message = '';

    if (isset($_POST['attempt_id'])) {
        // Delete single subject result
        $attempt_id = (int)$_POST['attempt_id'];
        
        try {
            // First, get attempt details for the log/redirect
            $stmt = $mysqli->prepare("SELECT student_id, exam_id FROM exam_attempts WHERE id = ?");
            $stmt->bind_param("i", $attempt_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $attempt = $result->fetch_assoc();
            
            if ($attempt) {
                // Delete from exam_attempts (student_answers will be deleted by ON DELETE CASCADE)
                $stmt = $mysqli->prepare("DELETE FROM exam_attempts WHERE id = ?");
                $stmt->bind_param("i", $attempt_id);
                if ($stmt->execute()) {
                    $success = true;
                    $message = "Exam result deleted successfully.";
                } else {
                    $message = "Error deleting exam result: " . $mysqli->error;
                }
            } else {
                $message = "Exam attempt not found.";
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    } elseif (isset($_POST['student_id']) && isset($_POST['exam_id'])) {
        // Delete all subjects for a student in a specific exam
        $student_id = (int)$_POST['student_id'];
        $exam_id = (int)$_POST['exam_id'];
        
        try {
            $stmt = $mysqli->prepare("DELETE FROM exam_attempts WHERE student_id = ? AND exam_id = ?");
            $stmt->bind_param("ii", $student_id, $exam_id);
            if ($stmt->execute()) {
                $count = $mysqli->affected_rows;
                $success = true;
                $message = "$count result(s) deleted successfully.";
            } else {
                $message = "Error deleting exam results: " . $mysqli->error;
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    } else {
        $message = "Invalid request parameters.";
    }

    // Redirect back with message
    $redirect_url = 'view_all_results.php?tab=results';
    if (isset($_POST['redirect'])) {
        $redirect_url = $_POST['redirect'];
    }
    
    // Add message to session or URL
    // For simplicity, using URL parameter in this system
    $connector = (strpos($redirect_url, '?') !== false) ? '&' : '?';
    header("Location: " . $redirect_url . $connector . "message=" . urlencode($message) . "&status=" . ($success ? 'success' : 'error'));
    exit();
} else {
    // Only POST allowed
    header('Location: view_all_results.php?tab=results');
    exit();
}
?>
