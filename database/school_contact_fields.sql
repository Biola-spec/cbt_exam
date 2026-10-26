-- Add editable school contact details for report cards and result sheets.
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
