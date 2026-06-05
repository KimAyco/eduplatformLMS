<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireSuperAdmin();

processSchoolStatusAction('superadmin/dashboard.php');

$pageTitle = 'Dashboard';
$pageHeading = 'Platform overview';
$activeMenu = 'dashboard';
$menuItems = superAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'superadmin/dashboard.php'],
];

$stats = [
    'total'     => db()->query('SELECT COUNT(*) FROM schools')->fetchColumn(),
    'pending'   => db()->query("SELECT COUNT(*) FROM schools WHERE status = 'pending'")->fetchColumn(),
    'active'    => db()->query("SELECT COUNT(*) FROM schools WHERE status = 'active'")->fetchColumn(),
    'suspended' => db()->query("SELECT COUNT(*) FROM schools WHERE status = 'suspended'")->fetchColumn(),
];

$recent = db()->query("SELECT * FROM schools ORDER BY registered_at DESC LIMIT 5")->fetchAll();
$pending = db()->query("SELECT * FROM schools WHERE status = 'pending' ORDER BY registered_at DESC LIMIT 6")->fetchAll();

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="stats-grid">
    <div class="stat-card"><i class="fa-solid fa-school"></i><div><div class="value"><?= (int)$stats['total'] ?></div><div class="label">Total schools</div></div></div>
    <div class="stat-card"><i class="fa-solid fa-clock"></i><div><div class="value"><?= (int)$stats['pending'] ?></div><div class="label">Pending approval</div></div></div>
    <div class="stat-card"><i class="fa-solid fa-circle-check"></i><div><div class="value"><?= (int)$stats['active'] ?></div><div class="label">Active schools</div></div></div>
    <div class="stat-card"><i class="fa-solid fa-ban"></i><div><div class="value"><?= (int)$stats['suspended'] ?></div><div class="label">Suspended</div></div></div>
</div>

<?php if (!empty($pending)): ?>
<div class="panel">
    <div class="panel-header">
        <h2><i class="fa-solid fa-clock"></i> Pending approval</h2>
        <a href="<?= url('superadmin/schools.php?status=pending') ?>" class="btn btn-secondary btn-sm">View all</a>
    </div>
    <div class="course-grid">
        <?php foreach ($pending as $s): ?>
        <div class="course-card pending-card">
            <div class="course-card-header" style="background:linear-gradient(135deg,#a6670e,#856404);">
                <h3><?= e($s['name']) ?></h3>
                <small><?= e($s['email']) ?></small>
            </div>
            <div class="course-card-body">
                <p class="text-muted" style="font-size:.875rem;">Registered <?= formatDate($s['registered_at'], 'M j, Y') ?></p>
            </div>
            <div class="course-card-actions">
                <?= schoolStatusActionButtons($s) ?>
                <a href="<?= url('superadmin/school-view.php?id=' . $s['id']) ?>" class="btn btn-sm btn-secondary">Details</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <h2>Recent registrations</h2>
        <a href="<?= url('superadmin/schools.php') ?>" class="btn btn-secondary btn-sm">View all</a>
    </div>
    <?php if (empty($recent)): ?>
        <div class="empty-state"><i class="fa-solid fa-school"></i><h3>No schools yet</h3></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>School</th><th>Email</th><th>Status</th><th>Registered</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $s): ?>
                    <tr>
                        <td><a href="<?= url('superadmin/school-view.php?id=' . $s['id']) ?>"><?= e($s['name']) ?></a></td>
                        <td><?= e($s['email']) ?></td>
                        <td><span class="badge badge-<?= e($s['status']) ?>"><?= e(SCHOOL_STATUSES[$s['status']] ?? $s['status']) ?></span></td>
                        <td><?= formatDate($s['registered_at'], 'M j, Y') ?></td>
                        <td class="actions">
                            <?= schoolStatusActionButtons($s) ?>
                            <a href="<?= url('superadmin/school-view.php?id=' . $s['id']) ?>" class="btn btn-sm btn-secondary">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
