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
