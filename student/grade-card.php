<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout/gradebook_table.php';
requireRole('student');
requireSchoolActive();

$user = currentUser();
$classId = (int) ($_GET['class_id'] ?? 0);
requireClassAccess($classId, 'student');

$class = getClass($classId);
if (!$class) {
    flash('error', 'Class not found.');
    redirect('student/classes.php');
}

$students = ClassGroupRepository::enrolledStudents((int) $class['class_group_id']);
$isEnrolled = false;
foreach ($students as $s) {
    if ((int) $s['id'] === (int) $user['id']) {
        $isEnrolled = true;
        break;
    }
}
if (!$isEnrolled) {
    flash('error', 'You are not enrolled in this class.');
    redirect('student/classes.php');
}

$schemeComponents = GradebookRepository::componentsForSubject((int) $class['subject_id'], schoolId());
$gradebook = GradebookRepository::gradebookForClass($classId, [$user], $schemeComponents);

$pageTitle = 'Grade card';
$pageHeading = 'My grade card';
$pageSubtitle = classDisplayName($class);
$activeMenu = 'classes';
$menuItems = studentMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'student/dashboard.php'],
    ['label' => 'My courses', 'url' => 'student/classes.php'],
    ['label' => $class['name'], 'url' => 'student/course.php?id=' . $classId],
    ['label' => 'Grade card', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="actions mb-1">
    <a href="<?= url('student/course.php?id=' . $classId) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to course</a>
</div>

<?php renderStudentGradeCardView($gradebook, $class, $user); ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
