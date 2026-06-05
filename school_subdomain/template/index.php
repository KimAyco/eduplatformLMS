<?php
require_once __DIR__ . '/init.php';

$school = resolveSubdomainSchool();
$errors = [];
$primaryColor = subdomainPrimaryColor();

if ($school === null) {
    http_response_code(503);
    $configError = 'This portal is not linked to an active school. Check school_code in config.php.';
} elseif ($school['status'] !== 'active') {
    http_response_code(503);
    $configError = 'This school is not available for login yet (' . ($school['status'] ?? 'inactive') . ').';
} else {
    $configError = null;
}

if ($configError === null && isLoggedIn()) {
    $user = currentUser();
    if ($user && $user['role'] !== 'super_admin' && (int) $user['school_id'] === (int) $school['id']) {
        redirectByRoleToLms();
    }
}

$loginError = trim($_GET['login_error'] ?? '');
if ($loginError !== '') {
    $errors[] = $loginError;
}

$portalTitle = $school ? subdomainPortalTitle($school) : subdomainConfig('portal_title', 'School Portal');
$pageTitle = 'Sign in — ' . $portalTitle;
$tagline = (string) subdomainConfig('tagline', 'Your dedicated learning portal');
$welcome = (string) subdomainConfig('welcome_message', '');
$logoUrl = trim((string) subdomainConfig('logo_url', ''));
$platformName = (string) subdomainConfig('platform_name', APP_NAME);
$platformUrl = (string) subdomainConfig('platform_url', subdomainConfig('lms_url', ''));
$showPlatformLink = (bool) subdomainConfig('show_platform_link', true);
$supportEmail = trim((string) subdomainConfig('support_email', ''));
$schoolInitial = $school ? strtoupper(mb_substr($school['name'], 0, 1)) : 'S';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    ? 'https' : 'http';
$returnUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
$returnUrl = strtok($returnUrl, '?') ?: $returnUrl;
$schoolCode = $school ? normalizeSchoolCode((string) ($school['school_code'] ?? '')) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= e(lmsUrl('assets/css/app.css')) ?>">
    <link rel="stylesheet" href="assets/css/subdomain.css">
    <style>:root { --subdomain-primary: <?= e($primaryColor) ?>; }</style>
</head>
<body class="subdomain-body">
<div class="subdomain-shell">
    <section class="subdomain-brand" aria-hidden="false">
        <div class="subdomain-brand-inner">
            <?php if ($logoUrl !== ''): ?>
                <img src="<?= e($logoUrl) ?>" alt="" class="subdomain-logo">
            <?php else: ?>
                <div class="subdomain-logo-fallback"><?= e($schoolInitial) ?></div>
            <?php endif; ?>
            <h1><?= e($portalTitle) ?></h1>
            <p class="subdomain-tagline"><?= e($tagline) ?></p>
            <?php if ($welcome !== ''): ?>
                <p class="subdomain-welcome"><?= e($welcome) ?></p>
            <?php endif; ?>
            <?php if ($school): ?>
            <ul class="subdomain-features">
                <li><i class="fa-solid fa-book-open"></i> Courses &amp; materials</li>
                <li><i class="fa-solid fa-pen-to-square"></i> Assignments</li>
                <li><i class="fa-solid fa-circle-question"></i> Quizzes &amp; grades</li>
            </ul>
            <?php endif; ?>
        </div>
    </section>

    <section class="subdomain-auth">
        <div class="subdomain-auth-card">
            <?php if ($configError !== null): ?>
                <div class="alert alert-error"><?= e($configError) ?></div>
                <p class="text-muted" style="font-size:.875rem;">Contact your school administrator or platform support.</p>
            <?php else: ?>
                <div class="subdomain-auth-head">
                    <h2>Welcome back</h2>
                    <p>Sign in as admin, teacher, or student</p>
                </div>

                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-error"><?= e($err) ?></div>
                <?php endforeach; ?>

                <form method="post" action="<?= e(lmsUrl('subdomain-auth.php')) ?>" class="subdomain-form">
                    <input type="hidden" name="school_code" value="<?= e($schoolCode) ?>">
                    <input type="hidden" name="return_url" value="<?= e($returnUrl) ?>">
                    <div class="form-group">
                        <label for="email">School email</label>
                        <input type="email" id="email" name="email" class="form-control" required autofocus placeholder="you@school.edu">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required placeholder="Enter your password">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block subdomain-submit">
                        <i class="fa-solid fa-right-to-bracket"></i> Sign in
                    </button>
                </form>

                <?php if ($supportEmail !== ''): ?>
                <p class="subdomain-footnote">
                    Need help? <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a>
                </p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($showPlatformLink && $platformUrl !== ''): ?>
            <p class="subdomain-platform-link">
                Powered by <a href="<?= e($platformUrl) ?>" target="_blank" rel="noopener"><?= e($platformName) ?></a>
            </p>
            <?php endif; ?>
        </div>
    </section>
</div>
<script src="<?= e(lmsUrl('assets/js/app.js')) ?>"></script>
</body>
</html>
