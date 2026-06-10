CREATE TABLE IF NOT EXISTS programs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_programs_school_name (school_id, name),
    KEY idx_programs_school (school_id),
    CONSTRAINT fk_programs_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS program_levels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    program_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    level_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_program_levels_order (program_id, level_order),
    KEY idx_program_levels_program (program_id),
    CONSTRAINT fk_program_levels_program FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS program_terms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    program_level_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    term_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_program_terms_order (program_level_id, term_order),
    KEY idx_program_terms_level (program_level_id),
    CONSTRAINT fk_program_terms_level FOREIGN KEY (program_level_id) REFERENCES program_levels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS program_term_subjects (
    program_term_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (program_term_id, subject_id),
    KEY idx_pts_subject (subject_id),
    CONSTRAINT fk_pts_term FOREIGN KEY (program_term_id) REFERENCES program_terms(id) ON DELETE CASCADE,
    CONSTRAINT fk_pts_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_program_enrollments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    program_id INT UNSIGNED NOT NULL,
    status ENUM('active', 'completed', 'withdrawn') NOT NULL DEFAULT 'active',
    enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_program (student_id, program_id),
    KEY idx_spe_program (program_id),
    KEY idx_spe_student (student_id),
    CONSTRAINT fk_spe_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_spe_program FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE class_groups
    ADD COLUMN program_id INT UNSIGNED NULL DEFAULT NULL AFTER academic_year,
    ADD COLUMN program_level_id INT UNSIGNED NULL DEFAULT NULL AFTER program_id,
    ADD KEY idx_class_groups_program (program_id),
    ADD KEY idx_class_groups_program_level (program_level_id),
    ADD CONSTRAINT fk_class_groups_program FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_class_groups_program_level FOREIGN KEY (program_level_id) REFERENCES program_levels(id) ON DELETE SET NULL;
