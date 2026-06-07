<?php
require_once __DIR__ . '/includes/bootstrap.php';

$file = trim($_GET['file'] ?? '');

if ($file === '' || str_contains($file, '..') || !preg_match('#^(platform|\d+)/profiles/profile-\d+-[a-zA-Z0-9._-]+$#', $file)) {
    http_response_code(400);
    exit('Invalid file.');
}

$fullPath = realpath(UPLOAD_DIR . '/' . ltrim($file, '/'));
$uploadRoot = realpath(UPLOAD_DIR);

if (!$fullPath || !$uploadRoot || !str_starts_with($fullPath, $uploadRoot) || !is_file($fullPath)) {
    http_response_code(404);
    exit('File not found.');
}

$stmt = db()->prepare('SELECT id, school_id, role FROM users WHERE profile_image = ? LIMIT 1');
$stmt->execute([ltrim($file, '/')]);
$owner = $stmt->fetch();

if (!$owner) {
    http_response_code(404);
    exit('File not found.');
}

$viewer = currentUser();
$allowed = false;

if ($viewer) {
    if ((int) $viewer['id'] === (int) $owner['id']) {
        $allowed = true;
    } elseif (($viewer['role'] ?? '') === 'super_admin') {
        $allowed = true;
    } elseif ((int) ($viewer['school_id'] ?? 0) > 0
        && (int) ($viewer['school_id'] ?? 0) === (int) ($owner['school_id'] ?? 0)) {
        $allowed = true;
    }
}

if (!$allowed) {
    http_response_code(403);
    exit('Forbidden.');
}

$mime = mime_content_type($fullPath) ?: 'image/jpeg';

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;
