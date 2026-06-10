<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireSuperAdmin();

$errors = [];
$settings = PlatformSettingsRepository::all();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $user = currentUser();

    PlatformSettingsRepository::set('ai_enabled', isset($_POST['ai_enabled']), (int) $user['id']);
    $limit = max(1, min(60, (int) ($_POST['groq_rate_limit_per_minute'] ?? 3)));
    PlatformSettingsRepository::set('groq_rate_limit_per_minute', $limit, (int) $user['id']);
    $model = trim((string) ($_POST['groq_model'] ?? ''));
    if ($model !== '') {
        PlatformSettingsRepository::set('groq_model', $model, (int) $user['id']);
    }

    flash('success', 'Platform settings saved.');
    redirect('superadmin/settings.php');
}

$settings = PlatformSettingsRepository::all();
$keyCount = groqKeyCount();

$pageTitle = 'Settings';
$pageHeading = 'Platform settings';
$pageSubtitle = 'Configure AI features and Groq API rate limits.';
$activeMenu = 'settings';
$menuItems = superAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'superadmin/dashboard.php'],
    ['label' => 'Settings', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="panel superadmin-panel">
    <h2><i class="fa-solid fa-gear"></i> AI configuration</h2>
    <form method="post" class="form-stack superadmin-settings-form">
        <?= csrfField() ?>

        <label class="form-check">
            <input type="checkbox" name="ai_enabled" value="1"<?= !empty($settings['ai_enabled']) ? ' checked' : '' ?>>
            <span>Enable AI features platform-wide</span>
        </label>

        <label>
            <span>Groq requests per API key per minute</span>
            <input type="number" name="groq_rate_limit_per_minute" class="form-control" min="1" max="60"
                value="<?= (int) ($settings['groq_rate_limit_per_minute'] ?? 3) ?>">
        </label>

        <label>
            <span>Groq model</span>
            <input type="text" name="groq_model" class="form-control"
                value="<?= e((string) ($settings['groq_model'] ?? 'llama-3.3-70b-versatile')) ?>">
        </label>

        <p class="text-muted">
            <i class="fa-solid fa-key"></i>
            <?= $keyCount ?> API <?= $keyCount === 1 ? 'key' : 'keys' ?> loaded from <code>GROQ_API_KEYS</code> in <code>.env</code>.
            Requests are distributed round-robin across keys.
        </p>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save settings</button>
            <a href="<?= url('superadmin/ai-monitor.php') ?>" class="btn btn-secondary">Open AI monitor</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
