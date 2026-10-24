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
