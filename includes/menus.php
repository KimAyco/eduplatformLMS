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
        ['key' => 'storage', 'label' => 'Storage', 'url' => 'superadmin/storage.php', 'icon' => 'fa-hard-drive'],
        ['key' => 'ai_monitor', 'label' => 'AI Monitor', 'url' => 'superadmin/ai-monitor.php', 'icon' => 'fa-robot'],
        ['key' => 'ai_analytics', 'label' => 'AI Analytics', 'url' => 'superadmin/ai-analytics.php', 'icon' => 'fa-chart-line'],
        ['key' => 'settings', 'label' => 'Settings', 'url' => 'superadmin/settings.php', 'icon' => 'fa-gear'],
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
        ['key' => 'programs', 'label' => 'Programs', 'url' => 'school/programs.php', 'icon' => 'fa-sitemap'],
        ['key' => 'teachers', 'label' => 'Teachers', 'url' => 'school/teachers.php', 'icon' => 'fa-chalkboard-user'],
        ['key' => 'students', 'label' => 'Students', 'url' => 'school/students.php', 'icon' => 'fa-user-graduate'],
        ['key' => 'class_groups', 'label' => 'Class Groups', 'url' => 'school/class-groups.php', 'icon' => 'fa-layer-group'],
        ['key' => 'resources', 'label' => 'Resources', 'url' => 'school/resources.php', 'icon' => 'fa-folder-open'],
        ['key' => 'library', 'label' => 'Library', 'url' => 'school/library.php', 'icon' => 'fa-book-bookmark'],
        ['key' => 'announcements', 'label' => 'Announcements', 'url' => 'school/announcements.php', 'icon' => 'fa-bullhorn'],
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
        ['key' => 'resources', 'label' => 'Resources', 'url' => 'teacher/resources.php', 'icon' => 'fa-folder-open'],
        ['key' => 'library', 'label' => 'Virtual Library', 'url' => 'teacher/library.php', 'icon' => 'fa-book-bookmark'],
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
    $items = [
        ['section' => 'Overview'],
        ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'student/dashboard.php', 'icon' => 'fa-gauge'],
        ['section' => 'Courses'],
        ['key' => 'courses', 'label' => 'My courses', 'url' => 'student/classes.php', 'icon' => 'fa-book-open'],
        ['key' => 'library', 'label' => 'Virtual Library', 'url' => 'student/library.php', 'icon' => 'fa-book-bookmark'],
        ['key' => 'practice', 'label' => 'Practice & stats', 'url' => 'student/practice-stats.php', 'icon' => 'fa-dumbbell'],
        ['key' => 'profile', 'label' => 'My profile', 'url' => 'profile.php', 'icon' => 'fa-circle-user', 'bottom' => true],
    ];
    if (!schoolPracticeQuizzesEnabled()) {
        $items = array_values(array_filter($items, static fn ($item) => ($item['key'] ?? '') !== 'practice'));
    }
    return $items;
}
