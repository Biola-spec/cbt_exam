-- Add parent logins and per-session Early Years report settings.
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
