-- Add the Affective rating group. Admins define the skill fields in Report Card Settings.
-- Run once on existing databases after class_teacher_attendance.sql.
ALTER TABLE class_report_categories
    MODIFY domain ENUM('affective','psychomotor','locomotor') NOT NULL;
