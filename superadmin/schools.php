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

$pageTitle = 'Manage Schools';
$pageHeading = 'Schools';
$activeMenu = 'schools';
$menuItems = superAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'superadmin/dashboard.php'],
    ['label' => 'Schools', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="filter-bar">
    <span>Filter:</span>
    <a href="<?= url('superadmin/schools.php') ?>" class="btn btn-sm <?= $filter === '' ? 'btn-primary' : 'btn-secondary' ?>">All</a>
    <?php foreach (SCHOOL_STATUSES as $key => $label): ?>
        <a href="<?= url('superadmin/schools.php?status=' . $key) ?>" class="btn btn-sm <?= $filter === $key ? 'btn-primary' : 'btn-secondary' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr><th>School</th><th>Admin</th><th>Email</th><th>Status</th><th>Registered</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php if (empty($schools)): ?>
            <tr><td colspan="6" class="text-muted">No schools found.</td></tr>
        <?php else: ?>
            <?php foreach ($schools as $s): ?>
                <tr>
                    <td><a href="<?= url('superadmin/school-view.php?id=' . $s['id']) ?>"><?= e($s['name']) ?></a></td>
                    <td><?= e(trim(($s['admin_first'] ?? '') . ' ' . ($s['admin_last'] ?? ''))) ?: '—' ?></td>
                    <td><?= e($s['admin_email'] ?? $s['email']) ?></td>
                    <td><span class="badge badge-<?= e($s['status']) ?>"><?= e(SCHOOL_STATUSES[$s['status']]) ?></span></td>
                    <td><?= formatDate($s['registered_at'], 'M j, Y') ?></td>
                    <td class="actions">
                        <?= schoolStatusActionButtons($s) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$base = 'superadmin/schools.php' . ($filter ? '?status=' . urlencode($filter) : '');
echo renderPagination($pager, $base);
?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
