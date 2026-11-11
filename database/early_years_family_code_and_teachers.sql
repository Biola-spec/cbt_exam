-- Early Years family-code login and multiple teachers per class.
CREATE TABLE IF NOT EXISTS early_years_family_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    access_code VARCHAR(24) NOT NULL UNIQUE,
    family_label VARCHAR(120) NULL,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ey_family_code_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS early_years_family_students (
    family_id INT NOT NULL,
    student_id INT NOT NULL,
    PRIMARY KEY (family_id, student_id),
    KEY idx_ey_family_student (student_id),
    CONSTRAINT fk_ey_family_students_family FOREIGN KEY (family_id) REFERENCES early_years_family_codes(id) ON DELETE CASCADE,
    CONSTRAINT fk_ey_family_students_student FOREIGN KEY (student_id) REFERENCES early_years_students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS early_years_class_teachers (
    class_id INT NOT NULL,
    teacher_id INT NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (class_id, teacher_id),
    KEY idx_ey_class_teacher_user (teacher_id),
    CONSTRAINT fk_ey_class_teachers_class FOREIGN KEY (class_id) REFERENCES early_years_classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_ey_class_teachers_user FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Convert family codes created before the FAMAIIS-EY-number format.
UPDATE early_years_family_codes
SET access_code=CONCAT('FAMAIIS-EY-', LPAD(id, 6, '0'))
WHERE access_code NOT LIKE 'FAMAIIS-EY-%';

-- Carry existing one-teacher assignments into the new many-to-many table.
INSERT IGNORE INTO early_years_class_teachers(class_id, teacher_id)
SELECT id, teacher_id FROM early_years_classes WHERE teacher_id IS NOT NULL;

-- Create one family code for each existing parent and retain linked pupils.
INSERT IGNORE INTO early_years_family_codes(access_code, family_label)
SELECT CONCAT('FAMAIIS-EY-', LPAD(id, 6, '0')), LEFT(full_name, 120)
FROM early_years_parents;
INSERT IGNORE INTO early_years_family_students(family_id, student_id)
SELECT fc.id, pc.student_id
FROM early_years_parent_children pc
JOIN early_years_parents p ON p.id=pc.parent_id
JOIN early_years_family_codes fc ON fc.access_code=CONCAT('FAMAIIS-EY-', LPAD(p.id, 6, '0'));
