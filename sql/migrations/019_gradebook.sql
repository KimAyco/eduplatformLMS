CREATE TABLE IF NOT EXISTS subject_grading_components (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_id INT UNSIGNED NOT NULL,
    school_id INT UNSIGNED NOT NULL,
    category VARCHAR(32) NOT NULL,
    label VARCHAR(255) NOT NULL,
    weight_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_sgc_subject (subject_id),
    KEY idx_sgc_school (school_id),
    CONSTRAINT fk_sgc_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_sgc_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_grading_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    component_id INT UNSIGNED NOT NULL,
    quiz_id INT UNSIGNED DEFAULT NULL,
    assignment_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_class_component (class_id, component_id),
    KEY idx_cgl_class (class_id),
    KEY idx_cgl_component (component_id),
    KEY idx_cgl_quiz (quiz_id),
    KEY idx_cgl_assignment (assignment_id),
    CONSTRAINT fk_cgl_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_cgl_component FOREIGN KEY (component_id) REFERENCES subject_grading_components(id) ON DELETE CASCADE,
    CONSTRAINT fk_cgl_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE SET NULL,
    CONSTRAINT fk_cgl_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_component_grades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    component_id INT UNSIGNED NOT NULL,
    score DECIMAL(8,2) DEFAULT NULL,
    max_score DECIMAL(8,2) DEFAULT NULL,
    percent DECIMAL(5,2) DEFAULT NULL,
    source_quiz_attempt_id INT UNSIGNED DEFAULT NULL,
    source_submission_id INT UNSIGNED DEFAULT NULL,
    is_manual TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_scg_student_component (class_id, student_id, component_id),
    KEY idx_scg_class (class_id),
    KEY idx_scg_student (student_id),
    KEY idx_scg_component (component_id),
    CONSTRAINT fk_scg_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_scg_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_scg_component FOREIGN KEY (component_id) REFERENCES subject_grading_components(id) ON DELETE CASCADE,
    CONSTRAINT fk_scg_quiz_attempt FOREIGN KEY (source_quiz_attempt_id) REFERENCES quiz_attempts(id) ON DELETE SET NULL,
    CONSTRAINT fk_scg_submission FOREIGN KEY (source_submission_id) REFERENCES assignment_submissions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
