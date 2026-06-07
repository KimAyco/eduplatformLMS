USE lms_saas;

ALTER TABLE classes
    ADD COLUMN cover_image VARCHAR(512) DEFAULT NULL AFTER subject_id;
