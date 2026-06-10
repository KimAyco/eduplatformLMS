<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout/gradebook_table.php';
require_once __DIR__ . '/../includes/layout/class_student_progress.php';
requireRole('teacher');
requireSchoolActive();

$user = currentUser();
$classId = (int) ($_GET['class_id'] ?? 0);
$studentId = (int) ($_GET['student_id'] ?? 0);
requireClassAccess($classId, 'teacher');

$class = getClass($classId);
if (!$class) {
    flash('error', 'Class not found.');
    redirect('teacher/classes.php');
}

$students = ClassGroupRepository::enrolledStudents((int) $class['class_group_id']);
$studentCount = count($students);
$classTitle = classDisplayName($class);
$subjectId = (int) $class['subject_id'];
$schoolId = schoolId();

if ($studentId > 0) {
    $student = null;
    foreach ($students as $row) {
        if ((int) $row['id'] === $studentId) {
            $student = $row;
            break;
        }
    }
    if (!$student) {
        flash('error', 'Student not found in this class.');
        redirect('teacher/class-students.php?class_id=' . $classId);
    }

    $progress = ClassProgressRepository::studentActivityProgress($classId, $studentId);
    $schemeComponents = GradebookRepository::componentsForSubject($subjectId, $schoolId);
    $gradebook = GradebookRepository::gradebookForClass($classId, [$student], $schemeComponents);
    $gradebookRow = $gradebook['rows'][0] ?? null;

    $pageTitle = $student['first_name'] . ' ' . $student['last_name'] . ' — ' . $class['name'];
    $pageHeading = 'Student progress';
    $pageSubtitle = $classTitle;
    $activeMenu = 'classes';
    $menuItems = teacherMenu();
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => 'teacher/dashboard.php'],
        ['label' => $class['name'], 'url' => 'teacher/course.php?id=' . $classId],
        ['label' => 'Students', 'url' => 'teacher/class-students.php?class_id=' . $classId],
        ['label' => $student['first_name'] . ' ' . $student['last_name'], 'url' => ''],
    ];

    require __DIR__ . '/../includes/layout/dashboard_header.php';
    ?>

    <div class="actions mb-1">
        <a href="<?= e(teacherClassStudentsUrl($classId)) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> All students</a>
        <a href="<?= url('teacher/grade-submissions.php?class_id=' . $classId) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-table"></i> Gradebook</a>
    </div>

    <?php renderClassStudentProgressHero($student, $progress, $gradebookRow, $class, $classId); ?>
    <?php renderClassStudentActivityList($progress, $classId); ?>

    <?php if ($schemeComponents !== [] && $gradebookRow): ?>
    <section class="student-progress-section panel mt-1">
        <h3 class="student-progress-section__title"><i class="fa-solid fa-scale-balanced"></i> Grade breakdown</h3>
        <?php renderStudentGradeCardView($gradebook, $class, $student); ?>
    </section>
    <?php endif; ?>

    <?php require __DIR__ . '/../includes/layout/dashboard_footer.php';
    exit;
}

$progressByStudent = ClassProgressRepository::rosterSummaryForClass($classId, $subjectId, $schoolId, $students);

$pageTitle = 'Students — ' . $class['name'];
$pageHeading = 'Enrolled students';
$pageSubtitle = $classTitle . ($class['group_name'] ? ' · ' . $class['group_name'] : '');
$activeMenu = 'classes';
$menuItems = teacherMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'teacher/dashboard.php'],
    ['label' => $class['name'], 'url' => 'teacher/course.php?id=' . $classId],
    ['label' => 'Students', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="actions mb-1">
    <a href="<?= e(teacherCourseUrl($classId)) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to course</a>
    <a href="<?= url('teacher/grade-submissions.php?class_id=' . $classId) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-table"></i> Gradebook</a>
</div>

<div class="class-roster-hero panel">
    <div class="class-roster-hero__main">
        <span class="class-roster-hero__badge"><?= e($class['name']) ?></span>
        <h2 class="class-roster-hero__title"><?= e($classTitle) ?></h2>
        <p class="class-roster-hero__meta text-muted">
            <?php if ($class['group_name']): ?>
                <i class="fa-solid fa-layer-group"></i> <?= e($class['group_name']) ?>
            <?php endif; ?>
            <?php if ($class['group_academic_year']): ?>
                · <i class="fa-solid fa-calendar"></i> <?= e($class['group_academic_year']) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="class-roster-hero__count">
        <strong><?= $studentCount ?></strong>
        <span>student<?= $studentCount !== 1 ? 's' : '' ?> enrolled</span>
    </div>
</div>

<?php if ($studentCount === 0): ?>
<div class="empty-state panel">
    <i class="fa-solid fa-user-graduate"></i>
    <h3>No students enrolled</h3>
    <p>Students are enrolled through their class group. Contact your school administrator to add students to <strong><?= e($class['group_name'] ?: 'this group') ?></strong>.</p>
</div>
<?php else: ?>
<div class="class-roster-panel panel">
    <div class="class-roster-toolbar">
        <label class="gb-table-search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" class="form-control" placeholder="Search students…" data-roster-search autocomplete="off">
        </label>
        <span class="gb-table-toolbar__count" data-roster-count><?= $studentCount ?> student<?= $studentCount !== 1 ? 's' : '' ?></span>
    </div>
    <div class="class-roster-grid" data-roster-grid>
        <?php foreach ($students as $student):
            $sid = (int) $student['id'];
            $summary = $progressByStudent[$sid] ?? [];
            $searchText = strtolower($student['first_name'] . ' ' . $student['last_name'] . ' ' . $student['email']);
        ?>
        <a href="<?= e(teacherClassStudentUrl($classId, $sid)) ?>" class="class-roster-card" data-roster-card="<?= e($searchText) ?>">
            <div class="class-roster-card__top">
                <?= userAvatarHtml($student, 'class-roster-card__avatar') ?>
                <div class="class-roster-card__info">
                    <strong><?= e($student['first_name'] . ' ' . $student['last_name']) ?></strong>
                    <span><?= e($student['email']) ?></span>
                </div>
                <i class="fa-solid fa-chevron-right class-roster-card__chevron" aria-hidden="true"></i>
            </div>
            <?php if ($summary !== []): ?>
                <?php renderClassRosterProgressSummary($summary); ?>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    <p class="class-roster-note text-muted">
        <i class="fa-solid fa-circle-info"></i>
        Click a student to view assignment, quiz, and grade progress. Progress counts published quizzes and submitted assignments.
    </p>
</div>
<script>
(function () {
    var search = document.querySelector('[data-roster-search]');
    var grid = document.querySelector('[data-roster-grid]');
    var countEl = document.querySelector('[data-roster-count]');
    if (!search || !grid) return;

    var cards = grid.querySelectorAll('[data-roster-card]');
    var total = cards.length;

    search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase();
        var visible = 0;
        cards.forEach(function (card) {
            var text = card.getAttribute('data-roster-card') || '';
            var show = !q || text.indexOf(q) !== -1;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (countEl) {
            countEl.textContent = visible + ' of ' + total + ' student' + (total !== 1 ? 's' : '');
        }
    });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
