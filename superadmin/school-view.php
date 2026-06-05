<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireSuperAdmin();

$schoolId = (int) ($_GET['id'] ?? 0);
processSchoolStatusAction('superadmin/school-view.php?id=' . $schoolId);

$stmt = db()->prepare('SELECT s.*, u.email AS admin_email, u.first_name AS admin_first, u.last_name AS admin_last, u.id AS admin_id
    FROM schools s
    LEFT JOIN users u ON u.school_id = s.id AND u.role = ?
    WHERE s.id = ?');
$stmt->execute(['school_admin', $schoolId]);
$school = $stmt->fetch();

if (!$school) {
    flash('error', 'School not found.');
    redirect('superadmin/schools.php');
}

$schoolCode = !empty($school['school_code']) ? $school['school_code'] : '';

$counts = db()->prepare('SELECT
    (SELECT COUNT(*) FROM users WHERE school_id = ? AND role = ?) AS teachers,
    (SELECT COUNT(*) FROM users WHERE school_id = ? AND role = ?) AS students,
    (SELECT COUNT(*) FROM classes WHERE school_id = ?) AS classes');
$counts->execute([$schoolId, 'teacher', $schoolId, 'student', $schoolId]);
$stats = $counts->fetch();

$pageTitle = $school['name'];
$pageHeading = $school['name'];
$activeMenu = 'schools';
$menuItems = superAdminMenu();

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="actions mb-1">
    <a href="<?= url('superadmin/schools.php') ?>" class="btn btn-secondary btn-sm">Back to Schools</a>
    <?= schoolStatusActionButtons($school) ?>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="value"><?= (int)$stats['teachers'] ?></div><div class="label">Teachers</div></div>
    <div class="stat-card"><div class="value"><?= (int)$stats['students'] ?></div><div class="label">Students</div></div>
    <div class="stat-card"><div class="value"><?= (int)$stats['classes'] ?></div><div class="label">Classes</div></div>
</div>

<div class="panel">
    <h2>School Details</h2>
    <table>
        <tr><th>Status</th><td><span class="badge badge-<?= e($school['status']) ?>"><?= e(SCHOOL_STATUSES[$school['status']]) ?></span></td></tr>
        <tr><th>School Code</th><td><strong style="letter-spacing:0.1em;"><?= $schoolCode !== '' ? e($schoolCode) : '—' ?></strong></td></tr>
        <tr><th>Email</th><td><?= e($school['email']) ?></td></tr>
        <tr><th>Phone</th><td><?= e($school['phone'] ?: '—') ?></td></tr>
        <tr><th>Address</th><td><?= e($school['address'] ?: '—') ?></td></tr>
        <tr><th>Registered</th><td><?= formatDate($school['registered_at']) ?></td></tr>
        <tr><th>Approved</th><td><?= formatDate($school['approved_at']) ?></td></tr>
        <tr><th>Slug</th><td><?= e($school['slug']) ?></td></tr>
    </table>
</div>

<div class="panel">
    <h2>School Admin</h2>
    <?php if ($school['admin_email']): ?>
        <p><strong><?= e($school['admin_first'] . ' ' . $school['admin_last']) ?></strong><br>
        <?= e($school['admin_email']) ?></p>
    <?php else: ?>
        <p class="text-muted">No school admin found.</p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
