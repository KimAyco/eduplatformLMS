<?php

function courseSectionOptions(array $sections, ?int $selectedId = null, bool $includeEmpty = true): string
{
    $html = '';
    if ($includeEmpty) {
        $html .= '<option value="">Unassigned</option>';
    }
    foreach ($sections as $section) {
        $id = (int) $section['id'];
        $sel = $selectedId === $id ? ' selected' : '';
        $html .= '<option value="' . $id . '"' . $sel . '>' . e($section['title']) . '</option>';
    }
    return $html;
}

function renderCourseLessonSections(array $content, int $classId, string $mode, array $sections = []): void
{
    $hasSections = !empty($content['sections']);
    $hasUncategorized = !empty($content['uncategorized']);
    $hasAny = ($content['activity_count'] ?? 0) > 0 || $hasSections;

    if (!$hasAny && $mode === 'student') {
        return;
    }

    if ($hasUncategorized) {
        $fallback = [
            'id' => 0,
            'title' => $hasSections ? 'Unassigned' : 'Course content',
            'description' => $hasSections ? 'Move these into a lesson using the section menu on each item.' : null,
            'activities' => $content['uncategorized'],
        ];
        renderCourseLessonSection($fallback, $classId, $mode, $sections, 0, !$hasSections);
    }

    $sectionIndex = 0;
    foreach ($content['sections'] as $section) {
        $sectionIndex++;
        renderCourseLessonSection($section, $classId, $mode, $sections, $sectionIndex, $sectionIndex === 1);
    }
}

function renderCourseLessonSection(array $section, int $classId, string $mode, array $allSections, int $index, bool $openDefault): void
{
    $activities = $section['activities'] ?? [];
    $activityCount = count($activities);
    $sectionId = (int) ($section['id'] ?? 0);
    $isGeneral = $sectionId === 0;
    $isOpen = $openDefault;
    $lessonClass = 'course-lesson' . ($isOpen ? ' is-open' : '') . ($isGeneral ? ' course-lesson--general' : '');
    ?>
    <div class="<?= $lessonClass ?>" data-lesson="<?= $sectionId ?: 'general' ?>">
        <div class="course-lesson-header">
            <button type="button" class="course-lesson-toggle" data-accordion-btn aria-expanded="<?= $isOpen ? 'true' : 'false' ?>">
                <?php if ($isGeneral): ?>
                    <span class="course-lesson-index course-lesson-index--general"><i class="fa-solid fa-inbox"></i></span>
                <?php else: ?>
                    <span class="course-lesson-index"><?= (int) $index ?></span>
                <?php endif; ?>
                <span class="course-lesson-heading">
                    <strong><?= e($section['title']) ?></strong>
                    <small><?= $activityCount ?> activit<?= $activityCount !== 1 ? 'ies' : 'y' ?></small>
                </span>
                <i class="fa-solid fa-chevron-down course-lesson-chevron" aria-hidden="true"></i>
            </button>
            <?php if ($mode === 'teacher' && $sectionId): ?>
            <div class="course-lesson-admin">
                <a href="<?= e(teacherCourseUrl($classId, 'action=edit_section&section_id=' . $sectionId)) ?>" class="course-lesson-admin-btn" title="Edit lesson"><i class="fa-solid fa-pen"></i></a>
                <form method="post" data-confirm="Delete this lesson? Activities will move to Unassigned."><?= csrfField() ?>
                    <input type="hidden" name="form_action" value="delete_section">
                    <input type="hidden" name="section_id" value="<?= $sectionId ?>">
                    <button type="submit" class="course-lesson-admin-btn course-lesson-admin-btn--danger" title="Delete lesson"><i class="fa-solid fa-trash"></i></button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <div class="course-lesson-body">
            <?php if (!empty($section['description'])): ?>
                <p class="course-lesson-desc"><?= e($section['description']) ?></p>
            <?php endif; ?>
            <?php if ($activityCount === 0): ?>
                <p class="course-lesson-empty text-muted"><?= $mode === 'teacher' ? 'No activities yet. Use the toolbar above to add content to this lesson.' : 'No activities in this lesson yet.' ?></p>
            <?php else: ?>
            <div class="activity-list<?= $mode === 'teacher' ? ' activity-list--modules' : '' ?>">
                <?php foreach ($activities as $act):
                    if ($mode === 'teacher') {
                        renderTeacherCourseActivityCard($act, $classId, $allSections);
                    } else {
                        renderStudentCourseActivityCard($act, $classId);
                    }
                endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function renderStudentCourseActivityCard(array $act, int $classId): void
{
    $item = $act['item'];
    if ($act['type'] === 'material'): ?>
    <article class="activity-card activity-card--material">
        <div class="activity-card-icon"><i class="fa-solid fa-file-lines"></i></div>
        <div class="activity-card-body">
            <span class="activity-card-type">Material</span>
            <h3><?= e($item['title']) ?></h3>
            <?php if ($item['body']): ?><p><?= e($item['body']) ?></p><?php endif; ?>
            <div class="activity-card-meta">
                <span class="activity-meta-chip muted"><i class="fa-solid fa-user"></i> <?= e($item['teacher_first'] . ' ' . $item['teacher_last']) ?></span>
                <?php if ($item['file_path']): ?><a href="<?= e(uploadUrl($item['file_path'])) ?>" class="activity-meta-chip" download><i class="fa-solid fa-download"></i> Download</a><?php endif; ?>
                <?php if ($item['external_link']): ?><a href="<?= e($item['external_link']) ?>" target="_blank" class="activity-meta-chip"><i class="fa-solid fa-link"></i> Open link</a><?php endif; ?>
            </div>
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
                <?php if ($item['my_status']): ?>
                    <span class="badge badge-<?= e($item['my_status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $item['my_status']))) ?></span>
                    <?php if ($item['my_grade'] !== null): ?><span class="activity-meta-chip"><i class="fa-solid fa-check"></i> Grade: <?= e($item['my_grade']) ?></span><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="activity-card-actions">
            <a href="<?= url('student/assignments.php?id=' . $item['id']) ?>" class="btn btn-sm btn-primary"><?= $item['my_status'] ? 'View' : 'Submit' ?></a>
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
                <span class="activity-meta-chip"><i class="fa-regular fa-calendar"></i> Due <?= formatDate($item['due_date'], 'M j, Y') ?></span>
                <?php if ($item['my_attempt_status']): ?><span class="badge badge-submitted"><?= e(ucfirst(str_replace('_', ' ', $item['my_attempt_status']))) ?></span><?php endif; ?>
            </div>
        </div>
        <div class="activity-card-actions">
            <a href="<?= url('student/quiz-take.php?quiz_id=' . $item['id']) ?>" class="btn btn-sm btn-primary">Take quiz</a>
            <?php if ($item['my_attempt_status'] && $item['my_attempt_status'] !== 'in_progress'): ?>
            <a href="<?= url('student/quiz-results.php?quiz_id=' . $item['id']) ?>" class="btn btn-sm btn-secondary">Results</a>
            <?php endif; ?>
        </div>
    </article>
    <?php endif;
}

function renderTeacherCourseActivityCard(array $act, int $classId, array $sections): void
{
    $item = $act['item'];
    $type = $act['type'];
    $typeLabel = ucfirst($type);
    ?>
    <article class="activity-module activity-module--<?= e($type) ?>">
        <div class="activity-module-icon" aria-hidden="true">
            <i class="fa-solid <?= $type === 'material' ? 'fa-file-lines' : ($type === 'assignment' ? 'fa-pen-to-square' : 'fa-circle-question') ?>"></i>
        </div>
        <div class="activity-module-main">
            <div class="activity-module-top">
                <span class="activity-module-type"><?= e($typeLabel) ?></span>
                <h3><?= e($item['title']) ?></h3>
            </div>
            <div class="activity-module-meta">
                <?php if ($type === 'material'): ?>
                    <?php if ($item['file_path']): ?><a href="<?= e(uploadUrl($item['file_path'])) ?>" class="activity-meta-chip" download><i class="fa-solid fa-download"></i> Download</a><?php endif; ?>
                    <?php if ($item['external_link']): ?><a href="<?= e($item['external_link']) ?>" target="_blank" rel="noopener" class="activity-meta-chip"><i class="fa-solid fa-link"></i> Open link</a><?php endif; ?>
                    <span class="activity-meta-chip muted"><?= formatDate($item['created_at'], 'M j, Y') ?></span>
                <?php elseif ($type === 'assignment'): ?>
                    <span class="activity-meta-chip"><i class="fa-regular fa-calendar"></i> <?= formatDate($item['due_date'], 'M j, Y') ?></span>
                    <span class="activity-meta-chip"><?= e($item['max_points']) ?> pts</span>
                    <span class="activity-meta-chip"><?= (int) $item['submission_count'] ?> submitted</span>
                <?php else: ?>
                    <span class="activity-meta-chip"><?= (int) $item['question_count'] ?> questions</span>
                    <span class="activity-meta-chip"><?= (int) $item['attempt_count'] ?> attempts</span>
                    <span class="activity-meta-chip"><i class="fa-regular fa-calendar"></i> <?= formatDate($item['due_date'], 'M j, Y') ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="activity-module-actions">
            <?php if (!empty($sections)): ?>
            <form method="post" class="activity-module-move" data-no-loader>
                <?= csrfField() ?>
                <input type="hidden" name="form_action" value="move_activity">
                <input type="hidden" name="item_type" value="<?= e($type) ?>">
                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                <select name="section_id" class="form-control form-control-sm" aria-label="Move to lesson" onchange="this.form.submit()">
                    <?= courseSectionOptions($sections, !empty($item['section_id']) ? (int) $item['section_id'] : null) ?>
                </select>
            </form>
            <?php endif; ?>
            <div class="activity-module-buttons">
                <?php if ($type === 'material'): ?>
                    <a href="<?= teacherCourseUrl($classId, 'action=edit_material&item_id=' . $item['id']) ?>" class="btn btn-sm btn-secondary" title="Edit"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" data-confirm="Delete this material?"><?= csrfField() ?><input type="hidden" name="form_action" value="delete_material"><input type="hidden" name="material_id" value="<?= (int) $item['id'] ?>"><button class="btn btn-sm btn-danger" title="Delete"><i class="fa-solid fa-trash"></i></button></form>
                <?php elseif ($type === 'assignment'): ?>
                    <a href="<?= url('teacher/grade-submissions.php?assignment_id=' . $item['id']) ?>" class="btn btn-sm btn-primary">Grade</a>
                    <a href="<?= teacherCourseUrl($classId, 'action=edit_assignment&item_id=' . $item['id']) ?>" class="btn btn-sm btn-secondary" title="Edit"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" data-confirm="Delete this assignment?"><?= csrfField() ?><input type="hidden" name="form_action" value="delete_assignment"><input type="hidden" name="assignment_id" value="<?= (int) $item['id'] ?>"><button class="btn btn-sm btn-danger" title="Delete"><i class="fa-solid fa-trash"></i></button></form>
                <?php else: ?>
                    <a href="<?= url('teacher/quiz-edit.php?id=' . $item['id'] . '&class_id=' . $classId) ?>" class="btn btn-sm btn-primary">Questions</a>
                    <a href="<?= teacherCourseUrl($classId, 'action=edit_quiz&item_id=' . $item['id']) ?>" class="btn btn-sm btn-secondary" title="Edit"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" data-confirm="Delete this quiz?"><?= csrfField() ?><input type="hidden" name="form_action" value="delete_quiz"><input type="hidden" name="quiz_id" value="<?= (int) $item['id'] ?>"><button class="btn btn-sm btn-danger" title="Delete"><i class="fa-solid fa-trash"></i></button></form>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
}
