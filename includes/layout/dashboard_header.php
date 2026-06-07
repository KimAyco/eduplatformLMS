<?php
$user = currentUser();
$role = $user['role'] ?? '';
$menuItems = $menuItems ?? [];
$breadcrumbs = $breadcrumbs ?? [];
$initials = userInitials($user);
$drawerLabel = drawerLabel($role);
$drawerMainItems = [];
$drawerBottomItems = [];
foreach ($menuItems as $item) {
    if (!empty($item['bottom'])) {
        $drawerBottomItems[] = $item;
    } else {
        $drawerMainItems[] = $item;
    }
}
$navbarContextExtra = '';
if ($role === 'student') {
    $studentClasses = getStudentClasses($user['id']);
    if (!empty($studentClasses)) {
        $first = $studentClasses[0];
        $navbarContextExtra = $first['group_name'] ?? ($first['group_academic_year'] ?? '');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? APP_NAME) ?></title>
    <?php require __DIR__ . '/favicon.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body class="moodle-body">
<?php require __DIR__ . '/page_loader.php'; ?>
<div class="moodle-app">
    <header class="moodle-navbar">
        <button type="button" class="drawer-toggle" id="drawerToggle" aria-label="Toggle navigation">
            <i class="fa-solid fa-bars"></i>
        </button>
        <a href="<?= url('index.php') ?>" class="navbar-brand">
            <?= siteLogoImg('site-logo site-logo--navbar') ?>
            <span class="navbar-brand-name"><?= e(APP_NAME) ?></span>
        </a>
        <?php if ($user['school_name'] ?? null): ?>
            <span class="navbar-context"><?= e($user['school_name']) ?></span>
        <?php endif; ?>
        <?php if ($navbarContextExtra !== ''): ?>
            <span class="navbar-term-chip"><?= e($navbarContextExtra) ?></span>
        <?php endif; ?>
        <div class="navbar-spacer"></div>
        <div class="navbar-notifications">
            <button type="button" class="navbar-notif-btn" id="notifBtn" aria-label="Notifications">
                <i class="fa-regular fa-bell"></i>
            </button>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-dropdown-header">Notifications</div>
                <div class="notif-empty">No notifications yet</div>
            </div>
        </div>
        <div class="user-menu" id="userMenu">
            <button type="button" class="user-menu-btn" id="userMenuBtn">
                <?= userAvatarHtml($user, 'user-avatar') ?>
                <span class="user-name"><?= e($user['first_name']) ?></span>
                <i class="fa-solid fa-chevron-down"></i>
            </button>
            <div class="user-dropdown" id="userDropdown">
                <div class="user-dropdown-header">
                    <strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                    <span class="role-badge"><?= e(ROLES[$role] ?? $role) ?></span>
                </div>
                <a href="<?= url('profile.php') ?>"><i class="fa-solid fa-circle-user"></i> My profile</a>
                <a href="<?= url('index.php') ?>"><i class="fa-solid fa-house"></i> Back to home</a>
                <a href="<?= url('logout.php') ?>"><i class="fa-solid fa-right-from-bracket"></i> Log out</a>
            </div>
        </div>
    </header>

    <div class="moodle-layout">
        <aside class="moodle-drawer" id="moodleDrawer">
            <div class="drawer-brand">
                <span class="drawer-brand-label"><?= e($drawerLabel) ?></span>
            </div>
            <nav class="drawer-nav">
                <div class="drawer-nav-main">
                <?php foreach ($drawerMainItems as $item): ?>
                    <?php if (!empty($item['section'])): ?>
                        <span class="drawer-section-label"><?= e($item['section']) ?></span>
                    <?php else: ?>
                    <a href="<?= url($item['url']) ?>" class="drawer-link <?= ($activeMenu ?? '') === ($item['key'] ?? '') ? 'active' : '' ?>">
                        <i class="fa-solid <?= e($item['icon'] ?? 'fa-circle') ?>"></i>
                        <span><?= e($item['label']) ?></span>
                        <?php if (!empty($item['badge'])): ?>
                            <span class="drawer-link-badge"><?= (int) $item['badge'] ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                <?php endforeach; ?>
                </div>
                <?php if ($drawerBottomItems): ?>
                <div class="drawer-nav-bottom">
                <?php foreach ($drawerBottomItems as $item): ?>
                    <a href="<?= url($item['url']) ?>" class="drawer-link <?= ($activeMenu ?? '') === ($item['key'] ?? '') ? 'active' : '' ?>">
                        <i class="fa-solid <?= e($item['icon'] ?? 'fa-circle') ?>"></i>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </nav>
        </aside>
        <div class="drawer-overlay" id="drawerOverlay"></div>

        <main class="moodle-main">
            <?php require __DIR__ . '/breadcrumbs.php'; ?>
            <?php if (empty($hidePageHeader)): ?>
            <div class="page-header admin-page-header">
                <div class="admin-page-header-text">
                    <h1><?= e($pageHeading ?? $pageTitle ?? '') ?></h1>
                    <?php if (!empty($pageSubtitle)): ?>
                        <p class="page-subtitle"><?= e($pageSubtitle) ?></p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($pageActionUrl) && !empty($pageActionLabel)): ?>
                    <a href="<?= url($pageActionUrl) ?>" class="btn btn-primary">
                        <?php if (!empty($pageActionIcon)): ?><i class="fa-solid <?= e($pageActionIcon) ?>"></i><?php endif; ?>
                        <?= e($pageActionLabel) ?>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div id="toastContainer" class="toast-container">
                <?php foreach (getFlashes() as $type => $messages): ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="toast toast-<?= e($type) ?>" data-toast>
                            <i class="fa-solid <?= $type === 'success' ? 'fa-circle-check' : ($type === 'error' ? 'fa-circle-xmark' : 'fa-circle-info') ?>"></i>
                            <span><?= e($msg) ?></span>
                            <button type="button" class="toast-close" aria-label="Close">&times;</button>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>

            <div class="page-content">
