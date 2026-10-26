-- Online cPanel/phpMyAdmin database upgrade for CBT Exam System
-- Select the existing cbt_exam_system database before running.
-- Safe upgrade: creates missing tables/columns and preserves existing records.
-- Run in this order: term results, exam links, report card signatories, class attendance.


-- ============================================================
-- Source: database/term_results.sql
-- ============================================================
-- Manual, three-term academic results. Safe to apply again to upgrade an existing database.
-- Student enrollment uses users.class_id; add it only on older installations where it is absent.
SET @has_student_class_id = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'class_id'
);
SET @student_class_sql = IF(@has_student_class_id = 0,
    'ALTER TABLE users ADD COLUMN class_id INT NULL AFTER role',
    'SELECT 1');
PREPARE student_class_stmt FROM @student_class_sql;
EXECUTE student_class_stmt;
DEALLOCATE PREPARE student_class_stmt;

CREATE TABLE IF NOT EXISTS academic_sessions (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL UNIQUE,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_academic_sessions_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS class_result_settings (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    session_id INT NOT NULL,
    ca_count TINYINT UNSIGNED NOT NULL DEFAULT 3,
    exam_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_class_result_settings (class_id, session_id),
    CONSTRAINT fk_result_settings_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_result_settings_session FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS class_result_component_names (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    session_id INT NOT NULL,
    component_type ENUM('ca','exam') NOT NULL,
    component_number TINYINT UNSIGNED NOT NULL,
    label VARCHAR(60) NOT NULL,
    max_score DECIMAL(7,2) NOT NULL DEFAULT 100.00,
    UNIQUE KEY uq_result_component_name (class_id, session_id, component_type, component_number),
    CONSTRAINT fk_result_component_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_result_component_session FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @has_component_max_score = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'class_result_component_names' AND COLUMN_NAME = 'max_score'
);
SET @component_max_sql = IF(@has_component_max_score = 0,
    'ALTER TABLE class_result_component_names ADD COLUMN max_score DECIMAL(7,2) NOT NULL DEFAULT 100.00 AFTER label',
    'SELECT 1');
PREPARE component_max_stmt FROM @component_max_sql;
EXECUTE component_max_stmt;
DEALLOCATE PREPARE component_max_stmt;

CREATE TABLE IF NOT EXISTS result_periods (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    session_id INT NOT NULL,
    term TINYINT UNSIGNED NOT NULL,
    status ENUM('draft','submitted','approved','recalled') NOT NULL DEFAULT 'draft',
    submitted_at DATETIME NULL,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_result_period (class_id, session_id, term),
    CONSTRAINT fk_result_period_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_result_period_session FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_result_period_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS term_scores (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    period_id INT NOT NULL,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_term_score (period_id, student_id, subject_id),
    KEY idx_term_scores_student (student_id, period_id),
    CONSTRAINT fk_term_score_period FOREIGN KEY (period_id) REFERENCES result_periods(id) ON DELETE CASCADE,
    CONSTRAINT fk_term_score_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_term_score_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_term_score_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @has_term_score_release_status = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'term_scores' AND COLUMN_NAME = 'release_status'
);
SET @term_score_release_status_sql = IF(@has_term_score_release_status = 0,
    "ALTER TABLE term_scores ADD COLUMN release_status ENUM('draft','submitted','approved','recalled') NOT NULL DEFAULT 'draft' AFTER teacher_id",
    'SELECT 1');
PREPARE term_score_release_status_stmt FROM @term_score_release_status_sql;
EXECUTE term_score_release_status_stmt;
DEALLOCATE PREPARE term_score_release_status_stmt;

SET @has_term_score_released_by = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'term_scores' AND COLUMN_NAME = 'released_by'
);
SET @term_score_released_by_sql = IF(@has_term_score_released_by = 0,
    'ALTER TABLE term_scores ADD COLUMN released_by INT NULL AFTER release_status',
    'SELECT 1');
PREPARE term_score_released_by_stmt FROM @term_score_released_by_sql;
EXECUTE term_score_released_by_stmt;
DEALLOCATE PREPARE term_score_released_by_stmt;

SET @has_term_score_released_at = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'term_scores' AND COLUMN_NAME = 'released_at'
);
SET @term_score_released_at_sql = IF(@has_term_score_released_at = 0,
    'ALTER TABLE term_scores ADD COLUMN released_at DATETIME NULL AFTER released_by',
    'SELECT 1');
PREPARE term_score_released_at_stmt FROM @term_score_released_at_sql;
EXECUTE term_score_released_at_stmt;
DEALLOCATE PREPARE term_score_released_at_stmt;

UPDATE term_scores ts
JOIN result_periods rp ON rp.id = ts.period_id
SET ts.release_status = rp.status,
    ts.released_by = IF(rp.status = 'approved', rp.approved_by, NULL),
    ts.released_at = IF(rp.status = 'approved', rp.approved_at, NULL)
WHERE rp.status IN ('submitted','approved','recalled') AND ts.release_status = 'draft';

CREATE TABLE IF NOT EXISTS term_score_items (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    term_score_id INT NOT NULL,
    component_type ENUM('ca','exam') NOT NULL,
    component_number TINYINT UNSIGNED NOT NULL,
    score DECIMAL(6,2) NULL,
    UNIQUE KEY uq_term_score_item (term_score_id, component_type, component_number),
    CONSTRAINT fk_term_score_item_score FOREIGN KEY (term_score_id) REFERENCES term_scores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- Source: database/exam_result_component_link.sql
-- ============================================================
SET @has_result_session_id = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_subjects' AND COLUMN_NAME = 'result_session_id'
);
SET @result_session_sql = IF(@has_result_session_id = 0,
    'ALTER TABLE exam_subjects ADD COLUMN result_session_id INT NULL AFTER subject_id',
    'SELECT 1');
PREPARE result_session_stmt FROM @result_session_sql;
EXECUTE result_session_stmt;
DEALLOCATE PREPARE result_session_stmt;

SET @has_result_term = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_subjects' AND COLUMN_NAME = 'result_term'
);
SET @result_term_sql = IF(@has_result_term = 0,
    'ALTER TABLE exam_subjects ADD COLUMN result_term TINYINT UNSIGNED NULL AFTER result_session_id',
    'SELECT 1');
PREPARE result_term_stmt FROM @result_term_sql;
EXECUTE result_term_stmt;
DEALLOCATE PREPARE result_term_stmt;

SET @has_result_component_type = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_subjects' AND COLUMN_NAME = 'result_component_type'
);
SET @result_component_type_sql = IF(@has_result_component_type = 0,
    "ALTER TABLE exam_subjects ADD COLUMN result_component_type ENUM('ca','exam') NULL AFTER result_term",
    'SELECT 1');
PREPARE result_component_type_stmt FROM @result_component_type_sql;
EXECUTE result_component_type_stmt;
DEALLOCATE PREPARE result_component_type_stmt;

SET @has_result_component_number = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_subjects' AND COLUMN_NAME = 'result_component_number'
);
SET @result_component_number_sql = IF(@has_result_component_number = 0,
    'ALTER TABLE exam_subjects ADD COLUMN result_component_number TINYINT UNSIGNED NULL AFTER result_component_type',
    'SELECT 1');
PREPARE result_component_number_stmt FROM @result_component_number_sql;
EXECUTE result_component_number_stmt;
DEALLOCATE PREPARE result_component_number_stmt;

SET @has_result_link_index = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_subjects' AND INDEX_NAME = 'idx_exam_subject_result_link'
);
SET @result_link_index_sql = IF(@has_result_link_index = 0,
    'CREATE INDEX idx_exam_subject_result_link ON exam_subjects (class_id, subject_id, result_session_id, result_term)',
    'SELECT 1');
PREPARE result_link_index_stmt FROM @result_link_index_sql;
EXECUTE result_link_index_stmt;
DEALLOCATE PREPARE result_link_index_stmt;

SET @has_exam_result_session_id = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'result_session_id'
);
SET @exam_result_session_sql = IF(@has_exam_result_session_id = 0,
    'ALTER TABLE exams ADD COLUMN result_session_id INT NULL AFTER academic_session',
    'SELECT 1');
PREPARE exam_result_session_stmt FROM @exam_result_session_sql;
EXECUTE exam_result_session_stmt;
DEALLOCATE PREPARE exam_result_session_stmt;

SET @has_exam_result_term = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'result_term'
);
SET @exam_result_term_sql = IF(@has_exam_result_term = 0,
    'ALTER TABLE exams ADD COLUMN result_term TINYINT UNSIGNED NULL AFTER result_session_id',
    'SELECT 1');
PREPARE exam_result_term_stmt FROM @exam_result_term_sql;
EXECUTE exam_result_term_stmt;
DEALLOCATE PREPARE exam_result_term_stmt;

SET @has_exam_result_component_type = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'result_component_type'
);
SET @exam_result_component_type_sql = IF(@has_exam_result_component_type = 0,
    "ALTER TABLE exams ADD COLUMN result_component_type ENUM('ca','exam') NULL AFTER result_term",
    'SELECT 1');
PREPARE exam_result_component_type_stmt FROM @exam_result_component_type_sql;
EXECUTE exam_result_component_type_stmt;
DEALLOCATE PREPARE exam_result_component_type_stmt;

SET @has_exam_result_component_number = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'result_component_number'
);
SET @exam_result_component_number_sql = IF(@has_exam_result_component_number = 0,
    'ALTER TABLE exams ADD COLUMN result_component_number TINYINT UNSIGNED NULL AFTER result_component_type',
    'SELECT 1');
PREPARE exam_result_component_number_stmt FROM @exam_result_component_number_sql;
EXECUTE exam_result_component_number_stmt;
DEALLOCATE PREPARE exam_result_component_number_stmt;

SET @has_exam_result_link_index = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exams' AND INDEX_NAME = 'idx_exam_result_link'
);
SET @exam_result_link_index_sql = IF(@has_exam_result_link_index = 0,
    'CREATE INDEX idx_exam_result_link ON exams (result_session_id, result_term)',
    'SELECT 1');
PREPARE exam_result_link_index_stmt FROM @exam_result_link_index_sql;
EXECUTE exam_result_link_index_stmt;
DEALLOCATE PREPARE exam_result_link_index_stmt;

UPDATE exams e
JOIN (
    SELECT exam_id, MIN(id) AS exam_subject_id
    FROM exam_subjects
    WHERE result_session_id IS NOT NULL AND result_term IS NOT NULL
      AND result_component_type IS NOT NULL AND result_component_number IS NOT NULL
    GROUP BY exam_id
) linked ON linked.exam_id=e.id
JOIN exam_subjects es ON es.id=linked.exam_subject_id
SET e.result_session_id=es.result_session_id,
    e.result_term=es.result_term,
    e.result_component_type=es.result_component_type,
    e.result_component_number=es.result_component_number
WHERE e.result_session_id IS NULL;

CREATE TABLE IF NOT EXISTS exam_class_result_links (
    exam_id INT NOT NULL,
    class_id INT NOT NULL,
    result_session_id INT NOT NULL,
    result_term TINYINT UNSIGNED NOT NULL,
    result_component_type ENUM('ca','exam') NOT NULL,
    result_component_number TINYINT UNSIGNED NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (exam_id, class_id),
    CONSTRAINT fk_exam_class_result_link_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    CONSTRAINT fk_exam_class_result_link_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_exam_class_result_link_session FOREIGN KEY (result_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO exam_class_result_links
    (exam_id, class_id, result_session_id, result_term, result_component_type, result_component_number)
SELECT e.id, es.class_id, e.result_session_id, e.result_term, e.result_component_type, e.result_component_number
FROM exams e
JOIN exam_subjects es ON es.exam_id=e.id
WHERE e.result_session_id IS NOT NULL AND e.result_term BETWEEN 1 AND 3
  AND e.result_component_type IS NOT NULL AND e.result_component_number IS NOT NULL;


-- ============================================================
-- Source: database/school_contact_fields.sql
-- Editable school address and phone details for printed report cards and score sheets.
SET @has_school_address = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'school_config' AND COLUMN_NAME = 'school_address'
);
SET @sql = IF(@has_school_address = 0,
    'ALTER TABLE school_config ADD COLUMN school_address VARCHAR(500) NULL AFTER school_name',
    'SELECT 1');
PREPARE school_contact_stmt FROM @sql;
EXECUTE school_contact_stmt;
DEALLOCATE PREPARE school_contact_stmt;

SET @has_school_phone = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'school_config' AND COLUMN_NAME = 'school_phone'
);
SET @sql = IF(@has_school_phone = 0,
    'ALTER TABLE school_config ADD COLUMN school_phone VARCHAR(150) NULL AFTER school_address',
    'SELECT 1');
PREPARE school_contact_stmt FROM @sql;
EXECUTE school_contact_stmt;
DEALLOCATE PREPARE school_contact_stmt;

-- ============================================================
-- Source: database/report_card_signatory.sql
-- ============================================================
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


-- ============================================================
-- Source: database/class_teacher_attendance.sql
-- ============================================================
-- Class-teacher assignments, daily attendance and term remarks.
CREATE TABLE IF NOT EXISTS class_teacher_assignments (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    teacher_id INT NOT NULL,
    assigned_by INT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_class_teacher (class_id, teacher_id),
    CONSTRAINT fk_class_teacher_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_class_teacher_user FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_class_teacher_assigner FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS class_attendance (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    student_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present','absent','late','excused') NOT NULL,
    marked_by INT NULL,
    marked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_class_attendance_day (class_id, student_id, attendance_date),
    KEY idx_class_attendance_date (class_id, attendance_date),
    CONSTRAINT fk_attendance_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_marker FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS class_term_remarks (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    session_id INT NOT NULL,
    term TINYINT UNSIGNED NOT NULL,
    student_id INT NOT NULL,
    remark TEXT NOT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_class_term_student_remark (class_id, session_id, term, student_id),
    CONSTRAINT fk_class_remark_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_class_remark_session FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_class_remark_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_class_remark_teacher FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS term_report_settings (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    term TINYINT UNSIGNED NOT NULL,
    school_days_open SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_term_report_settings (session_id, term),
    CONSTRAINT fk_report_setting_session FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_report_setting_admin FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS class_student_attendance_summary (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    session_id INT NOT NULL,
    term TINYINT UNSIGNED NOT NULL,
    student_id INT NOT NULL,
    days_present SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_term_attendance (class_id, session_id, term, student_id),
    CONSTRAINT fk_attendance_summary_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_summary_session FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_summary_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_summary_teacher FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS class_report_categories (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    domain ENUM('psychomotor','locomotor') NOT NULL,
    label VARCHAR(80) NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_report_category_domain_label (domain, label),
    KEY idx_report_categories_display (domain, is_active, sort_order),
    CONSTRAINT fk_report_category_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS class_student_ratings (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    class_id INT NOT NULL,
    session_id INT NOT NULL,
    term TINYINT UNSIGNED NOT NULL,
    student_id INT NOT NULL,
    score TINYINT UNSIGNED NOT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_term_category_rating (category_id, class_id, session_id, term, student_id),
    CONSTRAINT fk_student_rating_category FOREIGN KEY (category_id) REFERENCES class_report_categories(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_rating_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_rating_session FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_rating_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_rating_teacher FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
