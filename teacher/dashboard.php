<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('teacher');
requireSchoolActive();

$user = currentUser();
$classes = getTeacherClasses();
$classIds = array_column($classes, 'id');
$stats = DashboardRepository::teacherStats($user['id'], $classIds);

$pageTitle = 'Dashboard';
$pageHeading = 'My courses';
$activeMenu = 'dashboard';
$menuItems = teacherMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'teacher/dashboard.php'],
];
$welcomeSubtitle = 'Manage your teaching courses and grade student work.';

require __DIR__ . '/../includes/layout/dashboard_header.php';
require __DIR__ . '/../includes/layout/dashboard_welcome.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon stat-card-icon-blue"><i class="fa-solid fa-book-open"></i></div>
        <div><div class="value"><?= $stats['classes'] ?></div><div class="label">Courses</div><div class="stat-card-term">This term</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon stat-card-icon-green"><i class="fa-solid fa-file-lines"></i></div>
        <div><div class="value"><?= $stats['materials'] ?></div><div class="label">Materials</div><div class="stat-card-term">This term</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon stat-card-icon-orange"><i class="fa-solid fa-pen-to-square"></i></div>
        <div><div class="value"><?= $stats['assignments'] ?></div><div class="label">Assignments</div><div class="stat-card-term">This term</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon stat-card-icon-purple"><i class="fa-solid fa-circle-question"></i></div>
        <div><div class="value"><?= $stats['quizzes'] ?></div><div class="label">Quizzes</div><div class="stat-card-term">This term</div></div>
    </div>
</div>

<h2 class="mb-1" style="font-size:1.1rem;font-weight:700;">Teaching courses</h2>

<?php if (empty($classes)): ?>
<div class="empty-state">
    <i class="fa-solid fa-chalkboard"></i>
    <h3>No courses assigned</h3>
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
