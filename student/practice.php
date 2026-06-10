<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('student');
requireSchoolActive();

if (!schoolPracticeQuizzesEnabled()) {
    flash('error', 'Practice quizzes are not enabled for your school.');
    redirect('student/dashboard.php');
}

$user = currentUser();
$classId = (int) ($_GET['class_id'] ?? 0);
requireClassAccess($classId, 'student');

$class = getClass($classId);
if (!$class) {
    flash('error', 'Class not found.');
    redirect('student/classes.php');
}

$sections = CourseSectionRepository::forClass($classId);
$proficiency = aiPracticeTablesReady()
    ? PracticeQuizService::proficiencyForStudent((int) $user['id'], $classId)
    : [];
$profByKey = [];
foreach ($proficiency as $p) {
    if (!empty($p['is_course_wide'])) {
        $profByKey['course'] = $p;
    } elseif (empty($p['section_id'])) {
        $profByKey['unassigned'] = $p;
    } else {
        $profByKey[(int) $p['section_id']] = $p;
    }
}

$courseProf = $profByKey['course'] ?? null;
$migrationsReady = aiPracticeTablesReady();

$pageTitle = 'Practice';
$pageHeading = 'Practice: ' . classDisplayName($class);
$pageSubtitle = 'Ungraded practice quizzes based on lesson materials. Scores track your proficiency only.';
$activeMenu = 'practice';
$menuItems = studentMenu();
$pageScripts = ['assets/js/student-practice.js'];
$breadcrumbs = [
    ['label' => 'My courses', 'url' => 'student/classes.php'],
    ['label' => classDisplayName($class), 'url' => 'student/course.php?id=' . $classId],
    ['label' => 'Practice', 'url' => ''],
];

require_once __DIR__ . '/../includes/layout/practice_config_modal.php';

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div id="studentPracticeApp" class="practice-page" data-api-url="<?= e(url('api/ai.php')) ?>" data-class-id="<?= $classId ?>" data-quiz-url="<?= e(url('student/quiz-take.php')) ?>">
    <div class="practice-intro panel">
        <p><i class="fa-solid fa-robot"></i> Practice questions are generated from your lesson materials, library resources, and exam topics. They do <strong>not</strong> affect your grade.</p>
        <a href="<?= url('student/practice-stats.php?class_id=' . $classId) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-chart-simple"></i> View all stats</a>
    </div>

    <?php if (!$migrationsReady): ?>
    <div class="stu-alert stu-alert--info">
        <i class="fa-solid fa-circle-info"></i>
        <div>Practice quizzes are being set up. Please try again later or contact your school administrator.</div>
    </div>
    <?php else: ?>

    <article class="practice-course-card panel">
        <div class="practice-course-card__badge"><i class="fa-solid fa-layer-group"></i></div>
        <div class="practice-course-card__body">
            <h3>Full course practice</h3>
            <p class="text-muted">Questions drawn from <strong>all lessons</strong> in this course — materials, library items, and exam topics combined.</p>
            <?php if ($courseProf): ?>
            <span class="practice-level practice-level--<?= e($courseProf['proficiency_level']) ?>">
                <?= e(PracticeQuizService::proficiencyLabel($courseProf['proficiency_level'])) ?>
                · <?= e($courseProf['avg_score_pct']) ?>% avg
                · <?= (int) $courseProf['attempts'] ?> attempt<?= (int) $courseProf['attempts'] !== 1 ? 's' : '' ?>
            </span>
            <?php else: ?>
            <span class="text-muted">No full-course attempts yet</span>
            <?php endif; ?>
        </div>
        <button type="button" class="btn btn-primary" data-start-practice data-practice-scope="course" data-practice-label="Full course practice">
            <i class="fa-solid fa-play"></i> Start full course practice
        </button>
        <div class="practice-status text-muted" data-practice-status="course" hidden></div>
    </article>

    <h2 class="practice-section-heading">Practice by lesson</h2>
    <div class="practice-lessons">
        <?php
        $lessons = array_merge([['id' => 0, 'title' => 'Unassigned materials']], $sections);
        foreach ($lessons as $i => $section):
            $sid = (int) ($section['id'] ?? 0);
            $profKey = $sid ?: 'unassigned';
            $prof = $profByKey[$profKey] ?? null;
            $level = $prof['proficiency_level'] ?? 'beginner';
            $scope = $sid ? 'lesson' : 'unassigned';
        ?>
        <article class="practice-lesson-card panel">
            <div class="practice-lesson-card__head">
                <span class="practice-lesson-index"><?= $sid ? (int) $i : '—' ?></span>
                <div>
                    <h3><?= e($section['title']) ?></h3>
                    <?php if ($prof): ?>
                    <span class="practice-level practice-level--<?= e($level) ?>">
                        <?= e(PracticeQuizService::proficiencyLabel($level)) ?>
                        · <?= e($prof['avg_score_pct']) ?>% avg
                        · <?= (int) $prof['attempts'] ?> attempt<?= (int) $prof['attempts'] !== 1 ? 's' : '' ?>
                    </span>
                    <?php else: ?>
                    <span class="text-muted">No practice attempts yet</span>
                    <?php endif; ?>
                </div>
            </div>
            <button type="button" class="btn btn-primary btn-sm" data-start-practice data-practice-scope="<?= e($scope) ?>" data-section-id="<?= $sid ?>" data-practice-label="<?= e($section['title']) ?>">
                <i class="fa-solid fa-play"></i> Start practice
            </button>
            <div class="practice-status text-muted" data-practice-status="<?= e($scope . ($sid ? '-' . $sid : '')) ?>" hidden></div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($migrationsReady): ?>
<?php renderPracticeConfigModal(); ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
