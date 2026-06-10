<?php

class LessonContextService
{
    public static function indexClassSection(int $classId, ?int $sectionId, bool $courseWide = false): array
    {
        if ($courseWide) {
            return self::indexClassCourse($classId);
        }

        $sources = self::collectSources($classId, $sectionId, false);
        return self::persistContext($classId, $sectionId, false, $sources);
    }

    public static function indexClassCourse(int $classId): array
    {
        $sources = self::collectSources($classId, null, true);
        return self::persistContext($classId, null, true, $sources);
    }

    public static function indexEntireClass(int $classId): void
    {
        self::indexClassSection($classId, null, false);
        self::indexClassCourse($classId);
        $sections = CourseSectionRepository::forClass($classId);
        foreach ($sections as $section) {
            self::indexClassSection($classId, (int) $section['id'], false);
        }
    }

    public static function getContext(int $classId, ?int $sectionId, bool $courseWide = false): ?array
    {
        $stmt = db()->prepare('SELECT * FROM lesson_contexts WHERE class_id = ? AND is_course_wide = ? AND '
            . ($courseWide || $sectionId === null || $sectionId === 0 ? 'section_id IS NULL' : 'section_id = ?'));
        $params = [$classId, $courseWide ? 1 : 0];
        if (!$courseWide && $sectionId !== null && $sectionId > 0) {
            $params[] = $sectionId;
        }
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function ensureIndexed(int $classId, ?int $sectionId, bool $courseWide = false): array
    {
        if (!aiPracticeTablesReady()) {
            throw new RuntimeException('Practice quizzes are not set up yet. Please ask your administrator to run database migrations.');
        }

        $existing = self::getContext($classId, $sectionId, $courseWide);
        $sources = self::collectSources($classId, $sectionId, $courseWide);
        $hash = self::computeSourcesHash($sources);

        if ($existing && ($existing['sources_hash'] ?? '') === $hash && ($existing['status'] ?? '') === 'ready') {
            return $existing;
        }

        return self::indexClassSection($classId, $sectionId, $courseWide);
    }

    /** @param list<array<string, mixed>> $sources */
    private static function persistContext(int $classId, ?int $sectionId, bool $courseWide, array $sources): array
    {
        $hash = self::computeSourcesHash($sources);
        $sectionVal = ($courseWide || $sectionId === null || $sectionId === 0) ? null : $sectionId;

        $contextId = self::upsertContextRow($classId, $sectionVal, $courseWide, $hash);
        self::syncSourceRows($contextId, $sources);

        $combined = [];
        foreach ($sources as $source) {
            if (!empty($source['text'])) {
                $combined[] = $source['text'];
            }
        }
        $contextText = textTruncateForContext(trim(implode("\n\n---\n\n", $combined)));
        $status = $contextText !== '' ? 'ready' : 'empty';

        db()->prepare('UPDATE lesson_contexts SET context_text = ?, sources_hash = ?, token_estimate = ?,
            status = ?, last_indexed_at = UTC_TIMESTAMP() WHERE id = ?')
            ->execute([
                $contextText ?: null,
                $hash,
                (int) ceil(mb_strlen($contextText) / 4),
                $status,
                $contextId,
            ]);

        $row = self::getContext($classId, $sectionVal, $courseWide);
        if ($row && $status === 'ready') {
            self::maybeQueueBankRebuild($classId, $sectionVal, $courseWide, $hash);
        }

        return $row ?? [];
    }

    /** @return list<array<string, mixed>> */
    private static function collectSources(int $classId, ?int $sectionId, bool $courseWide): array
    {
        $sources = [];
        $sectionTitles = [];

        if ($courseWide) {
            foreach (CourseSectionRepository::forClass($classId) as $sec) {
                $sectionTitles[(int) $sec['id']] = (string) $sec['title'];
            }
        }

        $sql = 'SELECT m.*, cs.title AS section_title FROM materials m
            LEFT JOIN course_sections cs ON cs.id = m.section_id
            WHERE m.class_id = ?';
        $params = [$classId];

        if (!$courseWide) {
            if ($sectionId === null || $sectionId === 0) {
                $sql .= ' AND (m.section_id IS NULL OR m.section_id = 0)';
            } else {
                $sql .= ' AND m.section_id = ?';
                $params[] = $sectionId;
            }
        }

        $sql .= ' ORDER BY cs.sort_order, m.section_id, m.id';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        foreach ($stmt->fetchAll() as $material) {
            $text = textExtractFromMaterial($material);
            if ($courseWide && !empty($material['section_title'])) {
                $text = "Lesson: {$material['section_title']}\n" . $text;
            } elseif ($courseWide) {
                $text = "Lesson: Unassigned\n" . $text;
            }
            $sources[] = [
                'source_type' => 'material',
                'source_id' => (int) $material['id'],
                'title' => (string) $material['title'],
                'text' => $text,
                'content_hash' => textContentHash($text . '|' . ($material['updated_at'] ?? '')),
            ];
            if (!empty($material['library_resource_id'])) {
                $lib = LibraryResourceRepository::find((int) $material['library_resource_id']);
                if ($lib) {
                    $libText = textExtractFromLibraryResource($lib);
                    if ($courseWide && !empty($material['section_title'])) {
                        $libText = "Lesson: {$material['section_title']}\n" . $libText;
                    }
                    $sources[] = [
                        'source_type' => 'library',
                        'source_id' => (int) $lib['id'],
                        'title' => (string) $lib['title'],
                        'text' => $libText,
                        'content_hash' => textContentHash($libText . '|' . ($lib['updated_at'] ?? '')),
                    ];
                }
            }
        }

        $quizSql = "SELECT q.id, q.title, q.instructions, q.section_id, q.updated_at, cs.title AS section_title
            FROM quizzes q
            LEFT JOIN course_sections cs ON cs.id = q.section_id
            WHERE q.class_id = ? AND q.quiz_mode = 'exam'";
        $quizParams = [$classId];

        if (!$courseWide) {
            if ($sectionId === null || $sectionId === 0) {
                $quizSql .= ' AND (q.section_id IS NULL OR q.section_id = 0)';
            } else {
                $quizSql .= ' AND q.section_id = ?';
                $quizParams[] = $sectionId;
            }
        }

        $qStmt = db()->prepare($quizSql);
        $qStmt->execute($quizParams);
        foreach ($qStmt->fetchAll() as $quiz) {
            $meta = 'Exam topic: ' . $quiz['title'];
            if ($courseWide && !empty($quiz['section_title'])) {
                $meta = "Lesson: {$quiz['section_title']}\n" . $meta;
            } elseif ($courseWide) {
                $meta = "Lesson: Unassigned\n" . $meta;
            }
            if (!empty($quiz['instructions'])) {
                $meta .= "\nInstructions: " . $quiz['instructions'];
            }
            $sources[] = [
                'source_type' => 'exam_meta',
                'source_id' => (int) $quiz['id'],
                'title' => (string) $quiz['title'],
                'text' => $meta,
                'content_hash' => textContentHash($meta . '|' . ($quiz['updated_at'] ?? '')),
            ];
        }

        return $sources;
    }

    /** @param list<array<string, mixed>> $sources */
    private static function computeSourcesHash(array $sources): string
    {
        $parts = [];
        foreach ($sources as $s) {
            $parts[] = ($s['source_type'] ?? '') . ':' . ($s['source_id'] ?? '') . ':' . ($s['content_hash'] ?? '');
        }
        sort($parts);
        return hash('sha256', implode('|', $parts));
    }

    private static function upsertContextRow(int $classId, ?int $sectionId, bool $courseWide, string $hash): int
    {
        $existing = self::getContext($classId, $sectionId, $courseWide);
        if ($existing) {
            return (int) $existing['id'];
        }

        db()->prepare('INSERT INTO lesson_contexts (class_id, section_id, is_course_wide, sources_hash, status) VALUES (?, ?, ?, ?, ?)')
            ->execute([$classId, $sectionId, $courseWide ? 1 : 0, $hash, 'pending']);
        return (int) db()->lastInsertId();
    }

    /** @param list<array<string, mixed>> $sources */
    private static function syncSourceRows(int $contextId, array $sources): void
    {
        db()->prepare('DELETE FROM lesson_context_sources WHERE lesson_context_id = ?')->execute([$contextId]);
        $stmt = db()->prepare('INSERT INTO lesson_context_sources (lesson_context_id, source_type, source_id, title, content_hash, excerpt)
            VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($sources as $source) {
            $excerpt = mb_substr((string) ($source['text'] ?? ''), 0, 500);
            $stmt->execute([
                $contextId,
                $source['source_type'],
                $source['source_id'] ?? null,
                $source['title'] ?? '',
                $source['content_hash'] ?? '',
                $excerpt ?: null,
            ]);
        }
    }

    private static function maybeQueueBankRebuild(int $classId, ?int $sectionId, bool $courseWide, string $hash): void
    {
        if (!aiIsEnabled()) {
            return;
        }

        $stmt = db()->prepare('SELECT id, context_version FROM practice_question_bank WHERE class_id = ? AND is_course_wide = ? AND '
            . ($sectionId === null ? 'section_id IS NULL' : 'section_id = ?'));
        $params = [$classId, $courseWide ? 1 : 0];
        if ($sectionId !== null) {
            $params[] = $sectionId;
        }
        $stmt->execute($params);
        $bank = $stmt->fetch();
        if ($bank && ($bank['context_version'] ?? '') === $hash) {
            return;
        }

        $scopeLabel = $courseWide ? 'course' : ($sectionId ? 'section ' . $sectionId : 'unassigned');
        AiQueueRepository::enqueue('build_practice_bank', [
            'class_id' => $classId,
            'section_id' => $sectionId,
            'is_course_wide' => $courseWide ? 1 : 0,
            'context_version' => $hash,
        ], null, null, 6, 'Practice bank (' . $scopeLabel . ') class ' . $classId);
    }
}

function lessonContextReindexClass(int $classId): void
{
    if (!aiPracticeTablesReady()) {
        return;
    }
    try {
        LessonContextService::indexEntireClass($classId);
    } catch (Throwable $e) {
        if (APP_DEBUG) {
            error_log('lessonContextReindexClass: ' . $e->getMessage());
        }
    }
}
