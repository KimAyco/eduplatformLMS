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
<body class="<?= e($bodyClass ?? '') ?>">
<?php require __DIR__ . '/page_loader.php'; ?>
<?php if (!empty($showNav) && $showNav): ?>
<header class="public-header">
    <div class="container header-inner">
        <a href="<?= url('index.php') ?>" class="logo">
            <span class="logo-mark"><?= siteLogoImg('site-logo site-logo--header-mark') ?></span>
            <span class="logo-text"><span class="logo-text-edu">Edu</span><span class="logo-text-platform">Platform</span></span>
        </a>
        <nav class="public-nav">
            <a href="<?= url('login.php') ?>" class="nav-link">Login</a>
            <a href="<?= url('register/school.php') ?>" class="btn btn-primary btn-sm">Register School</a>
        </nav>
    </div>
</header>
<?php endif; ?>
<main class="<?= e($mainClass ?? 'container') ?>">
