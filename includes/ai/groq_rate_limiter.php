<?php

function groqRateLimitWindowStart(): string
{
    return gmdate('Y-m-d H:i:00');
}

function groqKeyUsageCount(int $keyIndex): int
{
    $window = groqRateLimitWindowStart();
    $stmt = db()->prepare('SELECT request_count FROM ai_key_usage WHERE key_index = ? AND window_start = ?');
    $stmt->execute([$keyIndex, $window]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

function groqRecordKeyUsage(int $keyIndex): void
{
    $window = groqRateLimitWindowStart();
    db()->prepare('INSERT INTO ai_key_usage (key_index, window_start, request_count) VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE request_count = request_count + 1')
        ->execute([$keyIndex, $window]);
}

function groqKeyHasSlot(int $keyIndex): bool
{
    return groqKeyUsageCount($keyIndex) < groqRateLimitPerMinute();
}

/**
 * Pick a key index with available quota, waiting if necessary.
 */
function groqAcquireKeyIndex(int $maxWaitSeconds = 120): int
{
    $keys = groqApiKeys();
    if ($keys === []) {
        throw new RuntimeException('No Groq API keys configured.');
    }

    $deadline = time() + $maxWaitSeconds;
    $startIndex = groqNextKeyIndex();

    while (time() < $deadline) {
        $count = count($keys);
        for ($offset = 0; $offset < $count; $offset++) {
            $index = ($startIndex + $offset) % $count;
            if (groqKeyHasSlot($index)) {
                return $index;
            }
        }
        sleep(2);
    }

    throw new RuntimeException('Groq rate limit: all API keys are busy. Please try again shortly.');
}

/** @return array{index: int, remaining: int, limit: int, used: int} */
function groqKeyStatsForIndex(int $keyIndex): array
{
    $limit = groqRateLimitPerMinute();
    $used = groqKeyUsageCount($keyIndex);
    return [
        'index' => $keyIndex,
        'limit' => $limit,
        'used' => $used,
        'remaining' => max(0, $limit - $used),
    ];
}

/** @return list<array{index: int, remaining: int, limit: int, used: int, masked: string}> */
function groqAllKeyStats(): array
{
    $keys = groqApiKeys();
    $stats = [];
    foreach ($keys as $i => $key) {
        $row = groqKeyStatsForIndex($i);
        $row['masked'] = groqMaskKey($key);
        $stats[] = $row;
    }
    return $stats;
}

function groqMaskKey(string $key): string
{
    $len = strlen($key);
    if ($len <= 8) {
        return str_repeat('*', $len);
    }
    return substr($key, 0, 4) . str_repeat('*', max(4, $len - 8)) . substr($key, -4);
}
