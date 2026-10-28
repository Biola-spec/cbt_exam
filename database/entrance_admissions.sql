-- Entrance examinations and candidate admission workflow.
-- Apply once to an existing CBT database.
CREATE TABLE IF NOT EXISTS entrance_exams (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    exam_subject_id INT NOT NULL UNIQUE,
    target_class_id INT NOT NULL,
    teacher_id INT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_entrance_teacher (teacher_id),
    KEY idx_entrance_class (target_class_id),
    CONSTRAINT fk_entrance_exam_subject FOREIGN KEY (exam_subject_id) REFERENCES exam_subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_entrance_class FOREIGN KEY (target_class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_entrance_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_entrance_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS entrance_applications (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    target_class_id INT NOT NULL,
    entrance_exam_id INT NOT NULL,
    status ENUM('pending','admitted','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_entrance_application_status (status),
    KEY idx_entrance_application_exam (entrance_exam_id),
    CONSTRAINT fk_entrance_application_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_entrance_application_class FOREIGN KEY (target_class_id) REFERENCES classes(id),
    CONSTRAINT fk_entrance_application_exam FOREIGN KEY (entrance_exam_id) REFERENCES entrance_exams(id) ON DELETE CASCADE,
    CONSTRAINT fk_entrance_application_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
