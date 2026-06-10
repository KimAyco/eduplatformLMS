<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('teacher');
requireSchoolActive();

$user = currentUser();
$classId = (int) ($_GET['id'] ?? $_POST['class_id'] ?? 0);
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

if ($action === 'edit_quiz' && $itemId) {
    redirect(quizEditUrl($itemId, $classId, 'settings'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['form_action'] ?? '';
    $wizQuizId = (int) ($_POST['quiz_id'] ?? $_GET['quiz'] ?? 0);
    if ($wizQuizId && handleQuizWizardPost($postAction, $wizQuizId, $classId, (int) $user['id'], $errors)) {
        // Quiz wizard action handled, or validation errors collected for re-render.
    } elseif ($postAction === 'remove_class_cover') {
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
    } elseif ($postAction === 'share_material_to_library') {
        $materialId = (int) ($_POST['material_id'] ?? 0);
        $mat = MaterialRepository::findForTeacher($materialId, (int) $user['id']);
        if (!$mat || (int) $mat['class_id'] !== $classId) {
            flash('error', 'Material not found.');
        } elseif (!canSubmitMaterialToLibrary($mat, (int) $user['id'])) {
            flash('error', 'This material cannot be shared to the library.');
        } else {
            try {
                LibraryResourceRepository::createFromMaterial(schoolId(), (int) $user['id'], $mat, [
                    'description' => trim($_POST['description'] ?? '') ?: ($mat['body'] ?? null),
                    'resource_kind' => $_POST['resource_kind'] ?? 'other',
                    'subject_id' => (int) ($_POST['subject_id'] ?? 0) ?: null,
                    'audience' => ($_POST['audience'] ?? 'all') === 'teachers' ? 'teachers' : 'all',
                ]);
                flash('success', 'Submitted to the Virtual Library for admin approval.');
            } catch (InvalidArgumentException $e) {
                flash('error', $e->getMessage());
            }
        }
        redirect('teacher/course.php?id=' . $classId);
    } elseif ($postAction === 'attach_from_resources') {
        $resourceId = (int) ($_POST['resource_id'] ?? 0);
        $sectionId = (int) ($_POST['section_id'] ?? 0) ?: null;
        try {
            ContentResourceRepository::attachToClass($resourceId, $classId, $sectionId, (int) $user['id'], schoolId());
            flash('success', 'Resource added to your course.');
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirect('teacher/course.php?id=' . $classId);
    } elseif ($postAction === 'add_material' || $postAction === 'edit_material') {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $matType = normalizeMaterialType($_POST['material_type'] ?? 'file');
        $link = normalizeExternalUrl(trim($_POST['external_link'] ?? ''));
        $fileAccess = ($_POST['file_access_mode'] ?? 'downloadable') === 'view_only' ? 'view_only' : 'downloadable';
        $materialId = (int) ($_POST['material_id'] ?? 0);

        if ($title === '') {
            $errors[] = 'Title is required.';
        }
        if ($matType === 'link' && $link === '') {
            $errors[] = 'URL is required for link materials.';
        }
        if ($matType === 'file' && $postAction === 'add_material' && empty($_FILES['file']['name'])) {
            $errors[] = 'Please choose a file to upload.';
        }

        if ($postAction === 'add_material' && empty($errors)) {
            try {
                $sectionId = CourseSectionRepository::resolveSectionId((int) ($_POST['section_id'] ?? 0), $classId);
                if ($matType === 'doc') {
                    $id = MaterialRepository::create([
                        'class_id' => $classId,
                        'section_id' => $sectionId,
                        'teacher_id' => $user['id'],
                        'type' => 'doc',
                        'title' => $title,
                        'body' => $body ?: null,
                        'file_access_mode' => 'downloadable',
                    ]);
                    flash('success', 'Document created. Add content below.');
                    redirect('teacher/material-editor.php?id=' . $id . '&class_id=' . $classId);
                }
                $filePath = null;
                $originalName = null;
                $mimeType = null;
                $fileSize = 0;
                if ($matType === 'file' && !empty($_FILES['file']['name'])) {
                    $meta = uploadFileWithMeta($_FILES['file'], schoolId() . '/materials');
                    $filePath = $meta['path'];
                    $originalName = $meta['original_name'];
                    $mimeType = $meta['mime_type'];
                    $fileSize = $meta['file_size'];
                }
                MaterialRepository::create([
                    'class_id' => $classId,
                    'section_id' => $sectionId,
                    'teacher_id' => $user['id'],
                    'type' => $matType,
                    'title' => $title,
                    'content' => $matType === 'link' ? $link : null,
                    'body' => $body ?: null,
                    'file_path' => $filePath,
                    'original_name' => $originalName,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'external_link' => $matType === 'link' ? $link : null,
                    'file_access_mode' => $matType === 'file' ? $fileAccess : 'downloadable',
                ]);
                flash('success', 'Material added.');
                lessonContextReindexClass($classId);
                redirect('teacher/course.php?id=' . $classId);
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
                $action = 'add_material';
            }
        } elseif ($postAction === 'edit_material' && $materialId && empty($errors)) {
            $mat = MaterialRepository::findForTeacher($materialId, $user['id']);
            if (!$mat || (int) $mat['class_id'] !== $classId) {
                $errors[] = 'Material not found.';
            } else {
                try {
                    $matType = normalizeMaterialType($_POST['material_type'] ?? $mat['type']);
                    if ($matType === 'doc') {
                        redirect('teacher/material-editor.php?id=' . $materialId . '&class_id=' . $classId);
                    }
                    $filePath = $mat['file_path'];
                    $originalName = $mat['original_name'];
                    $mimeType = $mat['mime_type'];
                    $fileSize = (int) $mat['file_size'];
                    if ($matType === 'file' && !empty($_FILES['file']['name'])) {
                        deleteUpload($filePath);
                        $meta = uploadFileWithMeta($_FILES['file'], schoolId() . '/materials');
                        $filePath = $meta['path'];
                        $originalName = $meta['original_name'];
                        $mimeType = $meta['mime_type'];
                        $fileSize = $meta['file_size'];
                    }
                    $sectionId = CourseSectionRepository::resolveSectionId((int) ($_POST['section_id'] ?? 0), $classId);
                    MaterialRepository::update($materialId, [
                        'type' => $matType,
                        'title' => $title,
                        'content' => $matType === 'link' ? $link : ($mat['content'] ?? null),
                        'body' => $body ?: null,
                        'file_path' => $matType === 'file' ? $filePath : null,
                        'original_name' => $originalName,
                        'mime_type' => $mimeType,
                        'file_size' => $fileSize,
                        'external_link' => $matType === 'link' ? $link : null,
                        'file_access_mode' => $matType === 'file' ? $fileAccess : 'downloadable',
                        'section_id' => $sectionId,
                    ]);
                    flash('success', 'Material updated.');
                    lessonContextReindexClass($classId);
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
        $mat = MaterialRepository::findForTeacher($materialId, $user['id']);
        if ($mat && (int) $mat['class_id'] === $classId) {
            MaterialRepository::delete($materialId);
            flash('success', 'Material deleted.');
            lessonContextReindexClass($classId);
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
    } elseif ($postAction === 'add_quiz') {
        $title = trim($_POST['title'] ?? '');

        if ($title === '') {
            $errors[] = 'Title is required.';
        }

        if (empty($errors)) {
            $sectionId = CourseSectionRepository::resolveSectionId((int) ($_POST['section_id'] ?? 0), $classId);
            $stmt = db()->prepare('INSERT INTO quizzes (class_id, section_id, teacher_id, title, is_published, show_score_to_students, max_attempts) VALUES (?, ?, ?, ?, 0, 1, 1)');
            $stmt->execute([$classId, $sectionId, $user['id'], $title]);
            $newId = (int) db()->lastInsertId();
            flash('success', 'Quiz created. Add your questions next.');
            lessonContextReindexClass($classId);
            redirect(quizEditUrl($newId, $classId, 'questions'));
        } else {
            $action = 'add_quiz';
        }
    } elseif ($postAction === 'delete_quiz') {
        $quizId = (int) ($_POST['quiz_id'] ?? 0);
        db()->prepare('DELETE FROM quizzes WHERE id=? AND class_id=? AND teacher_id=?')->execute([$quizId, $classId, $user['id']]);
        flash('success', 'Quiz deleted.');
        lessonContextReindexClass($classId);
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
            lessonContextReindexClass($classId);
            redirect('teacher/course.php?id=' . $classId);
        } elseif ($postAction === 'edit_section' && $sectionId && empty($errors)) {
            if (CourseSectionRepository::update($sectionId, $classId, $title, $description ?: null)) {
                flash('success', 'Lesson section updated.');
            } else {
                flash('error', 'Could not update section.');
            }
            lessonContextReindexClass($classId);
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
            lessonContextReindexClass($classId);
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
            lessonContextReindexClass($classId);
        } else {
            flash('error', 'Could not move activity.');
        }
        redirect('teacher/course.php?id=' . $classId);
    }
}

$editMaterial = null;
$editAssignment = null;
$editSection = null;

$quizBuilderId = (int) ($_GET['quiz'] ?? 0);
$quizStep = $_GET['step'] ?? 'questions';
$quizEditQuestionId = (int) ($_GET['edit_q'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedQuizId = (int) ($_POST['quiz_id'] ?? 0);
    if ($postedQuizId) {
        $quizBuilderId = $postedQuizId;
    }
    $postedAction = $_POST['form_action'] ?? '';
    if ($postedAction === 'save_settings') {
        $quizStep = 'settings';
    } elseif (in_array($postedAction, ['save_question', 'delete_question', 'reorder_question'], true)) {
        $quizStep = 'questions';
    }
}

$quizWizardCtx = null;
if ($quizBuilderId) {
    $action = '';
    $quizWizardCtx = loadQuizWizardContext($quizBuilderId, $classId, (int) $user['id'], $quizStep, $quizEditQuestionId, $errors);
    if (!$quizWizardCtx) {
        flash('error', 'Quiz not found.');
        redirect('teacher/course.php?id=' . $classId);
    }
}

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
if ($action === 'edit_section' && $sectionIdParam) {
    $editSection = CourseSectionRepository::get($sectionIdParam, $classId);
    if (!$editSection) {
        flash('error', 'Section not found.');
        redirect('teacher/course.php?id=' . $classId);
    }
}

$sections = CourseSectionRepository::forClass($classId);
$courseContent = CourseSectionRepository::loadCourseContent($classId, null, (int) $user['id']);
$subjects = SubjectRepository::forSchool(schoolId());
require_once __DIR__ . '/../includes/layout/resources_grid.php';
$myResources = ContentResourceRepository::forSchool(schoolId(), [
    'created_by' => (int) $user['id'],
]);
$materialCount = $courseContent['material_count'];
$assignmentCount = $courseContent['assignment_count'];
$quizCount = $courseContent['quiz_count'];
$activityCount = $courseContent['activity_count'];
$enrolledStudents = ClassGroupRepository::enrolledStudents((int) $class['class_group_id']);
$studentCount = count($enrolledStudents);
$defaultFormSectionId = $sectionIdParam ?: ($sections[0]['id'] ?? null);
$courseInitial = strtoupper(mb_substr($class['name'], 0, 1));
$formOpen = !$quizWizardCtx && in_array($action, ['add_material', 'edit_material', 'add_assignment', 'edit_assignment', 'add_quiz', 'add_section', 'edit_section'], true);
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

<div class="course-view<?= $quizWizardCtx ? ' course-view--quiz-builder' : '' ?>">
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
                <button type="button" class="course-builder-btn course-builder-btn--resources" id="openCourseResourcePicker"<?= $myResources === [] ? ' disabled title="Create resources first"' : '' ?>>
                    <i class="fa-solid fa-folder-open"></i> From resources
                </button>
                <a href="<?= url('teacher/library.php?attach_class=' . $classId) ?>" class="course-builder-btn course-builder-btn--library">
                    <i class="fa-solid fa-book-bookmark"></i> From library
                </a>
                <a href="<?= teacherCourseUrl($classId, 'action=add_assignment') ?>" class="course-builder-btn course-builder-btn--assignment<?= $formType === 'assignment' ? ' is-active' : '' ?>">
                    <i class="fa-solid fa-pen-to-square"></i> Assignment
                </a>
                <a href="<?= teacherCourseUrl($classId, 'action=add_quiz') ?>" class="course-builder-btn course-builder-btn--quiz<?= $formType === 'quiz' ? ' is-active' : '' ?>">
                    <i class="fa-solid fa-circle-question"></i> Quiz
                </a>
                <a href="<?= url('teacher/ai-quiz-builder.php?class_id=' . $classId) ?>" class="course-builder-btn course-builder-btn--ai">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> AI Quiz
                </a>
            </div>
        </div>
        <div class="course-builder-side">
            <a href="<?= e(teacherClassStudentsUrl($classId)) ?>" class="course-builder-students">
                <i class="fa-solid fa-user-graduate"></i> View students<?= $studentCount > 0 ? ' (' . $studentCount . ')' : '' ?>
            </a>
            <a href="<?= teacherCourseUrl($classId, 'action=add_section') ?>" class="course-builder-lesson<?= $formType === 'section' ? ' is-active' : '' ?>">
                <i class="fa-solid fa-folder-plus"></i> New lesson
            </a>
        </div>
    </div>

    <div class="course-main course-main--full">
            <?php if ($quizWizardCtx): ?>
            <section class="course-content-section course-content-section--quiz-wizard">
                <?php require __DIR__ . '/../includes/layout/quiz_wizard.php'; ?>
            </section>
            <?php else: ?>
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
                            <h2>New quiz</h2>
                        <?php endif; ?>
                    </div>
                    <a href="<?= e($courseUrl) ?>" class="course-form-close" aria-label="Close"><i class="fa-solid fa-xmark"></i></a>
                </div>
                <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

                <?php if ($action === 'add_material' || $editMaterial): ?>
                <?php $editMat = $editMaterial ? MaterialRepository::normalizeRow($editMaterial) : null; ?>
                <form method="post" enctype="multipart/form-data" class="course-form" id="materialForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="form_action" value="<?= $editMaterial ? 'edit_material' : 'add_material' ?>">
                    <?php if ($editMaterial): ?><input type="hidden" name="material_id" value="<?= (int) $editMaterial['id'] ?>"><?php endif; ?>
                    <div class="form-group">
                        <label>Material type</label>
                        <select name="material_type" id="materialType" class="form-control" onchange="toggleMaterialFields()">
                            <option value="file"<?= ($editMat['type'] ?? 'file') === 'file' ? ' selected' : '' ?>>File upload</option>
                            <option value="link"<?= ($editMat['type'] ?? '') === 'link' ? ' selected' : '' ?>>External link</option>
                            <option value="doc"<?= ($editMat['type'] ?? '') === 'doc' ? ' selected' : '' ?>>Rich document</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Title</label><input name="title" class="form-control" value="<?= e($editMaterial['title'] ?? '') ?>" required placeholder="e.g. Week 1 lecture slides"></div>
                    <?php if (!empty($sections)): ?>
                    <div class="form-group">
                        <label>Lesson section</label>
                        <select name="section_id" class="form-control">
                            <?= courseSectionOptions($sections, (int) ($editMaterial['section_id'] ?? $defaultFormSectionId ?: 0) ?: null) ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="form-group"><label>Description</label><textarea name="body" class="form-control" rows="2" placeholder="Optional note for students"><?= e($editMaterial['body'] ?? '') ?></textarea></div>
                    <div id="materialFileFields">
                        <div class="form-group">
                            <label>Upload file</label>
                            <input type="file" name="file" class="form-control">
                            <?php if ($editMaterial && $editMaterial['file_path']): ?>
                                <small class="text-muted">Current: <?= e($editMaterial['original_name'] ?? 'file') ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Student access</label>
                            <select name="file_access_mode" class="form-control">
                                <option value="downloadable"<?= ($editMat['file_access_mode'] ?? 'downloadable') === 'downloadable' ? ' selected' : '' ?>>Downloadable</option>
                                <option value="view_only"<?= ($editMat['file_access_mode'] ?? '') === 'view_only' ? ' selected' : '' ?>>View only</option>
                            </select>
                        </div>
                    </div>
                    <div id="materialLinkFields" style="display:none">
                        <div class="form-group"><label>URL</label><input type="url" name="external_link" class="form-control" value="<?= e($editMat['content'] ?? $editMaterial['external_link'] ?? '') ?>" placeholder="https://"></div>
                    </div>
                    <div id="materialDocFields" style="display:none">
                        <p class="text-muted">Save to open the rich document editor where you can write formatted content.</p>
                        <?php if ($editMaterial && ($editMat['type'] ?? '') === 'doc'): ?>
                            <a href="<?= url('teacher/material-editor.php?id=' . $editMaterial['id'] . '&class_id=' . $classId) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen"></i> Edit document content</a>
                        <?php endif; ?>
                    </div>
                    <div class="course-form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save material</button>
                        <a href="<?= e($courseUrl) ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
                <script>
                function toggleMaterialFields() {
                    var t = document.getElementById('materialType').value;
                    document.getElementById('materialFileFields').style.display = t === 'file' ? 'block' : 'none';
                    document.getElementById('materialLinkFields').style.display = t === 'link' ? 'block' : 'none';
                    document.getElementById('materialDocFields').style.display = t === 'doc' ? 'block' : 'none';
                }
                toggleMaterialFields();
                </script>
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

                <?php if ($action === 'add_quiz'): ?>
                <form method="post" class="course-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="form_action" value="add_quiz">
                    <input type="hidden" name="class_id" value="<?= (int) $classId ?>">
                    <p class="course-form-intro text-muted">Start with a title. You will add questions and configure schedule, timer, and publishing on the next screens.</p>
                    <div class="form-group"><label>Quiz title</label><input name="title" class="form-control" value="<?= e($_POST['title'] ?? '') ?>" required placeholder="e.g. Midterm quiz" autofocus></div>
                    <?php if (!empty($sections)): ?>
                    <div class="form-group">
                        <label>Lesson section</label>
                        <select name="section_id" class="form-control">
                            <?= courseSectionOptions($sections, (int) ($defaultFormSectionId ?: 0) ?: null) ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="course-form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-arrow-right"></i> Create &amp; add questions</button>
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
            <?php endif; ?>
    </div>
</div>

<?php renderCourseResourcePickerModal($myResources, $classId, $sections); ?>

<dialog class="library-share-dialog" id="libraryShareDialog">
    <form method="post" class="library-share-form">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="share_material_to_library">
        <input type="hidden" name="material_id" id="libraryShareMaterialId" value="">
        <header class="library-share-header">
            <h2>Share to Virtual Library</h2>
            <p id="libraryShareTitle" class="text-muted"></p>
            <button type="button" class="library-share-close" data-close-share aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <p class="text-muted">Your resource will be reviewed by a school admin before it appears in the library.</p>
        <div class="form-group">
            <label>Kind</label>
            <select name="resource_kind" class="form-control">
                <?php foreach (LibraryResourceRepository::RESOURCE_KINDS as $kind): ?>
                    <option value="<?= e($kind) ?>"><?= e(resourceKindLabel($kind)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Subject</label>
            <select name="subject_id" class="form-control">
                <option value="">None</option>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= (int) $subject['id'] ?>"><?= e($subject['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Audience</label>
            <select name="audience" class="form-control">
                <option value="all">Teachers & students</option>
                <option value="teachers">Teachers only</option>
            </select>
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Optional summary for the library listing"></textarea>
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">Submit for approval</button>
            <button type="button" class="btn btn-secondary" data-close-share>Cancel</button>
        </div>
    </form>
</dialog>

<script>
(function () {
    var resourcePicker = document.getElementById('courseResourcePickerDialog');
    var openPicker = document.getElementById('openCourseResourcePicker');
    if (openPicker && resourcePicker) {
        openPicker.addEventListener('click', function () { resourcePicker.showModal(); });
        resourcePicker.querySelectorAll('[data-close-course-resource]').forEach(function (btn) {
            btn.addEventListener('click', function () { resourcePicker.close(); });
        });
    }

    var dialog = document.getElementById('libraryShareDialog');
    if (!dialog) return;
    var idInput = document.getElementById('libraryShareMaterialId');
    var titleEl = document.getElementById('libraryShareTitle');
    document.querySelectorAll('[data-share-material]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            idInput.value = btn.getAttribute('data-share-material');
            titleEl.textContent = btn.getAttribute('data-share-title') || '';
            dialog.showModal();
        });
    });
    document.querySelectorAll('[data-close-share]').forEach(function (btn) {
        btn.addEventListener('click', function () { dialog.close(); });
    });
})();
</script>

<div class="activity-modal-overlay course-settings-overlay" id="courseSettingsModal" hidden>
    <div class="activity-modal course-settings-modal" role="dialog" aria-modal="true" aria-labelledby="courseSettingsTitle">
        <button type="button" class="activity-modal-close" id="closeCourseSettingsModal" aria-label="Close">&times;</button>
        <h3 id="courseSettingsTitle"><i class="fa-solid fa-gear"></i> Class settings</h3>
        <p class="course-settings-modal-intro">Manage how this class appears on course cards.</p>
        <?php require __DIR__ . '/../includes/layout/course_class_settings.php'; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
