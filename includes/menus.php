<?php

function superAdminMenu(): array
{
    return [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'superadmin/dashboard.php', 'icon' => 'fa-gauge'],
        ['key' => 'schools', 'label' => 'Schools', 'url' => 'superadmin/schools.php', 'icon' => 'fa-school'],
    ];
}

function schoolAdminMenu(): array
{
    return [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'school/dashboard.php', 'icon' => 'fa-gauge'],
        ['key' => 'teachers', 'label' => 'Teachers', 'url' => 'school/teachers.php', 'icon' => 'fa-chalkboard-user'],
        ['key' => 'students', 'label' => 'Students', 'url' => 'school/students.php', 'icon' => 'fa-user-graduate'],
        ['key' => 'classes', 'label' => 'Classes', 'url' => 'school/classes.php', 'icon' => 'fa-book'],
        ['key' => 'enrollments', 'label' => 'Enrollments', 'url' => 'school/enrollments.php', 'icon' => 'fa-users'],
    ];
}

function teacherMenu(): array
{
    return [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'teacher/dashboard.php', 'icon' => 'fa-gauge'],
        ['key' => 'classes', 'label' => 'My Classes', 'url' => 'teacher/classes.php', 'icon' => 'fa-book-open'],
        ['key' => 'materials', 'label' => 'Materials', 'url' => 'teacher/materials.php', 'icon' => 'fa-file-lines'],
        ['key' => 'assignments', 'label' => 'Assignments', 'url' => 'teacher/assignments.php', 'icon' => 'fa-pen-to-square'],
        ['key' => 'quizzes', 'label' => 'Quizzes', 'url' => 'teacher/quizzes.php', 'icon' => 'fa-circle-question'],
        ['key' => 'grading', 'label' => 'Grade Submissions', 'url' => 'teacher/grade-submissions.php', 'icon' => 'fa-check-double'],
    ];
}

function studentMenu(): array
{
    return [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'student/dashboard.php', 'icon' => 'fa-gauge'],
        ['key' => 'classes', 'label' => 'My Classes', 'url' => 'student/classes.php', 'icon' => 'fa-book-open'],
        ['key' => 'materials', 'label' => 'Materials', 'url' => 'student/materials.php', 'icon' => 'fa-file-lines'],
        ['key' => 'assignments', 'label' => 'Assignments', 'url' => 'student/assignments.php', 'icon' => 'fa-pen-to-square'],
        ['key' => 'quizzes', 'label' => 'Quizzes', 'url' => 'student/quizzes.php', 'icon' => 'fa-circle-question'],
    ];
}
