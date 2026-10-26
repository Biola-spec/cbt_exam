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
