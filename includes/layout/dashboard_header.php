<?php
$user = currentUser();
$role = $user['role'] ?? '';
$menuItems = $menuItems ?? [];
$breadcrumbs = $breadcrumbs ?? [];
$initials = userInitials($user);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body class="moodle-body">
<div class="moodle-app">
    <header class="moodle-navbar">
        <button type="button" class="drawer-toggle" id="drawerToggle" aria-label="Toggle navigation">
            <i class="fa-solid fa-bars"></i>
        </button>
        <a href="<?= url('index.php') ?>" class="navbar-brand">
            <i class="fa-solid fa-graduation-cap"></i>
            <span><?= e(APP_NAME) ?></span>
        </a>
        <?php if ($user['school_name'] ?? null): ?>
            <span class="navbar-context"><?= e($user['school_name']) ?></span>
        <?php endif; ?>
        <div class="navbar-spacer"></div>
        <div class="user-menu" id="userMenu">
            <button type="button" class="user-menu-btn" id="userMenuBtn">
                <span class="user-avatar"><?= e($initials) ?></span>
                <span class="user-name"><?= e($user['first_name']) ?></span>
                <i class="fa-solid fa-chevron-down"></i>
            </button>
            <div class="user-dropdown" id="userDropdown">
                <div class="user-dropdown-header">
                    <strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                    <small><?= e(ROLES[$role] ?? $role) ?></small>
                </div>
                <a href="<?= url('logout.php') ?>"><i class="fa-solid fa-right-from-bracket"></i> Log out</a>
            </div>
        </div>
    </header>

    <div class="moodle-layout">
        <aside class="moodle-drawer" id="moodleDrawer">
            <nav class="drawer-nav">
                <?php foreach ($menuItems as $item): ?>
                    <a href="<?= url($item['url']) ?>" class="drawer-link <?= ($activeMenu ?? '') === $item['key'] ? 'active' : '' ?>">
                        <i class="fa-solid <?= e($item['icon'] ?? 'fa-circle') ?>"></i>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>
        <div class="drawer-overlay" id="drawerOverlay"></div>

        <main class="moodle-main">
            <?php require __DIR__ . '/breadcrumbs.php'; ?>
            <div class="page-header">
                <h1><?= e($pageHeading ?? $pageTitle ?? '') ?></h1>
            </div>

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
