-- Class groups: e.g. BSIT4A contains subject classes (NSTP1, ENG101).
-- Students enroll in class groups; teachers are assigned to individual classes.

CREATE TABLE class_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    academic_year VARCHAR(20) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_class_groups_school (school_id),
    CONSTRAINT fk_class_groups_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE classes ADD COLUMN class_group_id INT UNSIGNED DEFAULT NULL AFTER school_id;

INSERT INTO class_groups (school_id, name, description, academic_year)
SELECT DISTINCT school_id,
    COALESCE(NULLIF(TRIM(section), ''), name) AS name,
    NULL,
    academic_year
FROM classes;

UPDATE classes c
INNER JOIN class_groups g ON g.school_id = c.school_id
    AND g.name = COALESCE(NULLIF(TRIM(c.section), ''), c.name)
    AND (g.academic_year <=> c.academic_year)
SET c.class_group_id = g.id;

CREATE TABLE class_group_students (
    class_group_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (class_group_id, student_id),
    KEY idx_cgs_student (student_id),
    CONSTRAINT fk_cgs_group FOREIGN KEY (class_group_id) REFERENCES class_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_cgs_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO class_group_students (class_group_id, student_id, enrolled_at)
SELECT c.class_group_id, cs.student_id, cs.enrolled_at
FROM class_students cs
INNER JOIN classes c ON c.id = cs.class_id
WHERE c.class_group_id IS NOT NULL;

DROP TABLE class_students;

ALTER TABLE classes
    DROP COLUMN section,
    DROP COLUMN academic_year,
    MODIFY class_group_id INT UNSIGNED NOT NULL,
    ADD KEY idx_classes_group (class_group_id),
    ADD CONSTRAINT fk_classes_group FOREIGN KEY (class_group_id) REFERENCES class_groups(id) ON DELETE CASCADE;
