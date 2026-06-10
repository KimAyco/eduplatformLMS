<?php

class ContentResourceRepository
{
    /**
     * @param array<string, mixed> $filters
     */
    public static function forSchool(int $schoolId, array $filters = []): array
    {
        $sql = 'SELECT cr.*,
            s.name AS subject_name,
            u.first_name AS creator_first, u.last_name AS creator_last,
            lr.status AS library_status
            FROM content_resources cr
            LEFT JOIN subjects s ON s.id = cr.subject_id
            INNER JOIN users u ON u.id = cr.created_by
            LEFT JOIN library_resources lr ON lr.id = cr.library_resource_id
            WHERE cr.school_id = ?';
        $params = [$schoolId];

        if (!empty($filters['created_by'])) {
            $sql .= ' AND cr.created_by = ?';
            $params[] = (int) $filters['created_by'];
        }
        if (!empty($filters['resource_type'])) {
            $sql .= ' AND cr.resource_type = ?';
            $params[] = normalizeContentResourceType((string) $filters['resource_type']);
        }
        if (($filters['status'] ?? '') === 'archived') {
            $sql .= " AND cr.status = 'archived'";
        } elseif (($filters['status'] ?? '') !== 'all') {
            $sql .= " AND cr.status = 'draft'";
        }
        if (($filters['shared'] ?? '') === 'yes') {
            $sql .= ' AND cr.library_resource_id IS NOT NULL';
        } elseif (($filters['shared'] ?? '') === 'no') {
            $sql .= ' AND cr.library_resource_id IS NULL';
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (cr.title LIKE ? OR cr.description LIKE ?)';
            $q = '%' . $filters['search'] . '%';
            $params[] = $q;
            $params[] = $q;
        }
        if (!empty($filters['subject_id'])) {
            $sql .= ' AND cr.subject_id = ?';
            $params[] = (int) $filters['subject_id'];
        }

        $sort = (string) ($filters['sort'] ?? 'updated');
        $sql .= match ($sort) {
            'title' => ' ORDER BY cr.title ASC, cr.id ASC',
            'type' => ' ORDER BY cr.resource_type ASC, cr.title ASC',
            default => ' ORDER BY cr.updated_at DESC, cr.id DESC',
        };
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return array_map([self::class, 'normalizeRow'], $stmt->fetchAll());
    }

    public static function get(int $id, int $schoolId): ?array
    {
        $stmt = db()->prepare('SELECT cr.*,
            s.name AS subject_name,
            u.first_name AS creator_first, u.last_name AS creator_last,
            lr.status AS library_status
            FROM content_resources cr
            LEFT JOIN subjects s ON s.id = cr.subject_id
            INNER JOIN users u ON u.id = cr.created_by
            LEFT JOIN library_resources lr ON lr.id = cr.library_resource_id
            WHERE cr.id = ? AND cr.school_id = ?');
        $stmt->execute([$id, $schoolId]);
        $row = $stmt->fetch();
        return $row ? self::normalizeRow($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(int $schoolId, int $userId, array $data): int
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Title is required.');
        }

        $resourceType = normalizeContentResourceType((string) ($data['resource_type'] ?? 'deck'));
        $rawContent = $data['content'] ?? ($resourceType === 'deck' ? defaultDeckContent() : '');
        if (is_array($rawContent)) {
            $rawContent = json_encode($rawContent, JSON_UNESCAPED_UNICODE);
        }
        $content = $resourceType === 'deck'
            ? json_encode(validateDeckContent((string) $rawContent), JSON_UNESCAPED_UNICODE)
            : sanitizeHtml((string) $rawContent);

        $stmt = db()->prepare('INSERT INTO content_resources
            (school_id, created_by, title, description, subject_id, resource_type, content, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $schoolId,
            $userId,
            $title,
            !empty($data['description']) ? trim((string) $data['description']) : null,
            !empty($data['subject_id']) ? (int) $data['subject_id'] : null,
            $resourceType,
            $content,
            'draft',
        ]);

        return (int) db()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(int $id, int $schoolId, array $data): bool
    {
        $existing = self::get($id, $schoolId);
        if (!$existing) {
            return false;
        }

        $resourceType = normalizeContentResourceType((string) ($data['resource_type'] ?? $existing['resource_type']));
        $content = $existing['content'];
        if (array_key_exists('content', $data)) {
            $rawContent = is_array($data['content'])
                ? json_encode($data['content'], JSON_UNESCAPED_UNICODE)
                : (string) $data['content'];
            $content = $resourceType === 'deck'
                ? json_encode(validateDeckContent($rawContent), JSON_UNESCAPED_UNICODE)
                : sanitizeHtml($rawContent);
        }

        $stmt = db()->prepare('UPDATE content_resources SET
            title = ?, description = ?, subject_id = ?, resource_type = ?,
            content = ?, thumbnail_path = ?, status = ?, updated_at = NOW()
            WHERE id = ? AND school_id = ?');
        $stmt->execute([
            trim((string) ($data['title'] ?? $existing['title'])),
            array_key_exists('description', $data) ? ($data['description'] ?: null) : $existing['description'],
            array_key_exists('subject_id', $data) ? ($data['subject_id'] ? (int) $data['subject_id'] : null) : $existing['subject_id'],
            $resourceType,
            $content,
            array_key_exists('thumbnail_path', $data) ? $data['thumbnail_path'] : $existing['thumbnail_path'],
            in_array($data['status'] ?? $existing['status'], ['draft', 'archived'], true) ? ($data['status'] ?? $existing['status']) : $existing['status'],
            $id,
            $schoolId,
        ]);

        return true;
    }

    public static function duplicate(int $id, int $schoolId, int $userId): int
    {
        $existing = self::get($id, $schoolId);
        if (!$existing || !canAccessContentResource($existing)) {
            throw new InvalidArgumentException('Resource not found.');
        }

        return self::create($schoolId, $userId, [
            'title' => $existing['title'] . ' (copy)',
            'description' => $existing['description'],
            'subject_id' => $existing['subject_id'],
            'resource_type' => $existing['resource_type'],
            'content' => $existing['content'],
        ]);
    }

    public static function archive(int $id, int $schoolId): bool
    {
        $stmt = db()->prepare("UPDATE content_resources SET status = 'archived', updated_at = NOW() WHERE id = ? AND school_id = ?");
        $stmt->execute([$id, $schoolId]);
        return $stmt->rowCount() > 0;
    }

    public static function restore(int $id, int $schoolId): bool
    {
        $stmt = db()->prepare("UPDATE content_resources SET status = 'draft', updated_at = NOW() WHERE id = ? AND school_id = ?");
        $stmt->execute([$id, $schoolId]);
        return $stmt->rowCount() > 0;
    }

    public static function delete(int $id, int $schoolId): bool
    {
        $existing = self::get($id, $schoolId);
        if (!$existing) {
            return false;
        }

        $linked = db()->prepare('SELECT COUNT(*) FROM materials WHERE content_resource_id = ?');
        $linked->execute([$id]);
        if ((int) $linked->fetchColumn() > 0) {
            throw new RuntimeException('Cannot delete a resource attached to classes. Archive it instead.');
        }

        if (!empty($existing['thumbnail_path'])) {
            deleteUpload($existing['thumbnail_path']);
        }

        db()->prepare('DELETE FROM content_resources WHERE id = ? AND school_id = ?')->execute([$id, $schoolId]);
        return true;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function shareToLibrary(int $resourceId, int $schoolId, int $userId, array $metadata): int
    {
        $resource = self::get($resourceId, $schoolId);
        if (!$resource || !canAccessContentResource($resource)) {
            throw new InvalidArgumentException('Resource not found.');
        }

        if (!empty($resource['library_resource_id'])) {
            $existing = LibraryResourceRepository::get((int) $resource['library_resource_id'], $schoolId);
            if ($existing && in_array($existing['status'] ?? '', ['pending', 'published'], true)) {
                throw new InvalidArgumentException('This resource is already in the library workflow.');
            }
        }

        $matType = contentResourceMaterialType($resource['resource_type']);
        $payload = [
            'title' => $resource['title'],
            'description' => $metadata['description'] ?? $resource['description'],
            'resource_kind' => $metadata['resource_kind'] ?? 'lesson',
            'subject_id' => $metadata['subject_id'] ?? $resource['subject_id'],
            'type' => $matType,
            'content' => $resource['content'],
            'body' => $resource['description'],
            'audience' => $metadata['audience'] ?? 'all',
        ];

        if (!empty($resource['library_resource_id'])) {
            $libraryId = (int) $resource['library_resource_id'];
            LibraryResourceRepository::update($libraryId, $schoolId, $payload);
            db()->prepare("UPDATE library_resources SET status = 'pending', rejection_note = NULL, approved_by = NULL, approved_at = NULL WHERE id = ? AND school_id = ?")
                ->execute([$libraryId, $schoolId]);
        } else {
            $stmt = db()->prepare('INSERT INTO library_resources
                (school_id, created_by, title, description, resource_kind, subject_id,
                 type, content, body, status, audience)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $schoolId,
                $userId,
                $payload['title'],
                $payload['description'] ?: null,
                normalizeResourceKind((string) $payload['resource_kind']),
                $payload['subject_id'] ? (int) $payload['subject_id'] : null,
                $matType,
                $payload['content'],
                $payload['body'] ?: null,
                'pending',
                ($payload['audience'] ?? 'all') === 'teachers' ? 'teachers' : 'all',
            ]);
            $libraryId = (int) db()->lastInsertId();
            db()->prepare('UPDATE content_resources SET library_resource_id = ? WHERE id = ? AND school_id = ?')
                ->execute([$libraryId, $resourceId, $schoolId]);
        }

        return $libraryId;
    }

    public static function attachToClass(int $resourceId, int $classId, ?int $sectionId, int $teacherId, int $schoolId): int
    {
        $resource = self::get($resourceId, $schoolId);
        if (!$resource || !canAccessContentResource($resource)) {
            throw new InvalidArgumentException('Resource not found.');
        }

        if (!teacherHasClass($classId)) {
            throw new InvalidArgumentException('You are not assigned to this class.');
        }

        $resolvedSection = $sectionId ? CourseSectionRepository::resolveSectionId($sectionId, $classId) : null;
        $matType = contentResourceMaterialType($resource['resource_type']);

        $materialId = MaterialRepository::create([
            'class_id' => $classId,
            'section_id' => $resolvedSection,
            'teacher_id' => $teacherId,
            'content_resource_id' => $resourceId,
            'library_resource_id' => $resource['library_resource_id'] ?: null,
            'type' => $matType,
            'title' => $resource['title'],
            'content' => $resource['content'],
            'body' => $resource['description'],
        ]);
        lessonContextReindexClass($classId);
        return $materialId;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizeRow(array $row): array
    {
        $row['resource_type'] = normalizeContentResourceType((string) ($row['resource_type'] ?? 'deck'));
        $row['status'] = in_array($row['status'] ?? 'draft', ['draft', 'archived'], true) ? $row['status'] : 'draft';
        return $row;
    }
}
