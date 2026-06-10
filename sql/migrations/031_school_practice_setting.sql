ALTER TABLE schools
    ADD COLUMN practice_quizzes_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER logo_image;
