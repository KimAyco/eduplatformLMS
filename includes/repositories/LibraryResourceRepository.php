<?php

class LibraryResourceRepository
{
    public const RESOURCE_KINDS = ['lesson', 'book', 'module', 'worksheet', 'reference', 'other'];

    public static function get(int $id, int $schoolId): ?array
    {
        $stmt = db()->prepare('SELECT lr.*,
            s.name AS subject_name,
            u.first_name AS creator_first, u.last_name AS creator_last
            FROM library_resources lr
            LEFT JOIN subjects s ON s.id = lr.subject_id
            INNER JOIN users u ON u.id = lr.created_by
            WHERE lr.id = ? AND lr.school_id = ?');
        $stmt->execute([$id, $schoolId]);
        $row = $stmt->fetch();
        return $row ? self::normalizeRow($row) : null;
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT lr.*,
            s.name AS subject_name,
            u.first_name AS creator_first, u.last_name AS creator_last
            FROM library_resources lr
            LEFT JOIN subjects s ON s.id = lr.subject_id
            INNER JOIN users u ON u.id = lr.created_by
            WHERE lr.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? self::normalizeRow($row) : null;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public static function forSchool(int $schoolId, array $filters = []): array
    {
        $sql = 'SELECT lr.*,
            s.name AS subject_name,
            u.first_name AS creator_first, u.last_name AS creator_last
            FROM library_resources lr
            LEFT JOIN subjects s ON s.id = lr.subject_id
            INNER JOIN users u ON u.id = lr.created_by
            WHERE lr.school_id = ?';
        $params = [$schoolId];

        if (!empty($filters['status'])) {
            $sql .= ' AND lr.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['audience'])) {
            $sql .= ' AND lr.audience = ?';
            $params[] = $filters['audience'];
        }
        if (!empty($filters['resource_kind'])) {
            $sql .= ' AND lr.resource_kind = ?';
            $params[] = normalizeResourceKind((string) $filters['resource_kind']);
        }
        if (!empty($filters['subject_id'])) {
            $sql .= ' AND lr.subject_id = ?';
            $params[] = (int) $filters['subject_id'];
        }
        if (!empty($filters['type'])) {
            $sql .= ' AND lr.type = ?';
            $params[] = normalizeMaterialType((string) $filters['type']);
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (lr.title LIKE ? OR lr.description LIKE ?)';
            $q = '%' . $filters['search'] . '%';
            $params[] = $q;
            $params[] = $q;
        }
        if (!empty($filters['created_by'])) {
            $sql .= ' AND lr.created_by = ?';
            $params[] = (int) $filters['created_by'];
        }
        if (($filters['browse_role'] ?? '') === 'student') {
            $sql .= " AND lr.status = 'published' AND lr.audience = 'all'";
        } elseif (($filters['browse_role'] ?? '') === 'teacher') {
            $sql .= " AND lr.status = 'published'";
        }

        $sql .= ' ORDER BY lr.created_at DESC, lr.id DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return array_map([self::class, 'normalizeRow'], $stmt->fetchAll());
    }

    public static function pendingForSchool(int $schoolId): array
    {
        return self::forSchool($schoolId, ['status' => 'pending']);
    }

    public static function countByStatus(int $schoolId, string $status): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM library_resources WHERE school_id = ? AND status = ?');
        $stmt->execute([$schoolId, $status]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function createFromAdmin(int $schoolId, int $adminId, array $data): int
    {
        return self::insert($schoolId, $adminId, $data, 'published');
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function createFromMaterial(int $schoolId, int $teacherId, array $material, array $data): int
    {
        $mat = MaterialRepository::normalizeRow($material);
        $existingId = !empty($mat['library_resource_id']) ? (int) $mat['library_resource_id'] : 0;

        if ($existingId) {
            $existing = self::get($existingId, $schoolId);
            if (!$existing || $existing['status'] !== 'rejected') {
                throw new InvalidArgumentException('This material is already in the library workflow.');
            }
            self::update($existingId, $schoolId, [
                'description' => $data['description'] ?? ($mat['body'] ?? null),
                'resource_kind' => $data['resource_kind'] ?? 'other',
                'subject_id' => $data['subject_id'] ?? null,
                'audience' => $data['audience'] ?? 'all',
            ]);
            db()->prepare("UPDATE library_resources SET status = 'pending', rejection_note = NULL, approved_by = NULL, approved_at = NULL WHERE id = ? AND school_id = ?")
                ->execute([$existingId, $schoolId]);
            return $existingId;
        }

        $payload = [
            'source_material_id' => (int) $mat['id'],
            'title' => $mat['title'],
            'description' => $data['description'] ?? ($mat['body'] ?? null),
            'resource_kind' => $data['resource_kind'] ?? 'other',
            'subject_id' => $data['subject_id'] ?? null,
            'type' => $mat['type'],
            'content' => $mat['content'] ?? null,
            'body' => $mat['body'] ?? null,
            'file_path' => $mat['file_path'] ?? null,
            'original_name' => $mat['original_name'] ?? null,
            'mime_type' => $mat['mime_type'] ?? null,
            'file_size' => (int) ($mat['file_size'] ?? 0),
            'file_access_mode' => $mat['file_access_mode'] ?? 'downloadable',
            'external_link' => $mat['external_link'] ?? null,
            'audience' => $data['audience'] ?? 'all',
        ];

        $libraryId = self::insert($schoolId, $teacherId, $payload, 'pending');

        db()->prepare('UPDATE materials SET library_resource_id = ? WHERE id = ?')
            ->execute([$libraryId, (int) $mat['id']]);

        return $libraryId;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function insert(int $schoolId, int $userId, array $data, string $status): int
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Title is required.');
        }

        $type = normalizeMaterialType((string) ($data['type'] ?? 'file'));
        $audience = ($data['audience'] ?? 'all') === 'teachers' ? 'teachers' : 'all';
        $resourceKind = normalizeResourceKind((string) ($data['resource_kind'] ?? 'other'));
        $subjectId = !empty($data['subject_id']) ? (int) $data['subject_id'] : null;

        $stmt = db()->prepare('INSERT INTO library_resources
            (school_id, created_by, source_material_id, title, description, resource_kind, subject_id,
             type, content, body, file_path, original_name, mime_type, file_size, file_access_mode,
             external_link, status, audience)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $schoolId,
            $userId,
            $data['source_material_id'] ?? null,
            $title,
            !empty($data['description']) ? trim((string) $data['description']) : null,
            $resourceKind,
            $subjectId,
            $type,
            $data['content'] ?? null,
            $data['body'] ?? null,
            $data['file_path'] ?? null,
            $data['original_name'] ?? null,
            $data['mime_type'] ?? null,
            (int) ($data['file_size'] ?? 0),
            ($data['file_access_mode'] ?? 'downloadable') === 'view_only' ? 'view_only' : 'downloadable',
            $data['external_link'] ?? null,
            in_array($status, ['pending', 'published', 'rejected'], true) ? $status : 'pending',
            $audience,
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

        $type = normalizeMaterialType((string) ($data['type'] ?? $existing['type']));
        $stmt = db()->prepare('UPDATE library_resources SET
            title = ?, description = ?, resource_kind = ?, subject_id = ?,
            type = ?, content = ?, body = ?,
            file_path = ?, original_name = ?, mime_type = ?, file_size = ?,
            file_access_mode = ?, external_link = ?, audience = ?
            WHERE id = ? AND school_id = ?');
        $stmt->execute([
            trim((string) ($data['title'] ?? $existing['title'])),
            array_key_exists('description', $data) ? ($data['description'] ?: null) : $existing['description'],
            normalizeResourceKind((string) ($data['resource_kind'] ?? $existing['resource_kind'])),
            array_key_exists('subject_id', $data) ? ($data['subject_id'] ? (int) $data['subject_id'] : null) : $existing['subject_id'],
            $type,
            array_key_exists('content', $data) ? $data['content'] : $existing['content'],
            array_key_exists('body', $data) ? $data['body'] : $existing['body'],
            array_key_exists('file_path', $data) ? $data['file_path'] : $existing['file_path'],
            array_key_exists('original_name', $data) ? $data['original_name'] : $existing['original_name'],
            array_key_exists('mime_type', $data) ? $data['mime_type'] : $existing['mime_type'],
            array_key_exists('file_size', $data) ? (int) $data['file_size'] : (int) $existing['file_size'],
            ($data['file_access_mode'] ?? $existing['file_access_mode']) === 'view_only' ? 'view_only' : 'downloadable',
            array_key_exists('external_link', $data) ? $data['external_link'] : $existing['external_link'],
            ($data['audience'] ?? $existing['audience']) === 'teachers' ? 'teachers' : 'all',
            $id,
            $schoolId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function approve(int $id, int $schoolId, int $adminId): bool
    {
        $stmt = db()->prepare("UPDATE library_resources
            SET status = 'published', approved_by = ?, approved_at = NOW(), rejection_note = NULL
            WHERE id = ? AND school_id = ? AND status = 'pending'");
        $stmt->execute([$adminId, $id, $schoolId]);
        return $stmt->rowCount() > 0;
    }

    public static function reject(int $id, int $schoolId, int $adminId, ?string $note = null): bool
    {
        $stmt = db()->prepare("UPDATE library_resources
            SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_note = ?
            WHERE id = ? AND school_id = ? AND status = 'pending'");
        $stmt->execute([$adminId, $note ?: null, $id, $schoolId]);
        return $stmt->rowCount() > 0;
    }

    public static function unpublish(int $id, int $schoolId): bool
    {
        $stmt = db()->prepare("UPDATE library_resources SET status = 'rejected', rejection_note = 'Unpublished by admin'
            WHERE id = ? AND school_id = ? AND status = 'published'");
        $stmt->execute([$id, $schoolId]);
        return $stmt->rowCount() > 0;
    }

    public static function delete(int $id, int $schoolId): bool
    {
        $existing = self::get($id, $schoolId);
        if (!$existing) {
            return false;
        }

        $linked = db()->prepare('SELECT COUNT(*) FROM materials WHERE library_resource_id = ?');
        $linked->execute([$id]);
        if ((int) $linked->fetchColumn() > 0) {
            throw new RuntimeException('Cannot delete a resource that is attached to classes. Unpublish it instead.');
        }

        if (!empty($existing['file_path']) && !self::isFilePathShared($existing['file_path'], $id)) {
            deleteUpload($existing['file_path']);
        }

        db()->prepare('DELETE FROM library_resources WHERE id = ? AND school_id = ?')->execute([$id, $schoolId]);
        return true;
    }

    public static function attachToClass(int $libraryId, int $classId, ?int $sectionId, int $teacherId, int $schoolId): int
    {
        $resource = self::get($libraryId, $schoolId);
        if (!$resource || $resource['status'] !== 'published') {
            throw new InvalidArgumentException('Library resource not found or not published.');
        }

        if (!teacherHasClass($classId)) {
            throw new InvalidArgumentException('You are not assigned to this class.');
        }

        $resolvedSection = $sectionId ? CourseSectionRepository::resolveSectionId($sectionId, $classId) : null;

        $stmt = db()->prepare('INSERT INTO materials
            (class_id, section_id, teacher_id, library_resource_id, type, title, content, body,
             file_path, original_name, mime_type, file_size, file_access_mode, external_link)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $classId,
            $resolvedSection,
            $teacherId,
            $libraryId,
            $resource['type'],
            $resource['title'],
            $resource['content'],
            $resource['body'],
            $resource['file_path'],
            $resource['original_name'],
            $resource['mime_type'],
            (int) $resource['file_size'],
            $resource['file_access_mode'],
            $resource['external_link'],
        ]);

        $materialId = (int) db()->lastInsertId();
        lessonContextReindexClass($classId);
        return $materialId;
    }

    public static function findByFilePath(string $filePath): ?array
    {
        $stmt = db()->prepare('SELECT * FROM library_resources WHERE file_path = ?');
        $stmt->execute([ltrim($filePath, '/')]);
        $row = $stmt->fetch();
        return $row ? self::normalizeRow($row) : null;
    }

    public static function getLibraryStatusForMaterial(int $materialId): ?array
    {
        $stmt = db()->prepare('SELECT lr.* FROM library_resources lr
            INNER JOIN materials m ON m.library_resource_id = lr.id
            WHERE m.id = ?');
        $stmt->execute([$materialId]);
        $row = $stmt->fetch();
        return $row ? self::normalizeRow($row) : null;
    }

    public static function userHasClassAccessViaResource(int $libraryId, array $user): bool
    {
        $stmt = db()->prepare('SELECT m.class_id FROM materials m WHERE m.library_resource_id = ?');
        $stmt->execute([$libraryId]);
        while ($classId = $stmt->fetchColumn()) {
            if ($user['role'] === 'teacher' && teacherHasClass((int) $classId)) {
                return true;
            }
            if ($user['role'] === 'student' && studentHasClass((int) $classId)) {
                return true;
            }
        }
        return false;
    }

    private static function isFilePathShared(string $filePath, int $excludeId): bool
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM library_resources WHERE file_path = ? AND id != ?');
        $stmt->execute([$filePath, $excludeId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return true;
        }

        $stmt = db()->prepare('SELECT COUNT(*) FROM materials WHERE file_path = ?');
        $stmt->execute([$filePath]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizeRow(array $row): array
    {
        $row['type'] = normalizeMaterialType((string) ($row['type'] ?? 'file'));
        $row['resource_kind'] = normalizeResourceKind((string) ($row['resource_kind'] ?? 'other'));

        if ($row['type'] === 'link') {
            $row['content'] = trim((string) ($row['content'] ?? $row['external_link'] ?? ''));
        }
        if ($row['type'] === 'doc' && empty($row['content']) && !empty($row['body'])) {
            $row['content'] = $row['body'];
        }
        if ($row['type'] === 'deck' && empty($row['content'])) {
            $row['content'] = defaultDeckContent();
        }
        if (empty($row['file_access_mode'])) {
            $row['file_access_mode'] = 'downloadable';
        }

        return $row;
    }
}
