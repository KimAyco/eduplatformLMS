<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirectByRole();
}

$prefillCode = normalizeSchoolCode($_GET['code'] ?? '');
$school = null;

if ($prefillCode !== '') {
    $school = resolveLoginSchool(null, 0, $prefillCode);
} elseif (trim($_GET['school'] ?? '') !== '') {
    $school = resolveLoginSchool(trim($_GET['school']), 0, null);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postedSchoolId = (int) ($_POST['school_id'] ?? 0);
    $schoolCode = normalizeSchoolCode($_POST['school_code'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($postedSchoolId > 0) {
        $school = resolveLoginSchool(null, $postedSchoolId, null);
    } elseif ($schoolCode !== '') {
        $school = resolveLoginSchool(null, 0, $schoolCode);
    } else {
        $school = null;
    }

    if ($postedSchoolId <= 0 && $schoolCode === '') {
        $errors[] = 'School code is required.';
    } elseif ($school === null) {
        $errors[] = $postedSchoolId > 0
            ? 'Invalid school selected.'
            : 'Invalid school code. Please check the code and try again.';
    } elseif ($school['status'] !== 'active') {
        $errors[] = 'This school is not available for login yet.';
    } elseif ($email === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    } elseif (!checkLoginRateLimit($email)) {
        $errors[] = loginLockedMessage();
    } else {
        $result = authenticate($email, $password, 'school', (int) $school['id']);

        if ($result === null) {
            recordLoginAttempt($email, false);
            $errors[] = 'Invalid email or password for ' . $school['name'] . '.';
        } elseif (isset($result['error']) && $result['error'] === 'school_inactive') {
            $status = $result['school_status'];
            if ($status === 'pending') {
                $errors[] = 'Your school registration is pending approval.';
            } elseif ($status === 'rejected') {
                $errors[] = 'Your school registration was rejected.';
            } elseif ($status === 'suspended') {
                $errors[] = 'Your school account has been suspended.';
            } else {
                $errors[] = 'Your school account is not active.';
            }
        } else {
            recordLoginAttempt($email, true);
            loginUser($result);
            session_write_close();
            redirectByRole();
        }
    }

    setOld(['email' => $email, 'school_code' => $schoolCode]);
}

$schoolPreselected = $school
    && $school['status'] === 'active'
    && (
        (int) ($_POST['school_id'] ?? 0) > 0
        || ($_SERVER['REQUEST_METHOD'] !== 'POST' && ($prefillCode !== '' || trim($_GET['school'] ?? '') !== ''))
    );

$pageTitle = $schoolPreselected
    ? 'Sign in — ' . $school['name']
    : 'Login — ' . APP_NAME;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    clearOld();
}

$formAction = 'login.php';
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
        <?php if ($schoolPreselected): ?>
            <div class="school-login-banner">
                <i class="fa-solid fa-school"></i>
                <div>
                    <small>Signing in to</small>
                    <strong><?= e($school['name']) ?></strong>
                </div>
            </div>
            <h1>School Login</h1>
            <p class="subtitle">Sign in as School Admin, Teacher, or Student</p>
        <?php else: ?>
            <h1>School Login</h1>
            <p class="subtitle">Enter your school code, email, and password</p>
        <?php endif; ?>

        <?php foreach ($errors as $err): ?>
            <div class="alert alert-error"><?= e($err) ?></div>
        <?php endforeach; ?>
        <?php foreach (getFlashes() as $type => $messages): ?>
            <?php foreach ($messages as $msg): ?>
                <div class="alert alert-<?= e($type) ?>"><?= e($msg) ?></div>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <form method="post" action="<?= url($formAction) ?>">
            <?= csrfField() ?>

            <?php if ($schoolPreselected): ?>
                <input type="hidden" name="school_id" value="<?= (int) $school['id'] ?>">
            <?php else: ?>
                <div class="form-group">
                    <label for="school_code">School Code</label>
                    <input type="text" id="school_code" name="school_code" class="form-control school-code-input"
                        value="<?= old('school_code') ?>"
                        placeholder="e.g. TEST-SCHOOL" required autofocus
                        autocomplete="off" spellcheck="false"
                        style="text-transform:uppercase; letter-spacing:0.08em; font-weight:600;">
                    <small class="text-muted">Ask your school admin for the code, or pick a school on the <a href="<?= url('index.php#schools') ?>">homepage</a>.</small>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= old('email') ?>" required <?= $schoolPreselected ? 'autofocus' : '' ?>>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
        </form>

        <?php if ($schoolPreselected): ?>
            <p class="mt-1 text-muted" style="text-align:center;font-size:.875rem;">
                <a href="<?= url('login.php') ?>">Use a different school code</a>
            </p>
        <?php endif; ?>

        <p class="mt-1 text-muted" style="text-align:center;font-size:.875rem;">
            Don't have a school account? <a href="<?= url('register/school.php') ?>">Register your school</a>
        </p>
        <p class="text-muted" style="text-align:center;font-size:.875rem;">
            <a href="<?= url('index.php') ?>">Back to home</a>
        </p>
    </div>
</div>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
