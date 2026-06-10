<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout/ai_quiz_builder.php';
requireRole('teacher');
requireSchoolActive();

$user = currentUser();
$classId = (int) ($_GET['class_id'] ?? 0);
requireClassAccess($classId, 'teacher');

$class = getClass($classId);
if (!$class) {
    flash('error', 'Class not found.');
    redirect('teacher/classes.php');
}

$sections = CourseSectionRepository::forClass($classId);
$courseUrl = teacherCourseUrl($classId);

$pageTitle = 'AI Quiz Builder';
$pageHeading = 'AI Quiz Builder';
$pageSubtitle = 'Generate exam questions from lesson materials or your own documents.';
$activeMenu = 'classes';
$menuItems = teacherMenu();
$pageScripts = ['assets/js/ai-quiz-builder.js'];
$breadcrumbs = [
    ['label' => 'Classes', 'url' => 'teacher/classes.php'],
    ['label' => classDisplayName($class), 'url' => 'teacher/course.php?id=' . $classId],
    ['label' => 'AI Quiz Builder', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div id="aiQuizBuilderApp"
    class="ai-quiz-builder-page"
    data-api-url="<?= e(url('api/ai.php')) ?>"
    data-class-id="<?= $classId ?>"
    data-csrf="<?= e(csrfToken()) ?>"
    data-course-url="<?= e($courseUrl) ?>">
    <?php if (!aiIsEnabled()): ?>
    <div class="stu-alert stu-alert--info">
        <i class="fa-solid fa-circle-info"></i>
        <div>AI features are disabled by the platform administrator.</div>
    </div>
    <?php else: ?>
    <?php renderAiQuizBuilderHero($class, $courseUrl); ?>
    <?php renderAiQuizBuilderForm($classId, $sections, $courseUrl); ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
