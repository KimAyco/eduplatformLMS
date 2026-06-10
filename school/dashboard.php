<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('school_admin');
requireSchoolActive();

$stats = DashboardRepository::schoolAdminStats(schoolId());
$setup = DashboardRepository::schoolSetupProgress(schoolId());
$schoolCode = getSchoolCode(schoolId());
$loginUrl = $schoolCode !== '' ? url('login.php?code=' . urlencode($schoolCode)) : url('login.php');

$setupSteps = [
    [
        'done' => $setup['subjects'],
        'label' => 'Add subjects to catalog',
        'desc' => 'Create all subjects your school offers (e.g. ENG101, NSTP1).',
        'url' => 'school/subjects.php?action=add',
    ],
    [
        'done' => $setup['programs'],
        'label' => 'Build program curricula',
        'desc' => 'Create programs with levels, terms, and required subjects.',
        'url' => 'school/programs.php?action=add',
    ],
    [
        'done' => $setup['teachers'],
        'label' => 'Add teachers & teachable subjects',
        'desc' => 'Register teachers and select which subjects each can teach.',
        'url' => 'school/teachers.php?action=add',
    ],
    [
        'done' => $setup['groups_offered'],
        'label' => 'Create class groups & add subjects',
        'desc' => 'Set up cohorts (e.g. BSIT 1A) and assign subjects with teachers.',
        'url' => 'school/class-groups.php?action=add',
    ],
    [
        'done' => $setup['students_enrolled'],
        'label' => 'Enroll students in groups',
        'desc' => 'Add students to class groups so they can access all subjects.',
        'url' => 'school/class-groups.php',
    ],
];
$completedSteps = count(array_filter($setupSteps, fn($s) => $s['done']));

$pageTitle = 'Dashboard';
$pageHeading = 'Dashboard';
$pageSubtitle = 'Overview of your school and setup progress.';
$activeMenu = 'dashboard';
$menuItems = schoolAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'school/dashboard.php'],
];

$welcomeSubtitle = 'Overview of your school and setup progress.';

require __DIR__ . '/../includes/layout/dashboard_header.php';
require __DIR__ . '/../includes/layout/dashboard_welcome.php';
?>

<div class="panel login-code-card">
    <div class="login-code-inner">
        <div class="login-code-info">
            <h2><i class="fa-solid fa-key"></i> School login code</h2>
            <p class="login-code-desc">Share with teachers and students for sign-in.</p>
        </div>
        <?php if ($schoolCode !== ''): ?>
            <div class="login-code-details">
                <div class="login-code-value"><?= e($schoolCode) ?></div>
                <p class="login-code-url">Login: <a href="<?= e($loginUrl) ?>"><?= e($loginUrl) ?></a></p>
            </div>
        <?php else: ?>
            <p class="login-code-desc">No school code on file. Contact platform support.</p>
        <?php endif; ?>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon stat-card-icon-blue"><i class="fa-solid fa-book"></i></div>
        <div><div class="value"><?= (int)$stats['subjects'] ?></div><div class="label">Subjects</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon stat-card-icon-green"><i class="fa-solid fa-chalkboard-user"></i></div>
        <div><div class="value"><?= (int)$stats['teachers'] ?></div><div class="label">Teachers</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon stat-card-icon-purple"><i class="fa-solid fa-user-graduate"></i></div>
        <div><div class="value"><?= (int)$stats['students'] ?></div><div class="label">Students</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon stat-card-icon-orange"><i class="fa-solid fa-layer-group"></i></div>
        <div><div class="value"><?= (int)$stats['class_groups'] ?></div><div class="label">Class Groups</div></div>
    </div>
</div>

<?php if ($completedSteps < count($setupSteps)): ?>
<div class="panel">
    <div class="panel-header">
        <div>
            <h2><i class="fa-solid fa-list-check"></i> Setup progress</h2>
            <p class="text-muted"><?= $completedSteps ?> of <?= count($setupSteps) ?> steps complete</p>
        </div>
    </div>
    <ol class="setup-checklist">
        <?php foreach ($setupSteps as $i => $step): ?>
            <li class="setup-checklist-item <?= $step['done'] ? 'is-done' : '' ?>">
                <span class="setup-checklist-num"><?= $step['done'] ? '<i class="fa-solid fa-check"></i>' : ($i + 1) ?></span>
                <div class="setup-checklist-body">
                    <strong><?= e($step['label']) ?></strong>
                    <p class="text-muted"><?= e($step['desc']) ?></p>
                    <?php if (!$step['done']): ?>
                        <a href="<?= url($step['url']) ?>" class="btn btn-sm btn-primary">Start</a>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
</div>
<?php endif; ?>

<div class="panel">
    <h2><i class="fa-solid fa-bolt"></i> Quick actions</h2>
    <div class="action-tiles">
        <a href="<?= url('school/subjects.php?action=add') ?>" class="action-tile"><i class="fa-solid fa-book"></i><span>Add subject</span></a>
        <a href="<?= url('school/programs.php?action=add') ?>" class="action-tile"><i class="fa-solid fa-sitemap"></i><span>Add program</span></a>
        <a href="<?= url('school/teachers.php?action=add') ?>" class="action-tile"><i class="fa-solid fa-user-plus"></i><span>Add teacher</span></a>
        <a href="<?= url('school/students.php?action=add') ?>" class="action-tile"><i class="fa-solid fa-user-graduate"></i><span>Add student</span></a>
        <a href="<?= url('school/class-groups.php?action=add') ?>" class="action-tile"><i class="fa-solid fa-layer-group"></i><span>Add class group</span></a>
        <a href="<?= url('school/class-groups.php') ?>" class="action-tile"><i class="fa-solid fa-users"></i><span>Manage groups</span></a>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
