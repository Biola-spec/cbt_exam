-- Student results sheets, configurable grading, and report metadata.
-- Safe additive migration; existing scores and attempts are not changed.
ALTER TABLE users
    ADD COLUMN admission_number VARCHAR(100) NULL AFTER full_name;

ALTER TABLE exams
    ADD COLUMN academic_session VARCHAR(50) NULL AFTER title;

CREATE TABLE grade_settings (
    id INT NOT NULL AUTO_INCREMENT,
    minimum_score DECIMAL(5,2) NOT NULL,
    maximum_score DECIMAL(5,2) NOT NULL,
    grade_letter VARCHAR(20) NOT NULL,
    remark VARCHAR(120) NOT NULL,
    is_passing TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_grade_settings_minimum (minimum_score),
    UNIQUE KEY uq_grade_settings_letter (grade_letter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO grade_settings (minimum_score, maximum_score, grade_letter, remark, is_passing, sort_order) VALUES
(0.00, 39.99, 'F', 'Fail', 0, 1),
(40.00, 49.99, 'E', 'Pass', 1, 2),
(50.00, 59.99, 'D', 'Fair', 1, 3),
(60.00, 69.99, 'C', 'Good', 1, 4),
(70.00, 79.99, 'B', 'Very Good', 1, 5),
(80.00, 100.00, 'A', 'Excellent', 1, 6);
