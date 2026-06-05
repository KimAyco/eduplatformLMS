<?php
require_once __DIR__ . '/includes/bootstrap.php';

$token = trim($_GET['t'] ?? '');

if ($token !== '') {
    $parsed = parseSubdomainLoginToken($token);

    if ($parsed === null) {
        flash('error', 'Your sign-in link expired or is invalid. Please log in again from your school portal.');
        redirect('login.php');
    }

    $stmt = db()->prepare('SELECT u.*, s.status AS school_status, s.name AS school_name
        FROM users u
        LEFT JOIN schools s ON s.id = u.school_id
        WHERE u.id = ? AND u.status = ? AND u.role IN (?, ?, ?)');
    $stmt->execute([
        $parsed['user_id'],
        'active',
        'school_admin',
        'teacher',
        'student',
    ]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['school_id'] !== $parsed['school_id']) {
        flash('error', 'Unable to complete sign-in. Please try again from your school portal.');
        redirect('login.php');
    }

    if ($user['school_status'] !== 'active') {
        flash('error', 'Your school account is not active.');
        redirect('login.php');
    }

    loginUser($user);
    session_write_close();
    redirectByRole();
}

if (isLoggedIn()) {
    redirectByRole();
}

flash('error', 'Missing sign-in token. Please log in from your school portal.');
redirect('login.php');
