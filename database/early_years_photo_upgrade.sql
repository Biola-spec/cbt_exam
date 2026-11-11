-- Run once on installations where early_years.sql was already applied.
SET @ey_photo_upgrade = IF(
    EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'early_years_students'
              AND COLUMN_NAME = 'passport_path'),
    'SELECT 1',
    'ALTER TABLE early_years_students ADD COLUMN passport_path VARCHAR(255) NULL AFTER gender'
);
PREPARE ey_photo_statement FROM @ey_photo_upgrade;
EXECUTE ey_photo_statement;
DEALLOCATE PREPARE ey_photo_statement;
