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
$sectionIdParam = (int) ($_GET['section_id'] ?? 0);
$errors = [];
$courseUrl = teacherCourseUrl($classId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['form_action'] ?? '';

    if ($postAction === 'remove_class_cover') {
        if (!empty($class['cover_image'])) {
            deleteUpload($class['cover_image']);
            db()->prepare('UPDATE classes SET cover_image = NULL WHERE id = ? AND school_id = ?')->execute([$classId, schoolId()]);
            clearClassCoverCaches($classId, schoolId());
        }
        flash('success', 'Course cover removed.');
        redirect('teacher/course.php?id=' . $classId . '&settings=1');
    } elseif ($postAction === 'upload_class_cover') {
        try {
            $newPath = uploadClassCover($_FILES['cover'] ?? [], schoolId(), $classId);
            if ($newPath === null) {
                $errors[] = 'Please choose an image file to upload.';
            } else {
                if (!empty($class['cover_image'])) {
                    deleteUpload($class['cover_image']);
                }
                db()->prepare('UPDATE classes SET cover_image = ? WHERE id = ? AND school_id = ?')->execute([$newPath, $classId, schoolId()]);
                clearClassCoverCaches($classId, schoolId());
                flash('success', 'Course cover updated.');
                redirect('teacher/course.php?id=' . $classId . '&settings=1');
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    } elseif ($postAction === 'add_material' || $postAction === 'edit_material') {
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
                $sectionId = CourseSectionRepository::resolveSectionId((int) ($_POST['section_id'] ?? 0), $classId);
                $stmt = db()->prepare('INSERT INTO materials (class_id, section_id, teacher_id, title, body, file_path, external_link) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$classId, $sectionId, $user['id'], $title, $body ?: null, $filePath, $link ?: null]);
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
                    $stmt = db()->prepare('UPDATE materials SET title=?, body=?, file_path=?, external_link=?, section_id=? WHERE id=? AND teacher_id=?');
                    $sectionId = CourseSectionRepository::resolveSectionId((int) ($_POST['section_id'] ?? 0), $classId);
                    $stmt->execute([$title, $body ?: null, $filePath, $link ?: null, $sectionId, $materialId, $user['id']]);
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
            $sectionId = CourseSectionRepository::resolveSectionId((int) ($_POST['section_id'] ?? 0), $classId);
            $stmt = db()->prepare('INSERT INTO assignments (class_id, section_id, teacher_id, title, instructions, due_date, max_points, allow_late) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$classId, $sectionId, $user['id'], $title, $instructions ?: null, $dueDate ?: null, $maxPoints, $allowLate]);
            flash('success', 'Assignment created.');
            redirect('teacher/course.php?id=' . $classId);
        } elseif ($postAction === 'edit_assignment' && $assignmentId && empty($errors)) {
            $sectionId = CourseSectionRepository::resolveSectionId((int) ($_POST['section_id'] ?? 0), $classId);
            $stmt = db()->prepare('UPDATE assignments SET title=?, instructions=?, due_date=?, max_points=?, allow_late=?, section_id=? WHERE id=? AND class_id=? AND teacher_id=?');
            $stmt->execute([$title, $instructions ?: null, $dueDate ?: null, $maxPoints, $allowLate, $sectionId, $assignmentId, $classId, $user['id']]);
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
            $sectionId = CourseSectionRepository::resolveSectionId((int) ($_POST['section_id'] ?? 0), $classId);
            $stmt = db()->prepare('INSERT INTO quizzes (class_id, section_id, teacher_id, title, instructions, time_limit_minutes, due_date, max_attempts) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$classId, $sectionId, $user['id'], $title, $instructions ?: null, $timeLimit, $dueDate ?: null, $maxAttempts]);
            $newId = (int) db()->lastInsertId();
            flash('success', 'Quiz created. Add questions next.');
            redirect('teacher/quiz-edit.php?id=' . $newId . '&class_id=' . $classId);
        } elseif ($postAction === 'edit_quiz' && $quizId && empty($errors)) {
            $sectionId = CourseSectionRepository::resolveSectionId((int) ($_POST['section_id'] ?? 0), $classId);
            $stmt = db()->prepare('UPDATE quizzes SET title=?, instructions=?, time_limit_minutes=?, due_date=?, max_attempts=?, section_id=? WHERE id=? AND class_id=? AND teacher_id=?');
            $stmt->execute([$title, $instructions ?: null, $timeLimit, $dueDate ?: null, $maxAttempts, $sectionId, $quizId, $classId, $user['id']]);
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
    } elseif ($postAction === 'add_section' || $postAction === 'edit_section') {
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title === '') {
            $errors[] = 'Section title is required.';
        } elseif ($postAction === 'add_section' && empty($errors)) {
            CourseSectionRepository::create($classId, $title, $description ?: null);
            flash('success', 'Lesson section added.');
            redirect('teacher/course.php?id=' . $classId);
        } elseif ($postAction === 'edit_section' && $sectionId && empty($errors)) {
            if (CourseSectionRepository::update($sectionId, $classId, $title, $description ?: null)) {
                flash('success', 'Lesson section updated.');
            } else {
                flash('error', 'Could not update section.');
            }
            redirect('teacher/course.php?id=' . $classId);
        } else {
            $action = $postAction === 'edit_section' ? 'edit_section' : 'add_section';
            if ($sectionId) {
                $sectionIdParam = $sectionId;
            }
        }
    } elseif ($postAction === 'delete_section') {
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        if (CourseSectionRepository::delete($sectionId, $classId)) {
            flash('success', 'Lesson section deleted.');
        } else {
            flash('error', 'Could not delete section.');
        }
        redirect('teacher/course.php?id=' . $classId);
    } elseif ($postAction === 'move_activity') {
        $itemType = $_POST['item_type'] ?? '';
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $targetSection = ($_POST['section_id'] ?? '') !== '' ? (int) $_POST['section_id'] : null;
        if (CourseSectionRepository::moveItem($itemType, $itemId, $classId, $targetSection)) {
            flash('success', 'Activity moved.');
        } else {
            flash('error', 'Could not move activity.');
        }
        redirect('teacher/course.php?id=' . $classId);
    }
}

$editMaterial = null;
$editAssignment = null;
$editQuiz = null;
$editSection = null;

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
if ($action === 'edit_section' && $sectionIdParam) {
    $editSection = CourseSectionRepository::get($sectionIdParam, $classId);
    if (!$editSection) {
        flash('error', 'Section not found.');
        redirect('teacher/course.php?id=' . $classId);
    }
}

$sections = CourseSectionRepository::forClass($classId);
$courseContent = CourseSectionRepository::loadCourseContent($classId, null, (int) $user['id']);
$materialCount = $courseContent['material_count'];
$assignmentCount = $courseContent['assignment_count'];
$quizCount = $courseContent['quiz_count'];
$activityCount = $courseContent['activity_count'];
$defaultFormSectionId = $sectionIdParam ?: ($sections[0]['id'] ?? null);
$courseInitial = strtoupper(mb_substr($class['name'], 0, 1));
$formOpen = in_array($action, ['add_material', 'edit_material', 'add_assignment', 'edit_assignment', 'add_quiz', 'edit_quiz', 'add_section', 'edit_section'], true);
$formType = '';
if (str_contains($action, 'material')) {
    $formType = 'material';
} elseif (str_contains($action, 'assignment')) {
    $formType = 'assignment';
} elseif (str_contains($action, 'quiz')) {
    $formType = 'quiz';
} elseif (str_contains($action, 'section')) {
    $formType = 'section';
}
$coverPreviewUrl = classCoverImageUrl($class);

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
?>

<div class="course-view">
    <section class="course-hero<?= classHasCustomCover($class) ? ' course-hero--custom-cover' : '' ?>" style="background-image: url('<?= e($coverPreviewUrl) ?>')" data-preview-cover>
        <div class="course-hero-overlay" aria-hidden="true"></div>
        <button type="button" class="course-hero-settings-btn" id="openCourseSettings" aria-label="Class settings" title="Class settings">
            <i class="fa-solid fa-gear" aria-hidden="true"></i>
        </button>
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

    <div class="course-builder">
        <div class="course-builder-group">
            <span class="course-builder-label">Add content</span>
            <div class="course-builder-actions">
                <a href="<?= teacherCourseUrl($classId, 'action=add_material') ?>" class="course-builder-btn course-builder-btn--material<?= $formType === 'material' ? ' is-active' : '' ?>">
                    <i class="fa-solid fa-file-lines"></i> Material
                </a>
                <a href="<?= teacherCourseUrl($classId, 'action=add_assignment') ?>" class="course-builder-btn course-builder-btn--assignment<?= $formType === 'assignment' ? ' is-active' : '' ?>">
                    <i class="fa-solid fa-pen-to-square"></i> Assignment
                </a>
                <a href="<?= teacherCourseUrl($classId, 'action=add_quiz') ?>" class="course-builder-btn course-builder-btn--quiz<?= $formType === 'quiz' ? ' is-active' : '' ?>">
                    <i class="fa-solid fa-circle-question"></i> Quiz
                </a>
            </div>
        </div>
        <a href="<?= teacherCourseUrl($classId, 'action=add_section') ?>" class="course-builder-lesson<?= $formType === 'section' ? ' is-active' : '' ?>">
            <i class="fa-solid fa-folder-plus"></i> New lesson
        </a>
    </div>

    <div class="course-main course-main--full">
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
                        <?php elseif ($formType === 'section'): ?>
                            <span class="course-form-sheet-badge section"><i class="fa-solid fa-folder-tree"></i> Lesson section</span>
                            <h2><?= $editSection ? 'Edit section' : 'New lesson section' ?></h2>
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
                    <?php if (!empty($sections)): ?>
                    <div class="form-group">
                        <label>Lesson section</label>
                        <select name="section_id" class="form-control">
                            <?= courseSectionOptions($sections, (int) ($editMaterial['section_id'] ?? $defaultFormSectionId ?: 0) ?: null) ?>
                        </select>
                    </div>
                    <?php endif; ?>
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
                    <?php if (!empty($sections)): ?>
                    <div class="form-group">
                        <label>Lesson section</label>
                        <select name="section_id" class="form-control">
                            <?= courseSectionOptions($sections, (int) ($editAssignment['section_id'] ?? $defaultFormSectionId ?: 0) ?: null) ?>
                        </select>
                    </div>
                    <?php endif; ?>
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
                    <?php if (!empty($sections)): ?>
                    <div class="form-group">
                        <label>Lesson section</label>
                        <select name="section_id" class="form-control">
                            <?= courseSectionOptions($sections, (int) ($editQuiz['section_id'] ?? $defaultFormSectionId ?: 0) ?: null) ?>
                        </select>
                    </div>
                    <?php endif; ?>
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

                <?php if ($action === 'add_section' || $editSection): ?>
                <form method="post" class="course-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="form_action" value="<?= $editSection ? 'edit_section' : 'add_section' ?>">
                    <?php if ($editSection): ?><input type="hidden" name="section_id" value="<?= (int) $editSection['id'] ?>"><?php endif; ?>
                    <div class="form-group">
                        <label>Section title</label>
                        <input name="title" class="form-control" value="<?= e($editSection['title'] ?? '') ?>" required placeholder="e.g. Lesson 1 — Introduction">
                    </div>
                    <div class="form-group">
                        <label>Description <span class="text-muted">(optional)</span></label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief overview of this lesson"><?= e($editSection['description'] ?? '') ?></textarea>
                    </div>
                    <div class="course-form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save section</button>
                        <a href="<?= e($courseUrl) ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <section class="course-content-section">
                <?php
                $lessonCount = count($sections);
                $summaryParts = [];
                if ($activityCount > 0) {
                    $summaryParts[] = $activityCount . ' activit' . ($activityCount !== 1 ? 'ies' : 'y');
                }
                if ($lessonCount > 0) {
                    $summaryParts[] = $lessonCount . ' lesson' . ($lessonCount !== 1 ? 's' : '');
                }
                $contentSummary = $summaryParts ? implode(' · ', $summaryParts) : 'Start building your course';
                ?>
                <div class="course-content-header">
                    <div class="course-content-intro">
                        <h2>Course content</h2>
                        <p class="course-content-summary"><?= e($contentSummary) ?></p>
                    </div>
                </div>

                <?php if ($activityCount === 0 && empty($sections)): ?>
                <div class="course-empty">
                    <div class="course-empty-icon"><i class="fa-solid fa-folder-open"></i></div>
                    <h3>Build your course</h3>
                    <p>Create lesson sections (e.g. Lesson 1, Lesson 2), then add materials, assignments, and quizzes to each section.</p>
                    <div class="course-empty-actions">
                        <a href="<?= teacherCourseUrl($classId, 'action=add_section') ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-folder-plus"></i> Add first section</a>
                        <a href="<?= teacherCourseUrl($classId, 'action=add_material') ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-file-lines"></i> Add material</a>
                    </div>
                </div>
                <?php else: ?>
                <div class="course-lessons">
                    <?php renderCourseLessonSections($courseContent, $classId, 'teacher', $sections); ?>
                </div>
                <?php endif; ?>
            </section>
    </div>
</div>

<div class="activity-modal-overlay course-settings-overlay" id="courseSettingsModal" hidden>
    <div class="activity-modal course-settings-modal" role="dialog" aria-modal="true" aria-labelledby="courseSettingsTitle">
        <button type="button" class="activity-modal-close" id="closeCourseSettingsModal" aria-label="Close">&times;</button>
        <h3 id="courseSettingsTitle"><i class="fa-solid fa-gear"></i> Class settings</h3>
        <p class="course-settings-modal-intro">Manage how this class appears on course cards.</p>
        <?php require __DIR__ . '/../includes/layout/course_class_settings.php'; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
