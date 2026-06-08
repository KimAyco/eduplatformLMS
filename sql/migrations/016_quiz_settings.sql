-- Quiz scheduling, publishing, and display settings
ALTER TABLE quizzes
    ADD COLUMN opens_at DATETIME NULL AFTER due_date,
    ADD COLUMN closes_at DATETIME NULL AFTER opens_at,
    ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 1 AFTER closes_at,
    ADD COLUMN randomize_questions_order TINYINT(1) NOT NULL DEFAULT 0 AFTER is_published,
    ADD COLUMN show_score_to_students TINYINT(1) NOT NULL DEFAULT 1 AFTER randomize_questions_order,
    ADD COLUMN cover_image VARCHAR(512) NULL AFTER show_score_to_students;

UPDATE quizzes SET closes_at = due_date WHERE due_date IS NOT NULL AND closes_at IS NULL;
