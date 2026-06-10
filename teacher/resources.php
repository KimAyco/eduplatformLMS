<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/resources_page.php';
require_once __DIR__ . '/../includes/layout/resources_grid.php';
requireRole('teacher');
requireSchoolActive();

$ctx = resourcesPageContext();
$user = currentUser();
$sid = schoolId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleResourcesPost($ctx['list_url']);
}

$filters = [
    'created_by' => (int) $user['id'],
    'search' => trim($_GET['q'] ?? ''),
    'resource_type' => $_GET['type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'shared' => $_GET['shared'] ?? '',
    'subject_id' => (int) ($_GET['subject_id'] ?? 0) ?: null,
    'sort' => $_GET['sort'] ?? 'updated',
];
$resources = ContentResourceRepository::forSchool($sid, $filters);
$subjects = SubjectRepository::forSchool($sid);
$teacherClasses = ClassRepository::forTeacher((int) $user['id'], $sid);

$pageTitle = 'Resources';
$pageScripts = ['assets/js/resources-grid-preview.js', 'assets/js/resources-hub.js'];
$pageHeading = 'Resources';
$pageSubtitle = 'Create slide decks and documents for your classes and the Virtual Library.';
$activeMenu = 'resources';
$menuItems = teacherMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => $ctx['dashboard_url']],
    ['label' => 'Resources', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<?php renderResourcesStats($resources); ?>
<?php renderResourcesToolbar($ctx['list_url'], $filters, $subjects); ?>
<?php renderResourcesGrid($resources, $ctx['role'], $teacherClasses); ?>
<?php renderResourceAttachModal($teacherClasses, $ctx['list_url']); ?>
<?php renderResourceShareModal($subjects, $ctx['list_url']); ?>
<?php resourcesPageScripts(); ?>
<?php renderResourcesPreviewAssets(); ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
