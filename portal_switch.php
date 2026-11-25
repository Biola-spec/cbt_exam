<?php
require_once __DIR__ . '/config/session.php';
requireLogin();

if (($_SESSION['role'] ?? null) !== 'admin_teacher') {
    header('Location: index.php');
    exit();
}

$portal = $_GET['portal'] ?? '';
if (!in_array($portal, ['admin', 'teacher'], true)) {
    header('Location: index.php');
    exit();
}

$_SESSION['active_portal'] = $portal;
header('Location: ' . ($portal === 'teacher' ? 'teacher/home.php' : 'admin/dashboard.php'));
exit();
