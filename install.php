<?php
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/config/app.php';

$errors = [];
$success = false;

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s', env('DB_HOST'), env('DB_NAME'), env('DB_CHARSET', 'utf8mb4')),
        env('DB_USER'),
        env('DB_PASS', ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Database connection failed. Check your .env file. ' . (APP_DEBUG ? htmlspecialchars($e->getMessage()) : ''));
}

$existing = $pdo->query("SELECT id FROM users WHERE role = 'super_admin' AND school_id IS NULL LIMIT 1")->fetch();
if ($existing) {
    die('Installation already complete. Super admin exists. Delete install.php for security.');
}

$email = trim(env('SUPER_ADMIN_EMAIL', ''));
$password = env('SUPER_ADMIN_PASSWORD', '');
$firstName = trim(env('SUPER_ADMIN_FIRST_NAME', 'Platform'));
$lastName = trim(env('SUPER_ADMIN_LAST_NAME', 'Owner'));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Set SUPER_ADMIN_EMAIL in your .env file.';
}
if ($password === '' || strlen($password) < 8) {
    $errors[] = 'Set SUPER_ADMIN_PASSWORD in your .env file (minimum 8 characters).';
}

if (empty($errors) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (school_id, email, password_hash, role, first_name, last_name, status) VALUES (NULL, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$email, $hash, 'super_admin', $firstName, $lastName, 'active']);
    $success = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install — <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL . '/assets/css/app.css') ?>">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <h1>Install <?= htmlspecialchars(APP_NAME) ?></h1>
        <?php if ($success): ?>
            <div class="alert alert-success">Super admin created successfully. Delete <code>install.php</code> now.</div>
            <a href="<?= htmlspecialchars(BASE_URL . '/superadmin/login.php') ?>" class="btn btn-primary btn-block">Go to Super Admin Login</a>
        <?php else: ?>
            <?php foreach ($errors as $err): ?>
                <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
            <?php if (empty($errors)): ?>
                <p class="subtitle">Create the platform super admin from your .env settings.</p>
                <table class="mb-1" style="width:100%;font-size:.875rem;">
                    <tr><th>Email</th><td><?= htmlspecialchars($email) ?></td></tr>
                    <tr><th>Name</th><td><?= htmlspecialchars("$firstName $lastName") ?></td></tr>
                </table>
                <form method="post">
                    <button type="submit" class="btn btn-primary btn-block">Run Installation</button>
                </form>
            <?php else: ?>
                <p class="text-muted">Copy <code>.env.example</code> to <code>.env</code> and configure it, then refresh.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
