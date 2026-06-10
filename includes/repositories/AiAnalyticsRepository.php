<?php

class AiAnalyticsRepository
{
    public const GRANULARITIES = ['minute', 'hour', 'day', 'week', 'month', 'year'];

    /** @return array{granularity: string, from: string, to: string, bucket_expr: string} */
    public static function resolveRange(string $granularity): array
    {
        if (!in_array($granularity, self::GRANULARITIES, true)) {
            $granularity = 'day';
        }

        $to = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $from = match ($granularity) {
            'minute' => $to->modify('-59 minutes'),
            'hour' => $to->modify('-23 hours'),
            'day' => $to->modify('-29 days'),
            'week' => $to->modify('-11 weeks')->modify('monday this week'),
            'month' => $to->modify('-11 months')->modify('first day of this month'),
            'year' => $to->modify('-4 years')->modify('first day of january'),
            default => $to->modify('-29 days'),
        };

        return [
            'granularity' => $granularity,
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
            'bucket_expr' => self::bucketSql($granularity),
        ];
    }

    private static function bucketSql(string $granularity): string
    {
        return match ($granularity) {
            'minute' => "DATE_FORMAT(q.created_at, '%Y-%m-%d %H:%i:00')",
            'hour' => "DATE_FORMAT(q.created_at, '%Y-%m-%d %H:00:00')",
            'day' => 'DATE(q.created_at)',
            'week' => "DATE(DATE_SUB(q.created_at, INTERVAL WEEKDAY(q.created_at) DAY))",
            'month' => "DATE_FORMAT(q.created_at, '%Y-%m-01')",
            'year' => "DATE_FORMAT(q.created_at, '%Y-01-01')",
            default => 'DATE(q.created_at)',
        };
    }

    /**
     * @return array{total: int, completed: int, failed: int, processing: int, pending: int, cancelled: int, schools_active: int}
     */
    public static function summary(string $from, string $to, ?int $schoolId = null): array
    {
        $sql = "SELECT
            COUNT(*) AS total,
            SUM(q.status = 'completed') AS completed,
            SUM(q.status = 'failed') AS failed,
            SUM(q.status = 'processing') AS processing,
            SUM(q.status = 'pending') AS pending,
            SUM(q.status = 'cancelled') AS cancelled,
            COUNT(DISTINCT q.school_id) AS schools_active
            FROM ai_request_queue q
            WHERE q.created_at >= ? AND q.created_at <= ?";
        $params = [$from, $to];
        if ($schoolId !== null && $schoolId > 0) {
            $sql .= ' AND q.school_id = ?';
            $params[] = $schoolId;
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'failed' => (int) ($row['failed'] ?? 0),
            'processing' => (int) ($row['processing'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
            'schools_active' => (int) ($row['schools_active'] ?? 0),
        ];
    }

    /**
     * @return list<array{bucket: string, label: string, total: int, completed: int, failed: int}>
     */
    public static function usageTrend(string $granularity, string $from, string $to, ?int $schoolId = null): array
    {
        $bucketExpr = self::bucketSql($granularity);
        $sql = "SELECT {$bucketExpr} AS bucket,
            COUNT(*) AS total,
            SUM(q.status = 'completed') AS completed,
            SUM(q.status = 'failed') AS failed
            FROM ai_request_queue q
            WHERE q.created_at >= ? AND q.created_at <= ?";
        $params = [$from, $to];
        if ($schoolId !== null && $schoolId > 0) {
            $sql .= ' AND q.school_id = ?';
            $params[] = $schoolId;
        }
        $sql .= ' GROUP BY bucket ORDER BY bucket';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        $byBucket = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = (string) $row['bucket'];
            $byBucket[$key] = [
                'bucket' => $key,
                'label' => self::formatBucketLabel($key, $granularity),
                'total' => (int) $row['total'],
                'completed' => (int) $row['completed'],
                'failed' => (int) $row['failed'],
            ];
        }

        $series = [];
        foreach (self::generateBuckets($granularity, $from, $to) as $bucket) {
            $series[] = $byBucket[$bucket] ?? [
                'bucket' => $bucket,
                'label' => self::formatBucketLabel($bucket, $granularity),
                'total' => 0,
                'completed' => 0,
                'failed' => 0,
            ];
        }

        return $series;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function usageBySchool(string $from, string $to): array
    {
        $stmt = db()->prepare("SELECT
            COALESCE(s.id, 0) AS school_id,
            COALESCE(s.name, 'Unassigned') AS school_name,
            COUNT(*) AS total,
            SUM(q.status = 'completed') AS completed,
            SUM(q.status = 'failed') AS failed,
            SUM(q.status = 'pending') AS pending,
            SUM(q.status = 'processing') AS processing
            FROM ai_request_queue q
            LEFT JOIN schools s ON s.id = q.school_id
            WHERE q.created_at >= ? AND q.created_at <= ?
            GROUP BY COALESCE(s.id, 0), COALESCE(s.name, 'Unassigned')
            ORDER BY total DESC");
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['school_id'] = (int) $row['school_id'];
            $row['total'] = (int) $row['total'];
            $row['completed'] = (int) $row['completed'];
            $row['failed'] = (int) $row['failed'];
            $row['pending'] = (int) $row['pending'];
            $row['processing'] = (int) $row['processing'];
            $row['job_types'] = self::jobTypesForSchool((int) $row['school_id'], $from, $to);
        }
        unset($row);

        return $rows;
    }

    /** @return list<array{job_type: string, count: int}> */
    public static function jobTypesForSchool(int $schoolId, string $from, string $to): array
    {
        $sql = "SELECT q.job_type, COUNT(*) AS cnt FROM ai_request_queue q
            WHERE q.created_at >= ? AND q.created_at <= ?";
        $params = [$from, $to];
        if ($schoolId > 0) {
            $sql .= ' AND q.school_id = ?';
            $params[] = $schoolId;
        } else {
            $sql .= ' AND q.school_id IS NULL';
        }
        $sql .= ' GROUP BY q.job_type ORDER BY cnt DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return array_map(static fn ($r) => [
            'job_type' => (string) $r['job_type'],
            'count' => (int) $r['cnt'],
        ], $stmt->fetchAll());
    }

    /** @return list<array{id: int, name: string}> */
    public static function schoolsForFilter(): array
    {
        $stmt = db()->query("SELECT DISTINCT s.id, s.name
            FROM schools s
            INNER JOIN ai_request_queue q ON q.school_id = s.id
            ORDER BY s.name");
        return array_map(static fn ($r) => [
            'id' => (int) $r['id'],
            'name' => (string) $r['name'],
        ], $stmt->fetchAll());
    }

    /** @return list<string> */
    private static function generateBuckets(string $granularity, string $from, string $to): array
    {
        $start = new DateTimeImmutable($from, new DateTimeZone('UTC'));
        $end = new DateTimeImmutable($to, new DateTimeZone('UTC'));
        $buckets = [];
        $current = self::alignBucketStart($start, $granularity);

        while ($current <= $end) {
            $buckets[] = self::bucketKey($current, $granularity);
            $current = self::advanceBucket($current, $granularity);
            if (count($buckets) > 500) {
                break;
            }
        }

        return $buckets;
    }

    private static function alignBucketStart(DateTimeImmutable $dt, string $granularity): DateTimeImmutable
    {
        return match ($granularity) {
            'minute' => $dt->setTime((int) $dt->format('H'), (int) $dt->format('i'), 0),
            'hour' => $dt->setTime((int) $dt->format('H'), 0, 0),
            'day' => $dt->setTime(0, 0, 0),
            'week' => $dt->modify('monday this week')->setTime(0, 0, 0),
            'month' => $dt->modify('first day of this month')->setTime(0, 0, 0),
            'year' => $dt->modify('first day of january')->setTime(0, 0, 0),
            default => $dt->setTime(0, 0, 0),
        };
    }

    private static function advanceBucket(DateTimeImmutable $dt, string $granularity): DateTimeImmutable
    {
        return match ($granularity) {
            'minute' => $dt->modify('+1 minute'),
            'hour' => $dt->modify('+1 hour'),
            'day' => $dt->modify('+1 day'),
            'week' => $dt->modify('+1 week'),
            'month' => $dt->modify('+1 month'),
            'year' => $dt->modify('+1 year'),
            default => $dt->modify('+1 day'),
        };
    }

    private static function bucketKey(DateTimeImmutable $dt, string $granularity): string
    {
        return match ($granularity) {
            'minute' => $dt->format('Y-m-d H:i:00'),
            'hour' => $dt->format('Y-m-d H:00:00'),
            'day' => $dt->format('Y-m-d'),
            'week' => $dt->modify('monday this week')->format('Y-m-d'),
            'month' => $dt->format('Y-m-01'),
            'year' => $dt->format('Y-01-01'),
            default => $dt->format('Y-m-d'),
        };
    }

    public static function formatBucketLabel(string $bucket, string $granularity): string
    {
        try {
            $dt = new DateTimeImmutable($bucket, new DateTimeZone('UTC'));
        } catch (Exception) {
            return $bucket;
        }

        return match ($granularity) {
            'minute' => $dt->format('M j, g:i A'),
            'hour' => $dt->format('M j, g A'),
            'day' => $dt->format('M j'),
            'week' => 'Week of ' . $dt->format('M j'),
            'month' => $dt->format('M Y'),
            'year' => $dt->format('Y'),
            default => $dt->format('M j, Y'),
        };
    }

    public static function granularityLabel(string $granularity): string
    {
        return match ($granularity) {
            'minute' => 'By minute (last hour)',
            'hour' => 'By hour (last 24h)',
            'day' => 'By day (last 30 days)',
            'week' => 'By week (last 12 weeks)',
            'month' => 'By month (last 12 months)',
            'year' => 'By year (last 5 years)',
            default => ucfirst($granularity),
        };
    }
}
