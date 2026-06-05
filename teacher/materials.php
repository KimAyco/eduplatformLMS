<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('teacher');
requireSchoolActive();

$user = currentUser();
$classes = getTeacherClasses();
$action = $_GET['action'] ?? '';
$editId = (int) ($_GET['id'] ?? 0);
$filterClass = (int) ($_GET['class_id'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['form_action'] ?? '';
    $classId = (int) ($_POST['class_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $link = trim($_POST['external_link'] ?? '');
    $materialId = (int) ($_POST['material_id'] ?? 0);

    requireClassAccess($classId, 'teacher');

    if ($title === '') $errors[] = 'Title is required.';

    if ($postAction === 'add' && empty($errors)) {
        try {
            $filePath = null;
            if (!empty($_FILES['file']['name'])) {
                $filePath = uploadFile($_FILES['file'], schoolId() . '/materials');
            }
            $stmt = db()->prepare('INSERT INTO materials (class_id, teacher_id, title, body, file_path, external_link) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$classId, $user['id'], $title, $body ?: null, $filePath, $link ?: null]);
            flash('success', 'Material added.');
            redirect('teacher/materials.php' . ($filterClass ? '?class_id=' . $filterClass : ''));
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    } elseif ($postAction === 'edit' && $materialId && empty($errors)) {
        $stmt = db()->prepare('SELECT * FROM materials WHERE id=? AND teacher_id=?');
        $stmt->execute([$materialId, $user['id']]);
        $mat = $stmt->fetch();
        if (!$mat) {
            $errors[] = 'Material not found.';
        } else {
            try {
                $filePath = $mat['file_path'];
                if (!empty($_FILES['file']['name'])) {
                    deleteUpload($filePath);
                    $filePath = uploadFile($_FILES['file'], schoolId() . '/materials');
                }
                $stmt = db()->prepare('UPDATE materials SET class_id=?, title=?, body=?, file_path=?, external_link=? WHERE id=? AND teacher_id=?');
                $stmt->execute([$classId, $title, $body ?: null, $filePath, $link ?: null, $materialId, $user['id']]);
                flash('success', 'Material updated.');
                redirect('teacher/materials.php' . ($filterClass ? '?class_id=' . $filterClass : ''));
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
    } elseif ($postAction === 'delete' && $materialId) {
        $stmt = db()->prepare('SELECT * FROM materials WHERE id=? AND teacher_id=?');
        $stmt->execute([$materialId, $user['id']]);
        $mat = $stmt->fetch();
        if ($mat) {
            deleteUpload($mat['file_path']);
            db()->prepare('DELETE FROM materials WHERE id=?')->execute([$materialId]);
            flash('success', 'Material deleted.');
        }
        redirect('teacher/materials.php' . ($filterClass ? '?class_id=' . $filterClass : ''));
    }
}

$editMat = null;
if ($action === 'edit' && $editId) {
    $stmt = db()->prepare('SELECT m.* FROM materials m INNER JOIN class_teachers ct ON ct.class_id = m.class_id WHERE m.id=? AND m.teacher_id=? AND ct.teacher_id=?');
    $stmt->execute([$editId, $user['id'], $user['id']]);
    $editMat = $stmt->fetch();
}

$classIds = array_column($classes, 'id');
$materials = [];
if (!empty($classIds)) {
    $sql = 'SELECT m.*, c.name AS class_name, c.section FROM materials m
            INNER JOIN classes c ON c.id = m.class_id
            WHERE m.teacher_id = ?';
    $params = [$user['id']];
    if ($filterClass && in_array($filterClass, $classIds)) {
        $sql .= ' AND m.class_id = ?';
        $params[] = $filterClass;
    }
    $sql .= ' ORDER BY m.created_at DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $materials = $stmt->fetchAll();
}

$pageTitle = 'Materials';
$pageHeading = 'Class Materials';
$activeMenu = 'materials';
$menuItems = teacherMenu();

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="filter-bar">
    <a href="<?= url('teacher/materials.php?action=add') ?>" class="btn btn-primary btn-sm">Add Material</a>
    <?php if (!empty($classes)): ?>
    <form method="get" style="display:flex;gap:.5rem;align-items:center;">
        <select name="class_id" class="form-control" onchange="this.form.submit()">
            <option value="">All classes</option>
            <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $filterClass === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name'] . ($c['section'] ? ' - '.$c['section'] : '')) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>
</div>

<?php if ($action === 'add' || $editMat): ?>
<div class="panel">
    <h2><?= $editMat ? 'Edit Material' : 'Add Material' ?></h2>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <?php if (empty($classes)): ?>
        <p class="text-muted">You need to be assigned to a class first.</p>
    <?php else: ?>
    <form method="post" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="<?= $editMat ? 'edit' : 'add' ?>">
        <?php if ($editMat): ?><input type="hidden" name="material_id" value="<?= $editMat['id'] ?>"><?php endif; ?>
        <div class="form-group">
            <label>Class</label>
            <select name="class_id" class="form-control" required>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($editMat['class_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['name'] . ($c['section'] ? ' - '.$c['section'] : '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Title</label><input name="title" class="form-control" value="<?= e($editMat['title'] ?? '') ?>" required></div>
        <div class="form-group"><label>Description</label><textarea name="body" class="form-control"><?= e($editMat['body'] ?? '') ?></textarea></div>
        <div class="form-group"><label>External Link</label><input type="url" name="external_link" class="form-control" value="<?= e($editMat['external_link'] ?? '') ?>"></div>
        <div class="form-group">
            <label>File</label>
            <input type="file" name="file" class="form-control">
            <?php if ($editMat && $editMat['file_path']): ?>
                <small>Current: <a href="<?= e(uploadUrl($editMat['file_path'])) ?>" target="_blank">Download</a></small>
            <?php endif; ?>
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="<?= url('teacher/materials.php') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="table-wrap">
    <table>
        <thead><tr><th>Title</th><th>Class</th><th>File/Link</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (empty($materials)): ?>
            <tr><td colspan="5" class="text-muted">No materials yet.</td></tr>
        <?php else: foreach ($materials as $m): ?>
            <tr>
                <td><?= e($m['title']) ?></td>
                <td><?= e($m['class_name'] . ($m['section'] ? ' - '.$m['section'] : '')) ?></td>
                <td>
                    <?php if ($m['file_path']): ?><a href="<?= e(uploadUrl($m['file_path'])) ?>" target="_blank">File</a><?php endif; ?>
                    <?php if ($m['external_link']): ?><?= $m['file_path'] ? ' · ' : '' ?><a href="<?= e($m['external_link']) ?>" target="_blank">Link</a><?php endif; ?>
                    <?= !$m['file_path'] && !$m['external_link'] ? '—' : '' ?>
                </td>
                <td><?= formatDate($m['created_at'], 'M j, Y') ?></td>
                <td class="actions">
                    <a href="<?= url('teacher/materials.php?action=edit&id='.$m['id']) ?>" class="btn btn-sm btn-secondary">Edit</a>
                    <form method="post" style="display:inline" onsubmit="return confirm('Delete?')"><?= csrfField() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="material_id" value="<?= $m['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
