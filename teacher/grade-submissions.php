<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout/gradebook_table.php';
requireRole('teacher');
requireSchoolActive();

$user = currentUser();
$assignmentId = (int) ($_GET['assignment_id'] ?? 0);
$classId = (int) ($_GET['class_id'] ?? 0);

/**
 * @return array<string, mixed>|null
 */
function gradingClassForTeacher(int $classId, int $teacherId): ?array
{
    foreach (getTeacherClasses($teacherId) as $class) {
        if ((int) $class['id'] === $classId) {
            return $class;
        }
    }

    return null;
}

/**
 * @param array<string, mixed> $class
 * @return array<string, mixed>
 */
function loadGradingClassContext(array $class, int $teacherId): array
{
    $classId = (int) $class['id'];
    $students = ClassGroupRepository::enrolledStudents((int) $class['class_group_id']);

    $pendingStmt = db()->prepare("SELECT COUNT(*) FROM assignment_submissions s
        INNER JOIN assignments a ON a.id = s.assignment_id
        WHERE a.class_id = ? AND a.teacher_id = ? AND s.status = 'submitted' AND s.grade IS NULL");
    $pendingStmt->execute([$classId, $teacherId]);
    $pendingGrades = (int) $pendingStmt->fetchColumn();

    $assignmentsStmt = db()->prepare("SELECT a.id, a.title, a.due_date, a.max_points,
        (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) AS submission_count,
        (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND status = 'submitted' AND grade IS NULL) AS pending_count
        FROM assignments a
        WHERE a.class_id = ? AND a.teacher_id = ?
        ORDER BY a.due_date DESC, a.created_at DESC");
    $assignmentsStmt->execute([$classId, $teacherId]);

    return array_merge($class, [
        'students' => $students,
        'pending_grades' => $pendingGrades,
        'assignments' => $assignmentsStmt->fetchAll(),
    ]);
}

if ($assignmentId > 0) {
    $assignmentJoin = 'FROM assignments a
        INNER JOIN classes c ON c.id = a.class_id
        INNER JOIN subjects s ON s.id = c.subject_id
        INNER JOIN class_groups g ON g.id = c.class_group_id';

    $stmt = db()->prepare("SELECT a.*, s.name AS name, g.name AS group_name $assignmentJoin
        WHERE a.id = ? AND a.teacher_id = ?");
    $stmt->execute([$assignmentId, $user['id']]);
    $assignment = $stmt->fetch();

    if (!$assignment) {
        flash('error', 'Assignment not found.');
        redirect('teacher/grade-submissions.php');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $submissionId = (int) ($_POST['submission_id'] ?? 0);
        $grade = $_POST['grade'] !== '' ? (float) $_POST['grade'] : null;
        $feedback = trim($_POST['feedback'] ?? '');

        $sub = db()->prepare('SELECT s.* FROM assignment_submissions s
            INNER JOIN assignments a ON a.id = s.assignment_id
            WHERE s.id = ? AND a.id = ? AND a.teacher_id = ?');
        $sub->execute([$submissionId, $assignmentId, $user['id']]);
        $subRow = $sub->fetch();
        if ($subRow) {
            $status = $grade !== null ? 'graded' : 'submitted';
            db()->prepare('UPDATE assignment_submissions SET grade=?, feedback=?, status=? WHERE id=?')
                ->execute([$grade, $feedback ?: null, $status, $submissionId]);
            if ($status === 'graded') {
                syncAssignmentSubmissionToGradebook($submissionId);
            }
            flash('success', 'Submission graded.');
        }
        redirect('teacher/grade-submissions.php?assignment_id=' . $assignmentId . ($classId ? '&class_id=' . $classId : ''));
    }

    $submissions = db()->prepare('SELECT s.*, u.first_name, u.last_name, u.email
        FROM assignment_submissions s
        INNER JOIN users u ON u.id = s.student_id
        WHERE s.assignment_id = ?
        ORDER BY s.submitted_at DESC');
    $submissions->execute([$assignmentId]);
    $submissions = $submissions->fetchAll();

    $pageTitle = 'Grade Submissions';
    $pageHeading = 'Grade: ' . $assignment['title'];
    $activeMenu = 'grading';
    $menuItems = teacherMenu();
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => 'teacher/dashboard.php'],
        ['label' => 'Grade Submissions', 'url' => 'teacher/grade-submissions.php'],
        ['label' => classDisplayName($assignment), 'url' => 'teacher/grade-submissions.php?class_id=' . (int) $assignment['class_id']],
        ['label' => $assignment['title'], 'url' => 'teacher/grade-submissions.php?assignment_id=' . $assignmentId],
    ];

    require __DIR__ . '/../includes/layout/dashboard_header.php';
    ?>

    <div class="actions mb-1">
        <a href="<?= url('teacher/grade-submissions.php?class_id=' . (int) $assignment['class_id']) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to class</a>
    </div>

    <div class="panel">
        <p><strong>Class:</strong> <?= e(classDisplayName($assignment)) ?> · <strong>Max Points:</strong> <?= e($assignment['max_points']) ?> · <strong>Due:</strong> <?= formatDate($assignment['due_date']) ?></p>
    </div>

    <?php if (empty($submissions)): ?>
        <p class="text-muted">No submissions yet.</p>
    <?php else: foreach ($submissions as $s): ?>
    <div class="panel">
        <div class="panel-header">
            <h2><?= e($s['first_name'] . ' ' . $s['last_name']) ?></h2>
            <span class="badge badge-<?= e($s['status']) ?>"><?= e(ucfirst($s['status'])) ?></span>
        </div>
        <p class="text-muted">Submitted: <?= formatDate($s['submitted_at']) ?></p>
        <?php if ($s['content']): ?><div class="mb-1"><?= nl2br(e($s['content'])) ?></div><?php endif; ?>
        <?php if ($s['file_path']): ?><p><a href="<?= e(uploadUrl($s['file_path'], 'submission')) ?>" target="_blank">Download attachment</a></p><?php endif; ?>

        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="submission_id" value="<?= $s['id'] ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Grade (max <?= e($assignment['max_points']) ?>)</label>
                    <input type="number" step="0.01" name="grade" class="form-control" value="<?= e($s['grade'] ?? '') ?>" max="<?= e($assignment['max_points']) ?>">
                </div>
                <div class="form-group">
                    <label>Feedback</label>
                    <textarea name="feedback" class="form-control"><?= e($s['feedback'] ?? '') ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Save Grade</button>
        </form>
    </div>
    <?php endforeach; endif; ?>

    <?php require __DIR__ . '/../includes/layout/dashboard_footer.php';
    exit;
}

if ($classId > 0) {
    $class = gradingClassForTeacher($classId, (int) $user['id']);
    if (!$class) {
        flash('error', 'Class not found.');
        redirect('teacher/grade-submissions.php');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $formAction = $_POST['form_action'] ?? '';

        if ($formAction === 'sync_grading_links') {
            $subjectId = (int) $class['subject_id'];
            $components = GradebookRepository::componentsForSubject($subjectId, schoolId());
            $links = [];
            foreach ($components as $component) {
                $componentId = (int) $component['id'];
                $quizId = (int) ($_POST['link_quiz'][$componentId] ?? 0);
                $assignmentId = (int) ($_POST['link_assignment'][$componentId] ?? 0);
                if ($quizId > 0 || $assignmentId > 0) {
                    $links[$componentId] = [
                        'quiz_id' => $quizId ?: null,
                        'assignment_id' => $assignmentId ?: null,
                    ];
                }
            }
            GradebookRepository::saveClassLinks($classId, $links);
            recalculateClassGradebook($classId);
            flash('success', 'Grading links saved and scores synced.');
            redirect('teacher/grade-submissions.php?class_id=' . $classId);
        }

        if ($formAction === 'save_manual_grades' || $formAction === 'save_gradebook') {
            $subjectId = (int) $class['subject_id'];
            $components = GradebookRepository::componentsForSubject($subjectId, schoolId());
            $componentsById = [];
            foreach ($components as $component) {
                $componentsById[(int) $component['id']] = $component;
            }

            $posted = $_POST['gradebook'] ?? $_POST['manual_grade'] ?? [];
            if (is_array($posted)) {
                foreach ($posted as $studentId => $byComponent) {
                    if (!is_array($byComponent)) {
                        continue;
                    }
                    foreach ($byComponent as $componentId => $percent) {
                        $componentId = (int) $componentId;
                        $component = $componentsById[$componentId] ?? null;
                        if (!$component) {
                            continue;
                        }
                        $isAutoCategory = !GradebookRepository::isManualCategory($component['category']);

                        if ($percent === '' || $percent === null) {
                            $existing = GradebookRepository::getGradeCell($classId, (int) $studentId, $componentId);
                            if ($existing) {
                                GradebookRepository::deleteGrade($classId, (int) $studentId, $componentId);
                                if ($isAutoCategory && (int) ($existing['is_manual'] ?? 0) === 1) {
                                    resyncGradebookCell($classId, (int) $studentId, $componentId);
                                }
                            }
                            continue;
                        }

                        GradebookRepository::saveManualGrade($classId, (int) $studentId, $componentId, (float) $percent);
                    }
                }
            }
            flash('success', 'Grades saved.');
            redirect('teacher/grade-submissions.php?class_id=' . $classId);
        }
    }

    $class = loadGradingClassContext($class, (int) $user['id']);
    $students = $class['students'];
    $studentCount = count($students);
    $subjectId = (int) $class['subject_id'];
    $schemeComponents = GradebookRepository::componentsForSubject($subjectId, schoolId());
    $gradingLinks = GradebookRepository::linksForClass($classId);
    $gradebook = GradebookRepository::gradebookForClass($classId, $students, $schemeComponents);

    $quizzes = db()->prepare('SELECT id, title FROM quizzes WHERE class_id = ? AND teacher_id = ? ORDER BY title');
    $quizzes->execute([$classId, $user['id']]);
    $quizzes = $quizzes->fetchAll();

    $setupStats = gradebookSetupStats($schemeComponents, $gradingLinks);
    $defaultTab = (!$setupStats['ready'] && $setupStats['auto_slots'] > 0) ? 'setup' : 'gradebook';

    $pageTitle = 'Grade Submissions';
    $pageHeading = classDisplayName($class);
    $pageSubtitle = 'Sync activities to the grading scheme and view each student\'s grade card.';
    $activeMenu = 'grading';
    $menuItems = teacherMenu();
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => 'teacher/dashboard.php'],
        ['label' => 'Grade Submissions', 'url' => 'teacher/grade-submissions.php'],
        ['label' => classDisplayName($class), 'url' => 'teacher/grade-submissions.php?class_id=' . $classId],
    ];

    require __DIR__ . '/../includes/layout/dashboard_header.php';
    ?>

    <div class="actions mb-1">
        <a href="<?= url('teacher/grade-submissions.php') ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> All classes</a>
        <a href="<?= e(teacherCourseUrl($classId)) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open course</a>
    </div>

    <div class="gb-class-hero">
        <div class="gb-class-hero__main">
            <span class="gb-class-hero__badge"><?= e($class['name']) ?></span>
            <h2 class="gb-class-hero__title"><?= e(classDisplayName($class)) ?></h2>
            <div class="gb-class-hero__chips">
                <span><i class="fa-solid fa-users"></i> <?= $studentCount ?> student<?= $studentCount !== 1 ? 's' : '' ?></span>
                <span><i class="fa-solid fa-calendar"></i> <?= e($class['group_academic_year'] ?: 'Open course') ?></span>
                <?php if (!empty($schemeComponents)): ?>
                <span><i class="fa-solid fa-scale-balanced"></i> <?= count($schemeComponents) ?> grade components</span>
                <?php endif; ?>
                <?php if ((int) $class['pending_grades'] > 0): ?>
                <span class="gb-class-hero__pending"><i class="fa-solid fa-clock"></i> <?= (int) $class['pending_grades'] ?> to grade</span>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($schemeComponents)): ?>
        <div class="gb-class-hero__aside">
            <?php renderGradebookWeightBar($schemeComponents, 'gb-weight-bar--hero'); ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if (empty($schemeComponents)): ?>
        <div class="gb-empty-scheme panel">
            <div class="gb-empty-scheme__icon"><i class="fa-solid fa-scale-balanced"></i></div>
            <h3>Set up the grading scheme first</h3>
            <p>Your school admin needs to define grade components (quizzes, exams, weights) for <strong><?= e($class['name']) ?></strong> before you can use the gradebook.</p>
        </div>
    <?php else: ?>
    <div class="gb-workspace" data-gradebook-tabs>
        <nav class="gb-tabs" role="tablist" aria-label="Gradebook sections">
            <button type="button" class="gb-tab<?= $defaultTab === 'gradebook' ? ' is-active' : '' ?>" data-gradebook-tab="gradebook" role="tab" aria-selected="<?= $defaultTab === 'gradebook' ? 'true' : 'false' ?>">
                <i class="fa-solid fa-table"></i> Gradebook
            </button>
            <button type="button" class="gb-tab<?= $defaultTab === 'setup' ? ' is-active' : '' ?>" data-gradebook-tab="setup" role="tab" aria-selected="<?= $defaultTab === 'setup' ? 'true' : 'false' ?>">
                <i class="fa-solid fa-link"></i> Connect activities
                <?php if (!$setupStats['ready'] && $setupStats['auto_slots'] > 0): ?>
                <span class="gb-tab__badge"><?= (int) $setupStats['linked'] ?>/<?= (int) $setupStats['auto_slots'] ?></span>
                <?php endif; ?>
            </button>
            <?php if (!empty($class['assignments'])): ?>
            <button type="button" class="gb-tab" data-gradebook-tab="assignments" role="tab" aria-selected="false">
                <i class="fa-solid fa-pen-to-square"></i> Grade work
                <?php if ((int) $class['pending_grades'] > 0): ?>
                <span class="gb-tab__badge gb-tab__badge--warn"><?= (int) $class['pending_grades'] ?></span>
                <?php endif; ?>
            </button>
            <?php endif; ?>
        </nav>

        <div class="gb-tab-panel<?= $defaultTab === 'gradebook' ? ' is-active' : '' ?>" data-gradebook-panel="gradebook" role="tabpanel">
            <?php if ($studentCount > 0): ?>
            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="form_action" value="save_gradebook">
                <?php renderGradebookTable($gradebook, $classId, true); ?>
                <div class="gb-manual-actions">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save grades</button>
                    <span class="text-muted">Type in any cell to override synced scores. Overridden cells show an <strong>Edited</strong> badge. Clear a cell and save to restore the synced score.</span>
                </div>
            </form>
            <?php else: ?>
                <?php renderGradebookTable($gradebook, $classId, false); ?>
            <?php endif; ?>
        </div>

        <div class="gb-tab-panel<?= $defaultTab === 'setup' ? ' is-active' : '' ?>" data-gradebook-panel="setup" role="tabpanel">
            <?php renderGradebookSyncPanel($schemeComponents, $gradingLinks, $quizzes, $class['assignments'], $classId); ?>
        </div>

        <?php if (!empty($class['assignments'])): ?>
        <div class="gb-tab-panel" data-gradebook-panel="assignments" role="tabpanel">
            <p class="gb-tab-intro">Open an assignment to review submissions and enter grades. Linked assignments sync to the gradebook automatically.</p>
            <div class="gb-assignment-grid">
                <?php foreach ($class['assignments'] as $a): ?>
                <article class="gb-assignment-card">
                    <div class="gb-assignment-card__icon"><i class="fa-solid fa-pen-to-square"></i></div>
                    <div class="gb-assignment-card__body">
                        <strong><?= e($a['title']) ?></strong>
                        <span>Due <?= formatDate($a['due_date'], 'M j, Y') ?> · <?= e($a['max_points']) ?> pts</span>
                        <span><?= (int) $a['submission_count'] ?> submitted</span>
                    </div>
                    <div class="gb-assignment-card__actions">
                        <?php if ((int) $a['pending_count'] > 0): ?>
                            <span class="badge badge-warning"><?= (int) $a['pending_count'] ?> pending</span>
                        <?php endif; ?>
                        <a href="<?= url('teacher/grade-submissions.php?assignment_id=' . $a['id'] . '&class_id=' . $classId) ?>" class="btn btn-sm btn-primary">Grade submissions</a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <script src="<?= url('assets/js/gradebook-class.js') ?>"></script>
    <?php endif; ?>

    <?php require __DIR__ . '/../includes/layout/dashboard_footer.php';
    exit;
}

$classes = getTeacherClasses();
$classesWithStats = [];

foreach ($classes as $class) {
    $ctx = loadGradingClassContext($class, (int) $user['id']);
    $classId = (int) $ctx['id'];
    $schemeComponents = GradebookRepository::componentsForSubject((int) $ctx['subject_id'], schoolId());
    $gradingLinks = GradebookRepository::linksForClass($classId);
    $ctx['scheme_components'] = $schemeComponents;
    $ctx['setup_stats'] = gradebookSetupStats($schemeComponents, $gradingLinks);
    $classesWithStats[] = $ctx;
}

$totalStudents = array_sum(array_map(static fn ($c) => count($c['students']), $classesWithStats));
$totalPending = array_sum(array_column($classesWithStats, 'pending_grades'));

$pageTitle = 'Grade Submissions';
$pageHeading = 'Grade submissions';
$pageSubtitle = 'Open a class to connect activities, view the gradebook, and grade submissions.';
$activeMenu = 'grading';
$menuItems = teacherMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'teacher/dashboard.php'],
    ['label' => 'Grade Submissions', 'url' => 'teacher/grade-submissions.php'],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<?php if (empty($classesWithStats)): ?>
<div class="empty-state">
    <i class="fa-solid fa-chalkboard"></i>
    <h3>No classes assigned</h3>
    <p>You are not assigned to any classes yet. Contact your school administrator.</p>
    <a href="<?= url('teacher/classes.php') ?>" class="btn btn-primary btn-sm">View classes</a>
</div>
<?php else: ?>
<?php if ($totalPending > 0): ?>
<div class="grading-overview panel">
    <p class="mb-0 grading-overview__pending">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <strong><?= $totalPending ?></strong> submission<?= $totalPending !== 1 ? 's' : '' ?> awaiting grade across your classes
    </p>
</div>
<?php endif; ?>

<div class="course-grid">
    <?php foreach ($classesWithStats as $class):
        $studentCount = count($class['students']);
        $pending = (int) $class['pending_grades'];
        $setup = $class['setup_stats'] ?? ['total' => 0, 'ready' => true, 'auto_slots' => 0, 'linked' => 0];
        $componentCount = count($class['scheme_components'] ?? []);
        $bodyHtml = '<p class="text-muted course-card-desc">' . e($class['group_academic_year'] ?: 'Open course') . '</p>'
            . '<div class="grading-card-stats">'
            . '<span><i class="fa-solid fa-users" aria-hidden="true"></i> ' . $studentCount . ' student' . ($studentCount !== 1 ? 's' : '') . '</span>';
        if ($componentCount > 0) {
            $bodyHtml .= '<span><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> ' . $componentCount . ' components</span>';
            if (!$setup['ready'] && $setup['auto_slots'] > 0) {
                $bodyHtml .= '<span class="grading-card-stats__pending"><i class="fa-solid fa-link" aria-hidden="true"></i> ' . (int) $setup['linked'] . '/' . (int) $setup['auto_slots'] . ' linked</span>';
            }
        } else {
            $bodyHtml .= '<span class="grading-card-stats__pending"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> No scheme</span>';
        }
        if ($pending > 0) {
            $bodyHtml .= '<span class="grading-card-stats__pending"><i class="fa-solid fa-clock" aria-hidden="true"></i> ' . $pending . ' to grade</span>';
        }
        $bodyHtml .= '</div>';
        renderCourseCard(
            $class,
            url('teacher/grade-submissions.php?class_id=' . (int) $class['id']),
            $bodyHtml,
            'Open gradebook'
        );
    endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
