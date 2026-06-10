<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireSuperAdmin();

processSchoolStatusAction('superadmin/dashboard.php');

require_once __DIR__ . '/../includes/storage.php';
$storageReport = platformStorageReport();
$storageTopSchool = $storageReport['schools'][0] ?? null;

$pageTitle = 'Dashboard';
$pageHeading = 'Platform overview';
$pageSubtitle = 'Monitor registrations, approvals, and school status across the platform.';
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

$statusIcons = [
    'pending'   => 'fa-clock',
    'active'    => 'fa-circle-check',
    'rejected'  => 'fa-circle-xmark',
    'suspended' => 'fa-ban',
];

$welcomeSubtitle = 'Manage school registrations and platform health.';

require __DIR__ . '/../includes/layout/dashboard_header.php';
require __DIR__ . '/../includes/layout/dashboard_welcome.php';
?>

<div class="superadmin-dashboard">
    <div class="superadmin-stats stats-grid">
        <a href="<?= url('superadmin/schools.php') ?>" class="superadmin-stat-link">
            <div class="stat-card">
                <div class="stat-card-icon stat-card-icon-ns"><i class="fa-solid fa-school"></i></div>
                <div>
                    <div class="value"><?= (int) $stats['total'] ?></div>
                    <div class="label">Total schools</div>
                </div>
            </div>
        </a>
        <a href="<?= url('superadmin/schools.php?status=pending') ?>" class="superadmin-stat-link">
            <div class="stat-card<?= (int) $stats['pending'] > 0 ? ' stat-card--highlight' : '' ?>">
                <div class="stat-card-icon stat-card-icon-orange"><i class="fa-solid fa-clock"></i></div>
                <div>
                    <div class="value"><?= (int) $stats['pending'] ?></div>
                    <div class="label">Pending approval</div>
                </div>
            </div>
        </a>
        <a href="<?= url('superadmin/schools.php?status=active') ?>" class="superadmin-stat-link">
            <div class="stat-card">
                <div class="stat-card-icon stat-card-icon-green"><i class="fa-solid fa-circle-check"></i></div>
                <div>
                    <div class="value"><?= (int) $stats['active'] ?></div>
                    <div class="label">Active schools</div>
                </div>
            </div>
        </a>
        <a href="<?= url('superadmin/schools.php?status=suspended') ?>" class="superadmin-stat-link">
            <div class="stat-card">
                <div class="stat-card-icon stat-card-icon-muted"><i class="fa-solid fa-ban"></i></div>
                <div>
                    <div class="value"><?= (int) $stats['suspended'] ?></div>
                    <div class="label">Suspended</div>
                </div>
            </div>
        </a>
    </div>

    <div class="panel superadmin-panel">
        <div class="panel-header">
            <div>
                <h2><i class="fa-solid fa-hard-drive"></i> Storage overview</h2>
                <p class="superadmin-panel-sub">
                    <?= e(formatStorageSize($storageReport['total_bytes'])) ?> total · <?= number_format($storageReport['total_files']) ?> files across all schools
                </p>
            </div>
            <a href="<?= url('superadmin/storage.php') ?>" class="btn btn-secondary btn-sm">View storage</a>
        </div>
        <?php if ($storageTopSchool && $storageTopSchool['total_bytes'] > 0): ?>
            <p class="superadmin-panel-sub mb-1">
                Highest usage: <a href="<?= url('superadmin/school-view.php?id=' . $storageTopSchool['id']) ?>"><?= e($storageTopSchool['name']) ?></a>
                (<?= e(formatStorageSize($storageTopSchool['total_bytes'])) ?>)
            </p>
        <?php else: ?>
            <p class="text-muted">No uploaded files yet.</p>
        <?php endif; ?>
    </div>

    <?php if ((int) $stats['pending'] > 0): ?>
    <div class="superadmin-alert-banner">
        <div class="superadmin-alert-banner-icon"><i class="fa-solid fa-bell"></i></div>
        <div class="superadmin-alert-banner-text">
            <strong><?= (int) $stats['pending'] ?> school<?= (int) $stats['pending'] === 1 ? '' : 's' ?> awaiting approval</strong>
            <span>Review and approve or reject new registrations.</span>
        </div>
        <a href="<?= url('superadmin/schools.php?status=pending') ?>" class="btn btn-sm btn-primary">Review now</a>
    </div>
    <?php endif; ?>

    <div class="panel superadmin-panel">
        <div class="panel-header">
            <div>
                <h2><i class="fa-solid fa-clock"></i> Pending approval</h2>
                <?php if (!empty($pending)): ?>
                    <p class="superadmin-panel-sub"><?= count($pending) ?> most recent · <?= (int) $stats['pending'] ?> total</p>
                <?php endif; ?>
            </div>
            <a href="<?= url('superadmin/schools.php?status=pending') ?>" class="btn btn-secondary btn-sm">View all</a>
        </div>
        <?php if (empty($pending)): ?>
            <?= adminEmptyState('fa-circle-check', 'All caught up', 'No schools are waiting for approval right now.', 'superadmin/schools.php', 'Browse schools') ?>
        <?php else: ?>
            <div class="superadmin-pending-grid">
                <?php foreach ($pending as $s): ?>
                <article class="superadmin-pending-card">
                    <div class="superadmin-pending-card-head">
                        <?= schoolAvatarHtml($s, 'superadmin-school-avatar') ?>
                        <div class="superadmin-pending-card-info">
                            <h3><a href="<?= url('superadmin/school-view.php?id=' . $s['id']) ?>"><?= e($s['name']) ?></a></h3>
                            <p class="superadmin-pending-email"><i class="fa-solid fa-envelope"></i> <?= e($s['email']) ?></p>
                        </div>
                    </div>
                    <div class="superadmin-pending-meta">
                        <span><i class="fa-regular fa-calendar"></i> Registered <?= formatDate($s['registered_at'], 'M j, Y') ?></span>
                        <span class="badge badge-pending superadmin-status-badge"><i class="fa-solid fa-clock"></i> Pending</span>
                    </div>
                    <div class="superadmin-pending-actions actions">
                        <?= schoolStatusActionButtons($s) ?>
                        <a href="<?= url('superadmin/school-view.php?id=' . $s['id']) ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-arrow-up-right-from-square"></i> Details</a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel superadmin-panel">
        <div class="panel-header">
            <div>
                <h2><i class="fa-solid fa-clock-rotate-left"></i> Recent registrations</h2>
                <p class="superadmin-panel-sub">Latest schools on the platform</p>
            </div>
            <a href="<?= url('superadmin/schools.php') ?>" class="btn btn-secondary btn-sm">View all</a>
        </div>
        <?php if (empty($recent)): ?>
            <?= adminEmptyState('fa-school', 'No schools yet', 'Schools will appear here once they register on the platform.') ?>
        <?php else: ?>
            <div class="admin-table-card superadmin-table-card">
                <div class="table-wrap">
                    <table class="superadmin-table">
                        <thead>
                            <tr>
                                <th>School</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent as $s): ?>
                            <tr>
                                <td>
                                    <div class="table-school-cell">
                                        <?= schoolAvatarHtml($s, 'superadmin-school-avatar') ?>
                                        <a href="<?= url('superadmin/school-view.php?id=' . $s['id']) ?>" class="table-user-link">
                                            <span class="table-user-name"><?= e($s['name']) ?></span>
                                        </a>
                                    </div>
                                </td>
                                <td class="superadmin-email-cell"><?= e($s['email']) ?></td>
                                <td>
                                    <span class="badge badge-<?= e($s['status']) ?> superadmin-status-badge">
                                        <i class="fa-solid <?= e($statusIcons[$s['status']] ?? 'fa-circle') ?>"></i>
                                        <?= e(SCHOOL_STATUSES[$s['status']] ?? $s['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="superadmin-date-cell"><i class="fa-regular fa-calendar"></i> <?= formatDate($s['registered_at'], 'M j, Y') ?></span>
                                </td>
                                <td class="actions superadmin-actions">
                                    <div class="superadmin-action-group">
                                        <?= schoolStatusActionButtons($s) ?>
                                        <a href="<?= url('superadmin/school-view.php?id=' . $s['id']) ?>" class="superadmin-icon-btn superadmin-icon-btn--view" title="View details">
                                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
