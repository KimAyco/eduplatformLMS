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
    $instructions = trim($_POST['instructions'] ?? '');
    $dueDate = trim($_POST['due_date'] ?? '');
    $maxPoints = (float) ($_POST['max_points'] ?? 100);
    $allowLate = isset($_POST['allow_late']) ? 1 : 0;
    $assignmentId = (int) ($_POST['assignment_id'] ?? 0);

    requireClassAccess($classId, 'teacher');

    if ($title === '') $errors[] = 'Title is required.';

    if ($postAction === 'add' && empty($errors)) {
        $stmt = db()->prepare('INSERT INTO assignments (class_id, teacher_id, title, instructions, due_date, max_points, allow_late) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$classId, $user['id'], $title, $instructions ?: null, $dueDate ?: null, $maxPoints, $allowLate]);
        flash('success', 'Assignment created.');
        redirect('teacher/assignments.php');
    } elseif ($postAction === 'edit' && $assignmentId && empty($errors)) {
        $stmt = db()->prepare('UPDATE assignments SET class_id=?, title=?, instructions=?, due_date=?, max_points=?, allow_late=? WHERE id=? AND teacher_id=?');
        $stmt->execute([$classId, $title, $instructions ?: null, $dueDate ?: null, $maxPoints, $allowLate, $assignmentId, $user['id']]);
        flash('success', 'Assignment updated.');
        redirect('teacher/assignments.php');
    } elseif ($postAction === 'delete' && $assignmentId) {
        db()->prepare('DELETE FROM assignments WHERE id=? AND teacher_id=?')->execute([$assignmentId, $user['id']]);
        flash('success', 'Assignment deleted.');
        redirect('teacher/assignments.php');
    }
}

$editItem = null;
if ($action === 'edit' && $editId) {
    $stmt = db()->prepare('SELECT * FROM assignments WHERE id=? AND teacher_id=?');
    $stmt->execute([$editId, $user['id']]);
    $editItem = $stmt->fetch();
    if ($editItem && $editItem['due_date']) {
        $editItem['due_date_local'] = date('Y-m-d\TH:i', strtotime($editItem['due_date']));
    }
}

$sql = 'SELECT a.*, s.name AS name, s.name AS class_name, g.name AS group_name,
        (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) AS submission_count
        FROM assignments a
        INNER JOIN classes c ON c.id = a.class_id
        INNER JOIN subjects s ON s.id = c.subject_id
        INNER JOIN class_groups g ON g.id = c.class_group_id
        WHERE a.teacher_id = ?';
$params = [$user['id']];
if ($filterClass) {
    $sql .= ' AND a.class_id = ?';
    $params[] = $filterClass;
}
$sql .= ' ORDER BY a.due_date DESC, a.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$assignments = $stmt->fetchAll();

$pageTitle = 'Assignments';
$pageHeading = 'Assignments';
$activeMenu = 'assignments';
$menuItems = teacherMenu();

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="filter-bar">
    <a href="<?= url('teacher/assignments.php?action=add') ?>" class="btn btn-primary btn-sm">Create Assignment</a>
    <?php if (!empty($classes)): ?>
    <form method="get" style="display:flex;gap:.5rem;">
        <select name="class_id" class="form-control" onchange="this.form.submit()">
            <option value="">All classes</option>
            <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $filterClass === (int)$c['id'] ? 'selected' : '' ?>><?= e(classDisplayName($c)) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>
</div>

<?php if ($action === 'add' || $editItem): ?>
<div class="panel">
    <h2><?= $editItem ? 'Edit Assignment' : 'Create Assignment' ?></h2>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <?php if (empty($classes)): ?>
        <p class="text-muted">No classes assigned.</p>
    <?php else: ?>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="<?= $editItem ? 'edit' : 'add' ?>">
        <?php if ($editItem): ?><input type="hidden" name="assignment_id" value="<?= $editItem['id'] ?>"><?php endif; ?>
        <div class="form-group">
            <label>Class</label>
            <select name="class_id" class="form-control" required>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($editItem['class_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e(classDisplayName($c)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Title</label><input name="title" class="form-control" value="<?= e($editItem['title'] ?? '') ?>" required></div>
        <div class="form-group"><label>Instructions</label><textarea name="instructions" class="form-control"><?= e($editItem['instructions'] ?? '') ?></textarea></div>
        <div class="form-row">
            <div class="form-group"><label>Due Date</label><input type="datetime-local" name="due_date" class="form-control" value="<?= e($editItem['due_date_local'] ?? '') ?>"></div>
            <div class="form-group"><label>Max Points</label><input type="number" step="0.01" name="max_points" class="form-control" value="<?= e($editItem['max_points'] ?? '100') ?>"></div>
        </div>
        <div class="form-check"><input type="checkbox" name="allow_late" id="allow_late" <?= ($editItem['allow_late'] ?? 0) ? 'checked' : '' ?>><label for="allow_late">Allow late submissions</label></div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="<?= url('teacher/assignments.php') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="table-wrap">
    <table>
        <thead><tr><th>Title</th><th>Class</th><th>Due</th><th>Points</th><th>Submissions</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (empty($assignments)): ?>
            <tr><td colspan="6" class="text-muted">No assignments yet.</td></tr>
        <?php else: foreach ($assignments as $a): ?>
            <tr>
                <td><?= e($a['title']) ?></td>
                <td><?= e(classDisplayName($a)) ?></td>
                <td><?= formatDate($a['due_date']) ?></td>
                <td><?= e($a['max_points']) ?></td>
                <td><?= (int)$a['submission_count'] ?></td>
                <td class="actions">
                    <a href="<?= url('teacher/grade-submissions.php?assignment_id='.$a['id']) ?>" class="btn btn-sm btn-primary">Grade</a>
                    <a href="<?= url('teacher/assignments.php?action=edit&id='.$a['id']) ?>" class="btn btn-sm btn-secondary">Edit</a>
                    <form method="post" style="display:inline" onsubmit="return confirm('Delete?')"><?= csrfField() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="assignment_id" value="<?= $a['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
