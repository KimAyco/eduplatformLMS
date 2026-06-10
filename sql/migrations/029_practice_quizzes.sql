-- Practice quizzes, question banks, and student proficiency
ALTER TABLE quizzes
    ADD COLUMN quiz_mode ENUM('exam', 'practice') NOT NULL DEFAULT 'exam' AFTER max_attempts,
    ADD COLUMN source_section_id INT UNSIGNED DEFAULT NULL AFTER quiz_mode,
    ADD COLUMN context_version VARCHAR(64) DEFAULT NULL AFTER source_section_id,
    ADD COLUMN is_ai_generated TINYINT(1) NOT NULL DEFAULT 0 AFTER context_version,
    ADD COLUMN counts_toward_gradebook TINYINT(1) NOT NULL DEFAULT 1 AFTER is_ai_generated;

ALTER TABLE quizzes
    ADD KEY idx_quizzes_mode (quiz_mode),
    ADD KEY idx_quizzes_practice_section (class_id, source_section_id, quiz_mode);

CREATE TABLE IF NOT EXISTS practice_question_bank (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    section_id INT UNSIGNED DEFAULT NULL,
    is_course_wide TINYINT(1) NOT NULL DEFAULT 0,
    quiz_id INT UNSIGNED DEFAULT NULL,
    question_json LONGTEXT NOT NULL,
    difficulty VARCHAR(32) NOT NULL DEFAULT 'mixed',
    context_version VARCHAR(64) NOT NULL DEFAULT '',
    item_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_practice_bank (class_id, section_id, is_course_wide),
    KEY idx_practice_bank_quiz (quiz_id),
    CONSTRAINT fk_practice_bank_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_practice_bank_section FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE CASCADE,
    CONSTRAINT fk_practice_bank_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_lesson_proficiency (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    section_id INT UNSIGNED DEFAULT NULL,
    is_course_wide TINYINT(1) NOT NULL DEFAULT 0,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    best_score_pct DECIMAL(5,2) DEFAULT NULL,
    avg_score_pct DECIMAL(5,2) DEFAULT NULL,
    last_attempt_at DATETIME DEFAULT NULL,
    proficiency_level ENUM('beginner', 'developing', 'proficient', 'mastery') NOT NULL DEFAULT 'beginner',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_lesson_prof (student_id, class_id, section_id, is_course_wide),
    KEY idx_slp_class (class_id),
    CONSTRAINT fk_slp_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_slp_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_slp_section FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
