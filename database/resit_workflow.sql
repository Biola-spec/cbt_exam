CREATE TABLE IF NOT EXISTS resit_requests (
    id INT NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    exam_subject_id INT NOT NULL,
    requested_by INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reason TEXT NULL,
    admin_note TEXT NULL,
    resit_date DATE NULL,
    resit_start_time TIME NULL,
    resit_end_time TIME NULL,
    venue VARCHAR(255) NULL,
    penalty_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    approved_by INT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    resit_exam_id INT NULL,
    resit_exam_subject_id INT NULL,
    PRIMARY KEY (id),
    KEY idx_resit_requests_student (student_id),
    KEY idx_resit_requests_status (status),
    KEY idx_resit_requests_subject (exam_subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @has_resit_penalty_percentage = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'resit_requests' AND COLUMN_NAME = 'penalty_percentage'
);
SET @resit_penalty_sql = IF(@has_resit_penalty_percentage = 0,
    'ALTER TABLE resit_requests ADD COLUMN penalty_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER venue',
    'SELECT 1');
PREPARE resit_penalty_stmt FROM @resit_penalty_sql;
EXECUTE resit_penalty_stmt;
DEALLOCATE PREPARE resit_penalty_stmt;

ALTER TABLE exam_subjects
    ADD COLUMN resit_request_id INT NULL AFTER class_id,
    ADD KEY idx_exam_subjects_resit_request (resit_request_id);
