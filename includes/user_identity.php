<?php
/** Normalize a person's full name for duplicate-account comparisons. */
function normalizeAccountFullName($name) {
    $name = trim((string)$name);
    $name = preg_replace('/\s+/u', ' ', $name);
    return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
}

/**
 * Find a duplicate email or teacher/student full name.
 * Returns ['type' => 'email'|'name', 'id' => int] or null.
 */
function findDuplicateAccountIdentity(mysqli $mysqli, $email, $fullName, $excludeId = 0, $checkFullName = true) {
    $normalizedEmail = strtolower(trim((string)$email));
    $emailStmt = $mysqli->prepare('SELECT id FROM users WHERE LOWER(TRIM(email)) = ? AND id <> ? LIMIT 1');
    if (!$emailStmt) throw new RuntimeException('Could not check email uniqueness.');
    $excludeId = (int)$excludeId;
    $emailStmt->bind_param('si', $normalizedEmail, $excludeId);
    $emailStmt->execute();
    $emailRow = $emailStmt->get_result()->fetch_assoc();
    $emailStmt->close();
    if ($emailRow) return ['type' => 'email', 'id' => (int)$emailRow['id']];

    if (!$checkFullName) return null;
    $normalizedName = normalizeAccountFullName($fullName);
    if ($normalizedName === '') return null;
    $nameStmt = $mysqli->prepare("SELECT id, full_name FROM users WHERE role IN ('teacher', 'student', 'admin_teacher') AND id <> ?");
    if (!$nameStmt) throw new RuntimeException('Could not check name uniqueness.');
    $nameStmt->bind_param('i', $excludeId);
    $nameStmt->execute();
    $result = $nameStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (normalizeAccountFullName($row['full_name']) === $normalizedName) {
            $nameStmt->close();
            return ['type' => 'name', 'id' => (int)$row['id']];
        }
    }
    $nameStmt->close();
    return null;
}

function duplicateAccountMessage(array $duplicate) {
    return $duplicate['type'] === 'email'
        ? 'This Gmail/email address is already used by another account.'
        : 'A teacher or student already has this full name. Add the correct middle name or other distinguishing part, then try again.';
}
