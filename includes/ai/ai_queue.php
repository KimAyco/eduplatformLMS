<?php

function aiJsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function aiProcessNextJob(): ?array
{
    if (!aiIsEnabled()) {
        return null;
    }

    if (groqKeyCount() === 0) {
        return null;
    }

    $job = AiQueueRepository::claimNext();
    if (!$job) {
        return null;
    }

    $jobId = (int) $job['id'];
    try {
        $result = aiDispatchJob($job);
        $keyIndex = isset($result['key_index']) ? (int) $result['key_index'] : null;
        unset($result['key_index']);
        AiQueueRepository::markCompleted($jobId, $result, $keyIndex);
        $job = AiQueueRepository::findById($jobId);
        return $job;
    } catch (Throwable $e) {
        AiQueueRepository::markFailed($jobId, $e->getMessage());
        return AiQueueRepository::findById($jobId);
    }
}

function aiProcessPendingJobs(int $maxJobs = 1): int
{
    $processed = 0;
    for ($i = 0; $i < $maxJobs; $i++) {
        $result = aiProcessNextJob();
        if ($result === null) {
            break;
        }
        $processed++;
    }
    return $processed;
}

function aiEnqueueJob(string $type, array $payload, ?int $userId = null, ?int $schoolId = null, ?string $preview = null): int
{
    if (!aiIsEnabled()) {
        throw new RuntimeException('AI features are currently disabled.');
    }
    if (groqKeyCount() === 0) {
        throw new RuntimeException('No Groq API keys configured.');
    }
    return AiQueueRepository::enqueue($type, $payload, $userId, $schoolId, 5, $preview);
}

function aiFormatJobForClient(?array $job): ?array
{
    if (!$job) {
        return null;
    }
    return [
        'id' => (int) $job['id'],
        'job_type' => $job['job_type'],
        'status' => $job['status'],
        'prompt_preview' => $job['prompt_preview'],
        'error' => $job['error_message'],
        'result' => $job['result'],
        'queue_position' => $job['status'] === 'pending' ? AiQueueRepository::queuePosition((int) $job['id']) : null,
        'created_at' => $job['created_at'],
        'started_at' => $job['started_at'],
        'completed_at' => $job['completed_at'],
    ];
}
