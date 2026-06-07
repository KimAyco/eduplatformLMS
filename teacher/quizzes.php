<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('teacher');
requireSchoolActive();

$user = currentUser();
$classes = getTeacherClasses();
$action = $_GET['action'] ?? '';
$editId = (int) ($_GET['id'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['form_action'] ?? '';
    $classId = (int) ($_POST['class_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    $timeLimit = $_POST['time_limit_minutes'] !== '' ? (int) $_POST['time_limit_minutes'] : null;
    $dueDate = trim($_POST['due_date'] ?? '');
    $maxAttempts = max(1, (int) ($_POST['max_attempts'] ?? 1));
    $quizId = (int) ($_POST['quiz_id'] ?? 0);

    if ($postAction !== 'delete') {
        requireClassAccess($classId, 'teacher');
        if ($title === '') $errors[] = 'Title is required.';
    }

    if ($postAction === 'add' && empty($errors)) {
        $stmt = db()->prepare('INSERT INTO quizzes (class_id, teacher_id, title, instructions, time_limit_minutes, due_date, max_attempts) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$classId, $user['id'], $title, $instructions ?: null, $timeLimit, $dueDate ?: null, $maxAttempts]);
        $newId = (int) db()->lastInsertId();
        flash('success', 'Quiz created. Add questions below.');
        redirect('teacher/quiz-edit.php?id=' . $newId);
    } elseif ($postAction === 'edit' && $quizId && empty($errors)) {
        $stmt = db()->prepare('UPDATE quizzes SET class_id=?, title=?, instructions=?, time_limit_minutes=?, due_date=?, max_attempts=? WHERE id=? AND teacher_id=?');
        $stmt->execute([$classId, $title, $instructions ?: null, $timeLimit, $dueDate ?: null, $maxAttempts, $quizId, $user['id']]);
        flash('success', 'Quiz updated.');
        redirect('teacher/quiz-edit.php?id=' . $quizId);
    } elseif ($postAction === 'delete' && $quizId) {
        db()->prepare('DELETE FROM quizzes WHERE id=? AND teacher_id=?')->execute([$quizId, $user['id']]);
        flash('success', 'Quiz deleted.');
        redirect('teacher/quizzes.php');
    }
}

$editItem = null;
if ($action === 'edit' && $editId) {
    $stmt = db()->prepare('SELECT * FROM quizzes WHERE id=? AND teacher_id=?');
    $stmt->execute([$editId, $user['id']]);
    $editItem = $stmt->fetch();
    if ($editItem && $editItem['due_date']) {
        $editItem['due_date_local'] = date('Y-m-d\TH:i', strtotime($editItem['due_date']));
    }
}

$stmt = db()->prepare('SELECT q.*, sub.name AS name, sub.name AS class_name, g.name AS group_name,
    (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) AS question_count,
    (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id AND status != ?) AS attempt_count
    FROM quizzes q
    INNER JOIN classes c ON c.id = q.class_id
    INNER JOIN subjects sub ON sub.id = c.subject_id
    INNER JOIN class_groups g ON g.id = c.class_group_id
    WHERE q.teacher_id = ? ORDER BY q.created_at DESC');
$stmt->execute(['in_progress', $user['id']]);
$quizzes = $stmt->fetchAll();

$pageTitle = 'Quizzes';
$pageHeading = 'Quizzes & Exams';
$activeMenu = 'quizzes';
$menuItems = teacherMenu();

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="filter-bar">
    <a href="<?= url('teacher/quizzes.php?action=add') ?>" class="btn btn-primary btn-sm">Create Quiz</a>
</div>

<?php if ($action === 'add' || $editItem): ?>
<div class="panel">
    <h2><?= $editItem ? 'Edit Quiz Settings' : 'Create Quiz' ?></h2>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <?php if (empty($classes)): ?>
        <p class="text-muted">No classes assigned.</p>
    <?php else: ?>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="<?= $editItem ? 'edit' : 'add' ?>">
        <?php if ($editItem): ?><input type="hidden" name="quiz_id" value="<?= $editItem['id'] ?>"><?php endif; ?>
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
            <div class="form-group"><label>Time Limit (minutes)</label><input type="number" name="time_limit_minutes" class="form-control" value="<?= e($editItem['time_limit_minutes'] ?? '') ?>" placeholder="Optional"></div>
            <div class="form-group"><label>Due Date</label><input type="datetime-local" name="due_date" class="form-control" value="<?= e($editItem['due_date_local'] ?? '') ?>"></div>
            <div class="form-group"><label>Max Attempts</label><input type="number" name="max_attempts" class="form-control" value="<?= e($editItem['max_attempts'] ?? '1') ?>" min="1"></div>
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">Save</button>
            <?php if ($editItem): ?><a href="<?= url('teacher/quiz-edit.php?id='.$editItem['id']) ?>" class="btn btn-secondary">Manage Questions</a><?php endif; ?>
            <a href="<?= url('teacher/quizzes.php') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="table-wrap">
    <table>
        <thead><tr><th>Title</th><th>Class</th><th>Questions</th><th>Attempts</th><th>Due</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (empty($quizzes)): ?>
            <tr><td colspan="6" class="text-muted">No quizzes yet.</td></tr>
        <?php else: foreach ($quizzes as $q): ?>
            <tr>
                <td><?= e($q['title']) ?></td>
                <td><?= e(classDisplayName($q)) ?></td>
                <td><?= (int)$q['question_count'] ?></td>
                <td><?= (int)$q['attempt_count'] ?></td>
                <td><?= formatDate($q['due_date']) ?></td>
                <td class="actions">
                    <a href="<?= url('teacher/quiz-edit.php?id='.$q['id']) ?>" class="btn btn-sm btn-primary">Questions</a>
                    <a href="<?= url('teacher/quiz-attempts.php?quiz_id='.$q['id']) ?>" class="btn btn-sm btn-secondary">Attempts</a>
                    <a href="<?= url('teacher/quizzes.php?action=edit&id='.$q['id']) ?>" class="btn btn-sm btn-secondary">Edit</a>
                    <form method="post" style="display:inline" onsubmit="return confirm('Delete quiz?')"><?= csrfField() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="quiz_id" value="<?= $q['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
