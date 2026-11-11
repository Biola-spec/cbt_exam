<?php
/** Ensure shared family links exist and carry forward existing Early Years links. */
function familyAccessEnsureSchema(mysqli $db): bool {
    try {
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
