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
$backUrl = $schoolPreselected ? url('index.php#schools') : url('index.php');
$authPanelClass = $schoolPreselected ? 'auth-split-panel auth-split-panel--school' : 'auth-split-panel auth-split-panel--platform';
$authPanelCover = ($schoolPreselected && $school) ? schoolCoverImageUrl($school) : '';
$authPanelStyle = $authPanelCover !== '' ? ' style="--auth-panel-cover: url(\'' . e($authPanelCover) . '\')"' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?php require __DIR__ . '/includes/layout/favicon.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body>
<?php require __DIR__ . '/includes/layout/page_loader.php'; ?>
<div class="auth-page">
    <div class="auth-card-split">
        <div class="<?= e($authPanelClass) ?>"<?= $authPanelStyle ?>>
            <div class="auth-split-panel-bg" aria-hidden="true">
                <span class="auth-split-panel-orb auth-split-panel-orb--1"></span>
                <span class="auth-split-panel-orb auth-split-panel-orb--2"></span>
                <span class="auth-split-panel-orb auth-split-panel-orb--3"></span>
            </div>
            <div class="auth-split-panel-content">
                <?= authPanelBrandHtml($schoolPreselected ? $school : null) ?>
                <?php if ($schoolPreselected): ?>
                    <?php if (!empty($school['school_code'])): ?>
                        <span class="auth-panel-code"><i class="fa-solid fa-key"></i><?= e($school['school_code']) ?></span>
                    <?php endif; ?>
                    <h2><?= e($school['name']) ?></h2>
                    <p class="auth-panel-tagline">Your learning portal — courses, assignments, and progress in one place.</p>
                <?php else: ?>
                    <span class="auth-panel-eyebrow">Welcome back</span>
                    <h2><?= e(APP_NAME) ?></h2>
                    <p class="auth-panel-tagline">Your way to knowledge. Connect with your school and pick up where you left off.</p>
                    <ul class="auth-panel-features">
                        <li><i class="fa-solid fa-school"></i><span>Find your school</span></li>
                        <li><i class="fa-solid fa-graduation-cap"></i><span>Learn anywhere</span></li>
                        <li><i class="fa-solid fa-shield-halved"></i><span>Secure sign-in</span></li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="auth-split-form">
            <div class="auth-top">
                <a href="<?= e($backUrl) ?>" class="auth-footer-link muted"><i class="fa-solid fa-arrow-left"></i> Back<?= $schoolPreselected ? ' to schools' : ' to home' ?></a>
            </div>

            <div class="auth-brand" style="margin-bottom:1.25rem;">
                <span class="auth-brand-name" style="font-size:1.25rem;">
                    <?= $schoolPreselected ? 'Sign in to ' . e($school['name']) : 'Sign in to your school' ?>
                </span>
            </div>

            <?php foreach ($errors as $err): ?>
                <div class="alert alert-error alert-icon"><i class="fa-solid fa-circle-exclamation"></i><span><?= e($err) ?></span></div>
            <?php endforeach; ?>
            <?php foreach (getFlashes() as $type => $messages): ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="alert alert-<?= e($type) ?> alert-icon" data-auto-dismiss>
                        <i class="fa-solid <?= $type === 'success' ? 'fa-circle-check' : ($type === 'error' ? 'fa-circle-xmark' : 'fa-circle-info') ?>"></i>
                        <span><?= e($msg) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <form method="post" action="<?= url($formAction) ?>">
                <?= csrfField() ?>

                <?php if ($schoolPreselected): ?>
                    <input type="hidden" name="school_id" value="<?= (int) $school['id'] ?>">
                <?php else: ?>
                    <div class="form-group">
                        <label for="school_code">School Code</label>
                        <div class="input-wrap">
                            <span class="input-wrap-icon"><i class="fa-solid fa-school"></i></span>
                            <input type="text" id="school_code" name="school_code" class="form-control school-code-input"
                                value="<?= old('school_code') ?>"
                                placeholder="e.g. TEST-SCHOOL" required autofocus
                                autocomplete="off" spellcheck="false">
                        </div>
                        <span class="input-hint">Ask your school admin for the code, or pick a school on the <a href="<?= url('index.php#schools') ?>">homepage</a>.</span>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-wrap">
                        <span class="input-wrap-icon"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" id="email" name="email" class="form-control" value="<?= old('email') ?>" required placeholder="you@school.edu" <?= $schoolPreselected ? 'autofocus' : '' ?>>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <span class="input-wrap-icon"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" id="password" name="password" class="form-control" required placeholder="••••••••">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block auth-submit">
                    <i class="fa-solid fa-right-to-bracket"></i> Sign In
                </button>
            </form>
        </div>
    </div>
</div>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
