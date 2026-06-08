-- Materials module: file, link, doc types with metadata
ALTER TABLE materials
    ADD COLUMN type VARCHAR(16) NOT NULL DEFAULT 'file' AFTER teacher_id,
    ADD COLUMN content LONGTEXT NULL AFTER title,
    ADD COLUMN original_name VARCHAR(255) NULL AFTER file_path,
    ADD COLUMN mime_type VARCHAR(120) NULL AFTER original_name,
    ADD COLUMN file_size BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER mime_type,
    ADD COLUMN file_access_mode ENUM('view_only', 'downloadable') NOT NULL DEFAULT 'downloadable' AFTER file_size;

UPDATE materials SET type = 'link', content = external_link
WHERE external_link IS NOT NULL AND external_link != '' AND (file_path IS NULL OR file_path = '');

UPDATE materials SET type = 'file'
WHERE type = 'file' AND file_path IS NOT NULL AND file_path != '';

UPDATE materials SET content = body
WHERE body IS NOT NULL AND body != '' AND type = 'file' AND (content IS NULL OR content = '');
