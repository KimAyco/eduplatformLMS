<?php

/** @return array<string, string> */
function uploadCategoryLabels(): array
{
    return [
        'materials' => 'Course materials',
        'library' => 'Virtual library',
        'resources' => 'Resource assets',
        'submissions' => 'Assignment submissions',
        'quiz_attachments' => 'Quiz attachments',
        'quiz_submissions' => 'Quiz submissions',
        'quiz_covers' => 'Quiz covers',
        'class-covers' => 'Class covers',
        'class_covers' => 'Class covers',
        'covers' => 'School covers',
        'logos' => 'School logos',
        'profiles' => 'Profile photos',
    ];
}

function formatStorageSize(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    if ($bytes < 1024 * 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 2) . ' MB';
    }
    return number_format($bytes / (1024 * 1024 * 1024), 2) . ' GB';
}

/**
 * @return array{bytes: int, files: int}
 */
function directoryStorageStats(string $path): array
{
    $bytes = 0;
    $files = 0;

    if (!is_dir($path)) {
        return ['bytes' => 0, 'files' => 0];
    }

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $bytes += (int) $item->getSize();
                $files++;
            }
        }
    } catch (UnexpectedValueException) {
        return ['bytes' => 0, 'files' => 0];
    }

    return ['bytes' => $bytes, 'files' => $files];
}

function schoolUploadDirectory(int $schoolId): string
{
    return UPLOAD_DIR . '/' . $schoolId;
}

/**
 * @return array{
 *     total_bytes: int,
 *     total_files: int,
 *     breakdown: list<array{key: string, label: string, bytes: int, files: int}>
 * }
 */
function schoolStorageUsage(int $schoolId): array
{
    $labels = uploadCategoryLabels();
    $root = schoolUploadDirectory($schoolId);
    $breakdown = [];
    $totalBytes = 0;
    $totalFiles = 0;

    if (!is_dir($root)) {
        return [
            'total_bytes' => 0,
            'total_files' => 0,
            'breakdown' => [],
        ];
    }

    foreach (scandir($root) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $root . DIRECTORY_SEPARATOR . $entry;
        if (!is_dir($path)) {
            $size = @filesize($path);
            if ($size !== false) {
                $totalBytes += (int) $size;
                $totalFiles++;
                $breakdown[] = [
                    'key' => 'other',
                    'label' => 'Other files',
                    'bytes' => (int) $size,
                    'files' => 1,
                ];
            }
            continue;
        }

        $stats = directoryStorageStats($path);
        if ($stats['bytes'] === 0 && $stats['files'] === 0) {
            continue;
        }

        $breakdown[] = [
            'key' => $entry,
            'label' => $labels[$entry] ?? ucwords(str_replace(['_', '-'], ' ', $entry)),
            'bytes' => $stats['bytes'],
            'files' => $stats['files'],
        ];
        $totalBytes += $stats['bytes'];
        $totalFiles += $stats['files'];
    }

    usort($breakdown, static fn ($a, $b) => $b['bytes'] <=> $a['bytes']);

    return [
        'total_bytes' => $totalBytes,
        'total_files' => $totalFiles,
        'breakdown' => $breakdown,
    ];
}

/**
 * @return array{
 *     total_bytes: int,
 *     total_files: int,
 *     platform_bytes: int,
 *     schools: list<array{
 *         id: int,
 *         name: string,
 *         status: string,
 *         slug: string,
 *         total_bytes: int,
 *         total_files: int,
 *         breakdown: list<array{key: string, label: string, bytes: int, files: int}>
 *     }>
 * }
 */
function platformStorageReport(): array
{
    return remember('platform_storage_report', static function () {
        $schoolRows = db()->query('SELECT id, name, status, slug, logo_image FROM schools ORDER BY name ASC')->fetchAll();
        $schools = [];
        $totalBytes = 0;
        $totalFiles = 0;

        foreach ($schoolRows as $row) {
            $usage = schoolStorageUsage((int) $row['id']);
            $schools[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'status' => $row['status'],
                'slug' => $row['slug'],
                'logo_image' => $row['logo_image'] ?? null,
                'total_bytes' => $usage['total_bytes'],
                'total_files' => $usage['total_files'],
                'breakdown' => $usage['breakdown'],
            ];
            $totalBytes += $usage['total_bytes'];
            $totalFiles += $usage['total_files'];
        }

        usort($schools, static fn ($a, $b) => $b['total_bytes'] <=> $a['total_bytes']);

        $platformStats = directoryStorageStats(UPLOAD_DIR . '/platform');
        $totalBytes += $platformStats['bytes'];
        $totalFiles += $platformStats['files'];

        $orphanBytes = 0;
        $orphanFiles = 0;
        if (is_dir(UPLOAD_DIR)) {
            foreach (scandir(UPLOAD_DIR) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..' || $entry === 'platform' || ctype_digit($entry)) {
                    continue;
                }
                $stats = directoryStorageStats(UPLOAD_DIR . DIRECTORY_SEPARATOR . $entry);
                $orphanBytes += $stats['bytes'];
                $orphanFiles += $stats['files'];
            }
        }
        $totalBytes += $orphanBytes;
        $totalFiles += $orphanFiles;

        return [
            'total_bytes' => $totalBytes,
            'total_files' => $totalFiles,
            'platform_bytes' => $platformStats['bytes'],
            'platform_files' => $platformStats['files'],
            'orphan_bytes' => $orphanBytes,
            'orphan_files' => $orphanFiles,
            'schools' => $schools,
        ];
    });
}

function clearPlatformStorageCache(): void
{
    forgetCache('platform_storage_report');
}

function storageUsagePercent(int $bytes, int $totalBytes): float
{
    if ($totalBytes <= 0) {
        return 0.0;
    }
    return round(($bytes / $totalBytes) * 100, 1);
}
