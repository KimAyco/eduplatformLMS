<?php
require_once __DIR__ . '/includes/bootstrap.php';

$file = trim($_GET['file'] ?? '');

if ($file === '' || str_contains($file, '..') || !preg_match('#^\d+/covers/[a-zA-Z0-9._-]+$#', $file)) {
    http_response_code(400);
    exit('Invalid file.');
}

$fullPath = realpath(UPLOAD_DIR . '/' . ltrim($file, '/'));
$uploadRoot = realpath(UPLOAD_DIR);

if (!$fullPath || !$uploadRoot || !str_starts_with($fullPath, $uploadRoot) || !is_file($fullPath)) {
    http_response_code(404);
    exit('File not found.');
}

$stmt = db()->prepare('SELECT id FROM schools WHERE cover_image = ? AND status = ? LIMIT 1');
$stmt->execute([ltrim($file, '/'), 'active']);
if (!$stmt->fetch()) {
    http_response_code(404);
    exit('File not found.');
}

$mime = mime_content_type($fullPath) ?: 'image/jpeg';

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;
