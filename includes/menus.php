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
        ['key' => 'grading', 'label' => 'Grade Submissions', 'url' => 'teacher/grade-submissions.php', 'icon' => 'fa-check-double'],
    ];
}

function studentMenu(): array
{
    return [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'student/dashboard.php', 'icon' => 'fa-gauge'],
    ];
}
