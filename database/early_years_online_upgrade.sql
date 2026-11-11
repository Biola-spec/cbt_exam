-- Separate Early Years roster and teacher-entered report cards.
-- Academic sessions and school branding continue to use the main system tables.
CREATE TABLE IF NOT EXISTS early_years_classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    teacher_id INT NULL,
    teacher_signature_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ey_class_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS early_years_school_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    head_user_id INT NULL,
    head_name VARCHAR(180) NOT NULL,
    head_signature_path VARCHAR(255) NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ey_school_settings_head FOREIGN KEY (head_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_ey_school_settings_editor FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS early_years_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    full_name VARCHAR(180) NOT NULL,
    admission_number VARCHAR(80) NULL,
    date_of_birth DATE NULL,
    gender VARCHAR(30) NULL,
    passport_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ey_student_class (class_id, full_name),
    CONSTRAINT fk_ey_student_class FOREIGN KEY (class_id) REFERENCES early_years_classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS early_years_reports (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    session_id INT NOT NULL,
    term TINYINT UNSIGNED NOT NULL,
    report_type ENUM('half_term','end_term') NOT NULL,
    report_data LONGTEXT NOT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ey_report (student_id, session_id, term, report_type),
    CONSTRAINT fk_ey_report_student FOREIGN KEY (student_id) REFERENCES early_years_students(id) ON DELETE CASCADE,
    CONSTRAINT fk_ey_report_session FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_ey_report_editor FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS early_years_parents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(180) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS early_years_parent_children (
    parent_id INT NOT NULL,
    student_id INT NOT NULL,
    PRIMARY KEY (parent_id, student_id),
    CONSTRAINT fk_ey_parent_child_parent FOREIGN KEY (parent_id) REFERENCES early_years_parents(id) ON DELETE CASCADE,
    CONSTRAINT fk_ey_parent_child_student FOREIGN KEY (student_id) REFERENCES early_years_students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS early_years_term_settings (
    session_id INT NOT NULL,
    term TINYINT UNSIGNED NOT NULL,
    next_term_begins DATE NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (session_id, term),
    CONSTRAINT fk_ey_term_setting_session FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_ey_term_setting_editor FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Upgrade older Early Years installs safely.
SET @ey_teacher_signature_sql = IF(
  EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='early_years_classes' AND COLUMN_NAME='teacher_signature_path'),
  'SELECT 1',
  'ALTER TABLE early_years_classes ADD COLUMN teacher_signature_path VARCHAR(255) NULL AFTER teacher_id'
);
PREPARE ey_teacher_signature_stmt FROM @ey_teacher_signature_sql;
EXECUTE ey_teacher_signature_stmt;
DEALLOCATE PREPARE ey_teacher_signature_stmt;

SET @ey_pupil_photo_sql = IF(
  EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='early_years_students' AND COLUMN_NAME='passport_path'),
  'SELECT 1',
  'ALTER TABLE early_years_students ADD COLUMN passport_path VARCHAR(255) NULL AFTER gender'
);
PREPARE ey_pupil_photo_stmt FROM @ey_pupil_photo_sql;
EXECUTE ey_pupil_photo_stmt;
DEALLOCATE PREPARE ey_pupil_photo_stmt;

SET @ey_head_user_sql = IF(
  EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='early_years_school_settings' AND COLUMN_NAME='head_user_id'),
  'SELECT 1',
  'ALTER TABLE early_years_school_settings ADD COLUMN head_user_id INT NULL AFTER id'
);
PREPARE ey_head_user_stmt FROM @ey_head_user_sql;
EXECUTE ey_head_user_stmt;
DEALLOCATE PREPARE ey_head_user_stmt;

SET @ey_head_user_fk_sql = IF(
  EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='early_years_school_settings' AND COLUMN_NAME='head_user_id' AND REFERENCED_TABLE_NAME='users'),
  'SELECT 1',
  'ALTER TABLE early_years_school_settings ADD CONSTRAINT fk_ey_school_settings_head FOREIGN KEY (head_user_id) REFERENCES users(id) ON DELETE SET NULL'
);
PREPARE ey_head_user_fk_stmt FROM @ey_head_user_fk_sql;
EXECUTE ey_head_user_fk_stmt;
DEALLOCATE PREPARE ey_head_user_fk_stmt;

-- Early Years family-code login and multiple teachers per class.
CREATE TABLE IF NOT EXISTS early_years_family_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    access_code VARCHAR(24) NOT NULL UNIQUE,
    family_label VARCHAR(120) NULL,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ey_family_code_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS early_years_family_students (
    family_id INT NOT NULL,
    student_id INT NOT NULL,
    PRIMARY KEY (family_id, student_id),
    KEY idx_ey_family_student (student_id),
    CONSTRAINT fk_ey_family_students_family FOREIGN KEY (family_id) REFERENCES early_years_family_codes(id) ON DELETE CASCADE,
    CONSTRAINT fk_ey_family_students_student FOREIGN KEY (student_id) REFERENCES early_years_students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS early_years_class_teachers (
    class_id INT NOT NULL,
    teacher_id INT NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (class_id, teacher_id),
    KEY idx_ey_class_teacher_user (teacher_id),
    CONSTRAINT fk_ey_class_teachers_class FOREIGN KEY (class_id) REFERENCES early_years_classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_ey_class_teachers_user FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Convert family codes created before the FAMAIIS-EY-number format.
UPDATE early_years_family_codes
SET access_code=CONCAT('FAMAIIS-EY-', LPAD(id, 6, '0'))
WHERE access_code NOT LIKE 'FAMAIIS-EY-%';

-- Carry existing one-teacher assignments into the new many-to-many table.
INSERT IGNORE INTO early_years_class_teachers(class_id, teacher_id)
SELECT id, teacher_id FROM early_years_classes WHERE teacher_id IS NOT NULL;

-- Create one family code for each existing parent and retain linked pupils.
INSERT IGNORE INTO early_years_family_codes(access_code, family_label)
SELECT CONCAT('FAMAIIS-EY-', LPAD(id, 6, '0')), LEFT(full_name, 120)
FROM early_years_parents;
INSERT IGNORE INTO early_years_family_students(family_id, student_id)
SELECT fc.id, pc.student_id
FROM early_years_parent_children pc
JOIN early_years_parents p ON p.id=pc.parent_id
JOIN early_years_family_codes fc ON fc.access_code=CONCAT('FAMAIIS-EY-', LPAD(p.id, 6, '0'));
