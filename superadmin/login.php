<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (isLoggedIn() && currentUser()['role'] === 'super_admin') {
    redirect('superadmin/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    } elseif (!checkLoginRateLimit($email)) {
        $errors[] = loginLockedMessage();
    } else {
        $user = authenticate($email, $password, 'super_admin');
        if (!$user || isset($user['error'])) {
            recordLoginAttempt($email, false);
            $errors[] = 'Invalid super admin credentials.';
        } else {
            recordLoginAttempt($email, true);
            loginUser($user);
            session_write_close();
            redirect('superadmin/dashboard.php');
        }
    }
}

$pageTitle = 'Super Admin Login — ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?php require __DIR__ . '/../includes/layout/favicon.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body class="moodle-body">
<?php require __DIR__ . '/../includes/layout/page_loader.php'; ?>
<div class="auth-page auth-page--superadmin">
    <div class="auth-card-split auth-card-split--superadmin">
        <div class="auth-split-panel auth-split-panel--superadmin">
            <div class="auth-split-panel-bg" aria-hidden="true">
                <span class="auth-split-panel-orb auth-split-panel-orb--1"></span>
                <span class="auth-split-panel-orb auth-split-panel-orb--2"></span>
                <span class="auth-split-panel-orb auth-split-panel-orb--3"></span>
            </div>
            <div class="auth-split-panel-content">
                <div class="auth-superadmin-logo-wrap">
                    <img src="<?= url('assets/img/NSlogo.png') ?>" alt="Nehemiah Solutions" class="auth-superadmin-logo">
                </div>
                <h2>Super Admin</h2>
            </div>
        </div>

        <div class="auth-split-form auth-split-form--superadmin">
            <div class="auth-top">
                <a href="<?= url('index.php') ?>" class="auth-footer-link muted"><i class="fa-solid fa-arrow-left"></i> Back to home</a>
            </div>

            <div class="auth-superadmin-logo-wrap auth-superadmin-logo-wrap--mobile">
                <img src="<?= url('assets/img/NSlogo.png') ?>" alt="Nehemiah Solutions" class="auth-superadmin-logo">
            </div>

            <div class="auth-form-heading">
                <span class="auth-superadmin-badge"><i class="fa-solid fa-user-shield"></i> Super Admin</span>
                <h1>Sign in</h1>
                <p class="auth-form-subtitle">Use your platform owner credentials.</p>
            </div>

            <?php foreach ($errors as $err): ?>
                <div class="alert alert-error alert-icon"><i class="fa-solid fa-circle-exclamation"></i><span><?= e($err) ?></span></div>
            <?php endforeach; ?>

            <form method="post" class="auth-superadmin-form">
                <?= csrfField() ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-wrap">
                        <span class="input-wrap-icon"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" id="email" name="email" class="form-control" value="<?= e(old('email')) ?>" required autofocus placeholder="admin@example.com">
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <span class="input-wrap-icon"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" id="password" name="password" class="form-control" required placeholder="••••••••">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block auth-submit auth-submit--superadmin">
                    <i class="fa-solid fa-right-to-bracket"></i> Sign in
                </button>
            </form>

            <p class="auth-superadmin-note"><i class="fa-solid fa-lock"></i> Restricted area — authorized personnel only.</p>
        </div>
    </div>
</div>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
