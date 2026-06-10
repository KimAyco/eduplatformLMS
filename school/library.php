<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('school_admin');
requireSchoolActive();

$sid = schoolId();
$tab = $_GET['tab'] ?? 'published';
if (!in_array($tab, ['published', 'pending', 'rejected'], true)) {
    $tab = 'published';
}
$action = $_GET['action'] ?? '';
$editId = (int) ($_GET['id'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['form_action'] ?? '';
    $resourceId = (int) ($_POST['resource_id'] ?? 0);
    $user = currentUser();

    if ($postAction === 'approve' && $resourceId) {
        if (LibraryResourceRepository::approve($resourceId, $sid, (int) $user['id'])) {
            flash('success', 'Resource approved and published.');
        } else {
            flash('error', 'Could not approve resource.');
        }
        redirect('school/library.php?tab=pending');
    } elseif ($postAction === 'reject' && $resourceId) {
        $note = trim($_POST['rejection_note'] ?? '');
        if (LibraryResourceRepository::reject($resourceId, $sid, (int) $user['id'], $note ?: null)) {
            flash('success', 'Resource rejected.');
        } else {
            flash('error', 'Could not reject resource.');
        }
        redirect('school/library.php?tab=pending');
    } elseif ($postAction === 'unpublish' && $resourceId) {
        if (LibraryResourceRepository::unpublish($resourceId, $sid)) {
            flash('success', 'Resource unpublished.');
        } else {
            flash('error', 'Could not unpublish resource.');
        }
        redirect('school/library.php?tab=published');
    } elseif ($postAction === 'delete' && $resourceId) {
        try {
            LibraryResourceRepository::delete($resourceId, $sid);
            flash('success', 'Resource deleted.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirect('school/library.php?tab=' . urlencode($tab));
    } elseif ($postAction === 'add' || $postAction === 'edit') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $resourceKind = normalizeResourceKind($_POST['resource_kind'] ?? 'other');
        $subjectId = (int) ($_POST['subject_id'] ?? 0) ?: null;
        $matType = normalizeMaterialType($_POST['material_type'] ?? 'file');
        $link = normalizeExternalUrl(trim($_POST['external_link'] ?? ''));
        $audience = ($_POST['audience'] ?? 'all') === 'teachers' ? 'teachers' : 'all';
        $fileAccess = ($_POST['file_access_mode'] ?? 'downloadable') === 'view_only' ? 'view_only' : 'downloadable';

        if ($title === '') {
            $errors[] = 'Title is required.';
        }
        if ($matType === 'link' && $link === '') {
            $errors[] = 'URL is required for link resources.';
        }
        if ($matType === 'file' && $postAction === 'add' && empty($_FILES['file']['name'])) {
            $errors[] = 'Please choose a file to upload.';
        }

        if (empty($errors)) {
            try {
                $filePath = null;
                $originalName = null;
                $mimeType = null;
                $fileSize = 0;

                if ($postAction === 'add') {
                    if ($matType === 'file' && !empty($_FILES['file']['name'])) {
                        $meta = uploadFileWithMeta($_FILES['file'], $sid . '/library');
                        $filePath = $meta['path'];
                        $originalName = $meta['original_name'];
                        $mimeType = $meta['mime_type'];
                        $fileSize = $meta['file_size'];
                    }
                    $payload = [
                        'title' => $title,
                        'description' => $description ?: null,
                        'resource_kind' => $resourceKind,
                        'subject_id' => $subjectId,
                        'type' => $matType,
                        'content' => $matType === 'link' ? $link : null,
                        'body' => null,
                        'file_path' => $filePath,
                        'original_name' => $originalName,
                        'mime_type' => $mimeType,
                        'file_size' => $fileSize,
                        'external_link' => $matType === 'link' ? $link : null,
                        'file_access_mode' => $matType === 'file' ? $fileAccess : 'downloadable',
                        'audience' => $audience,
                    ];
                    $newId = LibraryResourceRepository::createFromAdmin($sid, (int) $user['id'], $payload);
                    if ($matType === 'doc') {
                        flash('success', 'Document created. Add content below.');
                        redirect('school/library-doc-editor.php?id=' . $newId);
                    }
                    flash('success', 'Resource added to library.');
                    redirect('school/library.php?tab=published');
                } else {
                    $existing = LibraryResourceRepository::get($resourceId, $sid);
                    if (!$existing) {
                        $errors[] = 'Resource not found.';
                    } else {
                        if ($matType === 'doc') {
                            redirect('school/library-doc-editor.php?id=' . $resourceId);
                        }
                        if ($matType === 'file' && !empty($_FILES['file']['name'])) {
                            if (!empty($existing['file_path'])) {
                                deleteUpload($existing['file_path']);
                            }
                            $meta = uploadFileWithMeta($_FILES['file'], $sid . '/library');
                            $filePath = $meta['path'];
                            $originalName = $meta['original_name'];
                            $mimeType = $meta['mime_type'];
                            $fileSize = $meta['file_size'];
                        } else {
                            $filePath = $existing['file_path'];
                            $originalName = $existing['original_name'];
                            $mimeType = $existing['mime_type'];
                            $fileSize = (int) $existing['file_size'];
                        }
                        LibraryResourceRepository::update($resourceId, $sid, [
                            'title' => $title,
                            'description' => $description ?: null,
                            'resource_kind' => $resourceKind,
                            'subject_id' => $subjectId,
                            'type' => $matType,
                            'content' => $matType === 'link' ? $link : $existing['content'],
                            'file_path' => $filePath,
                            'original_name' => $originalName,
                            'mime_type' => $mimeType,
                            'file_size' => $fileSize,
                            'external_link' => $matType === 'link' ? $link : null,
                            'file_access_mode' => $matType === 'file' ? $fileAccess : 'downloadable',
                            'audience' => $audience,
                        ]);
                        flash('success', 'Resource updated.');
                        redirect('school/library.php?tab=published');
                    }
                }
            } catch (RuntimeException | InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

$editResource = null;
if ($action === 'edit' && $editId) {
    $editResource = LibraryResourceRepository::get($editId, $sid);
}

$filters = [
    'tab' => $tab,
    'status' => $tab,
    'search' => trim($_GET['q'] ?? ''),
    'resource_kind' => $_GET['kind'] ?? '',
    'subject_id' => (int) ($_GET['subject_id'] ?? 0) ?: null,
    'type' => $_GET['type'] ?? '',
];
$resources = LibraryResourceRepository::forSchool($sid, $filters);
$subjects = SubjectRepository::forSchool($sid);
$pendingCount = LibraryResourceRepository::countByStatus($sid, 'pending');

$pageTitle = 'Virtual Library';
$pageHeading = 'Virtual Library';
$pageSubtitle = 'Manage school-wide resources for teachers and students.';
$pageActionUrl = ($action === 'add' || $editResource) ? null : 'school/library.php?action=add';
$pageActionLabel = ($action === 'add' || $editResource) ? null : 'Add resource';
$pageActionIcon = 'fa-plus';
$activeMenu = 'library';
$menuItems = schoolAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'school/dashboard.php'],
    ['label' => 'Library', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
require __DIR__ . '/../includes/layout/library_grid.php';
?>

<?php if ($action === 'add' || $editResource): ?>
<div class="admin-form-card">
<div class="panel">
    <h2><?= $editResource ? 'Edit resource' : 'Add resource' ?></h2>
    <p class="text-muted mb-1">Published resources appear in the Virtual Library for teachers and students.</p>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" enctype="multipart/form-data" class="library-admin-form">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="<?= $editResource ? 'edit' : 'add' ?>">
        <?php if ($editResource): ?><input type="hidden" name="resource_id" value="<?= (int) $editResource['id'] ?>"><?php endif; ?>
        <div class="form-row">
            <div class="form-group"><label>Title</label><input name="title" class="form-control" value="<?= e($editResource['title'] ?? '') ?>" required></div>
            <div class="form-group">
                <label>Kind</label>
                <select name="resource_kind" class="form-control">
                    <?php foreach (LibraryResourceRepository::RESOURCE_KINDS as $kind): ?>
                        <option value="<?= e($kind) ?>"<?= ($editResource['resource_kind'] ?? 'other') === $kind ? ' selected' : '' ?>><?= e(resourceKindLabel($kind)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Subject</label>
                <select name="subject_id" class="form-control">
                    <option value="">None</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= (int) $subject['id'] ?>"<?= (int) ($editResource['subject_id'] ?? 0) === (int) $subject['id'] ? ' selected' : '' ?>><?= e($subject['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Audience</label>
                <select name="audience" class="form-control">
                    <option value="all"<?= ($editResource['audience'] ?? 'all') === 'all' ? ' selected' : '' ?>>Teachers & students</option>
                    <option value="teachers"<?= ($editResource['audience'] ?? '') === 'teachers' ? ' selected' : '' ?>>Teachers only</option>
                </select>
            </div>
        </div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="2"><?= e($editResource['description'] ?? '') ?></textarea></div>
        <div class="form-row">
            <div class="form-group">
                <label>Type</label>
                <select name="material_type" class="form-control" data-library-type-select>
                    <option value="file"<?= ($editResource['type'] ?? 'file') === 'file' ? ' selected' : '' ?>>File upload</option>
                    <option value="link"<?= ($editResource['type'] ?? '') === 'link' ? ' selected' : '' ?>>External link</option>
                    <option value="doc"<?= ($editResource['type'] ?? '') === 'doc' ? ' selected' : '' ?>>Rich document</option>
                </select>
            </div>
            <div class="form-group" data-library-file-access>
                <label>File access</label>
                <select name="file_access_mode" class="form-control">
                    <option value="downloadable"<?= ($editResource['file_access_mode'] ?? 'downloadable') === 'downloadable' ? ' selected' : '' ?>>Downloadable</option>
                    <option value="view_only"<?= ($editResource['file_access_mode'] ?? '') === 'view_only' ? ' selected' : '' ?>>View only</option>
                </select>
            </div>
        </div>
        <div class="form-group" data-library-link-field hidden>
            <label>URL</label>
            <input name="external_link" class="form-control" value="<?= e($editResource['external_link'] ?? $editResource['content'] ?? '') ?>" placeholder="https://">
        </div>
        <div class="form-group" data-library-file-field>
            <label>File<?= $editResource && !empty($editResource['original_name']) ? ' (replace optional)' : '' ?></label>
            <input type="file" name="file" class="form-control">
            <?php if ($editResource && !empty($editResource['original_name'])): ?>
                <small class="text-muted">Current: <?= e($editResource['original_name']) ?></small>
            <?php endif; ?>
        </div>
        <?php if ($editResource && ($editResource['type'] ?? '') === 'doc'): ?>
            <p><a href="<?= url('school/library-doc-editor.php?id=' . (int) $editResource['id']) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-file-lines"></i> Edit document content</a></p>
        <?php endif; ?>
        <div class="actions">
            <button type="submit" class="btn btn-primary"><?= $editResource ? 'Update' : 'Add' ?> resource</button>
            <a href="<?= url('school/library.php?tab=published') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
</div>
<script>
(function () {
    var select = document.querySelector('[data-library-type-select]');
    if (!select) return;
    var linkField = document.querySelector('[data-library-link-field]');
    var fileField = document.querySelector('[data-library-file-field]');
    var fileAccess = document.querySelector('[data-library-file-access]');
    function sync() {
        var type = select.value;
        linkField.hidden = type !== 'link';
        fileField.hidden = type !== 'file';
        if (fileAccess) fileAccess.hidden = type !== 'file';
    }
    select.addEventListener('change', sync);
    sync();
})();
</script>
<?php else: ?>

<nav class="library-tabs" aria-label="Library status">
    <a href="<?= url('school/library.php?tab=published') ?>" class="library-tab<?= $tab === 'published' ? ' is-active' : '' ?>">Published</a>
    <a href="<?= url('school/library.php?tab=pending') ?>" class="library-tab<?= $tab === 'pending' ? ' is-active' : '' ?>">
        Pending approval<?= $pendingCount > 0 ? ' (' . $pendingCount . ')' : '' ?>
    </a>
    <a href="<?= url('school/library.php?tab=rejected') ?>" class="library-tab<?= $tab === 'rejected' ? ' is-active' : '' ?>">Rejected</a>
</nav>

<?php renderLibraryFilters('school/library.php', $subjects, $filters, true); ?>

<?php if ($tab === 'pending'): ?>
<div class="library-pending-list">
    <?php if ($resources === []): ?>
        <?= adminEmptyState('fa-clock', 'No pending submissions', 'Teacher-shared resources awaiting approval will appear here.') ?>
    <?php else: ?>
        <?php foreach ($resources as $resource): ?>
        <article class="library-pending-card panel">
            <div class="library-pending-main">
                <h3><?= e($resource['title']) ?></h3>
                <p class="text-muted">Submitted by <?= e(trim($resource['creator_first'] . ' ' . $resource['creator_last'])) ?> · <?= formatDate($resource['created_at'], 'M j, Y g:i A') ?></p>
                <div class="library-card-tags">
                    <span class="library-tag"><?= e(resourceKindLabel($resource['resource_kind'])) ?></span>
                    <?php if (!empty($resource['subject_name'])): ?><span class="library-tag library-tag--muted"><?= e($resource['subject_name']) ?></span><?php endif; ?>
                    <span class="library-tag library-tag--muted"><?= e(libraryAudienceLabel($resource['audience'])) ?></span>
                </div>
                <?php if (!empty($resource['description'])): ?><p><?= e($resource['description']) ?></p><?php endif; ?>
                <a href="<?= e(libraryViewUrl((int) $resource['id'])) ?>" class="btn btn-sm btn-secondary">Preview</a>
            </div>
            <div class="library-pending-actions">
                <form method="post"><?= csrfField() ?>
                    <input type="hidden" name="form_action" value="approve">
                    <input type="hidden" name="resource_id" value="<?= (int) $resource['id'] ?>">
                    <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                </form>
                <form method="post" class="library-reject-form"><?= csrfField() ?>
                    <input type="hidden" name="form_action" value="reject">
                    <input type="hidden" name="resource_id" value="<?= (int) $resource['id'] ?>">
                    <textarea name="rejection_note" class="form-control" rows="2" placeholder="Optional rejection note"></textarea>
                    <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                </form>
            </div>
        </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php else: ?>
    <?php renderLibraryGrid($resources, 'admin'); ?>
    <?php if ($resources !== [] && $tab === 'published'): ?>
    <div class="library-admin-actions panel mt-1">
        <p class="text-muted">Manage published resources from the edit action on each card, or use the list below.</p>
        <div class="table-wrap">
            <table class="admin-data-table">
                <thead><tr><th>Title</th><th>Kind</th><th>Subject</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($resources as $resource): ?>
                    <tr>
                        <td><a href="<?= e(libraryViewUrl((int) $resource['id'])) ?>"><?= e($resource['title']) ?></a></td>
                        <td><?= e(resourceKindLabel($resource['resource_kind'])) ?></td>
                        <td><?= e($resource['subject_name'] ?? '—') ?></td>
                        <td class="actions">
                            <a href="<?= url('school/library.php?action=edit&id=' . (int) $resource['id']) ?>" class="btn btn-sm btn-secondary">Edit</a>
                            <form method="post" class="inline-form" data-confirm="Unpublish this resource?"><?= csrfField() ?>
                                <input type="hidden" name="form_action" value="unpublish">
                                <input type="hidden" name="resource_id" value="<?= (int) $resource['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-secondary">Unpublish</button>
                            </form>
                            <form method="post" class="inline-form" data-confirm="Delete this resource?"><?= csrfField() ?>
                                <input type="hidden" name="form_action" value="delete">
                                <input type="hidden" name="resource_id" value="<?= (int) $resource['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
