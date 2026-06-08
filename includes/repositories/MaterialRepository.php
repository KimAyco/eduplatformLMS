<?php

class MaterialRepository
{
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT m.*, c.school_id FROM materials m
            INNER JOIN classes c ON c.id = m.class_id
            WHERE m.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findForTeacher(int $id, int $teacherId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM materials WHERE id = ? AND teacher_id = ?');
        $stmt->execute([$id, $teacherId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @alias find */
    public static function get(int $materialId, int $classId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM materials WHERE id = ? AND class_id = ?');
        $stmt->execute([$materialId, $classId]);
        return $stmt->fetch() ?: null;
    }

    public static function getById(int $materialId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM materials WHERE id = ?');
        $stmt->execute([$materialId]);
        return $stmt->fetch() ?: null;
    }

    public static function forClass(int $classId, ?int $sectionId = null, ?int $teacherId = null): array
    {
        $sql = 'SELECT * FROM materials WHERE class_id = ?';
        $params = [$classId];

        if ($sectionId !== null) {
            $sql .= ' AND section_id = ?';
            $params[] = $sectionId;
        }
        if ($teacherId !== null) {
            $sql .= ' AND teacher_id = ?';
            $params[] = $teacherId;
        }

        $sql .= ' ORDER BY created_at ASC, id ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        $classId = (int) ($data['class_id'] ?? 0);
        $teacherId = (int) ($data['teacher_id'] ?? 0);
        if ($classId <= 0 || $teacherId <= 0) {
            throw new InvalidArgumentException('class_id and teacher_id are required.');
        }

        $type = normalizeMaterialType((string) ($data['type'] ?? 'file'));
        $sectionId = isset($data['section_id']) ? CourseSectionRepository::resolveSectionId((int) $data['section_id'], $classId) : null;
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Title is required.');
        }

        $stmt = db()->prepare('INSERT INTO materials
            (class_id, section_id, teacher_id, type, title, content, body, file_path, original_name, mime_type, file_size, file_access_mode, external_link)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $classId,
            $sectionId,
            $teacherId,
            $type,
            $title,
            $data['content'] ?? null,
            $data['body'] ?? null,
            $data['file_path'] ?? null,
            $data['original_name'] ?? null,
            $data['mime_type'] ?? null,
            (int) ($data['file_size'] ?? 0),
            ($data['file_access_mode'] ?? 'downloadable') === 'view_only' ? 'view_only' : 'downloadable',
            $data['external_link'] ?? null,
        ]);

        return (int) db()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(int $materialId, array $data): bool
    {
        $existing = self::getById($materialId);
        if (!$existing) {
            return false;
        }

        $classId = (int) $existing['class_id'];
        $type = normalizeMaterialType((string) ($data['type'] ?? $existing['type']));
        $sectionId = array_key_exists('section_id', $data)
            ? CourseSectionRepository::resolveSectionId($data['section_id'] ? (int) $data['section_id'] : null, $classId)
            : $existing['section_id'];

        $stmt = db()->prepare('UPDATE materials SET
            section_id = ?, type = ?, title = ?, content = ?, body = ?,
            file_path = ?, original_name = ?, mime_type = ?, file_size = ?,
            file_access_mode = ?, external_link = ?
            WHERE id = ?');
        $stmt->execute([
            $sectionId,
            $type,
            trim((string) ($data['title'] ?? $existing['title'])),
            array_key_exists('content', $data) ? $data['content'] : $existing['content'],
            array_key_exists('body', $data) ? $data['body'] : $existing['body'],
            array_key_exists('file_path', $data) ? $data['file_path'] : $existing['file_path'],
            array_key_exists('original_name', $data) ? $data['original_name'] : $existing['original_name'],
            array_key_exists('mime_type', $data) ? $data['mime_type'] : $existing['mime_type'],
            array_key_exists('file_size', $data) ? (int) $data['file_size'] : (int) $existing['file_size'],
            ($data['file_access_mode'] ?? $existing['file_access_mode']) === 'view_only' ? 'view_only' : 'downloadable',
            array_key_exists('external_link', $data) ? $data['external_link'] : $existing['external_link'],
            $materialId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function delete(int $materialId): bool
    {
        $existing = self::getById($materialId);
        if (!$existing) {
            return false;
        }

        if (!empty($existing['file_path'])) {
            deleteUpload($existing['file_path']);
        }

        db()->prepare('DELETE FROM materials WHERE id = ?')->execute([$materialId]);
        return true;
    }

    public static function findByFilePath(string $filePath): ?array
    {
        $stmt = db()->prepare('SELECT m.*, c.school_id FROM materials m
            INNER JOIN classes c ON c.id = m.class_id
            WHERE m.file_path = ?');
        $stmt->execute([ltrim($filePath, '/')]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Normalize legacy rows and infer type/content from older columns.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizeRow(array $row): array
    {
        $type = normalizeMaterialType((string) ($row['type'] ?? 'file'));

        if ($type === 'file') {
            if (!empty($row['external_link']) && empty($row['file_path'])) {
                $type = 'link';
            } elseif (!empty($row['content']) && empty($row['file_path']) && empty($row['external_link'])) {
                $type = 'doc';
            }
        }

        $row['type'] = $type;

        if ($type === 'link') {
            $row['content'] = trim((string) ($row['content'] ?? $row['external_link'] ?? ''));
        }

        if ($type === 'doc' && empty($row['content']) && !empty($row['body'])) {
            $row['content'] = $row['body'];
        }

        if (empty($row['file_access_mode'])) {
            $row['file_access_mode'] = 'downloadable';
        }

        return $row;
    }
}
