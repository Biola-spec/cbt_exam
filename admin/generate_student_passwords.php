<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();
if (getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

function redirectToStudentManagement(): void {
    header('Location: dashboard.php?tab=students');
    exit();
}

function setStudentManagementMessage(string $message): void {
    $_SESSION['student_management_message'] = $message;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php?tab=students');
    exit();
}

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
    setStudentManagementMessage('Invalid session token. Please refresh and try again.');
    redirectToStudentManagement();
}

$mysqli = getDBConnection();
if (!$mysqli) {
    setStudentManagementMessage('Database connection failed.');
    redirectToStudentManagement();
}

$class_column = $mysqli->query("SHOW COLUMNS FROM users LIKE 'class_id'");
if (!$class_column || $class_column->num_rows === 0) {
    setStudentManagementMessage('Bulk generation requires the users.class_id field.');
    redirectToStudentManagement();
}

$target_class_id = (int)($_POST['target_class_id'] ?? -1);
if ($target_class_id < 0) {
    setStudentManagementMessage('Select a valid class or all students.');
    redirectToStudentManagement();
}
if ($target_class_id > 0) {
    $class_check = $mysqli->prepare('SELECT id FROM classes WHERE id = ?');
    $class_check->bind_param('i', $target_class_id);
    $class_check->execute();
    $valid_class = $class_check->get_result()->fetch_assoc();
    $class_check->close();
    if (!$valid_class) {
        setStudentManagementMessage('Select a valid class.');
        redirectToStudentManagement();
    }
}

$sql = "SELECT id, full_name, email FROM users WHERE role = 'student'";
if ($target_class_id > 0) $sql .= ' AND class_id = ?';
$sql .= ' ORDER BY full_name, id';
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    setStudentManagementMessage('Could not prepare the student list for password generation.');
    redirectToStudentManagement();
}
if ($target_class_id > 0) $stmt->bind_param('i', $target_class_id);
$stmt->execute();
$target_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!$target_students) {
    setStudentManagementMessage('No students were found for that selection.');
    redirectToStudentManagement();
}

$used_passwords = [];
$generated = [];
try {
    $mysqli->begin_transaction();
    $update = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'student'");
    if (!$update) throw new RuntimeException('Could not prepare password updates.');

    foreach ($target_students as $student) {
        if (count($used_passwords) < 900) {
            do {
                $plain_password = 'Famaiis' . random_int(100, 999);
            } while (isset($used_passwords[$plain_password]));
        } else {
            $plain_password = 'Famaiis' . (1000 + count($used_passwords));
        }
        $used_passwords[$plain_password] = true;
        $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);
        $student_id = (int)$student['id'];
        $update->bind_param('si', $password_hash, $student_id);
        if (!$update->execute() || $update->affected_rows !== 1) {
            throw new RuntimeException('A password could not be saved. No passwords were changed.');
        }
        $generated[] = [
            'full_name' => $student['full_name'],
            'email' => $student['email'],
            'password' => $plain_password
        ];
    }

    $update->close();
    $mysqli->commit();
    $_SESSION['bulk_generated_passwords'] = $generated;
} catch (Throwable $e) {
    $mysqli->rollback();
    setStudentManagementMessage('Password generation failed: ' . $e->getMessage());
}

redirectToStudentManagement();
