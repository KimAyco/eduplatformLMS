<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirectByRole();
}

$schools = db()->query("SELECT id, name, slug, school_code, email, phone, address, status, registered_at
    FROM schools
    ORDER BY name ASC")->fetchAll();

$pageTitle = APP_NAME . ' — Learning Management Platform';
$showNav = true;
$showFooter = true;
$bodyClass = 'landing-page';
$mainClass = 'landing-main';

$totalSchoolCount = count($schools);

require __DIR__ . '/includes/layout/header.php';

foreach (getFlashes() as $type => $messages) {
    foreach ($messages as $msg) {
        echo '<div class="container landing-flash"><div class="alert alert-' . e($type) . '">' . e($msg) . '</div></div>';
    }
}
?>

<section class="landing-hero">
    <div class="container landing-hero-inner">
        <div class="landing-hero-content">
            <span class="landing-badge"><i class="fa-solid fa-sparkles"></i> Built for schools &amp; institutions</span>
            <h1>One platform for your entire <span class="text-gradient">learning community</span></h1>
            <div class="landing-hero-actions">
                <a href="<?= url('register/school.php') ?>" class="btn btn-primary btn-lg">
                    <i class="fa-solid fa-school"></i> Register Your School
                </a>
                <a href="<?= url('login.php') ?>" class="btn btn-ghost btn-lg">
                    <i class="fa-solid fa-right-to-bracket"></i> School Login
                </a>
            </div>
        </div>

        <div class="landing-hero-visual" aria-hidden="true">
            <div class="hero-card hero-card-main">
                <div class="hero-card-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
                <div>
                    <strong>Teacher dashboard</strong>
                    <small>Materials · Assignments · Quizzes</small>
                </div>
            </div>
            <div class="hero-card hero-card-float hero-card-1">
                <i class="fa-solid fa-book-open"></i>
                <span>12 active classes</span>
            </div>
            <div class="hero-card hero-card-float hero-card-2">
                <i class="fa-solid fa-circle-check"></i>
                <span>24 submissions graded</span>
            </div>
            <div class="hero-card hero-card-float hero-card-3">
                <i class="fa-solid fa-user-graduate"></i>
                <span>Student progress</span>
            </div>
        </div>
    </div>
</section>

<section class="schools-section" id="schools">
    <div class="container">
        <div class="landing-section-head schools-section-head">
            <div>
                <h2><i class="fa-solid fa-school"></i> Registered Schools</h2>
                <p>Browse participating schools or sign in directly with your school code.</p>
            </div>
            <?php if ($totalSchoolCount > 0): ?>
                <span class="schools-count-badge"><?= $totalSchoolCount ?> school<?= $totalSchoolCount !== 1 ? 's' : '' ?></span>
            <?php endif; ?>
        </div>

        <?php if (empty($schools)): ?>
            <div class="empty-state landing-empty">
                <div class="empty-state-icon"><i class="fa-solid fa-school"></i></div>
                <h3>No schools registered yet</h3>
                <p>Be the first to bring your institution to <?= e(APP_NAME) ?>.</p>
                <a href="<?= url('register/school.php') ?>" class="btn btn-primary">Register Your School</a>
            </div>
        <?php else: ?>
            <div class="school-grid">
                <?php foreach ($schools as $school):
                    $initial = strtoupper(mb_substr($school['name'], 0, 1));
                ?>
                <article class="school-card-modern<?= $school['status'] !== 'active' ? ' is-' . e($school['status']) : '' ?>">
                    <div class="school-card-top">
                        <div class="school-avatar" aria-hidden="true"><?= e($initial) ?></div>
                        <div class="school-card-meta">
                            <h3><?= e($school['name']) ?></h3>
                            <span class="school-email"><i class="fa-solid fa-envelope"></i> <?= e($school['email']) ?></span>
                        </div>
                    </div>
                    <div class="school-card-details">
                        <?php if ($school['address']): ?>
                            <p><i class="fa-solid fa-location-dot"></i> <?= e($school['address']) ?></p>
                        <?php elseif ($school['phone']): ?>
                            <p><i class="fa-solid fa-phone"></i> <?= e($school['phone']) ?></p>
                        <?php else: ?>
                            <p><i class="fa-solid fa-calendar"></i> Registered <?= formatDate($school['registered_at'], 'M j, Y') ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="school-card-footer">
                        <span class="badge badge-<?= e($school['status']) ?>"><?= e(SCHOOL_STATUSES[$school['status']] ?? ucfirst($school['status'])) ?></span>
                        <?php if (!empty($school['school_code']) && $school['status'] === 'active'): ?>
                            <span class="school-code-tag" title="School login code">
                                <i class="fa-solid fa-key"></i> <?= e($school['school_code']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if ($school['status'] === 'active'): ?>
                    <a href="<?= url('login.php?code=' . urlencode($school['school_code'] ?? '')) ?>" class="school-card-link">
                        Sign in <i class="fa-solid fa-arrow-right"></i>
                    </a>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/layout/footer.php'; ?>
