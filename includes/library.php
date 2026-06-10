<?php

function normalizeResourceKind(string $kind): string
{
    $kind = strtolower(trim($kind));
    return in_array($kind, LibraryResourceRepository::RESOURCE_KINDS, true) ? $kind : 'other';
}

function resourceKindLabel(string $kind): string
{
    return match (normalizeResourceKind($kind)) {
        'lesson' => 'Lesson',
        'book' => 'Book',
        'module' => 'Module',
        'worksheet' => 'Worksheet',
        'reference' => 'Reference',
        default => 'Other',
    };
}

function libraryStatusLabel(string $status): string
{
    return match ($status) {
        'pending' => 'Pending approval',
        'published' => 'Published',
        'rejected' => 'Rejected',
        default => ucfirst($status),
    };
}

function canBrowseLibrary(): bool
{
    $user = currentUser();
    if (!$user) {
        return false;
    }
    if (($user['role'] ?? '') === 'super_admin') {
        return false;
    }
    return in_array($user['role'] ?? '', ['school_admin', 'teacher', 'student'], true);
}

function requireLibraryAccess(): void
{
    requireLogin();
    requireSchoolActive();
    if (!canBrowseLibrary()) {
        http_response_code(403);
        die('Access denied.');
    }
}

/**
 * @param array<string, mixed> $resource
 */
function canAccessLibraryResource(array $resource): bool
{
    $user = currentUser();
    if (!$user || (int) ($resource['school_id'] ?? 0) !== (int) schoolId()) {
        return false;
    }

    $role = $user['role'] ?? '';
    $status = $resource['status'] ?? '';

    if ($role === 'school_admin') {
        return true;
    }

    if ($status !== 'published') {
        if ($role === 'teacher' && (int) ($resource['created_by'] ?? 0) === (int) $user['id']) {
            return in_array($status, ['pending', 'rejected'], true);
        }
        return false;
    }

    if ($role === 'teacher') {
        return true;
    }

    if ($role === 'student') {
        return ($resource['audience'] ?? 'all') === 'all';
    }

    return false;
}

/**
 * @param array<string, mixed> $material
 */
function canSubmitMaterialToLibrary(array $material, int $teacherId): bool
{
    if ((int) ($material['teacher_id'] ?? 0) !== $teacherId) {
        return false;
    }

    if (!empty($material['library_resource_id'])) {
        $existing = LibraryResourceRepository::find((int) $material['library_resource_id']);
        return $existing && ($existing['status'] ?? '') === 'rejected';
    }

    $existing = LibraryResourceRepository::getLibraryStatusForMaterial((int) $material['id']);
    if ($existing && in_array($existing['status'], ['pending', 'published'], true)) {
        return false;
    }

    return true;
}

function libraryViewUrl(int $libraryId): string
{
    return url('library-view.php?id=' . $libraryId);
}

/**
 * @param array<string, mixed> $resource
 */
function libraryDownloadUrl(array $resource, bool $inline = false): ?string
{
    if (empty($resource['file_path'])) {
        return null;
    }
    $base = downloadUrl($resource['file_path'], 'library_resource') . '&library_id=' . (int) $resource['id'];
    if ($inline || (($resource['file_access_mode'] ?? 'downloadable') === 'view_only')) {
        $base .= '&disposition=inline';
    }
    return $base;
}

function libraryResourceIcon(string $kind, string $type): string
{
    if ($kind === 'book') {
        return 'fa-book';
    }
    if ($kind === 'lesson') {
        return 'fa-chalkboard';
    }
    if ($kind === 'module') {
        return 'fa-cubes';
    }
    return match (normalizeMaterialType($type)) {
        'link' => 'fa-link',
        'doc' => 'fa-file-lines',
        'deck' => 'fa-display',
        default => 'fa-file',
    };
}

function libraryAudienceLabel(string $audience): string
{
    return $audience === 'teachers' ? 'Teachers only' : 'Teachers & students';
}
