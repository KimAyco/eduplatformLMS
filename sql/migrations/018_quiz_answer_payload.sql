-- Quiz attempt answer payloads and manual grading fields
ALTER TABLE quiz_attempts
    ADD COLUMN max_score DECIMAL(8,2) NULL AFTER score,
    ADD COLUMN graded_by INT UNSIGNED NULL AFTER status,
    ADD KEY idx_attempts_graded_by (graded_by),
    ADD CONSTRAINT fk_attempts_graded_by FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE quiz_attempt_answers
    ADD COLUMN response_payload JSON NULL AFTER answer_text,
    ADD COLUMN student_attachment_path VARCHAR(512) NULL AFTER response_payload,
    ADD COLUMN teacher_feedback TEXT NULL AFTER points_earned;
