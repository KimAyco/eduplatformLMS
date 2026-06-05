<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

$file = trim($_GET['file'] ?? '');
$type = $_GET['type'] ?? 'material';

if ($file === '' || str_contains($file, '..')) {
    http_response_code(400);
    die('Invalid file.');
}

$fullPath = realpath(UPLOAD_DIR . '/' . ltrim($file, '/'));
$uploadRoot = realpath(UPLOAD_DIR);

if (!$fullPath || !$uploadRoot || !str_starts_with($fullPath, $uploadRoot) || !is_file($fullPath)) {
    http_response_code(404);
    die('File not found.');
}

$user = currentUser();
$allowed = false;

if ($type === 'material') {
    $stmt = db()->prepare('SELECT m.*, c.school_id FROM materials m INNER JOIN classes c ON c.id = m.class_id WHERE m.file_path = ?');
    $stmt->execute([ltrim($file, '/')]);
    $row = $stmt->fetch();
    if ($row) {
        if ($user['role'] === 'school_admin' && (int) $row['school_id'] === schoolId()) {
            $allowed = true;
        } elseif ($user['role'] === 'teacher' && teacherHasClass((int) $row['class_id'])) {
            $allowed = true;
        } elseif ($user['role'] === 'student' && studentHasClass((int) $row['class_id'])) {
            $allowed = true;
        }
    }
} elseif ($type === 'submission') {
    $stmt = db()->prepare('SELECT s.*, a.class_id, c.school_id FROM assignment_submissions s
        INNER JOIN assignments a ON a.id = s.assignment_id
        INNER JOIN classes c ON c.id = a.class_id
        WHERE s.file_path = ?');
    $stmt->execute([ltrim($file, '/')]);
    $row = $stmt->fetch();
    if ($row) {
        if ($user['role'] === 'school_admin' && (int) $row['school_id'] === schoolId()) {
            $allowed = true;
        } elseif ($user['role'] === 'teacher' && teacherHasClass((int) $row['class_id'])) {
            $allowed = true;
        } elseif ($user['role'] === 'student' && (int) $row['student_id'] === (int) $user['id']) {
            $allowed = true;
        }
    }
}

if (!$allowed) {
    http_response_code(403);
    die('Access denied.');
}

$mime = mime_content_type($fullPath) ?: 'application/octet-stream';
$filename = basename($fullPath);

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;
