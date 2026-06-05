<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('school_admin');
requireSchoolActive();

$stats = DashboardRepository::schoolAdminStats(schoolId());
$schoolCode = getSchoolCode(schoolId());
$loginUrl = $schoolCode !== '' ? url('login.php?code=' . urlencode($schoolCode)) : url('login.php');

$pageTitle = 'Dashboard';
$pageHeading = 'School administration';
$activeMenu = 'dashboard';
$menuItems = schoolAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'school/dashboard.php'],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="stats-grid">
    <div class="stat-card"><i class="fa-solid fa-chalkboard-user"></i><div><div class="value"><?= (int)$stats['teachers'] ?></div><div class="label">Teachers</div></div></div>
    <div class="stat-card"><i class="fa-solid fa-user-graduate"></i><div><div class="value"><?= (int)$stats['students'] ?></div><div class="label">Students</div></div></div>
    <div class="stat-card"><i class="fa-solid fa-book"></i><div><div class="value"><?= (int)$stats['classes'] ?></div><div class="label">Classes</div></div></div>
</div>

<div class="panel">
    <h2><i class="fa-solid fa-key"></i> School login code</h2>
    <p class="text-muted mb-1">Share this code with teachers and students so they can sign in at the login page.</p>
    <?php if ($schoolCode !== ''): ?>
        <p style="font-size:1.5rem;font-weight:700;letter-spacing:0.12em;color:var(--primary);"><?= e($schoolCode) ?></p>
        <p class="text-muted" style="font-size:.875rem;">Login URL: <a href="<?= e($loginUrl) ?>"><?= e($loginUrl) ?></a></p>
    <?php else: ?>
        <p class="text-muted">No school code on file. Contact platform support if you registered before codes were required.</p>
    <?php endif; ?>
</div>

<div class="panel">
    <h2>Quick actions</h2>
    <div class="action-tiles">
        <a href="<?= url('school/teachers.php?action=add') ?>" class="action-tile"><i class="fa-solid fa-user-plus"></i><span>Add teacher</span></a>
        <a href="<?= url('school/students.php?action=add') ?>" class="action-tile"><i class="fa-solid fa-user-plus"></i><span>Add student</span></a>
        <a href="<?= url('school/classes.php?action=add') ?>" class="action-tile"><i class="fa-solid fa-plus"></i><span>Add class</span></a>
        <a href="<?= url('school/enrollments.php') ?>" class="action-tile"><i class="fa-solid fa-users"></i><span>Enrollments</span></a>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
