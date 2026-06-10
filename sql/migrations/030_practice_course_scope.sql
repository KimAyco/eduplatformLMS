-- Full-course practice scope (all lessons combined)
ALTER TABLE lesson_contexts
    ADD COLUMN is_course_wide TINYINT(1) NOT NULL DEFAULT 0 AFTER section_id;

ALTER TABLE lesson_contexts
    DROP INDEX uq_lesson_context,
    ADD UNIQUE KEY uq_lesson_context (class_id, section_id, is_course_wide);

ALTER TABLE practice_question_bank
    ADD COLUMN is_course_wide TINYINT(1) NOT NULL DEFAULT 0 AFTER section_id;

ALTER TABLE practice_question_bank
    DROP INDEX uq_practice_bank,
    ADD UNIQUE KEY uq_practice_bank (class_id, section_id, is_course_wide);

ALTER TABLE student_lesson_proficiency
    ADD COLUMN is_course_wide TINYINT(1) NOT NULL DEFAULT 0 AFTER section_id;

ALTER TABLE student_lesson_proficiency
    DROP INDEX uq_student_lesson_prof,
    ADD UNIQUE KEY uq_student_lesson_prof (student_id, class_id, section_id, is_course_wide);

ALTER TABLE quizzes
    ADD COLUMN is_course_wide TINYINT(1) NOT NULL DEFAULT 0 AFTER source_section_id;
