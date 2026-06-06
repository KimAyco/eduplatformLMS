<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirectByRole();
}

$schools = db()->query("SELECT id, name, slug, school_code, email, phone, address, cover_image, logo_image, status, registered_at
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
        echo '<div class="container landing-flash"><div class="alert alert-' . e($type) . ' alert-icon" data-auto-dismiss><i class="fa-solid fa-circle-check"></i><span>' . e($msg) . '</span></div></div>';
    }
}
?>

<section class="schools-section" id="schools">
    <div class="schools-section-decor" aria-hidden="true"></div>
    <div class="container landing-section-container">
        <div class="schools-section-header" data-landing-reveal>
            <div class="schools-section-head">
                <div class="schools-section-title">
                    <h2><i class="fa-solid fa-school"></i> Registered Schools</h2>
                    <?php if ($totalSchoolCount > 0): ?>
                        <span class="schools-count-badge"><?= $totalSchoolCount ?> school<?= $totalSchoolCount !== 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                </div>
                <p class="schools-section-sub">Find your institution and sign in with your school code.</p>
            </div>

            <?php if (!empty($schools)): ?>
                <div class="schools-search-wrap">
                    <label for="schoolSearch" class="visually-hidden">Search schools</label>
                    <div class="schools-search">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input
                            type="search"
                            id="schoolSearch"
                            class="form-control schools-search-input"
                            placeholder="Search by name, code, email, or location…"
                            autocomplete="off"
                        >
                        <button type="button" class="schools-search-clear" id="schoolSearchClear" aria-label="Clear search" hidden>
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>
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
            <div class="school-carousel-wrap">
                <button type="button" class="school-carousel-nav school-carousel-nav--prev" id="schoolCarouselPrev" aria-label="Previous schools">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <div class="school-grid school-carousel" id="schoolGrid">
                <?php foreach ($schools as $i => $school):
                    $revealDelay = min(($i % 6) + 1, 6);
                    $searchText = mb_strtolower(implode(' ', array_filter([
                        $school['name'],
                        $school['school_code'] ?? '',
                        $school['email'],
                        $school['phone'] ?? '',
                        $school['address'] ?? '',
                    ])));
                ?>
                <?php
                    $cardClass = 'school-card-modern' . ($school['status'] !== 'active' ? ' is-' . e($school['status']) : '');
                    $cardAttrs = 'class="' . $cardClass . '" data-search="' . e($searchText) . '" data-status="' . e($school['status']) . '" data-landing-reveal data-landing-delay="' . (int) $revealDelay . '"';
                    $isActiveCard = $school['status'] === 'active' && !empty($school['school_code']);
                    $schoolPageUrl = $isActiveCard ? schoolEnrollUrl($school['school_code']) : '';
                    $coverUrl = schoolCoverImageUrl($school);
                ?>
                <div class="school-card-wrap" data-school-id="<?= (int) $school['id'] ?>" data-order="<?= (int) $i ?>">
                    <button
                        type="button"
                        class="school-card-pin"
                        aria-label="Pin school to front"
                        aria-pressed="false"
                        title="Pin to front"
                    >
                        <i class="fa-solid fa-thumbtack"></i>
                    </button>
                <?php if ($isActiveCard): ?>
                <a href="<?= e($schoolPageUrl) ?>" <?= $cardAttrs ?>>
                <?php else: ?>
                <article <?= $cardAttrs ?>>
                <?php endif; ?>
                    <div class="school-card-cover" style="background-image: url('<?= e($coverUrl) ?>')">
                        <span class="badge badge-<?= e($school['status']) ?> school-card-status"><?= e(SCHOOL_STATUSES[$school['status']] ?? ucfirst($school['status'])) ?></span>
                    </div>
                    <div class="school-card-content">
                        <div class="school-card-header">
                            <?= schoolAvatarHtml($school, 'school-avatar') ?>
                            <div class="school-card-identity">
                                <h3><?= e($school['name']) ?></h3>
                                <?php if (!empty($school['school_code']) && $school['status'] === 'active'): ?>
                                    <span class="school-code-tag" title="School login code">
                                        <i class="fa-solid fa-key"></i> <?= e($school['school_code']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="school-card-meta">
                            <?php if ($school['address']): ?>
                                <p class="school-card-location"><i class="fa-solid fa-location-dot"></i><span><?= e($school['address']) ?></span></p>
                            <?php elseif ($school['phone']): ?>
                                <p class="school-card-location"><i class="fa-solid fa-phone"></i><span><?= e($school['phone']) ?></span></p>
                            <?php else: ?>
                                <p class="school-card-location"><i class="fa-solid fa-calendar"></i><span>Registered <?= formatDate($school['registered_at'], 'M j, Y') ?></span></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($isActiveCard): ?>
                        <span class="school-card-cta">
                            Go to school page <i class="fa-solid fa-arrow-right"></i>
                        </span>
                        <?php endif; ?>
                    </div>
                <?php if ($isActiveCard): ?>
                </a>
                <?php else: ?>
                </article>
                <?php endif; ?>
                </div>
                <?php endforeach; ?>
                </div>
                <button type="button" class="school-carousel-nav school-carousel-nav--next" id="schoolCarouselNext" aria-label="Next schools">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
            <div class="empty-state landing-empty schools-search-empty" id="schoolSearchEmpty" hidden>
                <div class="empty-state-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                <h3>No schools match your search</h3>
                <p>Try a different name, school code, email, or location.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="landing-hero">
    <div class="landing-hero-decor" aria-hidden="true">
        <span class="landing-hero-orb landing-hero-orb--1"></span>
        <span class="landing-hero-orb landing-hero-orb--2"></span>
        <span class="landing-hero-orb landing-hero-orb--3"></span>
    </div>
    <div class="landing-hero-shell">
        <div class="landing-hero-grid">
            <div class="landing-hero-copy">
                <span class="landing-badge" data-landing-hero data-landing-delay="1"><i class="fa-solid fa-sparkles"></i> Built for schools &amp; institutions</span>
                <h1 data-landing-hero data-landing-delay="2">One platform for your entire <span class="text-gradient text-gradient--animated">learning community</span></h1>
                <p class="landing-lead" data-landing-hero data-landing-delay="3">Run classes, share materials, assign work, and track progress — built for teachers, students, and school administrators.</p>
                <div class="landing-hero-actions" data-landing-hero data-landing-delay="4">
                    <a href="<?= url('register/school.php') ?>" class="btn btn-primary btn-lg landing-hero-btn-primary">
                        <i class="fa-solid fa-school"></i> Register Your School
                    </a>
                    <a href="<?= url('login.php') ?>" class="btn btn-ghost btn-lg landing-hero-btn-secondary">
                        <i class="fa-solid fa-right-to-bracket"></i> School Login
                    </a>
                    <a href="<?= url('index.php#schools') ?>" class="btn btn-ghost btn-lg landing-hero-btn-tertiary">
                        <i class="fa-solid fa-compass"></i> Browse schools
                    </a>
                </div>
            </div>

            <div class="landing-hero-panel" aria-label="Platform capabilities">
                <div class="hero-panel-card hero-panel-card--featured">
                    <div class="hero-panel-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
                    <div>
                        <strong>Teacher tools</strong>
                        <p>Materials, assignments, quizzes, and grading in one course view.</p>
                    </div>
                </div>
                <div class="hero-panel-grid">
                    <div class="hero-panel-card">
                        <i class="fa-solid fa-file-lines"></i>
                        <span>Course materials</span>
                    </div>
                    <div class="hero-panel-card">
                        <i class="fa-solid fa-pen-to-square"></i>
                        <span>Assignments</span>
                    </div>
                    <div class="hero-panel-card">
                        <i class="fa-solid fa-circle-question"></i>
                        <span>Quizzes</span>
                    </div>
                    <div class="hero-panel-card">
                        <i class="fa-solid fa-layer-group"></i>
                        <span>Class groups</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="landing-features-section">
    <div class="container landing-section-container">
        <div class="landing-features-head" data-landing-reveal>
            <h2><i class="fa-solid fa-star"></i> Everything your school needs</h2>
            <p class="landing-features-sub">A complete toolkit for every role in your institution.</p>
        </div>
        <div class="features features--landing">
            <article class="feature-card">
                <div class="feature-card-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
                <h3>For teachers</h3>
                <p>Publish materials, create assignments, build quizzes, and grade submissions in one place.</p>
            </article>
            <article class="feature-card">
                <div class="feature-card-icon"><i class="fa-solid fa-user-graduate"></i></div>
                <h3>For students</h3>
                <p>Access course content, submit work, take quizzes, and track your learning progress.</p>
            </article>
            <article class="feature-card">
                <div class="feature-card-icon"><i class="fa-solid fa-school"></i></div>
                <h3>For school admins</h3>
                <p>Manage subjects, teachers, students, and class groups with a guided setup checklist.</p>
            </article>
            <article class="feature-card">
                <div class="feature-card-icon"><i class="fa-solid fa-book-open"></i></div>
                <h3>Course tools</h3>
                <p>Moodle-inspired course pages with activity feeds, due dates, and role-based access.</p>
            </article>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/layout/footer.php'; ?>
