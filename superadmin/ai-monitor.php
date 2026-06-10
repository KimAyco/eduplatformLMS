<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireSuperAdmin();

$counts = AiQueueRepository::statusCounts();
$keyStats = groqAllKeyStats();

$pageTitle = 'AI Monitor';
$pageHeading = 'AI request monitor';
$pageSubtitle = 'Live queue, API key usage, and recent prompts.';
$activeMenu = 'ai_monitor';
$menuItems = superAdminMenu();
$pageScripts = ['assets/js/ai-monitor.js'];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'superadmin/dashboard.php'],
    ['label' => 'AI Monitor', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="superadmin-storage-toolbar actions mb-1">
    <a href="<?= url('superadmin/ai-analytics.php') ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-chart-line"></i> Usage analytics</a>
</div>

<div id="aiMonitorApp" class="ai-monitor" data-api-url="<?= e(url('api/ai-platform.php')) ?>">
    <div class="stats-grid ai-monitor-stats">
        <div class="stat-card">
            <div class="stat-card-icon stat-card-icon-orange"><i class="fa-solid fa-clock"></i></div>
            <div>
                <div class="value" data-ai-stat="pending"><?= (int) $counts['pending'] ?></div>
                <div class="label">Pending</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon stat-card-icon-blue"><i class="fa-solid fa-spinner"></i></div>
            <div>
                <div class="value" data-ai-stat="processing"><?= (int) $counts['processing'] ?></div>
                <div class="label">Processing</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon stat-card-icon-green"><i class="fa-solid fa-check"></i></div>
            <div>
                <div class="value" data-ai-stat="completed"><?= (int) $counts['completed'] ?></div>
                <div class="label">Completed</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon stat-card-icon-ns"><i class="fa-solid fa-key"></i></div>
            <div>
                <div class="value"><?= groqKeyCount() ?></div>
                <div class="label">API keys</div>
            </div>
        </div>
    </div>

    <div class="panel superadmin-panel ai-monitor-keys">
        <h2><i class="fa-solid fa-gauge-high"></i> Key usage (this minute)</h2>
        <div class="ai-key-grid" data-ai-keys>
            <?php foreach ($keyStats as $ks): ?>
            <div class="ai-key-card">
                <strong>Key #<?= (int) $ks['index'] + 1 ?></strong>
                <span class="text-muted"><?= e($ks['masked']) ?></span>
                <div class="storage-bar-track" aria-hidden="true">
                    <div class="storage-bar-fill" style="width: <?= $ks['limit'] > 0 ? min(100, round(($ks['used'] / $ks['limit']) * 100)) : 0 ?>%"></div>
                </div>
                <small data-key-used="<?= (int) $ks['index'] ?>"><?= (int) $ks['used'] ?> / <?= (int) $ks['limit'] ?> requests</small>
            </div>
            <?php endforeach; ?>
            <?php if ($keyStats === []): ?>
            <p class="text-muted">No Groq API keys configured in <code>.env</code>.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel superadmin-panel">
        <div class="ai-monitor-toolbar">
            <h2><i class="fa-solid fa-list"></i> Request queue</h2>
            <span class="text-muted ai-monitor-live"><i class="fa-solid fa-circle ai-pulse"></i> Auto-refreshing</span>
        </div>
        <div class="table-responsive">
            <table class="superadmin-table ai-queue-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Prompt</th>
                        <th>Key</th>
                        <th>User</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-ai-queue-body>
                    <?php foreach (AiQueueRepository::recentQueue(30) as $row): ?>
                    <tr data-job-id="<?= (int) $row['id'] ?>">
                        <td>#<?= (int) $row['id'] ?></td>
                        <td><code><?= e($row['job_type']) ?></code></td>
                        <td><span class="badge badge-<?= e($row['status']) ?>"><?= e($row['status']) ?></span></td>
                        <td class="ai-prompt-cell"><?= e($row['prompt_preview'] ?? '—') ?></td>
                        <td><?= $row['assigned_key_index'] !== null ? '#' . ((int) $row['assigned_key_index'] + 1) : '—' ?></td>
                        <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?: '—' ?></td>
                        <td><?= e($row['created_at']) ?></td>
                        <td>
                            <?php if (in_array($row['status'], ['pending', 'processing'], true)): ?>
                            <button type="button" class="btn btn-sm btn-danger" data-cancel-job="<?= (int) $row['id'] ?>">Cancel</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
