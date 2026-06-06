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

$classTitle = classDisplayName($class);
$pageTitle = $classTitle;
$pageHeading = $classTitle;
$hidePageHeader = true;
$activeMenu = 'classes';
$menuItems = teacherMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'teacher/dashboard.php'],
    ['label' => $class['name'], 'url' => 'teacher/course.php?id=' . $classId],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';

$materialCount = count($materials);
$assignmentCount = count($assignments);
$quizCount = count($quizzes);
$activityCount = count($activities);
$courseInitial = strtoupper(mb_substr($class['name'], 0, 1));
$formOpen = in_array($action, ['add_material', 'edit_material', 'add_assignment', 'edit_assignment', 'add_quiz', 'edit_quiz'], true);
$formType = '';
if (str_contains($action, 'material')) {
    $formType = 'material';
} elseif (str_contains($action, 'assignment')) {
    $formType = 'assignment';
} elseif (str_contains($action, 'quiz')) {
    $formType = 'quiz';
}

$groupedByType = ['material' => [], 'assignment' => [], 'quiz' => []];
foreach ($activities as $act) {
    $groupedByType[$act['type']][] = $act;
}
$defaultOpenSection = !empty($activities) ? $activities[0]['type'] : 'material';
$sectionMeta = [
    'material' => ['label' => 'Materials', 'icon' => 'fa-file-lines'],
    'assignment' => ['label' => 'Assignments', 'icon' => 'fa-pen-to-square'],
    'quiz' => ['label' => 'Quizzes', 'icon' => 'fa-circle-question'],
];
?>

<div class="course-view">
    <section class="course-hero">
        <div class="course-hero-main">
            <a href="<?= url('teacher/dashboard.php') ?>" class="course-back-link"><i class="fa-solid fa-arrow-left"></i> My courses</a>
            <div class="course-hero-title-row">
                <div class="course-hero-avatar" aria-hidden="true"><?= e($courseInitial) ?></div>
                <div>
                    <h1 class="course-hero-title"><?= e($class['name']) ?></h1>
                    <div class="course-hero-tags">
                        <?php if ($class['group_name']): ?><span class="course-tag"><i class="fa-solid fa-layer-group"></i> <?= e($class['group_name']) ?></span><?php endif; ?>
                        <?php if ($class['group_academic_year']): ?><span class="course-tag"><i class="fa-solid fa-calendar"></i> <?= e($class['group_academic_year']) ?></span><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if ($class['description']): ?><p class="course-hero-desc"><?= e($class['description']) ?></p><?php endif; ?>
        </div>
        <div class="course-hero-stats">
            <div class="course-stat"><strong><?= $activityCount ?></strong><span>Activities</span></div>
            <div class="course-stat"><strong><?= $materialCount ?></strong><span>Materials</span></div>
            <div class="course-stat"><strong><?= $assignmentCount ?></strong><span>Assignments</span></div>
            <div class="course-stat"><strong><?= $quizCount ?></strong><span>Quizzes</span></div>
        </div>
    </section>

    <div class="course-layout">
        <aside class="course-sidebar">
            <h2 class="course-sidebar-title">Add to course</h2>
            <p class="course-sidebar-hint">Choose what students will see in this class.</p>
            <div class="activity-picker">
                <a href="<?= teacherCourseUrl($classId, 'action=add_material') ?>" class="activity-picker-card<?= $formType === 'material' ? ' is-active' : '' ?>">
                    <span class="activity-picker-icon material"><i class="fa-solid fa-file-lines"></i></span>
                    <span class="activity-picker-label">Material</span>
                    <span class="activity-picker-desc">Files, links &amp; notes</span>
                </a>
                <a href="<?= teacherCourseUrl($classId, 'action=add_assignment') ?>" class="activity-picker-card<?= $formType === 'assignment' ? ' is-active' : '' ?>">
                    <span class="activity-picker-icon assignment"><i class="fa-solid fa-pen-to-square"></i></span>
                    <span class="activity-picker-label">Assignment</span>
                    <span class="activity-picker-desc">Due dates &amp; submissions</span>
                </a>
                <a href="<?= teacherCourseUrl($classId, 'action=add_quiz') ?>" class="activity-picker-card<?= $formType === 'quiz' ? ' is-active' : '' ?>">
                    <span class="activity-picker-icon quiz"><i class="fa-solid fa-circle-question"></i></span>
                    <span class="activity-picker-label">Quiz</span>
                    <span class="activity-picker-desc">Questions &amp; grading</span>
                </a>
            </div>
        </aside>

        <div class="course-main">
            <?php if ($formOpen): ?>
            <section class="course-form-sheet course-form-sheet--<?= e($formType) ?>" id="courseFormPanel">
                <div class="course-form-sheet-header">
                    <div>
                        <?php if ($formType === 'material'): ?>
                            <span class="course-form-sheet-badge material"><i class="fa-solid fa-file-lines"></i> Material</span>
                            <h2><?= $editMaterial ? 'Edit material' : 'New material' ?></h2>
                        <?php elseif ($formType === 'assignment'): ?>
                            <span class="course-form-sheet-badge assignment"><i class="fa-solid fa-pen-to-square"></i> Assignment</span>
                            <h2><?= $editAssignment ? 'Edit assignment' : 'New assignment' ?></h2>
                        <?php else: ?>
                            <span class="course-form-sheet-badge quiz"><i class="fa-solid fa-circle-question"></i> Quiz</span>
                            <h2><?= $editQuiz ? 'Edit quiz' : 'New quiz' ?></h2>
                        <?php endif; ?>
                    </div>
                    <a href="<?= e($courseUrl) ?>" class="course-form-close" aria-label="Close"><i class="fa-solid fa-xmark"></i></a>
                </div>
                <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

                <?php if ($action === 'add_material' || $editMaterial): ?>
                <form method="post" enctype="multipart/form-data" class="course-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="form_action" value="<?= $editMaterial ? 'edit_material' : 'add_material' ?>">
                    <?php if ($editMaterial): ?><input type="hidden" name="material_id" value="<?= (int) $editMaterial['id'] ?>"><?php endif; ?>
                    <div class="form-group"><label>Title</label><input name="title" class="form-control" value="<?= e($editMaterial['title'] ?? '') ?>" required placeholder="e.g. Week 1 lecture slides"></div>
                    <div class="form-group"><label>Description</label><textarea name="body" class="form-control" rows="3" placeholder="Optional instructions for students"><?= e($editMaterial['body'] ?? '') ?></textarea></div>
                    <div class="form-row">
                        <div class="form-group"><label>External link</label><input type="url" name="external_link" class="form-control" value="<?= e($editMaterial['external_link'] ?? '') ?>" placeholder="https://"></div>
                        <div class="form-group">
                            <label>Upload file</label>
                            <input type="file" name="file" class="form-control">
                            <?php if ($editMaterial && $editMaterial['file_path']): ?>
                                <small class="text-muted">Current file: <a href="<?= e(uploadUrl($editMaterial['file_path'])) ?>" target="_blank">Download</a></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="course-form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save material</button>
                        <a href="<?= e($courseUrl) ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
                <?php endif; ?>

                <?php if ($action === 'add_assignment' || $editAssignment): ?>
                <form method="post" class="course-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="form_action" value="<?= $editAssignment ? 'edit_assignment' : 'add_assignment' ?>">
                    <?php if ($editAssignment): ?><input type="hidden" name="assignment_id" value="<?= (int) $editAssignment['id'] ?>"><?php endif; ?>
                    <div class="form-group"><label>Title</label><input name="title" class="form-control" value="<?= e($editAssignment['title'] ?? '') ?>" required placeholder="e.g. Research paper"></div>
                    <div class="form-group"><label>Instructions</label><textarea name="instructions" class="form-control" rows="4" placeholder="What should students submit?"><?= e($editAssignment['instructions'] ?? '') ?></textarea></div>
                    <div class="form-row">
                        <div class="form-group"><label>Due date</label><input type="datetime-local" name="due_date" class="form-control" value="<?= e($editAssignment['due_date_local'] ?? '') ?>"></div>
                        <div class="form-group"><label>Max points</label><input type="number" step="0.01" name="max_points" class="form-control" value="<?= e($editAssignment['max_points'] ?? '100') ?>"></div>
                    </div>
                    <div class="form-check"><input type="checkbox" name="allow_late" id="allow_late" <?= ($editAssignment['allow_late'] ?? 0) ? 'checked' : '' ?>><label for="allow_late">Allow late submissions</label></div>
                    <div class="course-form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save assignment</button>
                        <a href="<?= e($courseUrl) ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
                <?php endif; ?>

                <?php if ($action === 'add_quiz' || $editQuiz): ?>
                <form method="post" class="course-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="form_action" value="<?= $editQuiz ? 'edit_quiz' : 'add_quiz' ?>">
                    <?php if ($editQuiz): ?><input type="hidden" name="quiz_id" value="<?= (int) $editQuiz['id'] ?>"><?php endif; ?>
                    <div class="form-group"><label>Title</label><input name="title" class="form-control" value="<?= e($editQuiz['title'] ?? '') ?>" required placeholder="e.g. Midterm quiz"></div>
                    <div class="form-group"><label>Instructions</label><textarea name="instructions" class="form-control" rows="3"><?= e($editQuiz['instructions'] ?? '') ?></textarea></div>
                    <div class="form-row">
                        <div class="form-group"><label>Time limit (min)</label><input type="number" name="time_limit_minutes" class="form-control" value="<?= e($editQuiz['time_limit_minutes'] ?? '') ?>" placeholder="Optional"></div>
                        <div class="form-group"><label>Due date</label><input type="datetime-local" name="due_date" class="form-control" value="<?= e($editQuiz['due_date_local'] ?? '') ?>"></div>
                        <div class="form-group"><label>Max attempts</label><input type="number" name="max_attempts" class="form-control" value="<?= e($editQuiz['max_attempts'] ?? '1') ?>" min="1"></div>
                    </div>
                    <div class="course-form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> <?= $editQuiz ? 'Save quiz' : 'Create &amp; add questions' ?></button>
                        <?php if ($editQuiz): ?><a href="<?= url('teacher/quiz-edit.php?id=' . $editQuiz['id'] . '&class_id=' . $classId) ?>" class="btn btn-secondary">Manage questions</a><?php endif; ?>
                        <a href="<?= e($courseUrl) ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <section class="course-content-section">
                <div class="course-content-header">
                    <h2><i class="fa-solid fa-book-open"></i> Course content</h2>
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <?php if ($activityCount > 0): ?><span class="course-content-count"><?= $activityCount ?> item<?= $activityCount !== 1 ? 's' : '' ?></span><?php endif; ?>
                        <button type="button" class="btn btn-primary btn-sm" id="openActivityModal"><i class="fa-solid fa-plus"></i> Add activity</button>
                    </div>
                </div>

                <?php if (empty($activities)): ?>
                <div class="course-empty">
                    <div class="course-empty-icon"><i class="fa-solid fa-folder-open"></i></div>
                    <h3>Build your course</h3>
                    <p>Start by adding a material, assignment, or quiz. Everything you add appears here for enrolled students.</p>
                    <div class="course-empty-actions">
                        <a href="<?= teacherCourseUrl($classId, 'action=add_material') ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-file-lines"></i> Add material</a>
                        <a href="<?= teacherCourseUrl($classId, 'action=add_assignment') ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen-to-square"></i> Add assignment</a>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($sectionMeta as $sectionKey => $meta):
                    if (empty($groupedByType[$sectionKey])) continue;
                    $isOpen = $sectionKey === $defaultOpenSection;
                ?>
                <div class="course-section<?= $isOpen ? ' is-open' : '' ?>" data-section="<?= e($sectionKey) ?>">
                    <button type="button" class="course-section-header" data-accordion-btn>
                        <span><i class="fa-solid <?= e($meta['icon']) ?> section-icon"></i><?= e($meta['label']) ?></span>
                        <span class="section-count"><?= count($groupedByType[$sectionKey]) ?></span>
                        <i class="fa-solid fa-chevron-down section-chevron"></i>
                    </button>
                    <div class="course-section-body">
                <div class="activity-list">
                    <?php foreach ($groupedByType[$sectionKey] as $act):
                        $item = $act['item'];
                        if ($act['type'] === 'material'): ?>
                    <article class="activity-card activity-card--material">
                        <div class="activity-card-icon"><i class="fa-solid fa-file-lines"></i></div>
                        <div class="activity-card-body">
                            <span class="activity-card-type">Material</span>
                            <h3><?= e($item['title']) ?></h3>
                            <?php if ($item['body']): ?><p><?= e(mb_strimwidth($item['body'], 0, 140, '…')) ?></p><?php endif; ?>
                            <div class="activity-card-meta">
                                <?php if ($item['file_path']): ?><a href="<?= e(uploadUrl($item['file_path'])) ?>" target="_blank" class="activity-meta-chip"><i class="fa-solid fa-download"></i> Download</a><?php endif; ?>
                                <?php if ($item['external_link']): ?><a href="<?= e($item['external_link']) ?>" target="_blank" class="activity-meta-chip"><i class="fa-solid fa-link"></i> Open link</a><?php endif; ?>
                                <span class="activity-meta-chip muted"><i class="fa-regular fa-clock"></i> <?= formatDate($item['created_at'], 'M j, Y') ?></span>
                            </div>
                        </div>
                        <div class="activity-card-actions">
                            <a href="<?= teacherCourseUrl($classId, 'action=edit_material&item_id=' . $item['id']) ?>" class="btn btn-sm btn-secondary" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            <form method="post" data-confirm="Delete this material?"><?= csrfField() ?><input type="hidden" name="form_action" value="delete_material"><input type="hidden" name="material_id" value="<?= (int) $item['id'] ?>"><button class="btn btn-sm btn-danger" title="Delete"><i class="fa-solid fa-trash"></i></button></form>
                        </div>
                    </article>
                        <?php elseif ($act['type'] === 'assignment'): ?>
                    <article class="activity-card activity-card--assignment">
                        <div class="activity-card-icon"><i class="fa-solid fa-pen-to-square"></i></div>
                        <div class="activity-card-body">
                            <span class="activity-card-type">Assignment</span>
                            <h3><?= e($item['title']) ?></h3>
                            <div class="activity-card-meta">
                                <span class="activity-meta-chip"><i class="fa-regular fa-calendar"></i> Due <?= formatDate($item['due_date'], 'M j, Y') ?></span>
                                <span class="activity-meta-chip"><i class="fa-solid fa-star"></i> <?= e($item['max_points']) ?> pts</span>
                                <span class="activity-meta-chip"><i class="fa-solid fa-inbox"></i> <?= (int) $item['submission_count'] ?> submitted</span>
                            </div>
                        </div>
                        <div class="activity-card-actions">
                            <a href="<?= url('teacher/grade-submissions.php?assignment_id=' . $item['id']) ?>" class="btn btn-sm btn-primary">Grade</a>
                            <a href="<?= teacherCourseUrl($classId, 'action=edit_assignment&item_id=' . $item['id']) ?>" class="btn btn-sm btn-secondary" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            <form method="post" data-confirm="Delete this assignment?"><?= csrfField() ?><input type="hidden" name="form_action" value="delete_assignment"><input type="hidden" name="assignment_id" value="<?= (int) $item['id'] ?>"><button class="btn btn-sm btn-danger" title="Delete"><i class="fa-solid fa-trash"></i></button></form>
                        </div>
                    </article>
                        <?php else: ?>
                    <article class="activity-card activity-card--quiz">
                        <div class="activity-card-icon"><i class="fa-solid fa-circle-question"></i></div>
                        <div class="activity-card-body">
                            <span class="activity-card-type">Quiz</span>
                            <h3><?= e($item['title']) ?></h3>
                            <div class="activity-card-meta">
                                <span class="activity-meta-chip"><i class="fa-solid fa-list"></i> <?= (int) $item['question_count'] ?> questions</span>
                                <span class="activity-meta-chip"><i class="fa-solid fa-users"></i> <?= (int) $item['attempt_count'] ?> attempts</span>
                                <span class="activity-meta-chip"><i class="fa-regular fa-calendar"></i> Due <?= formatDate($item['due_date'], 'M j, Y') ?></span>
                            </div>
                        </div>
                        <div class="activity-card-actions">
                            <a href="<?= url('teacher/quiz-edit.php?id=' . $item['id'] . '&class_id=' . $classId) ?>" class="btn btn-sm btn-primary">Questions</a>
                            <a href="<?= url('teacher/quiz-attempts.php?quiz_id=' . $item['id']) ?>" class="btn btn-sm btn-secondary">Attempts</a>
                            <a href="<?= teacherCourseUrl($classId, 'action=edit_quiz&item_id=' . $item['id']) ?>" class="btn btn-sm btn-secondary" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            <form method="post" data-confirm="Delete this quiz?"><?= csrfField() ?><input type="hidden" name="form_action" value="delete_quiz"><input type="hidden" name="quiz_id" value="<?= (int) $item['id'] ?>"><button class="btn btn-sm btn-danger" title="Delete"><i class="fa-solid fa-trash"></i></button></form>
                        </div>
                    </article>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>

<div class="activity-modal-overlay" id="activityModal" hidden>
    <div class="activity-modal">
        <button type="button" class="activity-modal-close" id="closeActivityModal" aria-label="Close">&times;</button>
        <h3>Add to course</h3>
        <p>Choose what students will see in this class.</p>
        <div class="activity-modal-options">
            <a href="<?= teacherCourseUrl($classId, 'action=add_material') ?>" class="activity-modal-option">
                <span class="activity-picker-icon material"><i class="fa-solid fa-file-lines"></i></span>
                <span><strong>Material</strong><br><small>Files, links &amp; notes</small></span>
            </a>
            <a href="<?= teacherCourseUrl($classId, 'action=add_assignment') ?>" class="activity-modal-option">
                <span class="activity-picker-icon assignment"><i class="fa-solid fa-pen-to-square"></i></span>
                <span><strong>Assignment</strong><br><small>Due dates &amp; submissions</small></span>
            </a>
            <a href="<?= teacherCourseUrl($classId, 'action=add_quiz') ?>" class="activity-modal-option">
                <span class="activity-picker-icon quiz"><i class="fa-solid fa-circle-question"></i></span>
                <span><strong>Quiz</strong><br><small>Questions &amp; grading</small></span>
            </a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
