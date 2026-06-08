-- Extended quiz question types and JSON settings
ALTER TABLE quiz_questions
    MODIFY COLUMN type VARCHAR(32) NOT NULL DEFAULT 'multiple_choice',
    ADD COLUMN settings JSON NULL AFTER correct_answer,
    ADD COLUMN teacher_attachment_path VARCHAR(512) NULL AFTER settings,
    ADD COLUMN media_type VARCHAR(32) NULL AFTER teacher_attachment_path,
    ADD COLUMN media_path VARCHAR(512) NULL AFTER media_type,
    ADD COLUMN media_url VARCHAR(1024) NULL AFTER media_path;

UPDATE quiz_questions SET type = 'multiple_choice' WHERE type = 'mcq';
UPDATE quiz_questions SET type = 'essay' WHERE type = 'short_answer';
