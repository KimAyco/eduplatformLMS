<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('teacher');
requireSchoolActive();

$user = currentUser();
$classes = getTeacherClasses();

$pageTitle = 'Classes';
$pageHeading = 'My classes';
$pageSubtitle = 'All courses you are assigned to teach.';
$activeMenu = 'classes';
$menuItems = teacherMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'teacher/dashboard.php'],
    ['label' => 'Classes', 'url' => 'teacher/classes.php'],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<?php if (empty($classes)): ?>
<div class="empty-state">
    <i class="fa-solid fa-chalkboard"></i>
    <h3>No classes assigned</h3>
    <p>Contact your school administrator to be assigned to a class.</p>
</div>
<?php else: ?>
<div class="course-grid">
    <?php foreach ($classes as $c):
        $bodyHtml = '<p class="text-muted course-card-desc">' . e($c['group_academic_year'] ?: 'Open course') . '</p>';
        renderCourseCard($c, teacherCourseUrl((int) $c['id']), $bodyHtml);
    endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
