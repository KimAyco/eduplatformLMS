<?php

class AiQueueRepository
{
    public static function enqueue(string $jobType, array $payload, ?int $userId = null, ?int $schoolId = null, int $priority = 5, ?string $promptPreview = null): int
    {
        $stmt = db()->prepare('INSERT INTO ai_request_queue (job_type, payload, priority, requested_by, school_id, prompt_preview)
            VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $jobType,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $priority,
            $userId,
            $schoolId,
            $promptPreview !== null ? mb_substr($promptPreview, 0, 500) : null,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM ai_request_queue WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? self::decodeRow($row) : null;
    }

    public static function claimNext(): ?array
    {
        db()->beginTransaction();
        try {
            $stmt = db()->query("SELECT * FROM ai_request_queue WHERE status = 'pending'
                ORDER BY priority ASC, created_at ASC LIMIT 1 FOR UPDATE");
            $row = $stmt->fetch();
            if (!$row) {
                db()->commit();
                return null;
            }

            db()->prepare("UPDATE ai_request_queue SET status = 'processing', started_at = UTC_TIMESTAMP() WHERE id = ?")
                ->execute([(int) $row['id']]);

            db()->commit();
            return self::decodeRow($row);
        } catch (Throwable $e) {
            db()->rollBack();
            throw $e;
        }
    }

    public static function markCompleted(int $id, array $result, ?int $keyIndex = null): void
    {
        db()->prepare("UPDATE ai_request_queue SET status = 'completed', result = ?, assigned_key_index = ?,
            completed_at = UTC_TIMESTAMP() WHERE id = ?")
            ->execute([json_encode($result, JSON_UNESCAPED_UNICODE), $keyIndex, $id]);
    }

    public static function markFailed(int $id, string $error, ?int $keyIndex = null): void
    {
        db()->prepare("UPDATE ai_request_queue SET status = 'failed', error_message = ?, assigned_key_index = ?,
            completed_at = UTC_TIMESTAMP() WHERE id = ?")
            ->execute([mb_substr($error, 0, 2000), $keyIndex, $id]);
    }

    public static function cancel(int $id): bool
    {
        $stmt = db()->prepare("UPDATE ai_request_queue SET status = 'cancelled', completed_at = UTC_TIMESTAMP()
            WHERE id = ? AND status IN ('pending', 'processing')");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /** @return list<array<string, mixed>> */
    public static function recentQueue(int $limit = 50): array
    {
        $stmt = db()->prepare('SELECT q.*, u.first_name, u.last_name, u.email
            FROM ai_request_queue q
            LEFT JOIN users u ON u.id = q.requested_by
            ORDER BY q.id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([self::class, 'decodeRow'], $stmt->fetchAll());
    }

    /** @return array{pending: int, processing: int, completed: int, failed: int} */
    public static function statusCounts(): array
    {
        $rows = db()->query("SELECT status, COUNT(*) AS cnt FROM ai_request_queue GROUP BY status")->fetchAll();
        $out = ['pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0, 'cancelled' => 0];
        foreach ($rows as $row) {
            $out[$row['status']] = (int) $row['cnt'];
        }
        return $out;
    }

    public static function queuePosition(int $id): ?int
    {
        $job = self::findById($id);
        if (!$job || $job['status'] !== 'pending') {
            return null;
        }
        $stmt = db()->prepare("SELECT COUNT(*) FROM ai_request_queue WHERE status = 'pending'
            AND (priority < ? OR (priority = ? AND created_at < ?))");
        $stmt->execute([(int) $job['priority'], (int) $job['priority'], $job['created_at']]);
        return (int) $stmt->fetchColumn() + 1;
    }

 
    private static function decodeRow(array $row): array
    {
        if (isset($row['payload']) && is_string($row['payload'])) {
            $row['payload'] = json_decode($row['payload'], true) ?? [];
        }
        if (isset($row['result']) && is_string($row['result'])) {
            $row['result'] = json_decode($row['result'], true);
        }
        return $row;
    }
}
