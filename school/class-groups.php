<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('school_admin');
requireSchoolActive();

$sid = schoolId();
$action = $_GET['action'] ?? '';
$editId = (int) ($_GET['id'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['form_action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $year = trim($_POST['academic_year'] ?? '');
    $groupId = (int) ($_POST['group_id'] ?? 0);

    if ($name === '') {
        $errors[] = 'Group name is required.';
    }

    if ($postAction === 'add' && empty($errors)) {
        $stmt = db()->prepare('INSERT INTO class_groups (school_id, name, description, academic_year) VALUES (?, ?, ?, ?)');
        $stmt->execute([$sid, $name, $description ?: null, $year ?: null]);
        $newId = (int) db()->lastInsertId();
        flash('success', 'Class group created.');
        redirect('school/class-group.php?id=' . $newId);
    } elseif ($postAction === 'edit' && $groupId && empty($errors)) {
        $stmt = db()->prepare('UPDATE class_groups SET name=?, description=?, academic_year=? WHERE id=? AND school_id=?');
        $stmt->execute([$name, $description ?: null, $year ?: null, $groupId, $sid]);
        flash('success', 'Class group updated.');
        redirect('school/class-group.php?id=' . $groupId);
    } elseif ($postAction === 'delete' && $groupId) {
        $stmt = db()->prepare('DELETE FROM class_groups WHERE id=? AND school_id=?');
        $stmt->execute([$groupId, $sid]);
        flash('success', 'Class group deleted.');
        redirect('school/class-groups.php');
    }
}

$editGroup = null;
if ($action === 'edit' && $editId) {
    $editGroup = ClassGroupRepository::get($editId, $sid);
}

$groups = ClassGroupRepository::withCounts($sid);

$pageTitle = 'Class Groups';
$pageHeading = 'Class Groups';
$pageSubtitle = 'Each group contains subjects from your catalog with assigned teachers.';
$pageActionUrl = ($action === 'add' || $editGroup) ? null : 'school/class-groups.php?action=add';
$pageActionLabel = ($action === 'add' || $editGroup) ? null : 'Add Class Group';
$pageActionIcon = 'fa-plus';
$activeMenu = 'class_groups';
$menuItems = schoolAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'school/dashboard.php'],
    ['label' => 'Class Groups', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<?php if ($action === 'add' || $editGroup): ?>
<div class="admin-form-card">
<div class="panel">
    <h2><?= $editGroup ? 'Edit Class Group' : 'Add Class Group' ?></h2>
    <p class="text-muted mb-1">Create a cohort (e.g. BSIT 1A), then add subjects and assign teachers on the group page.</p>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="<?= $editGroup ? 'edit' : 'add' ?>">
        <?php if ($editGroup): ?><input type="hidden" name="group_id" value="<?= $editGroup['id'] ?>"><?php endif; ?>
        <div class="form-row">
            <div class="form-group"><label>Group Name</label><input name="name" class="form-control" value="<?= e($editGroup['name'] ?? '') ?>" required placeholder="e.g. BSIT 1A"></div>
            <div class="form-group"><label>Academic Year</label><input name="academic_year" class="form-control" value="<?= e($editGroup['academic_year'] ?? '') ?>" placeholder="e.g. 2025-2026"></div>
        </div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control"><?= e($editGroup['description'] ?? '') ?></textarea></div>
        <div class="actions">
            <button type="submit" class="btn btn-primary"><?= $editGroup ? 'Update' : 'Create' ?> Group</button>
            <?php if ($editGroup): ?>
                <a href="<?= url('school/class-group.php?id=' . $editGroup['id']) ?>" class="btn btn-secondary">Cancel</a>
            <?php else: ?>
                <a href="<?= url('school/class-groups.php') ?>" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>
</div>
<?php elseif (empty($groups)): ?>
<?php
$cta = SubjectRepository::count($sid) === 0
    ? ['school/subjects.php?action=add', 'Add subjects first']
    : ['school/class-groups.php?action=add', 'Create class group'];
echo adminEmptyState('fa-layer-group', 'No class groups yet', 'Create a class group after setting up your subject catalog and teachers.', $cta[0], $cta[1]);
?>
<?php else: ?>
<div class="admin-table-card">
<div class="table-wrap">
    <table>
        <thead><tr><th>Group</th><th>Subjects</th><th>Students</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($groups as $g): ?>
            <tr>
                <td>
                    <a href="<?= url('school/class-group.php?id=' . $g['id']) ?>" class="table-group-link">
                        <?= tableGroupCell($g['name'], $g['academic_year'] ?: null) ?>
                    </a>
                </td>
                <td><?= (int) $g['class_count'] ?></td>
                <td><?= (int) $g['student_count'] ?></td>
                <td>
                    <?php if ((int) $g['unassigned_count'] > 0): ?>
                        <span class="badge badge-pending"><?= (int) $g['unassigned_count'] ?> unassigned</span>
                    <?php else: ?>
                        <span class="badge badge-active">Ready</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <a href="<?= url('school/class-group.php?id=' . $g['id']) ?>" class="btn btn-sm btn-primary">Manage</a>
                    <a href="<?= url('school/class-groups.php?action=edit&id=' . $g['id']) ?>" class="btn btn-sm btn-secondary">Edit</a>
                    <form method="post" style="display:inline" data-confirm="Delete this group and all its subject assignments?"><?= csrfField() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="group_id" value="<?= $g['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
