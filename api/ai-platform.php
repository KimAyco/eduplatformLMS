<?php

require_once __DIR__ . '/../includes/bootstrap.php';
requireSuperAdmin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    match ($action) {
        'queue_snapshot' => handleQueueSnapshot(),
        'key_stats' => handleKeyStats(),
        'settings_get' => handleSettingsGet(),
        'settings_save' => handleSettingsSave(),
        'cancel_job' => handleCancelJob(),
        'process_next' => handleProcessNext(),
        'usage_analytics' => handleUsageAnalytics(),
        default => aiPlatformJsonResponse(['ok' => false, 'error' => 'Unknown action.'], 400),
    };
} catch (InvalidArgumentException $e) {
    aiPlatformJsonResponse(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (RuntimeException $e) {
    aiPlatformJsonResponse(['ok' => false, 'error' => $e->getMessage()], 403);
}

function aiPlatformJsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function handleQueueSnapshot(): never
{
    aiProcessPendingJobs(2);
    $queue = AiQueueRepository::recentQueue(40);
    $formatted = array_map(static function (array $row) {
        return [
            'id' => (int) $row['id'],
            'job_type' => $row['job_type'],
            'status' => $row['status'],
            'prompt_preview' => $row['prompt_preview'],
            'error' => $row['error_message'],
            'assigned_key_index' => $row['assigned_key_index'],
            'requested_by' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
            'created_at' => $row['created_at'],
            'started_at' => $row['started_at'],
            'completed_at' => $row['completed_at'],
            'queue_position' => $row['status'] === 'pending'
                ? AiQueueRepository::queuePosition((int) $row['id']) : null,
        ];
    }, $queue);

    aiPlatformJsonResponse([
        'ok' => true,
        'counts' => AiQueueRepository::statusCounts(),
        'queue' => $formatted,
        'key_count' => groqKeyCount(),
    ]);
}

function handleKeyStats(): never
{
    aiPlatformJsonResponse(['ok' => true, 'keys' => groqAllKeyStats()]);
}

function handleSettingsGet(): never
{
    aiPlatformJsonResponse(['ok' => true, 'settings' => PlatformSettingsRepository::all()]);
}

function handleSettingsSave(): never
{
    verifyCsrfHeader();
    $body = json_decode((string) file_get_contents('php://input'), true) ?? $_POST;
    $user = currentUser();

    if (isset($body['ai_enabled'])) {
        PlatformSettingsRepository::set('ai_enabled', !empty($body['ai_enabled']), (int) $user['id']);
    }
    if (isset($body['groq_rate_limit_per_minute'])) {
        $limit = max(1, min(60, (int) $body['groq_rate_limit_per_minute']));
        PlatformSettingsRepository::set('groq_rate_limit_per_minute', $limit, (int) $user['id']);
    }
    if (!empty($body['groq_model'])) {
        PlatformSettingsRepository::set('groq_model', trim((string) $body['groq_model']), (int) $user['id']);
    }

    aiPlatformJsonResponse(['ok' => true, 'settings' => PlatformSettingsRepository::all()]);
}

function handleCancelJob(): never
{
    verifyCsrfHeader();
    $body = json_decode((string) file_get_contents('php://input'), true) ?? $_POST;
    $id = (int) ($body['job_id'] ?? 0);
    if ($id <= 0) {
        throw new InvalidArgumentException('Job ID required.');
    }
    $ok = AiQueueRepository::cancel($id);
    aiPlatformJsonResponse(['ok' => $ok]);
}

function handleProcessNext(): never
{
    verifyCsrfHeader();
    $count = aiProcessPendingJobs(1);
    aiPlatformJsonResponse(['ok' => true, 'processed' => $count]);
}

function handleUsageAnalytics(): never
{
    $granularity = (string) ($_GET['granularity'] ?? 'day');
    $schoolId = (int) ($_GET['school_id'] ?? 0);
    $schoolFilter = $schoolId > 0 ? $schoolId : null;

    $range = AiAnalyticsRepository::resolveRange($granularity);
    $from = $range['from'];
    $to = $range['to'];
    $granularity = $range['granularity'];

    aiPlatformJsonResponse([
        'ok' => true,
        'granularity' => $granularity,
        'granularity_label' => AiAnalyticsRepository::granularityLabel($granularity),
        'from' => $from,
        'to' => $to,
        'school_id' => $schoolFilter,
        'summary' => AiAnalyticsRepository::summary($from, $to, $schoolFilter),
        'trend' => AiAnalyticsRepository::usageTrend($granularity, $from, $to, $schoolFilter),
        'by_school' => $schoolFilter === null
            ? AiAnalyticsRepository::usageBySchool($from, $to)
            : [],
        'schools' => AiAnalyticsRepository::schoolsForFilter(),
    ]);
}
