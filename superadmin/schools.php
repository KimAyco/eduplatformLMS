<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireSuperAdmin();

$filter = $_GET['status'] ?? '';
$redirect = 'superadmin/schools.php' . ($filter ? '?status=' . urlencode($filter) : '');
processSchoolStatusAction($redirect);

$page = max(1, (int) ($_GET['page'] ?? 1));
$list = UserRepository::paginatedSchools($filter ?: null, $page);
$schools = $list['items'];
$pager = paginate($list['total'], $list['page'], $list['per_page']);

$statusIcons = [
    'pending'   => 'fa-clock',
    'active'    => 'fa-circle-check',
    'rejected'  => 'fa-circle-xmark',
    'suspended' => 'fa-ban',
];

$filterCounts = array_fill_keys(array_keys(SCHOOL_STATUSES), 0);
$filterCounts['all'] = (int) db()->query('SELECT COUNT(*) FROM schools')->fetchColumn();
foreach (db()->query('SELECT status, COUNT(*) AS cnt FROM schools GROUP BY status')->fetchAll() as $row) {
    if (isset($filterCounts[$row['status']])) {
        $filterCounts[$row['status']] = (int) $row['cnt'];
    }
}

$filterLabel = $filter === '' ? 'All schools' : (SCHOOL_STATUSES[$filter] ?? 'Schools');

$pageTitle = 'Manage Schools';
$pageHeading = 'Schools';
$pageSubtitle = 'Review registrations, manage approvals, and monitor school status.';
$activeMenu = 'schools';
$menuItems = superAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'superadmin/dashboard.php'],
    ['label' => 'Schools', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="superadmin-schools">
    <nav class="superadmin-filter-tabs" aria-label="Filter schools by status">
        <a href="<?= url('superadmin/schools.php') ?>" class="superadmin-filter-tab<?= $filter === '' ? ' is-active' : '' ?>">
            <i class="fa-solid fa-layer-group"></i>
            <span>All</span>
            <span class="superadmin-filter-count"><?= $filterCounts['all'] ?></span>
        </a>
        <?php foreach (SCHOOL_STATUSES as $key => $label): ?>
            <a href="<?= url('superadmin/schools.php?status=' . $key) ?>" class="superadmin-filter-tab superadmin-filter-tab--<?= e($key) ?><?= $filter === $key ? ' is-active' : '' ?>">
                <i class="fa-solid <?= e($statusIcons[$key] ?? 'fa-circle') ?>"></i>
                <span><?= e($label) ?></span>
                <span class="superadmin-filter-count"><?= $filterCounts[$key] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="admin-table-card superadmin-table-card">
        <div class="superadmin-table-card-header">
            <div>
                <h2><?= e($filterLabel) ?></h2>
                <p class="superadmin-panel-sub">
                    <?= (int) $list['total'] ?> school<?= (int) $list['total'] === 1 ? '' : 's' ?>
                    <?php if ($pager['total_pages'] > 1): ?>
                        · Page <?= (int) $pager['page'] ?> of <?= (int) $pager['total_pages'] ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <?php if (empty($schools)): ?>
            <?php
            $emptyTitle = $filter === '' ? 'No schools yet' : 'No ' . strtolower($filterLabel);
            $emptyText = $filter === ''
                ? 'Schools will appear here once they register on the platform.'
                : 'No schools match this status filter. Try a different filter.';
            echo adminEmptyState('fa-school', $emptyTitle, $emptyText, $filter !== '' ? 'superadmin/schools.php' : null, $filter !== '' ? 'Show all schools' : null);
            ?>
        <?php else: ?>
            <div class="table-wrap superadmin-table-wrap">
                <table class="superadmin-table">
                    <thead>
                        <tr>
                            <th class="superadmin-col-school">School</th>
                            <th class="superadmin-col-admin">Administrator</th>
                            <th class="superadmin-col-status">Status</th>
                            <th class="superadmin-col-date">Registered</th>
                            <th class="superadmin-col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($schools as $s): ?>
                        <?php
                        $adminName = trim(($s['admin_first'] ?? '') . ' ' . ($s['admin_last'] ?? ''));
                        $adminEmail = $s['admin_email'] ?? $s['email'];
                        $viewUrl = url('superadmin/school-view.php?id=' . $s['id']);
                        ?>
                        <tr class="table-row-link superadmin-table-row" data-href="<?= e($viewUrl) ?>" tabindex="0">
                            <td>
                                <div class="table-school-cell superadmin-school-cell">
                                    <?= schoolAvatarHtml($s, 'superadmin-school-avatar') ?>
                                    <div class="superadmin-school-meta">
                                        <span class="superadmin-school-name"><?= e($s['name']) ?></span>
                                        <?php if (!empty($s['school_code'])): ?>
                                            <span class="superadmin-school-code"><?= e($s['school_code']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="superadmin-contact-cell">
                                    <span class="superadmin-admin-name"><?= $adminName !== '' ? e($adminName) : '—' ?></span>
                                    <span class="superadmin-contact-email"><?= e($adminEmail) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?= e($s['status']) ?> superadmin-status-badge">
                                    <i class="fa-solid <?= e($statusIcons[$s['status']] ?? 'fa-circle') ?>"></i>
                                    <?= e(SCHOOL_STATUSES[$s['status']]) ?>
                                </span>
                            </td>
                            <td>
                                <span class="superadmin-date-cell">
                                    <i class="fa-regular fa-calendar"></i>
                                    <?= formatDate($s['registered_at'], 'M j, Y') ?>
                                </span>
                            </td>
                            <td class="actions superadmin-actions">
                                <div class="superadmin-action-group">
                                    <?= schoolStatusActionButtons($s) ?>
                                    <a href="<?= e($viewUrl) ?>" class="superadmin-icon-btn superadmin-icon-btn--view" title="View details">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($schools)): ?>
        <?php
        $base = 'superadmin/schools.php' . ($filter ? '?status=' . urlencode($filter) : '');
        echo renderPagination($pager, $base);
        ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
