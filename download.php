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
$row = null;
$inline = false;
$downloadName = basename($fullPath);

if ($type === 'material') {
    $row = MaterialRepository::findByFilePath(ltrim($file, '/'));
    if ($row) {
        if ($user['role'] === 'school_admin' && (int) $row['school_id'] === schoolId()) {
            $allowed = true;
        } elseif ($user['role'] === 'teacher' && teacherHasClass((int) $row['class_id'])) {
            $allowed = true;
        } elseif ($user['role'] === 'student' && studentHasClass((int) $row['class_id'])) {
            $allowed = true;
        }
        if ($allowed && ($row['file_access_mode'] ?? 'downloadable') === 'view_only') {
            $inline = true;
        }
        if (!empty($row['original_name'])) {
            $downloadName = $row['original_name'];
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
} elseif ($type === 'quiz_attachment') {
    $relative = ltrim($file, '/');
    $stmt = db()->prepare('SELECT qq.id, qq.quiz_id, qq.teacher_attachment_path, q.class_id, c.school_id
        FROM quiz_questions qq
        INNER JOIN quizzes q ON q.id = qq.quiz_id
        INNER JOIN classes c ON c.id = q.class_id
        WHERE qq.teacher_attachment_path = ?');
    $stmt->execute([$relative]);
    $row = $stmt->fetch();
    if (!$row) {
        $stmt = db()->prepare('SELECT qaa.id, qaa.attempt_id, qaa.student_attachment_path, q.class_id, c.school_id, qa.student_id
            FROM quiz_attempt_answers qaa
            INNER JOIN quiz_attempts qa ON qa.id = qaa.attempt_id
            INNER JOIN quizzes q ON q.id = qa.quiz_id
            INNER JOIN classes c ON c.id = q.class_id
            WHERE qaa.student_attachment_path = ?');
        $stmt->execute([$relative]);
        $row = $stmt->fetch();
    }
    if ($row) {
        if ($user['role'] === 'school_admin' && (int) $row['school_id'] === schoolId()) {
            $allowed = true;
        } elseif ($user['role'] === 'teacher' && teacherHasClass((int) $row['class_id'])) {
            $allowed = true;
        } elseif ($user['role'] === 'student') {
            if (isset($row['student_id']) && (int) $row['student_id'] === (int) $user['id']) {
                $allowed = true;
            } elseif (studentHasClass((int) $row['class_id'])) {
                $allowed = true;
            }
        }
    }
} elseif ($type === 'quiz_cover') {
    $relative = ltrim($file, '/');
    $stmt = db()->prepare('SELECT q.*, c.school_id FROM quizzes q
        INNER JOIN classes c ON c.id = q.class_id
        WHERE q.cover_image = ?');
    $stmt->execute([$relative]);
    $row = $stmt->fetch();
    if ($row) {
        if ($user['role'] === 'school_admin' && (int) $row['school_id'] === schoolId()) {
            $allowed = true;
        } elseif ($user['role'] === 'teacher' && teacherHasClass((int) $row['class_id'])) {
            $allowed = true;
        } elseif ($user['role'] === 'student' && studentHasClass((int) $row['class_id'])) {
            $allowed = true;
        }
        $inline = true;
    }
} elseif ($type === 'resource_asset') {
    $relative = ltrim($file, '/');
    if (!preg_match('#^\d+/resources/#', $relative)) {
        $allowed = false;
    } elseif (($user['role'] ?? '') === 'school_admin' && str_starts_with($relative, schoolId() . '/resources/')) {
        $allowed = true;
        $inline = true;
    } elseif (($user['role'] ?? '') === 'teacher' && str_starts_with($relative, schoolId() . '/resources/')) {
        $allowed = true;
        $inline = true;
    } elseif (($user['role'] ?? '') === 'student') {
        $stmt = db()->prepare('SELECT COUNT(*) FROM content_resources cr
            INNER JOIN materials m ON m.content_resource_id = cr.id
            INNER JOIN classes c ON c.id = m.class_id
            INNER JOIN class_students cs ON cs.class_id = c.id AND cs.student_id = ?
            WHERE cr.thumbnail_path = ? OR cr.content LIKE ?');
        $stmt->execute([(int) $user['id'], $relative, '%' . $relative . '%']);
        if ((int) $stmt->fetchColumn() > 0) {
            $allowed = true;
            $inline = true;
        }
        $stmt = db()->prepare('SELECT COUNT(*) FROM library_resources lr
            WHERE lr.school_id = ? AND lr.status = ? AND (lr.thumbnail_path = ? OR lr.content LIKE ?)');
        $stmt->execute([schoolId(), 'published', $relative, '%' . $relative . '%']);
        if ((int) $stmt->fetchColumn() > 0) {
            $allowed = true;
            $inline = true;
        }
    }
} elseif ($type === 'library_resource') {
    $row = LibraryResourceRepository::findByFilePath(ltrim($file, '/'));
    if ($row) {
        if (canAccessLibraryResource($row)) {
            $allowed = true;
        } elseif (LibraryResourceRepository::userHasClassAccessViaResource((int) $row['id'], $user)) {
            $allowed = true;
        } elseif ($user['role'] === 'school_admin' && (int) $row['school_id'] === schoolId()) {
            $allowed = true;
        }
        if ($allowed && ($row['file_access_mode'] ?? 'downloadable') === 'view_only') {
            $inline = true;
        }
        if (!empty($row['original_name'])) {
            $downloadName = $row['original_name'];
        }
    }
}

if (!$allowed) {
    http_response_code(403);
    die('Access denied.');
}

$mime = mime_content_type($fullPath) ?: 'application/octet-stream';
$extension = pathinfo($fullPath, PATHINFO_EXTENSION);

if (!empty($row['title'])) {
    $base = preg_replace('/[^\pL\pN\-_]+/u', '-', trim($row['title'])) ?: 'download';
    $base = trim($base, '-');
    if ($extension !== '' && !str_ends_with(strtolower($downloadName), '.' . strtolower($extension))) {
        $downloadName = $base . '.' . $extension;
    }
}

$safeFilename = str_replace(['"', "\r", "\n"], '', $downloadName);

header('Content-Type: ' . $mime);
if ($inline) {
    header('Content-Disposition: inline; filename="' . $safeFilename . '"');
} else {
    header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
}
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: private, no-cache');
readfile($fullPath);
exit;
