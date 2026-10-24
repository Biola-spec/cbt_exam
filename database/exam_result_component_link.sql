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
