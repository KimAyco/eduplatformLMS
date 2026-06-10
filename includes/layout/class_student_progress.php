<?php

/**
 * @param array<string, mixed> $student
 * @param array<string, mixed> $progress
 * @param array<string, mixed>|null $gradebookRow
 */
function renderClassStudentProgressHero(array $student, array $progress, ?array $gradebookRow, array $class, int $classId): void
{
    $activityPercent = $progress['activity_percent'];
    $gradePercent = $gradebookRow['final_percent'] ?? null;
    $gradeTone = gradebookFinalTone($gradePercent !== null ? (float) $gradePercent : null);
    ?>
    <div class="student-progress-hero panel">
        <div class="student-progress-hero__identity">
            <?= userAvatarHtml($student, 'student-progress-hero__avatar') ?>
            <div>
                <h2><?= e($student['first_name'] . ' ' . $student['last_name']) ?></h2>
                <p class="text-muted"><?= e($student['email']) ?></p>
            </div>
        </div>
        <div class="student-progress-hero__metrics">
            <div class="student-progress-metric">
                <span class="student-progress-metric__label">Activity progress</span>
                <strong class="student-progress-metric__value"><?= $activityPercent !== null ? (int) $activityPercent . '%' : '—' ?></strong>
                <div class="student-progress-bar" aria-hidden="true">
                    <span style="width: <?= (int) ($activityPercent ?? 0) ?>%"></span>
                </div>
                <span class="student-progress-metric__hint">
                    <?= (int) $progress['completed_activities'] ?> of <?= (int) $progress['gradable_total'] ?> assignments &amp; quizzes
                </span>
            </div>
            <?php if ($gradePercent !== null): ?>
            <div class="student-progress-metric student-progress-metric--grade">
                <span class="student-progress-metric__label">Current grade</span>
                <span class="gb-final gb-final--<?= e($gradeTone) ?>"><?= e($gradePercent) ?>%</span>
            </div>
            <?php endif; ?>
        </div>
        <div class="student-progress-hero__chips">
            <span class="student-progress-chip">
                <i class="fa-solid fa-pen-to-square"></i>
                <?= (int) $progress['assignments_submitted'] ?>/<?= (int) $progress['assignments_total'] ?> assignments
            </span>
            <span class="student-progress-chip">
                <i class="fa-solid fa-circle-question"></i>
                <?= (int) $progress['quizzes_completed'] ?>/<?= (int) $progress['quizzes_total'] ?> quizzes
            </span>
            <?php if ((int) $progress['pending_count'] > 0): ?>
            <span class="student-progress-chip student-progress-chip--warn">
                <i class="fa-solid fa-clock"></i> <?= (int) $progress['pending_count'] ?> pending
            </span>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * @param array<string, mixed> $progress
 */
function renderClassStudentActivityList(array $progress, int $classId): void
{
    $assignments = $progress['assignments'];
    $quizzes = array_filter($progress['quizzes'], static fn ($q) => !empty($q['is_published']));
    ?>
    <div class="student-progress-sections">
        <section class="student-progress-section panel">
            <h3 class="student-progress-section__title"><i class="fa-solid fa-pen-to-square"></i> Assignments</h3>
            <?php if ($assignments === []): ?>
                <p class="text-muted">No assignments in this course yet.</p>
            <?php else: ?>
                <ul class="student-progress-list">
                    <?php foreach ($assignments as $assignment):
                        $status = $assignment['my_status'] ?? null;
                        $tone = ClassProgressRepository::statusTone($status, 'assignment');
                        $label = ClassProgressRepository::assignmentStatusLabel($status);
                    ?>
                    <li class="student-progress-item student-progress-item--<?= e($tone) ?>">
                        <div class="student-progress-item__main">
                            <strong><?= e($assignment['title']) ?></strong>
                            <?php if (!empty($assignment['due_date'])): ?>
                                <span>Due <?= formatDate($assignment['due_date'], 'M j, Y') ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="student-progress-item__status">
                            <span class="student-progress-status student-progress-status--<?= e($tone) ?>"><?= e($label) ?></span>
                            <?php if ($status === 'graded' && $assignment['my_grade'] !== null && $assignment['my_grade'] !== ''): ?>
                                <span class="student-progress-score"><?= e(gradebookFormatScore((float) $assignment['my_grade'])) ?>/<?= e(gradebookFormatScore((float) $assignment['max_points'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="student-progress-section panel">
            <h3 class="student-progress-section__title"><i class="fa-solid fa-circle-question"></i> Quizzes</h3>
            <?php if ($quizzes === []): ?>
                <p class="text-muted">No published quizzes in this course yet.</p>
            <?php else: ?>
                <ul class="student-progress-list">
                    <?php foreach ($quizzes as $quiz):
                        $status = $quiz['my_attempt_status'] ?? null;
                        $tone = ClassProgressRepository::statusTone($status, 'quiz');
                        $label = ClassProgressRepository::quizStatusLabel($status, true);
                    ?>
                    <li class="student-progress-item student-progress-item--<?= e($tone) ?>">
                        <div class="student-progress-item__main">
                            <strong><?= e($quiz['title']) ?></strong>
                            <?php if (!empty($quiz['due_date'])): ?>
                                <span>Due <?= formatDate($quiz['due_date'], 'M j, Y') ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="student-progress-item__status">
                            <span class="student-progress-status student-progress-status--<?= e($tone) ?>"><?= e($label) ?></span>
                            <?php if ($status && $status !== 'in_progress'): ?>
                            <a href="<?= url('teacher/quiz-attempts.php?quiz_id=' . (int) $quiz['id'] . '&class_id=' . $classId) ?>" class="btn btn-sm btn-secondary">View attempts</a>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
    <?php
}

/**
 * @param array<string, mixed> $summary
 */
function renderClassRosterProgressSummary(array $summary): void
{
    $activityPercent = $summary['activity_percent'];
    $gradePercent = $summary['grade_percent'] ?? null;
    $gradeTone = $gradePercent !== null ? gradebookFinalTone((float) $gradePercent) : null;
    $hasActivities = (int) ($summary['gradable_total'] ?? 0) > 0;
    ?>
    <div class="class-roster-progress">
        <div class="class-roster-progress__head">
            <span>Progress</span>
            <strong><?= $hasActivities && $activityPercent !== null ? (int) $activityPercent . '%' : ($hasActivities ? '0%' : '—') ?></strong>
        </div>
        <?php if ($hasActivities): ?>
        <div class="class-roster-progress__bar" aria-hidden="true"><span style="width: <?= (int) ($activityPercent ?? 0) ?>%"></span></div>
        <?php endif; ?>
        <div class="class-roster-progress__stats">
            <span><i class="fa-solid fa-pen-to-square"></i> <?= (int) $summary['assignments_submitted'] ?>/<?= (int) $summary['assignments_total'] ?></span>
            <span><i class="fa-solid fa-circle-question"></i> <?= (int) $summary['quizzes_completed'] ?>/<?= (int) $summary['quizzes_total'] ?></span>
            <?php if ($gradePercent !== null): ?>
            <span class="class-roster-progress__grade gb-final gb-final--<?= e($gradeTone) ?>"><?= e($gradePercent) ?>%</span>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
