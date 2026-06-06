USE lms_saas;

CREATE TABLE subjects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subjects_school_name (school_id, name),
    KEY idx_subjects_school (school_id),
    CONSTRAINT fk_subjects_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE teacher_subjects (
    teacher_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (teacher_id, subject_id),
    KEY idx_ts_subject (subject_id),
    CONSTRAINT fk_ts_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ts_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO subjects (school_id, name, description)
SELECT school_id, name, description
FROM classes
GROUP BY school_id, name, description;

ALTER TABLE classes ADD COLUMN subject_id INT UNSIGNED DEFAULT NULL AFTER class_group_id;

UPDATE classes c
INNER JOIN subjects s ON s.school_id = c.school_id AND s.name = c.name
SET c.subject_id = s.id;

ALTER TABLE classes
    DROP COLUMN name,
    DROP COLUMN description,
    MODIFY subject_id INT UNSIGNED NOT NULL,
    ADD UNIQUE KEY uq_classes_group_subject (class_group_id, subject_id),
    ADD KEY idx_classes_subject (subject_id),
    ADD CONSTRAINT fk_classes_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT;
