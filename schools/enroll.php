<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (isLoggedIn()) {
    redirectByRole();
}

$code = normalizeSchoolCode($_GET['code'] ?? '');
$school = $code !== '' ? resolveLoginSchool(null, 0, $code) : null;

if (!$school || $school['status'] !== 'active') {
    flash('error', 'School not found or not available.');
    redirect('index.php#schools');
}

$loginUrl = url('login.php?code=' . urlencode($school['school_code'] ?? ''));
$coverUrl = schoolCoverImageUrl($school);

$pageTitle = $school['name'] . ' — ' . APP_NAME;
$showNav = true;
$showFooter = true;
$bodyClass = 'school-public-page';
$mainClass = 'school-public-main';

require __DIR__ . '/../includes/layout/header.php';

foreach (getFlashes() as $type => $messages) {
    foreach ($messages as $msg) {
        echo '<div class="container landing-flash"><div class="alert alert-' . e($type) . ' alert-icon" data-auto-dismiss><i class="fa-solid fa-circle-' . ($type === 'error' ? 'xmark' : 'check') . '"></i><span>' . e($msg) . '</span></div></div>';
    }
}
?>

<section class="school-public-hero">
    <div class="container landing-section-container">
        <a href="<?= url('index.php#schools') ?>" class="school-public-back">
            <i class="fa-solid fa-arrow-left"></i> Back to schools
        </a>

        <div class="school-public-card">
            <div class="school-public-cover" style="background-image: url('<?= e($coverUrl) ?>')" aria-hidden="true">
                <?php if ($school['address']): ?>
                    <span class="school-public-cover-label"><i class="fa-solid fa-location-dot"></i> <?= e($school['address']) ?></span>
                <?php endif; ?>
            </div>

            <header class="school-public-header">
                <?= schoolAvatarHtml($school, 'school-public-avatar') ?>
                <div class="school-public-heading">
                    <div class="school-public-title-row">
                        <h1><?= e($school['name']) ?></h1>
                        <?php if (!empty($school['school_code'])): ?>
                            <span class="school-code-tag"><i class="fa-solid fa-key"></i> <?= e($school['school_code']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($school['phone']): ?>
                        <p class="school-public-meta"><i class="fa-solid fa-phone"></i> <?= e($school['phone']) ?></p>
                    <?php endif; ?>
                </div>
            </header>

            <div class="school-public-body">
                <h2>Welcome to <?= e($school['name']) ?></h2>
                <p class="school-public-intro">
                    Access course materials, assignments, quizzes, and class updates through <?= e(APP_NAME) ?>.
                    Sign in with your school account to enter the portal.
                </p>

                <div class="school-public-tags">
                    <span class="school-public-tag">Digital campus ready</span>
                    <span class="school-public-tag school-public-tag--muted">Teachers &amp; students</span>
                    <span class="school-public-tag school-public-tag--accent">Secure school login</span>
                </div>

                <div class="school-public-actions">
                    <a href="<?= e($loginUrl) ?>" class="btn btn-primary btn-lg">
                        <i class="fa-solid fa-right-to-bracket"></i> Sign in
                    </a>
                    <a href="<?= url('index.php#schools') ?>" class="btn btn-outline btn-lg">Browse other schools</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
