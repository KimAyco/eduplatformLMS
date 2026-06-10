<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();
requireSchoolActive();

$user = currentUser();
if (!canUseMessaging($user)) {
    http_response_code(403);
    die('Messaging is not available for your account.');
}

$role = $user['role'] ?? '';
$userId = (int) $user['id'];
$dashboardUrl = dashboardHomeForRole($role);
$startUserId = (int) ($_GET['user_id'] ?? 0);

$pageTitle = 'Messages';
$pageHeading = 'Messages';
$pageSubtitle = 'Direct messages with people at your school.';
$hidePageHeader = true;
$activeMenu = 'messages';
$menuItems = menuItemsForRole($role);
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => $dashboardUrl],
    ['label' => 'Messages', 'url' => ''],
];
$pageScripts = ['assets/js/messenger.js'];

require __DIR__ . '/includes/layout/dashboard_header.php';
require __DIR__ . '/includes/layout/messenger_ui.php';
require __DIR__ . '/includes/layout/dashboard_footer.php';
