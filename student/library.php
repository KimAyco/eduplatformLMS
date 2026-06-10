<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('student');
requireSchoolActive();
requireLibraryAccess();

$sid = schoolId();

$filters = [
    'browse_role' => 'student',
    'search' => trim($_GET['q'] ?? ''),
    'resource_kind' => $_GET['kind'] ?? '',
    'subject_id' => (int) ($_GET['subject_id'] ?? 0) ?: null,
    'type' => $_GET['type'] ?? '',
];
$resources = LibraryResourceRepository::forSchool($sid, $filters);
$subjects = SubjectRepository::forSchool($sid);

$pageTitle = 'Virtual Library';
$pageHeading = 'Virtual Library';
$pageSubtitle = 'Browse learning resources shared by your school.';
$activeMenu = 'library';
$menuItems = studentMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'student/dashboard.php'],
    ['label' => 'Virtual Library', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
require __DIR__ . '/../includes/layout/library_grid.php';
?>

<?php renderLibraryFilters('student/library.php', $subjects, $filters); ?>
<?php renderLibraryGrid($resources, 'browse'); ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
