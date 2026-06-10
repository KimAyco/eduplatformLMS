-- EduPlatform Database Schema
-- Import this file first, then seed_super_admin.sql

CREATE DATABASE IF NOT EXISTS lms_saas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lms_saas;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS student_component_grades;
DROP TABLE IF EXISTS class_grading_links;
DROP TABLE IF EXISTS subject_grading_components;
DROP TABLE IF EXISTS user_notifications;
DROP TABLE IF EXISTS announcement_targets;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS conversation_participants;
DROP TABLE IF EXISTS conversations;
DROP TABLE IF EXISTS quiz_attempt_answers;
DROP TABLE IF EXISTS quiz_attempts;
DROP TABLE IF EXISTS quiz_options;
DROP TABLE IF EXISTS quiz_questions;
DROP TABLE IF EXISTS quizzes;
DROP TABLE IF EXISTS assignment_submissions;
DROP TABLE IF EXISTS assignments;
DROP TABLE IF EXISTS library_resources;
DROP TABLE IF EXISTS materials;
DROP TABLE IF EXISTS course_sections;
DROP TABLE IF EXISTS class_group_students;
DROP TABLE IF EXISTS class_teachers;
DROP TABLE IF EXISTS teacher_subjects;
DROP TABLE IF EXISTS classes;
DROP TABLE IF EXISTS subjects;
DROP TABLE IF EXISTS class_groups;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS schools;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE schools (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    school_code VARCHAR(32) DEFAULT NULL UNIQUE,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    cover_image VARCHAR(512) DEFAULT NULL,
    logo_image VARCHAR(512) DEFAULT NULL,
    practice_quizzes_enabled TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('pending', 'active', 'rejected', 'suspended') NOT NULL DEFAULT 'pending',
    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME DEFAULT NULL,
    approved_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id INT UNSIGNED DEFAULT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'school_admin', 'teacher', 'student') NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    profile_image VARCHAR(512) DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_school_email (school_id, email),
    KEY idx_users_school (school_id),
    KEY idx_users_role (role),
    CONSTRAINT fk_users_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE schools ADD CONSTRAINT fk_schools_approved_by
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE class_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    academic_year VARCHAR(20) DEFAULT NULL,
    program_id INT UNSIGNED NULL DEFAULT NULL,
    program_level_id INT UNSIGNED NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_class_groups_school (school_id),
    KEY idx_class_groups_program (program_id),
    KEY idx_class_groups_program_level (program_level_id),
    CONSTRAINT fk_class_groups_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subjects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subjects_school_name (school_id, name),
    KEY idx_subjects_school (school_id),
    CONSTRAINT fk_subjects_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subject_grading_components (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_id INT UNSIGNED NOT NULL,
    school_id INT UNSIGNED NOT NULL,
    category VARCHAR(32) NOT NULL,
    label VARCHAR(255) NOT NULL,
    weight_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_sgc_subject (subject_id),
    KEY idx_sgc_school (school_id),
    CONSTRAINT fk_sgc_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_sgc_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE programs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_programs_school_name (school_id, name),
    KEY idx_programs_school (school_id),
    CONSTRAINT fk_programs_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE program_levels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    program_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    level_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_program_levels_order (program_id, level_order),
    KEY idx_program_levels_program (program_id),
    CONSTRAINT fk_program_levels_program FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE program_terms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    program_level_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    term_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_program_terms_order (program_level_id, term_order),
    KEY idx_program_terms_level (program_level_id),
    CONSTRAINT fk_program_terms_level FOREIGN KEY (program_level_id) REFERENCES program_levels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE program_term_subjects (
    program_term_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (program_term_id, subject_id),
    KEY idx_pts_subject (subject_id),
    CONSTRAINT fk_pts_term FOREIGN KEY (program_term_id) REFERENCES program_terms(id) ON DELETE CASCADE,
    CONSTRAINT fk_pts_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_program_enrollments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    program_id INT UNSIGNED NOT NULL,
    status ENUM('active', 'completed', 'withdrawn') NOT NULL DEFAULT 'active',
    enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_program (student_id, program_id),
    KEY idx_spe_program (program_id),
    KEY idx_spe_student (student_id),
    CONSTRAINT fk_spe_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_spe_program FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE class_groups
    ADD CONSTRAINT fk_class_groups_program FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_class_groups_program_level FOREIGN KEY (program_level_id) REFERENCES program_levels(id) ON DELETE SET NULL;

CREATE TABLE classes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id INT UNSIGNED NOT NULL,
    class_group_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    cover_image VARCHAR(512) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_classes_group_subject (class_group_id, subject_id),
    KEY idx_classes_school (school_id),
    KEY idx_classes_group (class_group_id),
    KEY idx_classes_subject (subject_id),
    CONSTRAINT fk_classes_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_classes_group FOREIGN KEY (class_group_id) REFERENCES class_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_classes_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE teacher_subjects (
    teacher_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (teacher_id, subject_id),
    KEY idx_ts_subject (subject_id),
    CONSTRAINT fk_ts_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ts_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE class_teachers (
    class_id INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (class_id, teacher_id),
    KEY idx_ct_teacher (teacher_id),
    CONSTRAINT fk_ct_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_ct_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE class_group_students (
    class_group_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (class_group_id, student_id),
    KEY idx_cgs_student (student_id),
    CONSTRAINT fk_cgs_group FOREIGN KEY (class_group_id) REFERENCES class_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_cgs_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE course_sections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_course_sections_class (class_id),
    CONSTRAINT fk_course_sections_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE library_resources (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    source_material_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    resource_kind ENUM('lesson', 'book', 'module', 'worksheet', 'reference', 'other') NOT NULL DEFAULT 'other',
    subject_id INT UNSIGNED DEFAULT NULL,
    type VARCHAR(16) NOT NULL DEFAULT 'file',
    content LONGTEXT DEFAULT NULL,
    body TEXT DEFAULT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    original_name VARCHAR(255) DEFAULT NULL,
    mime_type VARCHAR(120) DEFAULT NULL,
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    file_access_mode ENUM('view_only', 'downloadable') NOT NULL DEFAULT 'downloadable',
    external_link VARCHAR(500) DEFAULT NULL,
    status ENUM('pending', 'published', 'rejected') NOT NULL DEFAULT 'pending',
    audience ENUM('all', 'teachers') NOT NULL DEFAULT 'all',
    rejection_note TEXT DEFAULT NULL,
    approved_by INT UNSIGNED DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_library_school (school_id),
    KEY idx_library_status (school_id, status),
    KEY idx_library_subject (subject_id),
    KEY idx_library_source_material (source_material_id),
    KEY idx_library_file_path (file_path(191)),
    CONSTRAINT fk_library_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_library_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_library_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    CONSTRAINT fk_library_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE content_resources (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    subject_id INT UNSIGNED DEFAULT NULL,
    resource_type ENUM('deck', 'doc') NOT NULL DEFAULT 'deck',
    content LONGTEXT DEFAULT NULL,
    thumbnail_path VARCHAR(500) DEFAULT NULL,
    status ENUM('draft', 'archived') NOT NULL DEFAULT 'draft',
    library_resource_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_content_resources_school (school_id),
    KEY idx_content_resources_creator (school_id, created_by),
    KEY idx_content_resources_type (school_id, resource_type),
    KEY idx_content_resources_library (library_resource_id),
    CONSTRAINT fk_content_resources_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_resources_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_resources_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    CONSTRAINT fk_content_resources_library FOREIGN KEY (library_resource_id) REFERENCES library_resources(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE materials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    section_id INT UNSIGNED DEFAULT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    library_resource_id INT UNSIGNED DEFAULT NULL,
    content_resource_id INT UNSIGNED DEFAULT NULL,
    type VARCHAR(16) NOT NULL DEFAULT 'file',
    title VARCHAR(255) NOT NULL,
    content LONGTEXT DEFAULT NULL,
    body TEXT DEFAULT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    original_name VARCHAR(255) DEFAULT NULL,
    mime_type VARCHAR(120) DEFAULT NULL,
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    file_access_mode ENUM('view_only', 'downloadable') NOT NULL DEFAULT 'downloadable',
    external_link VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_materials_class (class_id),
    KEY idx_materials_section (section_id),
    KEY idx_materials_library (library_resource_id),
    KEY idx_materials_content_resource (content_resource_id),
    CONSTRAINT fk_materials_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_materials_section FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE SET NULL,
    CONSTRAINT fk_materials_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_materials_library FOREIGN KEY (library_resource_id) REFERENCES library_resources(id) ON DELETE SET NULL,
    CONSTRAINT fk_materials_content_resource FOREIGN KEY (content_resource_id) REFERENCES content_resources(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE library_resources
    ADD CONSTRAINT fk_library_source_material FOREIGN KEY (source_material_id) REFERENCES materials(id) ON DELETE SET NULL;

CREATE TABLE assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    section_id INT UNSIGNED DEFAULT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    instructions TEXT DEFAULT NULL,
    due_date DATETIME DEFAULT NULL,
    max_points DECIMAL(8,2) NOT NULL DEFAULT 100.00,
    allow_late TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_assignments_class (class_id),
    KEY idx_assignments_section (section_id),
    CONSTRAINT fk_assignments_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_assignments_section FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE SET NULL,
    CONSTRAINT fk_assignments_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assignment_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    content TEXT DEFAULT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    grade DECIMAL(8,2) DEFAULT NULL,
    feedback TEXT DEFAULT NULL,
    status ENUM('submitted', 'graded', 'returned') NOT NULL DEFAULT 'submitted',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_assignment_student (assignment_id, student_id),
    KEY idx_submissions_student (student_id),
    CONSTRAINT fk_submissions_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    CONSTRAINT fk_submissions_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quizzes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    section_id INT UNSIGNED DEFAULT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    instructions TEXT DEFAULT NULL,
    time_limit_minutes INT UNSIGNED DEFAULT NULL,
    due_date DATETIME DEFAULT NULL,
    opens_at DATETIME DEFAULT NULL,
    closes_at DATETIME DEFAULT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    randomize_questions_order TINYINT(1) NOT NULL DEFAULT 0,
    show_score_to_students TINYINT(1) NOT NULL DEFAULT 1,
    cover_image VARCHAR(512) DEFAULT NULL,
    max_attempts INT UNSIGNED NOT NULL DEFAULT 1,
    quiz_mode ENUM('exam', 'practice') NOT NULL DEFAULT 'exam',
    source_section_id INT UNSIGNED DEFAULT NULL,
    context_version VARCHAR(64) DEFAULT NULL,
    is_ai_generated TINYINT(1) NOT NULL DEFAULT 0,
    counts_toward_gradebook TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_quizzes_class (class_id),
    KEY idx_quizzes_section (section_id),
    KEY idx_quizzes_mode (quiz_mode),
    KEY idx_quizzes_practice_section (class_id, source_section_id, quiz_mode),
    CONSTRAINT fk_quizzes_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_quizzes_section FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE SET NULL,
    CONSTRAINT fk_quizzes_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quiz_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    type VARCHAR(32) NOT NULL DEFAULT 'multiple_choice',
    points DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    correct_answer TEXT DEFAULT NULL,
    settings JSON DEFAULT NULL,
    teacher_attachment_path VARCHAR(512) DEFAULT NULL,
    media_type VARCHAR(32) DEFAULT NULL,
    media_path VARCHAR(512) DEFAULT NULL,
    media_url VARCHAR(1024) DEFAULT NULL,
    KEY idx_questions_quiz (quiz_id),
    CONSTRAINT fk_questions_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quiz_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT UNSIGNED NOT NULL,
    option_text VARCHAR(500) NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    KEY idx_options_question (question_id),
    CONSTRAINT fk_options_question FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quiz_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME DEFAULT NULL,
    score DECIMAL(8,2) DEFAULT NULL,
    max_score DECIMAL(8,2) DEFAULT NULL,
    status ENUM('in_progress', 'submitted', 'graded') NOT NULL DEFAULT 'in_progress',
    graded_by INT UNSIGNED DEFAULT NULL,
    KEY idx_attempts_quiz (quiz_id),
    KEY idx_attempts_student (student_id),
    KEY idx_attempts_graded_by (graded_by),
    CONSTRAINT fk_attempts_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    CONSTRAINT fk_attempts_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_attempts_graded_by FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quiz_attempt_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    answer_text TEXT DEFAULT NULL,
    response_payload JSON DEFAULT NULL,
    student_attachment_path VARCHAR(512) DEFAULT NULL,
    selected_option_id INT UNSIGNED DEFAULT NULL,
    is_correct TINYINT(1) DEFAULT NULL,
    points_earned DECIMAL(8,2) DEFAULT NULL,
    teacher_feedback TEXT DEFAULT NULL,
    KEY idx_answers_attempt (attempt_id),
    KEY idx_answers_question (question_id),
    CONSTRAINT fk_answers_attempt FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    CONSTRAINT fk_answers_question FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_answers_option FOREIGN KEY (selected_option_id) REFERENCES quiz_options(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE class_grading_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    component_id INT UNSIGNED NOT NULL,
    quiz_id INT UNSIGNED DEFAULT NULL,
    assignment_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_class_component (class_id, component_id),
    KEY idx_cgl_class (class_id),
    KEY idx_cgl_component (component_id),
    KEY idx_cgl_quiz (quiz_id),
    KEY idx_cgl_assignment (assignment_id),
    CONSTRAINT fk_cgl_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_cgl_component FOREIGN KEY (component_id) REFERENCES subject_grading_components(id) ON DELETE CASCADE,
    CONSTRAINT fk_cgl_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE SET NULL,
    CONSTRAINT fk_cgl_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_component_grades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    component_id INT UNSIGNED NOT NULL,
    score DECIMAL(8,2) DEFAULT NULL,
    max_score DECIMAL(8,2) DEFAULT NULL,
    percent DECIMAL(5,2) DEFAULT NULL,
    source_quiz_attempt_id INT UNSIGNED DEFAULT NULL,
    source_submission_id INT UNSIGNED DEFAULT NULL,
    is_manual TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_scg_student_component (class_id, student_id, component_id),
    KEY idx_scg_class (class_id),
    KEY idx_scg_student (student_id),
    KEY idx_scg_component (component_id),
    CONSTRAINT fk_scg_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_scg_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_scg_component FOREIGN KEY (component_id) REFERENCES subject_grading_components(id) ON DELETE CASCADE,
    CONSTRAINT fk_scg_quiz_attempt FOREIGN KEY (source_quiz_attempt_id) REFERENCES quiz_attempts(id) ON DELETE SET NULL,
    CONSTRAINT fk_scg_submission FOREIGN KEY (source_submission_id) REFERENCES assignment_submissions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE conversations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_conversations_school_updated (school_id, updated_at),
    CONSTRAINT fk_conversations_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE conversation_participants (
    conversation_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    last_read_message_id INT UNSIGNED DEFAULT NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (conversation_id, user_id),
    KEY idx_cp_user (user_id),
    CONSTRAINT fk_cp_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_cp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    reply_to_message_id INT UNSIGNED NULL DEFAULT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_at DATETIME NULL DEFAULT NULL,
    deleted_at DATETIME NULL DEFAULT NULL,
    KEY idx_messages_conversation_id (conversation_id, id),
    KEY idx_messages_sender (sender_id),
    KEY idx_messages_reply_to (reply_to_message_id),
    CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_reply_to FOREIGN KEY (reply_to_message_id) REFERENCES messages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE message_edits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id INT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_message_edits_message (message_id, saved_at),
    CONSTRAINT fk_message_edits_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE message_user_hidden (
    message_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    hidden_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (message_id, user_id),
    KEY idx_message_user_hidden_user (user_id),
    CONSTRAINT fk_message_user_hidden_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    CONSTRAINT fk_message_user_hidden_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    priority ENUM('normal', 'important', 'urgent') NOT NULL DEFAULT 'normal',
    link_url VARCHAR(512) NULL DEFAULT NULL,
    link_label VARCHAR(100) NULL DEFAULT NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL DEFAULT NULL,
    expires_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_announcements_school_status (school_id, status, published_at),
    CONSTRAINT fk_announcements_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_announcements_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE announcement_targets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT UNSIGNED NOT NULL,
    target_type VARCHAR(40) NOT NULL,
    target_id INT UNSIGNED NULL DEFAULT NULL,
    KEY idx_announcement_targets_announcement (announcement_id),
    CONSTRAINT fk_announcement_targets_announcement FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    announcement_id INT UNSIGNED NOT NULL,
    read_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_notification (user_id, announcement_id),
    KEY idx_user_notifications_user_unread (user_id, read_at, created_at),
    CONSTRAINT fk_user_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_notifications_announcement FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platform_settings (
    setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ai_request_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_type VARCHAR(64) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    priority TINYINT UNSIGNED NOT NULL DEFAULT 5,
    payload JSON NOT NULL,
    prompt_preview VARCHAR(500) DEFAULT NULL,
    result JSON DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    assigned_key_index TINYINT UNSIGNED DEFAULT NULL,
    requested_by INT UNSIGNED DEFAULT NULL,
    school_id INT UNSIGNED DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ai_queue_status (status, priority, created_at),
    KEY idx_ai_queue_user (requested_by),
    KEY idx_ai_queue_school (school_id),
    KEY idx_ai_queue_school_created (school_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ai_key_usage (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_index TINYINT UNSIGNED NOT NULL,
    window_start DATETIME NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_ai_key_window (key_index, window_start),
    KEY idx_ai_key_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lesson_contexts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    section_id INT UNSIGNED DEFAULT NULL,
    context_text LONGTEXT DEFAULT NULL,
    sources_hash VARCHAR(64) NOT NULL DEFAULT '',
    token_estimate INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('pending', 'ready', 'empty', 'error') NOT NULL DEFAULT 'pending',
    last_indexed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_lesson_context (class_id, section_id),
    KEY idx_lesson_context_class (class_id),
    CONSTRAINT fk_lesson_context_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_lesson_context_section FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lesson_context_sources (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lesson_context_id INT UNSIGNED NOT NULL,
    source_type ENUM('material', 'library', 'exam_meta', 'upload') NOT NULL,
    source_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    content_hash VARCHAR(64) NOT NULL DEFAULT '',
    excerpt TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_lcs_context (lesson_context_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE practice_question_bank (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    section_id INT UNSIGNED DEFAULT NULL,
    quiz_id INT UNSIGNED DEFAULT NULL,
    question_json LONGTEXT NOT NULL,
    difficulty VARCHAR(32) NOT NULL DEFAULT 'mixed',
    context_version VARCHAR(64) NOT NULL DEFAULT '',
    item_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_practice_bank (class_id, section_id),
    KEY idx_practice_bank_quiz (quiz_id),
    CONSTRAINT fk_practice_bank_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_practice_bank_section FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE CASCADE,
    CONSTRAINT fk_practice_bank_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_lesson_proficiency (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    section_id INT UNSIGNED DEFAULT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    best_score_pct DECIMAL(5,2) DEFAULT NULL,
    avg_score_pct DECIMAL(5,2) DEFAULT NULL,
    last_attempt_at DATETIME DEFAULT NULL,
    proficiency_level ENUM('beginner', 'developing', 'proficient', 'mastery') NOT NULL DEFAULT 'beginner',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_lesson_prof (student_id, class_id, section_id),
    KEY idx_slp_class (class_id),
    CONSTRAINT fk_slp_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_slp_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_slp_section FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

