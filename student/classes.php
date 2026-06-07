<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('student');
requireSchoolActive();

$classes = getStudentClasses();

$pageTitle = 'My courses';
$pageHeading = 'My courses';
$pageSubtitle = 'All classes you are enrolled in this term.';
$activeMenu = 'courses';
$menuItems = studentMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'student/dashboard.php'],
    ['label' => 'My courses', 'url' => 'student/classes.php'],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<?php if (empty($classes)): ?>
<div class="empty-state">
    <i class="fa-solid fa-book-open"></i>
    <h3>No courses yet</h3>
    <p>You are not enrolled in any classes. Contact your school administrator.</p>
</div>
<?php else: ?>
<div class="course-grid">
    <?php foreach ($classes as $c):
        if (!empty($c['description'])) {
            $bodyHtml = '<p class="text-muted course-card-desc">' . e(mb_strimwidth($c['description'], 0, 100, '…')) . '</p>';
        } else {
            $bodyHtml = '<p class="text-muted course-card-desc">' . e($c['group_academic_year'] ?: 'Open course') . '</p>';
        }
        renderCourseCard($c, studentCourseUrl((int) $c['id']), $bodyHtml);
    endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
