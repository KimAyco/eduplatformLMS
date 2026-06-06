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
    <?php foreach ($classes as $c): ?>
    <a href="<?= teacherCourseUrl((int) $c['id']) ?>" class="course-card course-card-clickable">
        <div class="course-card-header">
            <h3><?= e($c['name']) ?></h3>
            <?php if ($c['group_name']): ?><small><?= e($c['group_name']) ?></small><?php endif; ?>
        </div>
        <div class="course-card-body">
            <p class="text-muted" style="font-size:.875rem;"><?= e($c['group_academic_year'] ?: 'Open course') ?></p>
        </div>
        <div class="course-card-footer">
            <span class="course-open-link">Open course <i class="fa-solid fa-arrow-right"></i></span>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
