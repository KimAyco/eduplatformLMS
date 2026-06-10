<?php

const ANNOUNCEMENT_PRIORITIES = ['normal', 'important', 'urgent'];

const ANNOUNCEMENT_TARGET_TYPES = [
    'all_teachers',
    'all_students',
    'all_users',
    'school_admins',
    'user',
    'class_group_students',
    'class_group_teachers',
    'class_group_all',
    'subject_students',
    'subject_teachers',
    'subject_all',
    'program',
    'program_level',
    'class_students',
    'class_teachers',
    'class_all',
];

function announcementPriorityLabel(string $priority): string
{
    return match ($priority) {
        'important' => 'Important',
        'urgent' => 'Urgent',
        default => 'Normal',
    };
}

function announcementTargetLabel(string $type, ?int $targetId = null, ?array $lookup = null): string
{
    $base = match ($type) {
        'all_teachers' => 'All teachers',
        'all_students' => 'All students',
        'all_users' => 'Everyone in school',
        'school_admins' => 'School administrators',
        'user' => 'Specific user',
        'class_group_students' => 'Students in class group',
        'class_group_teachers' => 'Teachers in class group',
        'class_group_all' => 'Class group (students & teachers)',
        'subject_students' => 'Students in subject',
        'subject_teachers' => 'Teachers in subject',
        'subject_all' => 'Subject (students & teachers)',
        'program' => 'Program enrollees',
        'program_level' => 'Program level',
        'class_students' => 'Students in class',
        'class_teachers' => 'Teachers in class',
        'class_all' => 'Class (students & teachers)',
        default => ucwords(str_replace('_', ' ', $type)),
    };

    if ($targetId && $lookup) {
        $name = $lookup['name'] ?? $lookup['title'] ?? null;
        if ($name) {
            return $base . ': ' . $name;
        }
    }

    return $base;
}

function announcementTargetOptions(): array
{
    return [
        ['type' => 'all_teachers', 'label' => 'All teachers', 'needs_id' => false],
        ['type' => 'all_students', 'label' => 'All students', 'needs_id' => false],
        ['type' => 'all_users', 'label' => 'Everyone (teachers, students, admins)', 'needs_id' => false],
        ['type' => 'school_admins', 'label' => 'School administrators only', 'needs_id' => false],
        ['type' => 'user', 'label' => 'Specific user', 'needs_id' => 'user'],
        ['type' => 'class_group_students', 'label' => 'Students in a class group', 'needs_id' => 'class_group'],
        ['type' => 'class_group_teachers', 'label' => 'Teachers in a class group', 'needs_id' => 'class_group'],
        ['type' => 'class_group_all', 'label' => 'Class group (students & teachers)', 'needs_id' => 'class_group'],
        ['type' => 'subject_students', 'label' => 'Students in a subject', 'needs_id' => 'subject'],
        ['type' => 'subject_teachers', 'label' => 'Teachers in a subject', 'needs_id' => 'subject'],
        ['type' => 'subject_all', 'label' => 'Subject (students & teachers)', 'needs_id' => 'subject'],
        ['type' => 'program', 'label' => 'Students in a program', 'needs_id' => 'program'],
        ['type' => 'program_level', 'label' => 'Students in a program level', 'needs_id' => 'program_level'],
        ['type' => 'class_students', 'label' => 'Students in a class', 'needs_id' => 'class'],
        ['type' => 'class_teachers', 'label' => 'Teachers in a class', 'needs_id' => 'class'],
        ['type' => 'class_all', 'label' => 'Class (students & teachers)', 'needs_id' => 'class'],
    ];
}

/**
 * @param array<int, array<string, mixed>> $targets
 * @return list<array{target_type: string, target_id: int|null}>
 */
function parseAnnouncementTargets(array $targets): array
{
    $parsed = [];
    foreach ($targets as $row) {
        if (!is_array($row)) {
            continue;
        }
        $type = (string) ($row['target_type'] ?? $row['type'] ?? '');
        if (!in_array($type, ANNOUNCEMENT_TARGET_TYPES, true)) {
            continue;
        }
        $targetId = isset($row['target_id']) && $row['target_id'] !== '' ? (int) $row['target_id'] : null;
        $option = null;
        foreach (announcementTargetOptions() as $opt) {
            if ($opt['type'] === $type) {
                $option = $opt;
                break;
            }
        }
        if ($option && $option['needs_id'] && ($targetId === null || $targetId <= 0)) {
            continue;
        }
        if (!$option['needs_id']) {
            $targetId = null;
        }
        $parsed[] = ['target_type' => $type, 'target_id' => $targetId];
    }
    return $parsed;
}

function notificationsJsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * @param array<string, mixed> $row
 */
function formatNotificationForClient(array $row): array
{
    $body = (string) ($row['body'] ?? '');
    $preview = mb_strlen($body) > 120 ? mb_substr($body, 0, 117) . '…' : $body;

    return [
        'id' => (int) $row['notification_id'],
        'announcement_id' => (int) $row['announcement_id'],
        'title' => (string) ($row['title'] ?? ''),
        'body' => $body,
        'preview' => $preview,
        'priority' => (string) ($row['priority'] ?? 'normal'),
        'priority_label' => announcementPriorityLabel((string) ($row['priority'] ?? 'normal')),
        'link_url' => $row['link_url'] ?? null,
        'link_label' => $row['link_label'] ?? null,
        'is_read' => !empty($row['read_at']),
        'read_at' => $row['read_at'] ?? null,
        'published_at' => $row['published_at'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'url' => url('notifications.php?id=' . (int) $row['notification_id']),
    ];
}
