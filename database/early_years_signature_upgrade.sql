-- Early Years class teacher and shared head-of-school signatures.
SET @ey_signature_upgrade = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='early_years_classes'
              AND COLUMN_NAME='teacher_signature_path'),
    'SELECT 1',
    'ALTER TABLE early_years_classes ADD COLUMN teacher_signature_path VARCHAR(255) NULL AFTER teacher_id'
);
PREPARE ey_signature_statement FROM @ey_signature_upgrade;
EXECUTE ey_signature_statement;
DEALLOCATE PREPARE ey_signature_statement;

CREATE TABLE IF NOT EXISTS early_years_school_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    head_name VARCHAR(180) NOT NULL,
    head_signature_path VARCHAR(255) NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ey_school_settings_editor FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @ey_head_user_upgrade = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='early_years_school_settings' AND COLUMN_NAME='head_user_id'),
    'SELECT 1',
    'ALTER TABLE early_years_school_settings ADD COLUMN head_user_id INT NULL AFTER id'
);
PREPARE ey_head_user_statement FROM @ey_head_user_upgrade;
EXECUTE ey_head_user_statement;
DEALLOCATE PREPARE ey_head_user_statement;
