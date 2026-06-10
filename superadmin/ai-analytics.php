<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireSuperAdmin();

$granularity = (string) ($_GET['granularity'] ?? 'day');
if (!in_array($granularity, AiAnalyticsRepository::GRANULARITIES, true)) {
    $granularity = 'day';
}

$pageTitle = 'AI Analytics';
$pageHeading = 'AI usage analytics';
$pageSubtitle = 'Request trends and usage breakdown by school.';
$activeMenu = 'ai_analytics';
$menuItems = superAdminMenu();
$pageScripts = ['assets/js/ai-analytics.js'];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'superadmin/dashboard.php'],
    ['label' => 'AI Analytics', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div id="aiAnalyticsApp" class="ai-analytics"
    data-api-url="<?= e(url('api/ai-platform.php')) ?>"
    data-school-url="<?= e(url('superadmin/school-view.php')) ?>"
    data-initial-granularity="<?= e($granularity) ?>">

    <div class="ai-analytics-toolbar panel">
        <div class="ai-analytics-filters">
            <span class="ai-analytics-filter-label">Time frame</span>
            <div class="ai-granularity-pills" role="group" aria-label="Time frame">
                <?php foreach (AiAnalyticsRepository::GRANULARITIES as $g): ?>
                <button type="button" class="ai-granularity-pill<?= $g === $granularity ? ' is-active' : '' ?>" data-granularity="<?= e($g) ?>">
                    <?= e(match ($g) {
                        'minute' => 'Minute',
                        'hour' => 'Hour',
                        'day' => 'Day',
                        'week' => 'Week',
                        'month' => 'Month',
                        'year' => 'Year',
                        default => ucfirst($g),
                    }) ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="ai-analytics-school-filter">
            <label for="aiSchoolFilter">School</label>
            <select id="aiSchoolFilter" class="form-control">
                <option value="0">All schools</option>
            </select>
        </div>
        <p class="text-muted ai-analytics-range" id="aiAnalyticsRange" hidden></p>
    </div>

    <div class="stats-grid ai-analytics-stats">
        <div class="stat-card">
            <div class="stat-card-icon stat-card-icon-blue"><i class="fa-solid fa-bolt"></i></div>
            <div>
                <div class="value" data-analytics-stat="total">—</div>
                <div class="label">Total requests</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon stat-card-icon-green"><i class="fa-solid fa-check"></i></div>
            <div>
                <div class="value" data-analytics-stat="completed">—</div>
                <div class="label">Completed</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon stat-card-icon-ns"><i class="fa-solid fa-xmark"></i></div>
            <div>
                <div class="value" data-analytics-stat="failed">—</div>
                <div class="label">Failed</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon stat-card-icon-orange"><i class="fa-solid fa-school"></i></div>
            <div>
                <div class="value" data-analytics-stat="schools_active">—</div>
                <div class="label">Schools active</div>
            </div>
        </div>
    </div>

    <div class="panel superadmin-panel ai-analytics-chart-panel">
        <div class="panel-header">
            <div>
                <h2><i class="fa-solid fa-chart-line"></i> Usage trend</h2>
                <p class="superadmin-panel-sub" id="aiTrendSubtitle">AI requests over time</p>
            </div>
        </div>
        <div class="ai-analytics-chart-wrap">
            <canvas id="aiUsageChart" height="120"></canvas>
        </div>
        <p class="text-muted ai-analytics-loading" id="aiAnalyticsLoading" hidden><i class="fa-solid fa-spinner fa-spin"></i> Loading…</p>
    </div>

    <div class="panel superadmin-panel" id="aiSchoolTablePanel">
        <div class="panel-header">
            <div>
                <h2><i class="fa-solid fa-table"></i> Usage by school</h2>
                <p class="superadmin-panel-sub">Breakdown for the selected time frame</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="superadmin-table">
                <thead>
                    <tr>
                        <th>School</th>
                        <th>Total</th>
                        <th>Completed</th>
                        <th>Failed</th>
                        <th>Pending</th>
                        <th>Top job types</th>
                    </tr>
                </thead>
                <tbody id="aiSchoolTableBody">
                    <tr><td colspan="6" class="text-muted">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
