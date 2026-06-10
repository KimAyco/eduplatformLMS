-- Lesson context indexing for AI practice and quiz generation
CREATE TABLE IF NOT EXISTS lesson_contexts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    section_id INT UNSIGNED DEFAULT NULL,
    is_course_wide TINYINT(1) NOT NULL DEFAULT 0,
    context_text LONGTEXT DEFAULT NULL,
    sources_hash VARCHAR(64) NOT NULL DEFAULT '',
    token_estimate INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('pending', 'ready', 'empty', 'error') NOT NULL DEFAULT 'pending',
    last_indexed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_lesson_context (class_id, section_id, is_course_wide),
    KEY idx_lesson_context_class (class_id),
    CONSTRAINT fk_lesson_context_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_lesson_context_section FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_context_sources (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lesson_context_id INT UNSIGNED NOT NULL,
    source_type ENUM('material', 'library', 'exam_meta', 'upload') NOT NULL,
    source_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    content_hash VARCHAR(64) NOT NULL DEFAULT '',
    excerpt TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_lcs_context (lesson_context_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
