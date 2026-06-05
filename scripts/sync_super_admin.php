<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$email = trim(env('SUPER_ADMIN_EMAIL', ''));
$password = env('SUPER_ADMIN_PASSWORD', '');
$firstName = trim(env('SUPER_ADMIN_FIRST_NAME', 'Platform'));
$lastName = trim(env('SUPER_ADMIN_LAST_NAME', 'Owner'));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Set a valid SUPER_ADMIN_EMAIL in .env\n");
    exit(1);
}
if ($password === '' || strlen($password) < 8) {
    fwrite(STDERR, "Set SUPER_ADMIN_PASSWORD in .env (minimum 8 characters).\n");
    exit(1);
}

$pdo = db();
$stmt = $pdo->prepare("SELECT id, email FROM users WHERE role = 'super_admin' AND school_id IS NULL LIMIT 1");
$stmt->execute();
$user = $stmt->fetch();

$hash = password_hash($password, PASSWORD_DEFAULT);

if ($user) {
    $update = $pdo->prepare('UPDATE users SET email = ?, password_hash = ?, first_name = ?, last_name = ?, status = ? WHERE id = ?');
    $update->execute([$email, $hash, $firstName, $lastName, 'active', $user['id']]);
    echo 'Super admin updated.' . PHP_EOL;
    echo '  Previous email: ' . $user['email'] . PHP_EOL;
    echo '  New email: ' . $email . PHP_EOL;
} else {
    $insert = $pdo->prepare('INSERT INTO users (school_id, email, password_hash, role, first_name, last_name, status) VALUES (NULL, ?, ?, ?, ?, ?, ?)');
    $insert->execute([$email, $hash, 'super_admin', $firstName, $lastName, 'active']);
    echo 'Super admin created.' . PHP_EOL;
    echo '  Email: ' . $email . PHP_EOL;
}

echo 'You can sign in at superadmin/login.php' . PHP_EOL;
