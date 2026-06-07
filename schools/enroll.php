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

$loginUrl = schoolLoginUrl($school['school_code'] ?? '');
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
    <div class="school-public-hero-bg" aria-hidden="true"></div>
    <div class="container landing-section-container">
        <a href="<?= url('index.php#schools') ?>" class="school-public-back">
            <i class="fa-solid fa-arrow-left"></i> Back to schools
        </a>

        <div class="school-public-card">
            <div class="school-public-cover" style="background-image: url('<?= e($coverUrl) ?>')">
                <span class="badge badge-active school-public-status">Active</span>
            </div>

            <header class="school-public-header">
                <?= schoolAvatarHtml($school, 'school-public-avatar') ?>
                <div class="school-public-heading">
                    <p class="school-public-eyebrow">School portal</p>
                    <h1><?= e($school['name']) ?></h1>
                    <div class="school-public-meta-row">
                        <?php if (!empty($school['school_code'])): ?>
                            <span class="school-public-meta-chip">
                                <i class="fa-solid fa-key"></i>
                                <span><?= e($school['school_code']) ?></span>
                            </span>
                        <?php endif; ?>
                        <?php if ($school['address']): ?>
                            <span class="school-public-meta-chip">
                                <i class="fa-solid fa-location-dot"></i>
                                <span><?= e($school['address']) ?></span>
                            </span>
                        <?php endif; ?>
                        <?php if ($school['phone']): ?>
                            <span class="school-public-meta-chip">
                                <i class="fa-solid fa-phone"></i>
                                <span><?= e($school['phone']) ?></span>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <div class="school-public-layout">
                <div class="school-public-content">
                    <h2>Welcome to your digital campus</h2>
                    <p class="school-public-intro">
                        <?= e($school['name']) ?> uses <?= e(APP_NAME) ?> for course materials, assignments,
                        quizzes, and class updates. Sign in with your school account to get started.
                    </p>

                    <ul class="school-public-features">
                        <li>
                            <span class="school-public-feature-icon"><i class="fa-solid fa-book-open"></i></span>
                            <div>
                                <strong>Courses &amp; materials</strong>
                                <span>Access lessons and resources from your teachers.</span>
                            </div>
                        </li>
                        <li>
                            <span class="school-public-feature-icon"><i class="fa-solid fa-clipboard-check"></i></span>
                            <div>
                                <strong>Assignments &amp; quizzes</strong>
                                <span>Submit work and take assessments online.</span>
                            </div>
                        </li>
                        <li>
                            <span class="school-public-feature-icon"><i class="fa-solid fa-chart-line"></i></span>
                            <div>
                                <strong>Grades &amp; progress</strong>
                                <span>Track feedback and stay on top of deadlines.</span>
                            </div>
                        </li>
                    </ul>
                </div>

                <aside class="school-public-signin">
                    <div class="school-public-signin-inner">
                        <span class="school-public-signin-icon" aria-hidden="true"><i class="fa-solid fa-right-to-bracket"></i></span>
                        <h3>Sign in to continue</h3>
                        <p>Use the email and password provided by your school.</p>
                        <?php if (!empty($school['school_code'])): ?>
                            <div class="school-public-code-box">
                                <span class="school-public-code-label">School code</span>
                                <code><?= e($school['school_code']) ?></code>
                            </div>
                        <?php endif; ?>
                        <a href="<?= e($loginUrl) ?>" class="btn btn-primary btn-lg btn-block school-public-signin-btn">
                            <i class="fa-solid fa-right-to-bracket"></i> Sign in to portal
                        </a>
                        <a href="<?= url('index.php#schools') ?>" class="school-public-alt-link">Browse other schools</a>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
