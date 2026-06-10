CREATE TABLE IF NOT EXISTS content_resources (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    subject_id INT UNSIGNED DEFAULT NULL,
    resource_type ENUM('deck', 'doc') NOT NULL DEFAULT 'deck',
    content LONGTEXT DEFAULT NULL,
    thumbnail_path VARCHAR(500) DEFAULT NULL,
    status ENUM('draft', 'archived') NOT NULL DEFAULT 'draft',
    library_resource_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_content_resources_school (school_id),
    KEY idx_content_resources_creator (school_id, created_by),
    KEY idx_content_resources_type (school_id, resource_type),
    KEY idx_content_resources_library (library_resource_id),
    CONSTRAINT fk_content_resources_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_resources_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_resources_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    CONSTRAINT fk_content_resources_library FOREIGN KEY (library_resource_id) REFERENCES library_resources(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE materials
    ADD COLUMN content_resource_id INT UNSIGNED NULL DEFAULT NULL AFTER library_resource_id,
    ADD KEY idx_materials_content_resource (content_resource_id),
    ADD CONSTRAINT fk_materials_content_resource FOREIGN KEY (content_resource_id) REFERENCES content_resources(id) ON DELETE SET NULL;
