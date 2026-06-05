<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('teacher');
requireSchoolActive();

$user = currentUser();
$classId = (int) ($_GET['id'] ?? 0);
requireClassAccess($classId, 'teacher');

$class = getClass($classId);
if (!$class) {
    flash('error', 'Class not found.');
    redirect('teacher/dashboard.php');
}

$action = $_GET['action'] ?? '';
$itemId = (int) ($_GET['item_id'] ?? 0);
$errors = [];
$courseUrl = teacherCourseUrl($classId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['form_action'] ?? '';

    if ($postAction === 'add_material' || $postAction === 'edit_material') {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $link = trim($_POST['external_link'] ?? '');
        $materialId = (int) ($_POST['material_id'] ?? 0);

        if ($title === '') {
            $errors[] = 'Title is required.';
        }

        if ($postAction === 'add_material' && empty($errors)) {
            try {
                $filePath = null;
                if (!empty($_FILES['file']['name'])) {
                    $filePath = uploadFile($_FILES['file'], schoolId() . '/materials');
                }
                $stmt = db()->prepare('INSERT INTO materials (class_id, teacher_id, title, body, file_path, external_link) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$classId, $user['id'], $title, $body ?: null, $filePath, $link ?: null]);
                flash('success', 'Material added.');
                redirect('teacher/course.php?id=' . $classId);
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
                $action = 'add_material';
            }
        } elseif ($postAction === 'edit_material' && $materialId && empty($errors)) {
            $stmt = db()->prepare('SELECT * FROM materials WHERE id=? AND class_id=? AND teacher_id=?');
            $stmt->execute([$materialId, $classId, $user['id']]);
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
                    $stmt = db()->prepare('UPDATE materials SET title=?, body=?, file_path=?, external_link=? WHERE id=? AND teacher_id=?');
                    $stmt->execute([$title, $body ?: null, $filePath, $link ?: null, $materialId, $user['id']]);
                    flash('success', 'Material updated.');
                    redirect('teacher/course.php?id=' . $classId);
                } catch (RuntimeException $e) {
                    $errors[] = $e->getMessage();
                    $action = 'edit_material';
                    $itemId = $materialId;
                }
            }
        }
    } elseif ($postAction === 'delete_material') {
        $materialId = (int) ($_POST['material_id'] ?? 0);
        $stmt = db()->prepare('SELECT * FROM materials WHERE id=? AND class_id=? AND teacher_id=?');
        $stmt->execute([$materialId, $classId, $user['id']]);
        $mat = $stmt->fetch();
        if ($mat) {
            deleteUpload($mat['file_path']);
            db()->prepare('DELETE FROM materials WHERE id=?')->execute([$materialId]);
            flash('success', 'Material deleted.');
        }
        redirect('teacher/course.php?id=' . $classId);
    } elseif ($postAction === 'add_assignment' || $postAction === 'edit_assignment') {
        $title = trim($_POST['title'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');
        $dueDate = trim($_POST['due_date'] ?? '');
        $maxPoints = (float) ($_POST['max_points'] ?? 100);
        $allowLate = isset($_POST['allow_late']) ? 1 : 0;
        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);

        if ($title === '') {
            $errors[] = 'Title is required.';
        }

        if ($postAction === 'add_assignment' && empty($errors)) {
            $stmt = db()->prepare('INSERT INTO assignments (class_id, teacher_id, title, instructions, due_date, max_points, allow_late) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$classId, $user['id'], $title, $instructions ?: null, $dueDate ?: null, $maxPoints, $allowLate]);
            flash('success', 'Assignment created.');
            redirect('teacher/course.php?id=' . $classId);
        } elseif ($postAction === 'edit_assignment' && $assignmentId && empty($errors)) {
            $stmt = db()->prepare('UPDATE assignments SET title=?, instructions=?, due_date=?, max_points=?, allow_late=? WHERE id=? AND class_id=? AND teacher_id=?');
            $stmt->execute([$title, $instructions ?: null, $dueDate ?: null, $maxPoints, $allowLate, $assignmentId, $classId, $user['id']]);
            flash('success', 'Assignment updated.');
            redirect('teacher/course.php?id=' . $classId);
        } else {
            $action = str_replace('add_', 'add_', $postAction) === 'edit_assignment' ? 'edit_assignment' : 'add_assignment';
            if ($assignmentId) {
                $itemId = $assignmentId;
                $action = 'edit_assignment';
            }
        }
    } elseif ($postAction === 'delete_assignment') {
        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
        db()->prepare('DELETE FROM assignments WHERE id=? AND class_id=? AND teacher_id=?')->execute([$assignmentId, $classId, $user['id']]);
        flash('success', 'Assignment deleted.');
        redirect('teacher/course.php?id=' . $classId);
    } elseif ($postAction === 'add_quiz' || $postAction === 'edit_quiz') {
        $title = trim($_POST['title'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');
        $timeLimit = $_POST['time_limit_minutes'] !== '' ? (int) $_POST['time_limit_minutes'] : null;
        $dueDate = trim($_POST['due_date'] ?? '');
        $maxAttempts = max(1, (int) ($_POST['max_attempts'] ?? 1));
        $quizId = (int) ($_POST['quiz_id'] ?? 0);

        if ($title === '') {
            $errors[] = 'Title is required.';
        }

        if ($postAction === 'add_quiz' && empty($errors)) {
            $stmt = db()->prepare('INSERT INTO quizzes (class_id, teacher_id, title, instructions, time_limit_minutes, due_date, max_attempts) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$classId, $user['id'], $title, $instructions ?: null, $timeLimit, $dueDate ?: null, $maxAttempts]);
            $newId = (int) db()->lastInsertId();
            flash('success', 'Quiz created. Add questions next.');
            redirect('teacher/quiz-edit.php?id=' . $newId . '&class_id=' . $classId);
        } elseif ($postAction === 'edit_quiz' && $quizId && empty($errors)) {
            $stmt = db()->prepare('UPDATE quizzes SET title=?, instructions=?, time_limit_minutes=?, due_date=?, max_attempts=? WHERE id=? AND class_id=? AND teacher_id=?');
            $stmt->execute([$title, $instructions ?: null, $timeLimit, $dueDate ?: null, $maxAttempts, $quizId, $classId, $user['id']]);
            flash('success', 'Quiz updated.');
            redirect('teacher/course.php?id=' . $classId);
        } else {
            $action = $quizId ? 'edit_quiz' : 'add_quiz';
            if ($quizId) {
                $itemId = $quizId;
            }
        }
    } elseif ($postAction === 'delete_quiz') {
        $quizId = (int) ($_POST['quiz_id'] ?? 0);
        db()->prepare('DELETE FROM quizzes WHERE id=? AND class_id=? AND teacher_id=?')->execute([$quizId, $classId, $user['id']]);
        flash('success', 'Quiz deleted.');
        redirect('teacher/course.php?id=' . $classId);
    }
}

$editMaterial = null;
$editAssignment = null;
$editQuiz = null;

if ($action === 'edit_material' && $itemId) {
    $stmt = db()->prepare('SELECT * FROM materials WHERE id=? AND class_id=? AND teacher_id=?');
    $stmt->execute([$itemId, $classId, $user['id']]);
    $editMaterial = $stmt->fetch();
}
if ($action === 'edit_assignment' && $itemId) {
    $stmt = db()->prepare('SELECT * FROM assignments WHERE id=? AND class_id=? AND teacher_id=?');
    $stmt->execute([$itemId, $classId, $user['id']]);
    $editAssignment = $stmt->fetch();
    if ($editAssignment && $editAssignment['due_date']) {
        $editAssignment['due_date_local'] = date('Y-m-d\TH:i', strtotime($editAssignment['due_date']));
    }
}
if ($action === 'edit_quiz' && $itemId) {
    $stmt = db()->prepare('SELECT * FROM quizzes WHERE id=? AND class_id=? AND teacher_id=?');
    $stmt->execute([$itemId, $classId, $user['id']]);
    $editQuiz = $stmt->fetch();
    if ($editQuiz && $editQuiz['due_date']) {
        $editQuiz['due_date_local'] = date('Y-m-d\TH:i', strtotime($editQuiz['due_date']));
    }
}

$stmt = db()->prepare('SELECT * FROM materials WHERE class_id=? AND teacher_id=? ORDER BY created_at DESC');
$stmt->execute([$classId, $user['id']]);
$materials = $stmt->fetchAll();

$stmt = db()->prepare('SELECT a.*, (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) AS submission_count FROM assignments a WHERE a.class_id=? AND a.teacher_id=? ORDER BY a.created_at DESC');
$stmt->execute([$classId, $user['id']]);
$assignments = $stmt->fetchAll();

$stmt = db()->prepare('SELECT q.*, (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) AS question_count, (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id AND status != ?) AS attempt_count FROM quizzes q WHERE q.class_id=? AND q.teacher_id=? ORDER BY q.created_at DESC');
$stmt->execute(['in_progress', $classId, $user['id']]);
$quizzes = $stmt->fetchAll();

$activities = [];
foreach ($materials as $m) {
    $activities[] = ['type' => 'material', 'sort' => strtotime($m['created_at']), 'item' => $m];
}
foreach ($assignments as $a) {
    $activities[] = ['type' => 'assignment', 'sort' => strtotime($a['created_at']), 'item' => $a];
}
foreach ($quizzes as $q) {
    $activities[] = ['type' => 'quiz', 'sort' => strtotime($q['created_at']), 'item' => $q];
}
usort($activities, fn ($x, $y) => $y['sort'] <=> $x['sort']);

$classTitle = $class['name'] . ($class['section'] ? ' — Section ' . $class['section'] : '');
$pageTitle = $classTitle;
$pageHeading = $classTitle;
$activeMenu = 'dashboard';
$menuItems = teacherMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'teacher/dashboard.php'],
    ['label' => $class['name'], 'url' => 'teacher/course.php?id=' . $classId],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="course-page-header">
    <div>
        <a href="<?= url('teacher/dashboard.php') ?>" class="course-back-link"><i class="fa-solid fa-arrow-left"></i> Back to courses</a>
        <h2><?= e($class['name']) ?></h2>
        <?php if ($class['section']): ?><p class="course-page-meta">Section <?= e($class['section']) ?></p><?php endif; ?>
        <?php if ($class['academic_year']): ?><p class="course-page-meta"><?= e($class['academic_year']) ?></p><?php endif; ?>
        <?php if ($class['description']): ?><p class="course-page-desc"><?= e($class['description']) ?></p><?php endif; ?>
    </div>
</div>

<div class="course-toolbar panel">
    <h2><i class="fa-solid fa-plus-circle"></i> Add to course</h2>
    <div class="course-add-actions">
        <a href="<?= e($courseUrl . '&action=add_material') ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-file-lines"></i> Material</a>
        <a href="<?= e($courseUrl . '&action=add_assignment') ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen-to-square"></i> Assignment</a>
        <a href="<?= e($courseUrl . '&action=add_quiz') ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-circle-question"></i> Quiz</a>
    </div>
</div>

<?php if ($action === 'add_material' || $editMaterial): ?>
<div class="panel">
    <h2><?= $editMaterial ? 'Edit material' : 'Add material' ?></h2>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="<?= $editMaterial ? 'edit_material' : 'add_material' ?>">
        <?php if ($editMaterial): ?><input type="hidden" name="material_id" value="<?= (int) $editMaterial['id'] ?>"><?php endif; ?>
        <div class="form-group"><label>Title</label><input name="title" class="form-control" value="<?= e($editMaterial['title'] ?? '') ?>" required></div>
        <div class="form-group"><label>Description</label><textarea name="body" class="form-control"><?= e($editMaterial['body'] ?? '') ?></textarea></div>
        <div class="form-group"><label>External link</label><input type="url" name="external_link" class="form-control" value="<?= e($editMaterial['external_link'] ?? '') ?>"></div>
        <div class="form-group">
            <label>File</label>
            <input type="file" name="file" class="form-control">
            <?php if ($editMaterial && $editMaterial['file_path']): ?>
                <small>Current: <a href="<?= e(uploadUrl($editMaterial['file_path'])) ?>" target="_blank">Download</a></small>
            <?php endif; ?>
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="<?= e($courseUrl) ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if ($action === 'add_assignment' || $editAssignment): ?>
<div class="panel">
    <h2><?= $editAssignment ? 'Edit assignment' : 'Create assignment' ?></h2>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="<?= $editAssignment ? 'edit_assignment' : 'add_assignment' ?>">
        <?php if ($editAssignment): ?><input type="hidden" name="assignment_id" value="<?= (int) $editAssignment['id'] ?>"><?php endif; ?>
        <div class="form-group"><label>Title</label><input name="title" class="form-control" value="<?= e($editAssignment['title'] ?? '') ?>" required></div>
        <div class="form-group"><label>Instructions</label><textarea name="instructions" class="form-control"><?= e($editAssignment['instructions'] ?? '') ?></textarea></div>
        <div class="form-row">
            <div class="form-group"><label>Due date</label><input type="datetime-local" name="due_date" class="form-control" value="<?= e($editAssignment['due_date_local'] ?? '') ?>"></div>
            <div class="form-group"><label>Max points</label><input type="number" step="0.01" name="max_points" class="form-control" value="<?= e($editAssignment['max_points'] ?? '100') ?>"></div>
        </div>
        <div class="form-check"><input type="checkbox" name="allow_late" id="allow_late" <?= ($editAssignment['allow_late'] ?? 0) ? 'checked' : '' ?>><label for="allow_late">Allow late submissions</label></div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="<?= e($courseUrl) ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if ($action === 'add_quiz' || $editQuiz): ?>
<div class="panel">
    <h2><?= $editQuiz ? 'Edit quiz' : 'Create quiz' ?></h2>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="<?= $editQuiz ? 'edit_quiz' : 'add_quiz' ?>">
        <?php if ($editQuiz): ?><input type="hidden" name="quiz_id" value="<?= (int) $editQuiz['id'] ?>"><?php endif; ?>
        <div class="form-group"><label>Title</label><input name="title" class="form-control" value="<?= e($editQuiz['title'] ?? '') ?>" required></div>
        <div class="form-group"><label>Instructions</label><textarea name="instructions" class="form-control"><?= e($editQuiz['instructions'] ?? '') ?></textarea></div>
        <div class="form-row">
            <div class="form-group"><label>Time limit (minutes)</label><input type="number" name="time_limit_minutes" class="form-control" value="<?= e($editQuiz['time_limit_minutes'] ?? '') ?>" placeholder="Optional"></div>
            <div class="form-group"><label>Due date</label><input type="datetime-local" name="due_date" class="form-control" value="<?= e($editQuiz['due_date_local'] ?? '') ?>"></div>
            <div class="form-group"><label>Max attempts</label><input type="number" name="max_attempts" class="form-control" value="<?= e($editQuiz['max_attempts'] ?? '1') ?>" min="1"></div>
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">Save</button>
            <?php if ($editQuiz): ?><a href="<?= url('teacher/quiz-edit.php?id=' . $editQuiz['id'] . '&class_id=' . $classId) ?>" class="btn btn-secondary">Manage questions</a><?php endif; ?>
            <a href="<?= e($courseUrl) ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="panel">
    <h2><i class="fa-solid fa-list"></i> Course content</h2>
    <?php if (empty($activities)): ?>
        <div class="empty-state" style="padding:2rem 1rem;">
            <i class="fa-solid fa-folder-open"></i>
            <h3>No activities yet</h3>
            <p>Add materials, assignments, or quizzes for your students.</p>
        </div>
    <?php else: ?>
        <ul class="course-module-list">
            <?php foreach ($activities as $act):
                $item = $act['item'];
                if ($act['type'] === 'material'): ?>
            <li class="course-module-item">
                <div class="course-module-icon material"><i class="fa-solid fa-file-lines"></i></div>
                <div class="course-module-body">
                    <strong><?= e($item['title']) ?></strong>
                    <span class="course-module-type">Material</span>
                    <?php if ($item['body']): ?><p class="text-muted"><?= e(mb_strimwidth($item['body'], 0, 120, '...')) ?></p><?php endif; ?>
                    <div class="course-module-meta">
                        <?php if ($item['file_path']): ?><a href="<?= e(uploadUrl($item['file_path'])) ?>" target="_blank"><i class="fa-solid fa-download"></i> File</a><?php endif; ?>
                        <?php if ($item['external_link']): ?><a href="<?= e($item['external_link']) ?>" target="_blank"><i class="fa-solid fa-link"></i> Link</a><?php endif; ?>
                        <span><?= formatDate($item['created_at'], 'M j, Y') ?></span>
                    </div>
                </div>
                <div class="course-module-actions">
                    <a href="<?= e($courseUrl . '&action=edit_material&item_id=' . $item['id']) ?>" class="btn btn-sm btn-secondary">Edit</a>
                    <form method="post" onsubmit="return confirm('Delete this material?')"><?= csrfField() ?><input type="hidden" name="form_action" value="delete_material"><input type="hidden" name="material_id" value="<?= (int) $item['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
                </div>
            </li>
                <?php elseif ($act['type'] === 'assignment'): ?>
            <li class="course-module-item">
                <div class="course-module-icon assignment"><i class="fa-solid fa-pen-to-square"></i></div>
                <div class="course-module-body">
                    <strong><?= e($item['title']) ?></strong>
                    <span class="course-module-type">Assignment</span>
                    <div class="course-module-meta">
                        <span>Due: <?= formatDate($item['due_date']) ?></span>
                        <span><?= e($item['max_points']) ?> pts</span>
                        <span><?= (int) $item['submission_count'] ?> submission(s)</span>
                    </div>
                </div>
                <div class="course-module-actions">
                    <a href="<?= url('teacher/grade-submissions.php?assignment_id=' . $item['id']) ?>" class="btn btn-sm btn-primary">Grade</a>
                    <a href="<?= e($courseUrl . '&action=edit_assignment&item_id=' . $item['id']) ?>" class="btn btn-sm btn-secondary">Edit</a>
                    <form method="post" onsubmit="return confirm('Delete this assignment?')"><?= csrfField() ?><input type="hidden" name="form_action" value="delete_assignment"><input type="hidden" name="assignment_id" value="<?= (int) $item['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
                </div>
            </li>
                <?php else: ?>
            <li class="course-module-item">
                <div class="course-module-icon quiz"><i class="fa-solid fa-circle-question"></i></div>
                <div class="course-module-body">
                    <strong><?= e($item['title']) ?></strong>
                    <span class="course-module-type">Quiz</span>
                    <div class="course-module-meta">
                        <span><?= (int) $item['question_count'] ?> question(s)</span>
                        <span><?= (int) $item['attempt_count'] ?> attempt(s)</span>
                        <span>Due: <?= formatDate($item['due_date']) ?></span>
                    </div>
                </div>
                <div class="course-module-actions">
                    <a href="<?= url('teacher/quiz-edit.php?id=' . $item['id'] . '&class_id=' . $classId) ?>" class="btn btn-sm btn-primary">Questions</a>
                    <a href="<?= url('teacher/quiz-attempts.php?quiz_id=' . $item['id']) ?>" class="btn btn-sm btn-secondary">Attempts</a>
                    <a href="<?= e($courseUrl . '&action=edit_quiz&item_id=' . $item['id']) ?>" class="btn btn-sm btn-secondary">Edit</a>
                    <form method="post" onsubmit="return confirm('Delete this quiz?')"><?= csrfField() ?><input type="hidden" name="form_action" value="delete_quiz"><input type="hidden" name="quiz_id" value="<?= (int) $item['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
                </div>
            </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
