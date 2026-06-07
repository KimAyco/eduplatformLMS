USE lms_saas;

ALTER TABLE users
    ADD COLUMN profile_image VARCHAR(512) DEFAULT NULL AFTER last_name;
