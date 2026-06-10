<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('school_admin');
requireSchoolActive();

$sid = schoolId();
$id = (int) ($_GET['id'] ?? 0);
$resource = LibraryResourceRepository::get($id, $sid);

if (!$resource || $resource['type'] !== 'doc') {
    flash('error', 'Document not found.');
    redirect('school/library.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $title = trim($_POST['title'] ?? '');
    $content = sanitizeHtml($_POST['content'] ?? '');

    if ($title === '') {
        $errors[] = 'Title is required.';
    } else {
        LibraryResourceRepository::update($id, $sid, [
            'type' => 'doc',
            'title' => $title,
            'content' => $content,
            'body' => trim($_POST['body'] ?? '') ?: null,
            'file_path' => null,
            'external_link' => null,
            'file_access_mode' => 'downloadable',
        ]);
        flash('success', 'Document saved.');
        redirect('school/library.php?tab=published');
    }
}

$pageTitle = 'Edit document — ' . $resource['title'];
$pageHeading = 'Library document editor';
$activeMenu = 'library';
$menuItems = schoolAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'school/dashboard.php'],
    ['label' => 'Library', 'url' => 'school/library.php'],
    ['label' => 'Document', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">

<div class="material-editor-wrap">
    <div class="material-editor-head">
        <div>
            <h1>Library document editor</h1>
            <p class="text-muted">Write and format content for the Virtual Library.</p>
        </div>
        <a href="<?= url('school/library.php?tab=published') ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to library</a>
    </div>

    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post" class="material-editor-form">
        <?= csrfField() ?>
        <div class="form-group"><label>Title</label><input name="title" class="form-control" value="<?= e($resource['title']) ?>" required></div>
        <div class="form-group"><label>Summary</label><textarea name="body" class="form-control" rows="2"><?= e($resource['body'] ?? '') ?></textarea></div>
        <div class="form-group">
            <label>Content</label>
            <div id="quillEditor"><?= sanitizeHtml($resource['content'] ?? '') ?></div>
            <input type="hidden" name="content" id="quillContent">
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">Save document</button>
            <a href="<?= url('school/library.php?tab=published') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<script>
(function () {
    var quill = new Quill('#quillEditor', { theme: 'snow' });
    document.querySelector('.material-editor-form').addEventListener('submit', function () {
        document.getElementById('quillContent').value = quill.root.innerHTML;
    });
})();
</script>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
