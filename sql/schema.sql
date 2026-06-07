-- EduPlatform Database Schema
-- Import this file first, then seed_super_admin.sql

CREATE DATABASE IF NOT EXISTS lms_saas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lms_saas;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS quiz_attempt_answers;
DROP TABLE IF EXISTS quiz_attempts;
DROP TABLE IF EXISTS quiz_options;
DROP TABLE IF EXISTS quiz_questions;
DROP TABLE IF EXISTS quizzes;
DROP TABLE IF EXISTS assignment_submissions;
DROP TABLE IF EXISTS assignments;
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
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_class_groups_school (school_id),
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

CREATE TABLE materials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    section_id INT UNSIGNED DEFAULT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT DEFAULT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    external_link VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_materials_class (class_id),
    KEY idx_materials_section (section_id),
    CONSTRAINT fk_materials_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_materials_section FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE SET NULL,
    CONSTRAINT fk_materials_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    max_attempts INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_quizzes_class (class_id),
    KEY idx_quizzes_section (section_id),
    CONSTRAINT fk_quizzes_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_quizzes_section FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE SET NULL,
    CONSTRAINT fk_quizzes_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quiz_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    type ENUM('mcq', 'true_false', 'short_answer') NOT NULL,
    points DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    correct_answer TEXT DEFAULT NULL,
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
    status ENUM('in_progress', 'submitted', 'graded') NOT NULL DEFAULT 'in_progress',
    KEY idx_attempts_quiz (quiz_id),
    KEY idx_attempts_student (student_id),
    CONSTRAINT fk_attempts_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    CONSTRAINT fk_attempts_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quiz_attempt_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    answer_text TEXT DEFAULT NULL,
    selected_option_id INT UNSIGNED DEFAULT NULL,
    is_correct TINYINT(1) DEFAULT NULL,
    points_earned DECIMAL(8,2) DEFAULT NULL,
    KEY idx_answers_attempt (attempt_id),
    KEY idx_answers_question (question_id),
    CONSTRAINT fk_answers_attempt FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    CONSTRAINT fk_answers_question FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_answers_option FOREIGN KEY (selected_option_id) REFERENCES quiz_options(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

