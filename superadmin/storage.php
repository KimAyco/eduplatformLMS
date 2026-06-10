<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/storage.php';
requireSuperAdmin();

if (isset($_GET['refresh'])) {
    clearPlatformStorageCache();
    redirect('superadmin/storage.php');
}

$report = platformStorageReport();
$schools = $report['schools'];
$topSchools = array_slice($schools, 0, 5);
$schoolsWithUsage = count(array_filter($schools, static fn ($s) => $s['total_bytes'] > 0));

$pageTitle = 'Storage';
$pageHeading = 'Storage monitoring';
$pageSubtitle = 'Uploaded file usage across all schools on the platform.';
$activeMenu = 'storage';
$menuItems = superAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'superadmin/dashboard.php'],
    ['label' => 'Storage', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="superadmin-storage">
    <div class="superadmin-storage-toolbar actions mb-1">
        <a href="<?= url('superadmin/storage.php?refresh=1') ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate"></i> Refresh</a>
    </div>

    <div class="superadmin-stats stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon stat-card-icon-ns"><i class="fa-solid fa-hard-drive"></i></div>
            <div>
                <div class="value"><?= e(formatStorageSize($report['total_bytes'])) ?></div>
                <div class="label">Total platform storage</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon stat-card-icon-green"><i class="fa-solid fa-file"></i></div>
            <div>
                <div class="value"><?= number_format($report['total_files']) ?></div>
                <div class="label">Uploaded files</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon stat-card-icon-orange"><i class="fa-solid fa-school"></i></div>
            <div>
                <div class="value"><?= $schoolsWithUsage ?></div>
                <div class="label">Schools using storage</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon stat-card-icon-muted"><i class="fa-solid fa-server"></i></div>
            <div>
                <div class="value"><?= e(formatStorageSize($report['platform_bytes'])) ?></div>
                <div class="label">Platform uploads</div>
            </div>
        </div>
    </div>

    <?php if ($topSchools !== []): ?>
    <div class="panel superadmin-panel superadmin-storage-top">
        <div class="panel-header">
            <div>
                <h2><i class="fa-solid fa-chart-simple"></i> Highest usage</h2>
                <p class="superadmin-panel-sub">Top schools by uploaded file size</p>
            </div>
        </div>
        <div class="storage-bar-list">
            <?php foreach ($topSchools as $school):
                $pct = storageUsagePercent($school['total_bytes'], max(1, $report['total_bytes']));
            ?>
            <div class="storage-bar-item">
                <div class="storage-bar-head">
                    <a href="<?= url('superadmin/school-view.php?id=' . $school['id']) ?>"><?= e($school['name']) ?></a>
                    <span><?= e(formatStorageSize($school['total_bytes'])) ?> · <?= number_format($school['total_files']) ?> files</span>
                </div>
                <div class="storage-bar-track" aria-hidden="true">
                    <div class="storage-bar-fill" style="width: <?= min(100, $pct) ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="panel superadmin-panel">
        <div class="panel-header">
            <div>
                <h2><i class="fa-solid fa-table"></i> Storage by school</h2>
                <p class="superadmin-panel-sub">All schools sorted by total upload size</p>
            </div>
        </div>

        <?php if ($schools === []): ?>
            <?= adminEmptyState('fa-hard-drive', 'No schools yet', 'Storage usage will appear here once schools upload files.') ?>
        <?php else: ?>
        <div class="admin-table-card superadmin-table-card">
            <div class="table-wrap">
                <table class="superadmin-table superadmin-storage-table">
                    <thead>
                        <tr>
                            <th>School</th>
                            <th>Status</th>
                            <th>Storage used</th>
                            <th>Files</th>
                            <th>Share of total</th>
                            <th>Top category</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($schools as $school):
                        $topCategory = $school['breakdown'][0] ?? null;
                        $share = storageUsagePercent($school['total_bytes'], max(1, $report['total_bytes']));
                    ?>
                        <tr>
                            <td>
                                <div class="table-school-cell">
                                    <?= schoolAvatarHtml($school, 'superadmin-school-avatar') ?>
                                    <a href="<?= url('superadmin/school-view.php?id=' . $school['id']) ?>" class="table-user-link">
                                        <span class="table-user-name"><?= e($school['name']) ?></span>
                                    </a>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?= e($school['status']) ?> superadmin-status-badge">
                                    <?= e(SCHOOL_STATUSES[$school['status']] ?? $school['status']) ?>
                                </span>
                            </td>
                            <td class="storage-size-cell"><strong><?= e(formatStorageSize($school['total_bytes'])) ?></strong></td>
                            <td><?= number_format($school['total_files']) ?></td>
                            <td>
                                <div class="storage-share-cell">
                                    <div class="storage-bar-track storage-bar-track--inline" aria-hidden="true">
                                        <div class="storage-bar-fill" style="width: <?= min(100, $share) ?>%"></div>
                                    </div>
                                    <span><?= e(number_format($share, 1)) ?>%</span>
                                </div>
                            </td>
                            <td class="storage-category-cell">
                                <?php if ($topCategory): ?>
                                    <?= e($topCategory['label']) ?> (<?= e(formatStorageSize($topCategory['bytes'])) ?>)
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <button type="button" class="btn btn-sm btn-secondary" data-storage-toggle="school-<?= (int) $school['id'] ?>">Details</button>
                            </td>
                        </tr>
                        <?php if ($school['breakdown'] !== []): ?>
                        <tr class="storage-breakdown-row" id="school-<?= (int) $school['id'] ?>" hidden>
                            <td colspan="7">
                                <div class="storage-breakdown">
                                    <h3>Breakdown — <?= e($school['name']) ?></h3>
                                    <div class="storage-breakdown-grid">
                                        <?php foreach ($school['breakdown'] as $item): ?>
                                        <div class="storage-breakdown-item">
                                            <span class="storage-breakdown-label"><?= e($item['label']) ?></span>
                                            <strong><?= e(formatStorageSize($item['bytes'])) ?></strong>
                                            <small><?= number_format($item['files']) ?> file<?= $item['files'] === 1 ? '' : 's' ?></small>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($report['platform_bytes'] > 0 || $report['orphan_bytes'] > 0): ?>
    <div class="panel superadmin-panel">
        <div class="panel-header">
            <div>
                <h2><i class="fa-solid fa-folder-tree"></i> Other storage</h2>
                <p class="superadmin-panel-sub">Files not tied to a school folder</p>
            </div>
        </div>
        <div class="storage-breakdown-grid">
            <?php if ($report['platform_bytes'] > 0): ?>
            <div class="storage-breakdown-item">
                <span class="storage-breakdown-label">Platform profiles</span>
                <strong><?= e(formatStorageSize($report['platform_bytes'])) ?></strong>
                <small><?= number_format($report['platform_files']) ?> files</small>
            </div>
            <?php endif; ?>
            <?php if ($report['orphan_bytes'] > 0): ?>
            <div class="storage-breakdown-item">
                <span class="storage-breakdown-label">Unassigned folders</span>
                <strong><?= e(formatStorageSize($report['orphan_bytes'])) ?></strong>
                <small><?= number_format($report['orphan_files']) ?> files</small>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    document.querySelectorAll('[data-storage-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = document.getElementById(btn.getAttribute('data-storage-toggle'));
            if (!row) return;
            var open = row.hidden;
            row.hidden = !open;
            btn.textContent = open ? 'Hide' : 'Details';
        });
    });
})();
</script>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
