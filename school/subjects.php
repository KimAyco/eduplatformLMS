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
    $subjectId = (int) ($_POST['subject_id'] ?? 0);

    if ($name === '') {
        $errors[] = 'Subject name is required.';
    }

    if ($postAction === 'add' && empty($errors)) {
        $dup = db()->prepare('SELECT id FROM subjects WHERE school_id = ? AND name = ?');
        $dup->execute([$sid, $name]);
        if ($dup->fetch()) {
            $errors[] = 'A subject with this name already exists.';
        } else {
            $stmt = db()->prepare('INSERT INTO subjects (school_id, name, description) VALUES (?, ?, ?)');
            $stmt->execute([$sid, $name, $description ?: null]);
            flash('success', 'Subject added to catalog.');
            redirect('school/subjects.php');
        }
    } elseif ($postAction === 'edit' && $subjectId && empty($errors)) {
        $dup = db()->prepare('SELECT id FROM subjects WHERE school_id = ? AND name = ? AND id != ?');
        $dup->execute([$sid, $name, $subjectId]);
        if ($dup->fetch()) {
            $errors[] = 'A subject with this name already exists.';
        } else {
            $stmt = db()->prepare('UPDATE subjects SET name=?, description=? WHERE id=? AND school_id=?');
            $stmt->execute([$name, $description ?: null, $subjectId, $sid]);
            flash('success', 'Subject updated.');
            redirect('school/subjects.php');
        }
    } elseif ($postAction === 'delete' && $subjectId) {
        $usage = db()->prepare('SELECT COUNT(*) FROM classes WHERE subject_id = ?');
        $usage->execute([$subjectId]);
        if ((int) $usage->fetchColumn() > 0) {
            flash('error', 'Cannot delete a subject that is used in a class group. Remove it from groups first.');
        } else {
            db()->prepare('DELETE FROM subjects WHERE id=? AND school_id=?')->execute([$subjectId, $sid]);
            flash('success', 'Subject deleted.');
        }
        redirect('school/subjects.php');
    }
}

$editSubject = null;
if ($action === 'edit' && $editId) {
    $editSubject = SubjectRepository::get($editId, $sid);
}

$subjects = SubjectRepository::withUsageCounts($sid);

$pageTitle = 'Subjects';
$pageHeading = 'Subjects';
$pageSubtitle = 'Create subjects first, then add teachers and assign them to class groups.';
$pageActionUrl = ($action === 'add' || $editSubject) ? null : 'school/subjects.php?action=add';
$pageActionLabel = ($action === 'add' || $editSubject) ? null : 'Add Subject';
$pageActionIcon = 'fa-plus';
$activeMenu = 'subjects';
$menuItems = schoolAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'school/dashboard.php'],
    ['label' => 'Subjects', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<?php if ($action === 'add' || $editSubject): ?>
<div class="admin-form-card">
<div class="panel">
    <h2><?= $editSubject ? 'Edit Subject' : 'Add Subject' ?></h2>
    <p class="text-muted mb-1">Add all subjects your school offers (e.g. ENG101, NSTP1). You will assign them to class groups later.</p>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="<?= $editSubject ? 'edit' : 'add' ?>">
        <?php if ($editSubject): ?><input type="hidden" name="subject_id" value="<?= $editSubject['id'] ?>"><?php endif; ?>
        <div class="form-row">
            <div class="form-group"><label>Subject Name</label><input name="name" class="form-control" value="<?= e($editSubject['name'] ?? '') ?>" required placeholder="e.g. ENG101"></div>
        </div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control"><?= e($editSubject['description'] ?? '') ?></textarea></div>
        <div class="actions">
            <button type="submit" class="btn btn-primary"><?= $editSubject ? 'Update' : 'Add' ?> Subject</button>
            <a href="<?= url('school/subjects.php') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
</div>
<?php endif; ?>

<?php if (empty($subjects) && $action !== 'add' && !$editSubject): ?>
<?= adminEmptyState('fa-book', 'No subjects yet', 'Add all subjects your school offers before setting up class groups.', 'school/subjects.php?action=add', 'Add your first subject') ?>
<?php elseif ($action !== 'add' && !$editSubject): ?>
<div class="admin-table-card">
<div class="table-wrap">
    <table>
        <thead><tr><th>Subject</th><th>Description</th><th>Used in groups</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($subjects as $s): ?>
            <tr>
                <td><?= tableSubjectCell($s['name']) ?></td>
                <td class="text-muted"><?= e($s['description'] ?: '—') ?></td>
                <td><?= (int) $s['usage_count'] ?></td>
                <td class="actions">
                    <a href="<?= url('school/subjects.php?action=edit&id=' . $s['id']) ?>" class="btn btn-sm btn-secondary">Edit</a>
                    <?php if ((int) $s['usage_count'] === 0): ?>
                    <form method="post" style="display:inline" data-confirm="Delete this subject?"><?= csrfField() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="subject_id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
