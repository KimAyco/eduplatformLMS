<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('student');
requireSchoolActive();

$user = currentUser();
$classes = getStudentClasses();
$classIds = array_column($classes, 'id');
$stats = DashboardRepository::studentStats($user['id'], $classIds);

$pageTitle = 'Dashboard';
$pageHeading = 'My learning';
$activeMenu = 'dashboard';
$menuItems = studentMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'student/dashboard.php'],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="stats-grid">
    <div class="stat-card"><i class="fa-solid fa-book-open"></i><div><div class="value"><?= $stats['classes'] ?></div><div class="label">Courses</div></div></div>
    <div class="stat-card"><i class="fa-solid fa-pen-to-square"></i><div><div class="value"><?= $stats['pending_assignments'] ?></div><div class="label">Pending assignments</div></div></div>
    <div class="stat-card"><i class="fa-solid fa-circle-question"></i><div><div class="value"><?= $stats['upcoming_quizzes'] ?></div><div class="label">Available quizzes</div></div></div>
</div>

<h2 class="mb-1" style="font-size:1.1rem;font-weight:700;">My courses</h2>

<?php if (empty($classes)): ?>
<div class="empty-state">
    <i class="fa-solid fa-book-open"></i>
    <h3>No courses yet</h3>
    <p>You are not enrolled in any classes. Contact your school administrator.</p>
</div>
<?php else: ?>
<div class="course-grid">
    <?php foreach ($classes as $c): ?>
    <div class="course-card">
        <div class="course-card-header">
            <h3><?= e($c['name']) ?></h3>
            <?php if ($c['section']): ?><small>Section <?= e($c['section']) ?></small><?php endif; ?>
        </div>
        <div class="course-card-body">
            <?php if ($c['description']): ?><p class="text-muted" style="font-size:.875rem;"><?= e(mb_strimwidth($c['description'], 0, 100, '...')) ?></p><?php else: ?>
            <p class="text-muted" style="font-size:.875rem;"><?= e($c['academic_year'] ?: 'Course') ?></p><?php endif; ?>
        </div>
        <div class="course-card-actions">
            <a href="<?= url('student/materials.php?class_id=' . $c['id']) ?>" class="activity-link"><i class="fa-solid fa-file-lines"></i> Materials</a>
            <a href="<?= url('student/assignments.php') ?>" class="activity-link"><i class="fa-solid fa-pen-to-square"></i> Assignments</a>
            <a href="<?= url('student/quizzes.php') ?>" class="activity-link"><i class="fa-solid fa-circle-question"></i> Quizzes</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
