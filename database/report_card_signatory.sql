-- Report-card signature/stamp assets and per-class signatory assignment.
SET @has_head_signature_path = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'school_config' AND COLUMN_NAME = 'head_signature_path'
);
SET @sql = IF(@has_head_signature_path = 0,
    'ALTER TABLE school_config ADD COLUMN head_signature_path VARCHAR(255) NULL AFTER logo_path',
    'SELECT 1');
PREPARE report_card_stmt FROM @sql;
EXECUTE report_card_stmt;
DEALLOCATE PREPARE report_card_stmt;

SET @has_school_stamp_path = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'school_config' AND COLUMN_NAME = 'school_stamp_path'
);
SET @sql = IF(@has_school_stamp_path = 0,
    'ALTER TABLE school_config ADD COLUMN school_stamp_path VARCHAR(255) NULL AFTER head_signature_path',
    'SELECT 1');
PREPARE report_card_stmt FROM @sql;
EXECUTE report_card_stmt;
DEALLOCATE PREPARE report_card_stmt;

SET @has_principal_signature_path = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'school_config' AND COLUMN_NAME = 'principal_signature_path'
);
SET @sql = IF(@has_principal_signature_path = 0,
    'ALTER TABLE school_config ADD COLUMN principal_signature_path VARCHAR(255) NULL AFTER head_signature_path',
    'SELECT 1');
PREPARE report_card_stmt FROM @sql;
EXECUTE report_card_stmt;
DEALLOCATE PREPARE report_card_stmt;

CREATE TABLE IF NOT EXISTS class_report_signatories (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    position ENUM('head_teacher','principal') NOT NULL DEFAULT 'head_teacher',
    staff_id INT NOT NULL,
    title VARCHAR(80) NOT NULL,
    signature_path VARCHAR(255) NULL,
    assigned_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_class_report_signatory (class_id),
    CONSTRAINT fk_report_signatory_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_report_signatory_staff FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_report_signatory_assigner FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @has_signatory_signature = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'class_report_signatories' AND COLUMN_NAME = 'signature_path'
);
SET @sql = IF(@has_signatory_signature = 0,
    'ALTER TABLE class_report_signatories ADD COLUMN signature_path VARCHAR(255) NULL AFTER title',
    'SELECT 1');
PREPARE report_card_stmt FROM @sql;
EXECUTE report_card_stmt;
DEALLOCATE PREPARE report_card_stmt;

SET @has_signatory_position = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'class_report_signatories' AND COLUMN_NAME = 'position'
);
SET @sql = IF(@has_signatory_position = 0,
    'ALTER TABLE class_report_signatories ADD COLUMN position ENUM(''head_teacher'',''principal'') NOT NULL DEFAULT ''head_teacher'' AFTER class_id',
    'SELECT 1');
PREPARE report_card_stmt FROM @sql;
EXECUTE report_card_stmt;
DEALLOCATE PREPARE report_card_stmt;

UPDATE class_report_signatories
SET position = 'principal'
WHERE position = 'head_teacher' AND LOWER(title) LIKE '%principal%';

DELETE head_teacher_row
FROM class_report_signatories head_teacher_row
JOIN class_report_signatories principal_row
    ON principal_row.class_id = head_teacher_row.class_id
    AND principal_row.position = 'principal'
WHERE head_teacher_row.position = 'head_teacher';

SET @has_single_signatory_key = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'class_report_signatories' AND INDEX_NAME = 'uq_class_report_signatory'
);
SET @sql = IF(@has_single_signatory_key = 0,
    'ALTER TABLE class_report_signatories ADD UNIQUE KEY uq_class_report_signatory (class_id)',
    'SELECT 1');
PREPARE report_card_stmt FROM @sql;
EXECUTE report_card_stmt;
DEALLOCATE PREPARE report_card_stmt;

SET @has_position_key = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'class_report_signatories' AND INDEX_NAME = 'uq_class_report_position'
);
SET @sql = IF(@has_position_key > 0,
    'ALTER TABLE class_report_signatories DROP INDEX uq_class_report_position',
    'SELECT 1');
PREPARE report_card_stmt FROM @sql;
EXECUTE report_card_stmt;
DEALLOCATE PREPARE report_card_stmt;

CREATE TABLE IF NOT EXISTS class_report_remarks (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    session_id INT NOT NULL,
    term TINYINT UNSIGNED NOT NULL,
    student_id INT NOT NULL,
    position ENUM('head_teacher','principal') NOT NULL,
    remark TEXT NOT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_authority_term_remark (class_id, session_id, term, student_id, position),
    CONSTRAINT fk_authority_remark_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_authority_remark_session FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_authority_remark_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_authority_remark_editor FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
