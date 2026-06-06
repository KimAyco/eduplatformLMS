USE lms_saas;

ALTER TABLE schools
    ADD COLUMN logo_image VARCHAR(512) DEFAULT NULL AFTER cover_image;
