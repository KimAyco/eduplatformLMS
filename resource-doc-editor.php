<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireContentResourceAccess();

$id = (int) ($_GET['id'] ?? 0);
$resource = ContentResourceRepository::get($id, schoolId());

if (!$resource || $resource['resource_type'] !== 'doc' || !canAccessContentResource($resource)) {
    flash('error', 'Document not found.');
    redirect(contentResourceListUrl(currentUser()['role'] ?? 'teacher'));
}

$user = currentUser();
$role = $user['role'] ?? 'teacher';
$listUrl = contentResourceListUrl($role);

$pageTitle = 'Edit — ' . $resource['title'];
$editorShell = true;
$pageScripts = ['assets/js/resource-doc-editor.js'];

require __DIR__ . '/includes/layout/dashboard_header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>

<div class="resource-editor-shell doc-editor-shell" id="docEditorApp"
    data-resource-id="<?= (int) $resource['id'] ?>"
    data-api-url="<?= e(url('api/content-resources.php')) ?>"
    data-csrf="<?= e(csrfToken()) ?>"
    data-list-url="<?= e($listUrl) ?>">

    <header class="deck-editor-topbar">
        <div class="deck-editor-topbar-left">
            <a href="<?= e($listUrl) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Resources</a>
            <input type="text" class="deck-title-input" id="docTitleInput" value="<?= e($resource['title']) ?>" maxlength="255" aria-label="Document title">
            <span class="deck-save-status" id="docSaveStatus">Saved</span>
        </div>
    </header>

    <div class="doc-editor-body">
        <div class="form-group">
            <label>Short description (optional)</label>
            <input type="text" id="docDescriptionInput" class="form-control" value="<?= e($resource['description'] ?? '') ?>" maxlength="500">
        </div>
        <div id="quillEditor" class="material-quill-editor doc-resource-editor"><?= sanitizeHtml($resource['content'] ?? '') ?></div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout/dashboard_footer.php'; ?>
