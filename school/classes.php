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
    $section = trim($_POST['section'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $year = trim($_POST['academic_year'] ?? '');
    $classId = (int) ($_POST['class_id'] ?? 0);

    if ($name === '') $errors[] = 'Class name is required.';

    if ($postAction === 'add' && empty($errors)) {
        $stmt = db()->prepare('INSERT INTO classes (school_id, name, section, description, academic_year) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$sid, $name, $section ?: null, $description ?: null, $year ?: null]);
        flash('success', 'Class created.');
        redirect('school/classes.php');
    } elseif ($postAction === 'edit' && $classId && empty($errors)) {
        $stmt = db()->prepare('UPDATE classes SET name=?, section=?, description=?, academic_year=? WHERE id=? AND school_id=?');
        $stmt->execute([$name, $section ?: null, $description ?: null, $year ?: null, $classId, $sid]);
        flash('success', 'Class updated.');
        redirect('school/classes.php');
    } elseif ($postAction === 'delete' && $classId) {
        $stmt = db()->prepare('DELETE FROM classes WHERE id=? AND school_id=?');
        $stmt->execute([$classId, $sid]);
        flash('success', 'Class deleted.');
        redirect('school/classes.php');
    }
}

$editClass = null;
if ($action === 'edit' && $editId) {
    $stmt = db()->prepare('SELECT * FROM classes WHERE id = ? AND school_id = ?');
    $stmt->execute([$editId, $sid]);
    $editClass = $stmt->fetch();
}

$classes = ClassRepository::withCounts($sid);

$pageTitle = 'Classes';
$pageHeading = 'Classes';
$activeMenu = 'classes';
$menuItems = schoolAdminMenu();

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<?php if ($action === 'add' || $editClass): ?>
<div class="panel">
    <h2><?= $editClass ? 'Edit Class' : 'Add Class' ?></h2>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="<?= $editClass ? 'edit' : 'add' ?>">
        <?php if ($editClass): ?><input type="hidden" name="class_id" value="<?= $editClass['id'] ?>"><?php endif; ?>
        <div class="form-row">
            <div class="form-group"><label>Class Name</label><input name="name" class="form-control" value="<?= e($editClass['name'] ?? '') ?>" required></div>
            <div class="form-group"><label>Section</label><input name="section" class="form-control" value="<?= e($editClass['section'] ?? '') ?>"></div>
        </div>
        <div class="form-group"><label>Academic Year</label><input name="academic_year" class="form-control" value="<?= e($editClass['academic_year'] ?? '') ?>" placeholder="e.g. 2025-2026"></div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control"><?= e($editClass['description'] ?? '') ?></textarea></div>
        <div class="actions">
            <button type="submit" class="btn btn-primary"><?= $editClass ? 'Update' : 'Create' ?> Class</button>
            <a href="<?= url('school/classes.php') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php else: ?>
<div class="panel-header" style="margin-bottom:1rem;">
    <h2>All Classes</h2>
    <a href="<?= url('school/classes.php?action=add') ?>" class="btn btn-primary btn-sm">Add Class</a>
</div>
<?php endif; ?>

<div class="table-wrap">
    <table>
        <thead><tr><th>Class</th><th>Section</th><th>Year</th><th>Teachers</th><th>Students</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (empty($classes)): ?>
            <tr><td colspan="6" class="text-muted">No classes yet.</td></tr>
        <?php else: foreach ($classes as $c): ?>
            <tr>
                <td><?= e($c['name']) ?></td>
                <td><?= e($c['section'] ?: '—') ?></td>
                <td><?= e($c['academic_year'] ?: '—') ?></td>
                <td><?= (int)$c['teacher_count'] ?></td>
                <td><?= (int)$c['student_count'] ?></td>
                <td class="actions">
                    <a href="<?= url('school/enrollments.php?class_id=' . $c['id']) ?>" class="btn btn-sm btn-primary">Enrollments</a>
                    <a href="<?= url('school/classes.php?action=edit&id=' . $c['id']) ?>" class="btn btn-sm btn-secondary">Edit</a>
                    <form method="post" style="display:inline" onsubmit="return confirm('Delete this class and all its content?')"><?= csrfField() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="class_id" value="<?= $c['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
