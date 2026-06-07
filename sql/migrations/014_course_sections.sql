-- Course content sections (lessons/modules) per class

CREATE TABLE IF NOT EXISTS course_sections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_course_sections_class (class_id),
    CONSTRAINT fk_course_sections_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE materials
    ADD COLUMN section_id INT UNSIGNED DEFAULT NULL AFTER class_id,
    ADD KEY idx_materials_section (section_id),
    ADD CONSTRAINT fk_materials_section FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE SET NULL;

ALTER TABLE assignments
    ADD COLUMN section_id INT UNSIGNED DEFAULT NULL AFTER class_id,
    ADD KEY idx_assignments_section (section_id),
    ADD CONSTRAINT fk_assignments_section FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE SET NULL;

ALTER TABLE quizzes
    ADD COLUMN section_id INT UNSIGNED DEFAULT NULL AFTER class_id,
    ADD KEY idx_quizzes_section (section_id),
    ADD CONSTRAINT fk_quizzes_section FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE SET NULL;
