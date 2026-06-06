USE lms_saas;

ALTER TABLE schools
    ADD COLUMN cover_image VARCHAR(512) DEFAULT NULL AFTER address;
