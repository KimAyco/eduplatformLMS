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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <h1>Super Admin</h1>
        <p class="subtitle">Platform owner access only</p>

        <?php foreach ($errors as $err): ?>
            <div class="alert alert-error"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
        </form>
    </div>
</div>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
