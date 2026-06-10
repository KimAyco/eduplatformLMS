<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout/deck_player.php';
requireLibraryAccess();

$id = (int) ($_GET['id'] ?? 0);
$resource = LibraryResourceRepository::find($id);

if (!$resource) {
    http_response_code(404);
    die('Resource not found.');
}

if (!canAccessLibraryResource($resource) && !LibraryResourceRepository::userHasClassAccessViaResource($id, currentUser())) {
    http_response_code(403);
    die('Access denied.');
}

$resource = LibraryResourceRepository::normalizeRow($resource);
$type = $resource['type'];
$user = currentUser();
$pageTitle = $resource['title'];
$pageHeading = $resource['title'];

if ($user['role'] === 'student') {
    $activeMenu = 'library';
    $menuItems = studentMenu();
    $backUrl = url('student/library.php');
} elseif ($user['role'] === 'teacher') {
    $activeMenu = 'library';
    $menuItems = teacherMenu();
    $backUrl = url('teacher/library.php');
} else {
    $activeMenu = 'library';
    $menuItems = schoolAdminMenu();
    $backUrl = url('school/library.php');
}

require __DIR__ . '/includes/layout/dashboard_header.php';
?>

<div class="actions mb-1">
    <a href="<?= e($backUrl) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to library</a>
</div>

<article class="material-view-card library-view-card">
    <header class="material-view-header">
        <span class="material-view-type">
            <i class="fa-solid <?= e(libraryResourceIcon($resource['resource_kind'], $type)) ?>"></i>
            <?= e(resourceKindLabel($resource['resource_kind'])) ?> · <?= e(materialTypeLabel($type)) ?>
        </span>
        <h1><?= e($resource['title']) ?></h1>
        <?php if (!empty($resource['description'])): ?>
            <p class="library-view-desc"><?= nl2br(e($resource['description'])) ?></p>
        <?php endif; ?>
        <div class="library-view-meta">
            <?php if (!empty($resource['subject_name'])): ?>
                <span class="activity-meta-chip muted"><i class="fa-solid fa-book"></i> <?= e($resource['subject_name']) ?></span>
            <?php endif; ?>
            <span class="activity-meta-chip muted"><i class="fa-solid fa-user"></i> <?= e(trim(($resource['creator_first'] ?? '') . ' ' . ($resource['creator_last'] ?? ''))) ?></span>
            <span class="activity-meta-chip muted"><?= formatDate($resource['created_at'], 'M j, Y') ?></span>
        </div>
    </header>

    <?php if ($type === 'deck'): ?>
        <?php renderDeckPlayer((string) ($resource['content'] ?? ''), $resource['title']); ?>
    <?php elseif ($type === 'doc'): ?>
        <div class="material-doc-body"><?= sanitizeHtml($resource['content'] ?? $resource['body'] ?? '') ?></div>
    <?php elseif ($type === 'link'): ?>
        <?php
        $link = normalizeExternalUrl($resource['content'] ?? $resource['external_link'] ?? '');
        $embed = youtubeEmbedUrl($link);
        ?>
        <?php if ($embed): ?>
            <div class="material-video-embed"><iframe src="<?= e($embed) ?>" title="<?= e($resource['title']) ?>" allowfullscreen loading="lazy"></iframe></div>
        <?php endif; ?>
        <p><a href="<?= e($link) ?>" target="_blank" rel="noopener" class="btn btn-primary"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open link</a></p>
    <?php elseif ($type === 'file' && !empty($resource['file_path'])): ?>
        <?php
        $mime = $resource['mime_type'] ?? '';
        $viewUrl = libraryDownloadUrl($resource, true);
        $dlUrl = libraryDownloadUrl($resource) . '&download=1';
        ?>
        <?php if ($viewUrl && str_starts_with($mime, 'image/')): ?>
            <div class="material-file-preview"><img src="<?= e($viewUrl) ?>" alt="<?= e($resource['title']) ?>"></div>
        <?php elseif ($viewUrl && $mime === 'application/pdf'): ?>
            <div class="material-file-preview material-file-preview--pdf"><iframe src="<?= e($viewUrl) ?>" title="<?= e($resource['title']) ?>"></iframe></div>
        <?php elseif ($viewUrl && str_starts_with($mime, 'video/')): ?>
            <div class="material-file-preview"><video controls src="<?= e($viewUrl) ?>"></video></div>
        <?php endif; ?>
        <div class="material-file-actions">
            <?php if (($resource['file_access_mode'] ?? 'downloadable') === 'downloadable'): ?>
                <a href="<?= e($dlUrl) ?>" class="btn btn-primary" download><i class="fa-solid fa-download"></i> Download<?= $resource['original_name'] ? ' (' . e($resource['original_name']) . ')' : '' ?></a>
            <?php else: ?>
                <a href="<?= e($viewUrl) ?>" class="btn btn-primary" target="_blank"><i class="fa-solid fa-eye"></i> View file</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="text-muted">No content available for this resource.</p>
    <?php endif; ?>
</article>

<?php require __DIR__ . '/includes/layout/dashboard_footer.php'; ?>
