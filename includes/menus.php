<?php

function drawerLabel(string $role): string
{
    return match ($role) {
        'school_admin' => 'School admin',
        'teacher' => 'Teaching',
        'student' => 'My learning',
        'super_admin' => 'Platform',
        default => 'Navigation',
    };
}

function superAdminMenu(): array
{
    return [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'superadmin/dashboard.php', 'icon' => 'fa-gauge'],
        ['key' => 'schools', 'label' => 'Schools', 'url' => 'superadmin/schools.php', 'icon' => 'fa-school'],
        ['key' => 'profile', 'label' => 'My profile', 'url' => 'profile.php', 'icon' => 'fa-circle-user', 'bottom' => true],
    ];
}

function schoolAdminMenu(): array
{
    return [
        ['section' => 'Overview'],
        ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'school/dashboard.php', 'icon' => 'fa-gauge'],
        ['section' => 'Academics'],
        ['key' => 'subjects', 'label' => 'Subjects', 'url' => 'school/subjects.php', 'icon' => 'fa-book'],
        ['key' => 'teachers', 'label' => 'Teachers', 'url' => 'school/teachers.php', 'icon' => 'fa-chalkboard-user'],
        ['key' => 'students', 'label' => 'Students', 'url' => 'school/students.php', 'icon' => 'fa-user-graduate'],
        ['key' => 'class_groups', 'label' => 'Class Groups', 'url' => 'school/class-groups.php', 'icon' => 'fa-layer-group'],
        ['key' => 'profile', 'label' => 'My profile', 'url' => 'profile.php', 'icon' => 'fa-circle-user', 'bottom' => true],
        ['key' => 'settings', 'label' => 'Settings', 'url' => 'school/settings.php', 'icon' => 'fa-gear', 'bottom' => true],
    ];
}

function teacherMenu(?int $pendingGrading = null): array
{
    if ($pendingGrading === null && function_exists('currentUser')) {
        $user = currentUser();
        if (($user['role'] ?? '') === 'teacher') {
            $pendingGrading = DashboardRepository::teacherPendingGradingCount((int) $user['id']);
        }
    }

    $items = [
        ['section' => 'Overview'],
        ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'teacher/dashboard.php', 'icon' => 'fa-gauge'],
        ['section' => 'Teaching'],
        ['key' => 'classes', 'label' => 'Classes', 'url' => 'teacher/classes.php', 'icon' => 'fa-book-open'],
        ['section' => 'Grading'],
        ['key' => 'grading', 'label' => 'Grade Submissions', 'url' => 'teacher/grade-submissions.php', 'icon' => 'fa-check-double'],
        ['key' => 'profile', 'label' => 'My profile', 'url' => 'profile.php', 'icon' => 'fa-circle-user', 'bottom' => true],
    ];
    if ($pendingGrading !== null && $pendingGrading > 0) {
        foreach ($items as &$item) {
            if (($item['key'] ?? '') === 'grading') {
                $item['badge'] = $pendingGrading;
            }
        }
        unset($item);
    }
    return $items;
}

function studentMenu(): array
{
    return [
        ['section' => 'Overview'],
        ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'student/dashboard.php', 'icon' => 'fa-gauge'],
        ['section' => 'Courses'],
        ['key' => 'courses', 'label' => 'My courses', 'url' => 'student/classes.php', 'icon' => 'fa-book-open'],
        ['key' => 'profile', 'label' => 'My profile', 'url' => 'profile.php', 'icon' => 'fa-circle-user', 'bottom' => true],
    ];
}
