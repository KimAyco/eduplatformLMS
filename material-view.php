<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);
$mat = MaterialRepository::find($id);

if (!$mat) {
    http_response_code(404);
    die('Material not found.');
}

$user = currentUser();
$allowed = false;
if ($user['role'] === 'school_admin' && (int) $mat['school_id'] === schoolId()) {
    $allowed = true;
} elseif ($user['role'] === 'teacher' && teacherHasClass((int) $mat['class_id'])) {
    $allowed = true;
} elseif ($user['role'] === 'student' && studentHasClass((int) $mat['class_id'])) {
    $allowed = true;
}

if (!$allowed) {
    http_response_code(403);
    die('Access denied.');
}

$mat = MaterialRepository::normalizeRow($mat);
$type = $mat['type'];
$pageTitle = $mat['title'];
$pageHeading = $mat['title'];

if ($user['role'] === 'student') {
    $activeMenu = 'classes';
    $menuItems = studentMenu();
    $backUrl = url('student/course.php?id=' . $mat['class_id']);
} else {
    $activeMenu = 'classes';
    $menuItems = teacherMenu();
    $backUrl = url('teacher/course.php?id=' . $mat['class_id']);
}

require __DIR__ . '/includes/layout/dashboard_header.php';
?>

<div class="actions mb-1">
    <a href="<?= e($backUrl) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to course</a>
</div>

<article class="material-view-card">
    <header class="material-view-header">
        <span class="material-view-type"><i class="fa-solid fa-<?= $type === 'doc' ? 'file-lines' : ($type === 'link' ? 'link' : 'file') ?>"></i> <?= e(materialTypeLabel($type)) ?></span>
        <h1><?= e($mat['title']) ?></h1>
    </header>

    <?php if ($type === 'doc'): ?>
        <div class="material-doc-body"><?= sanitizeHtml($mat['content'] ?? $mat['body'] ?? '') ?></div>
    <?php elseif ($type === 'link'): ?>
        <?php
        $link = normalizeExternalUrl($mat['content'] ?? $mat['external_link'] ?? '');
        $embed = youtubeEmbedUrl($link);
        ?>
        <?php if ($embed): ?>
            <div class="material-video-embed"><iframe src="<?= e($embed) ?>" title="<?= e($mat['title']) ?>" allowfullscreen loading="lazy"></iframe></div>
        <?php endif; ?>
        <p><a href="<?= e($link) ?>" target="_blank" rel="noopener" class="btn btn-primary"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open link</a></p>
    <?php elseif ($type === 'file' && !empty($mat['file_path'])): ?>
        <?php
        $mime = $mat['mime_type'] ?? '';
        $viewUrl = materialDownloadUrl($mat, true);
        $dlUrl = materialDownloadUrl($mat) . '&download=1';
        ?>
        <?php if ($viewUrl && str_starts_with($mime, 'image/')): ?>
            <div class="material-file-preview"><img src="<?= e($viewUrl) ?>" alt="<?= e($mat['title']) ?>"></div>
        <?php elseif ($viewUrl && $mime === 'application/pdf'): ?>
            <div class="material-file-preview material-file-preview--pdf"><iframe src="<?= e($viewUrl) ?>" title="<?= e($mat['title']) ?>"></iframe></div>
        <?php elseif ($viewUrl && str_starts_with($mime, 'video/')): ?>
            <div class="material-file-preview"><video controls src="<?= e($viewUrl) ?>"></video></div>
        <?php endif; ?>
        <div class="material-file-actions">
            <?php if (($mat['file_access_mode'] ?? 'downloadable') === 'downloadable'): ?>
                <a href="<?= e($dlUrl) ?>" class="btn btn-primary" download><i class="fa-solid fa-download"></i> Download<?= $mat['original_name'] ? ' (' . e($mat['original_name']) . ')' : '' ?></a>
            <?php else: ?>
                <a href="<?= e($viewUrl) ?>" class="btn btn-primary" target="_blank"><i class="fa-solid fa-eye"></i> View file</a>
            <?php endif; ?>
        </div>
        <?php if (!empty($mat['body'])): ?><p class="material-view-desc"><?= nl2br(e($mat['body'])) ?></p><?php endif; ?>
    <?php else: ?>
        <p class="text-muted">No content available for this resource.</p>
    <?php endif; ?>
</article>

<?php require __DIR__ . '/includes/layout/dashboard_footer.php'; ?>
