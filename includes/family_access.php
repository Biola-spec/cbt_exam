<?php
/** Ensure shared family links exist and carry forward existing Early Years links. */
function familyAccessEnsureSchema(mysqli $db): bool {
    try {
        $column = $db->query("SELECT CHARACTER_MAXIMUM_LENGTH max_length FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='early_years_family_codes' AND COLUMN_NAME='access_code'")->fetch_assoc();
        if ($column && (int)$column['max_length'] < 64) {
            $db->query('ALTER TABLE early_years_family_codes MODIFY access_code VARCHAR(64) NOT NULL');
        }
        $db->query("CREATE TABLE IF NOT EXISTS family_student_links (
            family_id INT NOT NULL,
            student_type ENUM('early_years','student') NOT NULL,
            student_id INT NOT NULL,
            PRIMARY KEY (family_id, student_type, student_id),
            KEY idx_family_student_lookup (student_type, student_id),
            CONSTRAINT fk_family_student_link_family FOREIGN KEY (family_id)
                REFERENCES early_years_family_codes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->query("INSERT IGNORE INTO family_student_links(family_id,student_type,student_id)
            SELECT family_id,'early_years',student_id FROM early_years_family_students");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}


/** Normalize family labels so "MUHAMMAD" and "Muhammad Family" identify one family. */
function familyAccessNormalizeFamilyLabel(string $label): string {
    $label = trim($label);
    $label = preg_replace('/\s+family\s*$/i', '', $label);
    if (function_exists('mb_strtolower')) $label = mb_strtolower($label, 'UTF-8');
    else $label = strtolower($label);
    return trim(preg_replace('/[^a-z0-9]+/i', ' ', $label));
}

function familyAccessFindCodeByLabel(mysqli $db, string $label, int $excludeId = 0): ?array {
    $key = familyAccessNormalizeFamilyLabel($label);
    if ($key === '') return null;
    $result = $db->query('SELECT id,access_code,family_label FROM early_years_family_codes ORDER BY id ASC');
    while ($family = $result->fetch_assoc()) {
        if ((int)$family['id'] !== $excludeId && familyAccessNormalizeFamilyLabel((string)($family['family_label'] ?? '')) === $key) return $family;
    }
    return null;
}

/** Merge duplicate labels while preserving every school and Early Years link. */
function familyAccessMergeDuplicateLabels(mysqli $db): int {
    $result = $db->query('SELECT id,access_code,family_label FROM early_years_family_codes ORDER BY id ASC');
    $groups = [];
    while ($family = $result->fetch_assoc()) {
        $key = familyAccessNormalizeFamilyLabel((string)($family['family_label'] ?? ''));
        if ($key !== '') $groups[$key][] = $family;
    }
    $deleted = 0;
    foreach ($groups as $group) {
        if (count($group) < 2) continue;
        $canonicalId = (int)$group[0]['id'];
        $db->begin_transaction();
        try {
            $copyShared = $db->prepare('INSERT IGNORE INTO family_student_links(family_id,student_type,student_id) SELECT ?,student_type,student_id FROM family_student_links WHERE family_id=?');
            $copyLegacy = $db->prepare('INSERT IGNORE INTO early_years_family_students(family_id,student_id) SELECT ?,student_id FROM early_years_family_students WHERE family_id=?');
            $remove = $db->prepare('DELETE FROM early_years_family_codes WHERE id=?');
            foreach (array_slice($group, 1) as $duplicate) {
                $duplicateId = (int)$duplicate['id'];
                $copyShared->bind_param('ii', $canonicalId, $duplicateId); $copyShared->execute();
                $copyLegacy->bind_param('ii', $canonicalId, $duplicateId); $copyLegacy->execute();
                $remove->bind_param('i', $duplicateId); $remove->execute();
                $deleted += $remove->affected_rows;
            }
            $copyShared->close(); $copyLegacy->close(); $remove->close();
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }
    return $deleted;
}

/** Reuse a code for an existing family label, or create the family's only code. */
function familyAccessCreateOrReuseCode(mysqli $db, string $label, int $createdBy): array {
    $label = trim($label);
    if (familyAccessNormalizeFamilyLabel($label) === '') throw new RuntimeException('Enter a family name so its members can share one code.');
    $lockName = 'family-code-create';
    $lock = $db->prepare('SELECT GET_LOCK(?,10) acquired');
    $lock->bind_param('s', $lockName); $lock->execute();
    $acquired = (int)($lock->get_result()->fetch_assoc()['acquired'] ?? 0); $lock->close();
    if ($acquired !== 1) throw new RuntimeException('Family code creation is busy. Please try again.');
    try {
        $existing = familyAccessFindCodeByLabel($db, $label);
        if ($existing) return ['id' => (int)$existing['id'], 'code' => $existing['access_code'], 'created' => false];
        $next = (int)($db->query("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(access_code,'-',-1) AS UNSIGNED)),0)+1 n FROM early_years_family_codes")->fetch_assoc()['n'] ?? 1);
        $prefix = preg_replace('/\s+family\s*$/i', '', $label);
        $prefix = strtoupper(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $prefix), '-'));
        $prefix = substr($prefix, 0, 32);
        $code = ($prefix !== '' ? $prefix . '-' : '') . 'FAMAIIS-FAMILY-' . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
        $stmt = $db->prepare('INSERT INTO early_years_family_codes(access_code,family_label,created_by) VALUES(?,?,?)');
        $stmt->bind_param('ssi', $code, $label, $createdBy); $stmt->execute(); $id = (int)$db->insert_id; $stmt->close();
        return ['id' => $id, 'code' => $code, 'created' => true];
    } finally {
        $release = $db->prepare('SELECT RELEASE_LOCK(?)');
        $release->bind_param('s', $lockName); $release->execute(); $release->close();
    }
}
