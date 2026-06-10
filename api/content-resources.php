<?php

require_once __DIR__ . '/../includes/bootstrap.php';

requireLogin();
requireSchoolActive();

if (!canManageContentResources()) {
    contentResourceJsonResponse(['ok' => false, 'error' => 'Access denied.'], 403);
}

$user = currentUser();
$userId = (int) $user['id'];
$schoolId = schoolId();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    match ($action) {
        'get' => handleGet($schoolId),
        'save' => handleSave($schoolId),
        'upload_asset' => handleUploadAsset($schoolId),
        'upload_thumbnail' => handleUploadThumbnail($schoolId),
        'templates' => handleTemplates(),
        default => contentResourceJsonResponse(['ok' => false, 'error' => 'Unknown action.'], 400),
    };
} catch (InvalidArgumentException $e) {
    contentResourceJsonResponse(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (RuntimeException $e) {
    contentResourceJsonResponse(['ok' => false, 'error' => $e->getMessage()], 403);
}

function handleGet(int $schoolId): never
{
    $id = (int) ($_GET['id'] ?? 0);
    $resource = ContentResourceRepository::get($id, $schoolId);
    if (!$resource || !canAccessContentResource($resource)) {
        contentResourceJsonResponse(['ok' => false, 'error' => 'Resource not found.'], 404);
    }

    contentResourceJsonResponse([
        'ok' => true,
        'resource' => formatResourceForApi($resource),
    ]);
}

function handleSave(int $schoolId): never
{
    verifyCsrfHeader();

    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($input)) {
        contentResourceJsonResponse(['ok' => false, 'error' => 'Invalid request body.'], 400);
    }

    $id = (int) ($input['id'] ?? 0);
    $resource = ContentResourceRepository::get($id, $schoolId);
    if (!$resource || !canAccessContentResource($resource)) {
        contentResourceJsonResponse(['ok' => false, 'error' => 'Resource not found.'], 404);
    }

    $data = [];
    if (isset($input['title'])) {
        $title = trim((string) $input['title']);
        if ($title === '') {
            contentResourceJsonResponse(['ok' => false, 'error' => 'Title is required.'], 422);
        }
        $data['title'] = $title;
    }
    if (array_key_exists('description', $input)) {
        $data['description'] = trim((string) $input['description']) ?: null;
    }
    if (array_key_exists('content', $input)) {
        $data['content'] = is_array($input['content'])
            ? json_encode($input['content'], JSON_UNESCAPED_UNICODE)
            : (string) $input['content'];
    }
    if (array_key_exists('thumbnail_path', $input)) {
        $data['thumbnail_path'] = trim((string) $input['thumbnail_path']) ?: null;
    }

    ContentResourceRepository::update($id, $schoolId, $data);
    $updated = ContentResourceRepository::get($id, $schoolId);

    contentResourceJsonResponse([
        'ok' => true,
        'resource' => formatResourceForApi($updated),
        'saved_at' => date('c'),
    ]);
}

function handleUploadAsset(int $schoolId): never
{
    verifyCsrfHeader();

    $id = (int) ($_POST['resource_id'] ?? 0);
    $resource = ContentResourceRepository::get($id, $schoolId);
    if (!$resource || !canAccessContentResource($resource)) {
        contentResourceJsonResponse(['ok' => false, 'error' => 'Resource not found.'], 404);
    }

    if (empty($_FILES['file']['name'])) {
        contentResourceJsonResponse(['ok' => false, 'error' => 'No file uploaded.'], 422);
    }

    $meta = uploadFileWithMeta($_FILES['file'], $schoolId . '/resources');
    if (empty($meta['path'])) {
        contentResourceJsonResponse(['ok' => false, 'error' => 'Upload failed.'], 422);
    }

    contentResourceJsonResponse([
        'ok' => true,
        'url' => downloadUrl($meta['path'], 'resource_asset'),
        'path' => $meta['path'],
    ]);
}

function handleUploadThumbnail(int $schoolId): never
{
    verifyCsrfHeader();

    $id = (int) ($_POST['resource_id'] ?? 0);
    $resource = ContentResourceRepository::get($id, $schoolId);
    if (!$resource || !canAccessContentResource($resource)) {
        contentResourceJsonResponse(['ok' => false, 'error' => 'Resource not found.'], 404);
    }

    if (empty($_FILES['file']['name'])) {
        contentResourceJsonResponse(['ok' => false, 'error' => 'No file uploaded.'], 422);
    }

    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
        contentResourceJsonResponse(['ok' => false, 'error' => 'Thumbnail must be an image.'], 422);
    }

    $meta = uploadFileWithMeta($file, $schoolId . '/resources/thumbs');
    if (empty($meta['path'])) {
        contentResourceJsonResponse(['ok' => false, 'error' => 'Upload failed.'], 422);
    }

    if (!empty($resource['thumbnail_path']) && $resource['thumbnail_path'] !== $meta['path']) {
        deleteUpload($resource['thumbnail_path']);
    }

    ContentResourceRepository::update($id, $schoolId, ['thumbnail_path' => $meta['path']]);

    contentResourceJsonResponse([
        'ok' => true,
        'url' => downloadUrl($meta['path'], 'resource_asset'),
        'path' => $meta['path'],
    ]);
}

function handleTemplates(): never
{
    contentResourceJsonResponse([
        'ok' => true,
        'templates' => deckTemplates(),
    ]);
}

/**
 * @param array<string, mixed> $resource
 * @return array<string, mixed>
 */
function formatResourceForApi(array $resource): array
{
    $content = $resource['content'] ?? '';
    if ($resource['resource_type'] === 'deck' && is_string($content)) {
        $decoded = json_decode($content, true);
        $content = is_array($decoded) ? $decoded : json_decode(defaultDeckContent(), true);
    }

    return [
        'id' => (int) $resource['id'],
        'title' => $resource['title'],
        'description' => $resource['description'],
        'resource_type' => $resource['resource_type'],
        'content' => $content,
        'thumbnail_url' => !empty($resource['thumbnail_path']) ? downloadUrl($resource['thumbnail_path'], 'resource_asset') : null,
        'status' => $resource['status'],
        'library_resource_id' => $resource['library_resource_id'] ? (int) $resource['library_resource_id'] : null,
        'library_status' => $resource['library_status'] ?? null,
        'updated_at' => $resource['updated_at'],
    ];
}
