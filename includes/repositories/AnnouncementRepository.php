<?php

class AnnouncementRepository
{
    public static function forSchool(int $schoolId, ?string $status = null): array
    {
        $sql = 'SELECT a.*, u.first_name AS author_first, u.last_name AS author_last,
            (SELECT COUNT(*) FROM user_notifications un WHERE un.announcement_id = a.id) AS recipient_count
            FROM announcements a
            INNER JOIN users u ON u.id = a.created_by
            WHERE a.school_id = ?';
        $params = [$schoolId];
        if ($status && in_array($status, ['draft', 'published', 'archived'], true)) {
            $sql .= ' AND a.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY COALESCE(a.published_at, a.created_at) DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function get(int $id, int $schoolId): ?array
    {
        $stmt = db()->prepare('SELECT a.*, u.first_name AS author_first, u.last_name AS author_last
            FROM announcements a
            INNER JOIN users u ON u.id = a.created_by
            WHERE a.id = ? AND a.school_id = ?');
        $stmt->execute([$id, $schoolId]);
        return $stmt->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> */
    public static function targetsFor(int $announcementId): array
    {
        $stmt = db()->prepare('SELECT * FROM announcement_targets WHERE announcement_id = ? ORDER BY id');
        $stmt->execute([$announcementId]);
        return $stmt->fetchAll();
    }

    public static function create(
        int $schoolId,
        int $createdBy,
        string $title,
        string $body,
        string $priority,
        ?string $linkUrl,
        ?string $linkLabel,
        ?string $expiresAt,
        array $targets,
        bool $publishNow
    ): int {
        if (!in_array($priority, ANNOUNCEMENT_PRIORITIES, true)) {
            $priority = 'normal';
        }

        $parsedTargets = parseAnnouncementTargets($targets);
        if ($parsedTargets === []) {
            throw new InvalidArgumentException('Select at least one audience.');
        }

        db()->beginTransaction();
        try {
            $stmt = db()->prepare('INSERT INTO announcements (school_id, created_by, title, body, priority, link_url, link_label, expires_at, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $schoolId,
                $createdBy,
                $title,
                $body,
                $priority,
                $linkUrl ?: null,
                $linkLabel ?: null,
                $expiresAt ?: null,
                $publishNow ? 'published' : 'draft',
            ]);
            $id = (int) db()->lastInsertId();
            self::saveTargets($id, $parsedTargets);
            if ($publishNow) {
                db()->prepare('UPDATE announcements SET published_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
                self::fanOutRecipients($id, $schoolId, $parsedTargets);
            }
            db()->commit();
            return $id;
        } catch (Throwable $e) {
            db()->rollBack();
            throw $e;
        }
    }

    public static function update(
        int $id,
        int $schoolId,
        string $title,
        string $body,
        string $priority,
        ?string $linkUrl,
        ?string $linkLabel,
        ?string $expiresAt,
        array $targets
    ): void {
        $row = self::get($id, $schoolId);
        if (!$row) {
            throw new RuntimeException('Announcement not found.');
        }
        if (($row['status'] ?? '') === 'published') {
            throw new RuntimeException('Published announcements cannot be edited. Archive it and create a new one.');
        }

        if (!in_array($priority, ANNOUNCEMENT_PRIORITIES, true)) {
            $priority = 'normal';
        }

        $parsedTargets = parseAnnouncementTargets($targets);
        if ($parsedTargets === []) {
            throw new InvalidArgumentException('Select at least one audience.');
        }

        db()->prepare('UPDATE announcements SET title = ?, body = ?, priority = ?, link_url = ?, link_label = ?, expires_at = ?, updated_at = UTC_TIMESTAMP()
            WHERE id = ? AND school_id = ?')
            ->execute([$title, $body, $priority, $linkUrl ?: null, $linkLabel ?: null, $expiresAt ?: null, $id, $schoolId]);

        db()->prepare('DELETE FROM announcement_targets WHERE announcement_id = ?')->execute([$id]);
        self::saveTargets($id, $parsedTargets);
    }

    public static function publish(int $id, int $schoolId): int
    {
        $row = self::get($id, $schoolId);
        if (!$row) {
            throw new RuntimeException('Announcement not found.');
        }
        if (($row['status'] ?? '') === 'published') {
            $c = db()->prepare('SELECT COUNT(*) FROM user_notifications WHERE announcement_id = ?');
            $c->execute([$id]);
            return (int) $c->fetchColumn();
        }

        $targets = self::targetsFor($id);
        db()->beginTransaction();
        try {
            db()->prepare("UPDATE announcements SET status = 'published', published_at = UTC_TIMESTAMP() WHERE id = ?")
                ->execute([$id]);
            $count = self::fanOutRecipients($id, $schoolId, $targets);
            db()->commit();
            return $count;
        } catch (Throwable $e) {
            db()->rollBack();
            throw $e;
        }
    }

    public static function archive(int $id, int $schoolId): void
    {
        db()->prepare("UPDATE announcements SET status = 'archived' WHERE id = ? AND school_id = ?")
            ->execute([$id, $schoolId]);
    }

    public static function delete(int $id, int $schoolId): void
    {
        $row = self::get($id, $schoolId);
        if (!$row) {
            throw new RuntimeException('Announcement not found.');
        }
        if (($row['status'] ?? '') === 'published') {
            throw new RuntimeException('Published announcements cannot be deleted. Archive instead.');
        }
        db()->prepare('DELETE FROM announcements WHERE id = ? AND school_id = ?')->execute([$id, $schoolId]);
    }

    public static function estimateRecipients(int $schoolId, array $targets): int
    {
        return count(self::resolveRecipientIds($schoolId, parseAnnouncementTargets($targets)));
    }

    /** @return list<int> */
    public static function resolveRecipientIds(int $schoolId, array $targets): array
    {
        $ids = [];
        foreach ($targets as $target) {
            $type = (string) ($target['target_type'] ?? '');
            $targetId = isset($target['target_id']) ? (int) $target['target_id'] : null;
            $ids = array_merge($ids, self::resolveTarget($schoolId, $type, $targetId));
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        sort($ids);
        return $ids;
    }

    public static function notificationsForUser(int $userId, int $limit = 20, bool $unreadOnly = false): array
    {
        $sql = 'SELECT un.id AS notification_id, un.read_at, un.created_at,
            a.id AS announcement_id, a.title, a.body, a.priority, a.link_url, a.link_label, a.published_at
            FROM user_notifications un
            INNER JOIN announcements a ON a.id = un.announcement_id
            WHERE un.user_id = ?
            AND a.status = \'published\'
            AND (a.expires_at IS NULL OR a.expires_at > UTC_TIMESTAMP())';
        if ($unreadOnly) {
            $sql .= ' AND un.read_at IS NULL';
        }
        $sql .= ' ORDER BY un.created_at DESC LIMIT ?';
        $stmt = db()->prepare($sql);
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getNotificationForUser(int $notificationId, int $userId): ?array
    {
        $stmt = db()->prepare('SELECT un.id AS notification_id, un.read_at, un.created_at,
            a.id AS announcement_id, a.title, a.body, a.priority, a.link_url, a.link_label, a.published_at, a.school_id
            FROM user_notifications un
            INNER JOIN announcements a ON a.id = un.announcement_id
            WHERE un.id = ? AND un.user_id = ? AND a.status = \'published\'');
        $stmt->execute([$notificationId, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function unreadCount(int $userId): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM user_notifications un
            INNER JOIN announcements a ON a.id = un.announcement_id
            WHERE un.user_id = ? AND un.read_at IS NULL
            AND a.status = \'published\'
            AND (a.expires_at IS NULL OR a.expires_at > UTC_TIMESTAMP())');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function markRead(int $notificationId, int $userId): void
    {
        db()->prepare('UPDATE user_notifications SET read_at = UTC_TIMESTAMP()
            WHERE id = ? AND user_id = ? AND read_at IS NULL')
            ->execute([$notificationId, $userId]);
    }

    public static function markAllRead(int $userId): void
    {
        db()->prepare('UPDATE user_notifications un
            INNER JOIN announcements a ON a.id = un.announcement_id
            SET un.read_at = UTC_TIMESTAMP()
            WHERE un.user_id = ? AND un.read_at IS NULL AND a.status = \'published\'')
            ->execute([$userId]);
    }

    public static function searchSchoolUsers(int $schoolId, string $query, int $limit = 20): array
    {
        $query = trim($query);
        $sql = "SELECT id, first_name, last_name, email, role FROM users
            WHERE school_id = ? AND status = 'active'
            AND role IN ('teacher', 'student', 'school_admin')";
        $params = [$schoolId];
        if ($query !== '') {
            $sql .= ' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)';
            $like = '%' . $query . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= ' ORDER BY last_name, first_name LIMIT ?';
        $params[] = $limit;
        $stmt = db()->prepare($sql);
        foreach ($params as $i => $val) {
            $stmt->bindValue($i + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @param list<array{target_type: string, target_id: int|null}> $targets */
    private static function saveTargets(int $announcementId, array $targets): void
    {
        $stmt = db()->prepare('INSERT INTO announcement_targets (announcement_id, target_type, target_id) VALUES (?, ?, ?)');
        foreach ($targets as $target) {
            $stmt->execute([$announcementId, $target['target_type'], $target['target_id']]);
        }
    }

    /** @param list<array{target_type: string, target_id: int|null}> $targets */
    private static function fanOutRecipients(int $announcementId, int $schoolId, array $targets): int
    {
        $userIds = self::resolveRecipientIds($schoolId, $targets);
        if ($userIds === []) {
            throw new RuntimeException('No recipients match the selected audience.');
        }
        $stmt = db()->prepare('INSERT IGNORE INTO user_notifications (user_id, announcement_id) VALUES (?, ?)');
        foreach ($userIds as $userId) {
            $stmt->execute([$userId, $announcementId]);
        }
        return count($userIds);
    }

    /** @return list<int> */
    private static function resolveTarget(int $schoolId, string $type, ?int $targetId): array
    {
        return match ($type) {
            'all_teachers' => self::userIdsByRole($schoolId, 'teacher'),
            'all_students' => self::userIdsByRole($schoolId, 'student'),
            'all_users' => self::userIdsByRoles($schoolId, ['teacher', 'student', 'school_admin']),
            'school_admins' => self::userIdsByRole($schoolId, 'school_admin'),
            'user' => self::validUserId($schoolId, $targetId) ? [$targetId] : [],
            'class_group_students' => self::studentsInClassGroup($schoolId, $targetId),
            'class_group_teachers' => self::teachersInClassGroup($schoolId, $targetId),
            'class_group_all' => array_merge(
                self::studentsInClassGroup($schoolId, $targetId),
                self::teachersInClassGroup($schoolId, $targetId)
            ),
            'subject_students' => self::studentsInSubject($schoolId, $targetId),
            'subject_teachers' => self::teachersInSubject($schoolId, $targetId),
            'subject_all' => array_merge(
                self::studentsInSubject($schoolId, $targetId),
                self::teachersInSubject($schoolId, $targetId)
            ),
            'program' => self::studentsInProgram($schoolId, $targetId),
            'program_level' => self::studentsInProgramLevel($schoolId, $targetId),
            'class_students' => self::studentsInClass($schoolId, $targetId),
            'class_teachers' => self::teachersInClass($schoolId, $targetId),
            'class_all' => array_merge(
                self::studentsInClass($schoolId, $targetId),
                self::teachersInClass($schoolId, $targetId)
            ),
            default => [],
        };
    }

    /** @return list<int> */
    private static function userIdsByRole(int $schoolId, string $role): array
    {
        $stmt = db()->prepare("SELECT id FROM users WHERE school_id = ? AND role = ? AND status = 'active'");
        $stmt->execute([$schoolId, $role]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param list<string> $roles @return list<int> */
    private static function userIdsByRoles(int $schoolId, array $roles): array
    {
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $stmt = db()->prepare("SELECT id FROM users WHERE school_id = ? AND role IN ($placeholders) AND status = 'active'");
        $stmt->execute(array_merge([$schoolId], $roles));
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function validUserId(int $schoolId, ?int $userId): bool
    {
        if (!$userId) {
            return false;
        }
        $stmt = db()->prepare("SELECT id FROM users WHERE id = ? AND school_id = ? AND status = 'active'
            AND role IN ('teacher', 'student', 'school_admin')");
        $stmt->execute([$userId, $schoolId]);
        return (bool) $stmt->fetchColumn();
    }

    /** @return list<int> */
    private static function studentsInClassGroup(int $schoolId, ?int $groupId): array
    {
        if (!$groupId || !self::validClassGroup($schoolId, $groupId)) {
            return [];
        }
        $stmt = db()->prepare('SELECT DISTINCT cgs.student_id FROM class_group_students cgs
            INNER JOIN class_groups g ON g.id = cgs.class_group_id
            INNER JOIN users u ON u.id = cgs.student_id
            WHERE g.id = ? AND g.school_id = ? AND u.status = \'active\'');
        $stmt->execute([$groupId, $schoolId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<int> */
    private static function teachersInClassGroup(int $schoolId, ?int $groupId): array
    {
        if (!$groupId || !self::validClassGroup($schoolId, $groupId)) {
            return [];
        }
        $stmt = db()->prepare('SELECT DISTINCT ct.teacher_id FROM class_teachers ct
            INNER JOIN classes c ON c.id = ct.class_id
            INNER JOIN users u ON u.id = ct.teacher_id
            WHERE c.class_group_id = ? AND c.school_id = ? AND u.status = \'active\'');
        $stmt->execute([$groupId, $schoolId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<int> */
    private static function studentsInSubject(int $schoolId, ?int $subjectId): array
    {
        if (!$subjectId || !SubjectRepository::get($subjectId, $schoolId)) {
            return [];
        }
        $stmt = db()->prepare('SELECT DISTINCT cgs.student_id FROM class_group_students cgs
            INNER JOIN classes c ON c.class_group_id = cgs.class_group_id
            INNER JOIN users u ON u.id = cgs.student_id
            WHERE c.school_id = ? AND c.subject_id = ? AND u.status = \'active\'');
        $stmt->execute([$schoolId, $subjectId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<int> */
    private static function teachersInSubject(int $schoolId, ?int $subjectId): array
    {
        if (!$subjectId || !SubjectRepository::get($subjectId, $schoolId)) {
            return [];
        }
        $stmt = db()->prepare("SELECT DISTINCT u.id FROM users u
            WHERE u.school_id = ? AND u.role = 'teacher' AND u.status = 'active'
            AND (
                EXISTS (SELECT 1 FROM teacher_subjects ts WHERE ts.teacher_id = u.id AND ts.subject_id = ?)
                OR EXISTS (
                    SELECT 1 FROM class_teachers ct
                    INNER JOIN classes c ON c.id = ct.class_id
                    WHERE ct.teacher_id = u.id AND c.subject_id = ?
                )
            )");
        $stmt->execute([$schoolId, $subjectId, $subjectId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<int> */
    private static function studentsInProgram(int $schoolId, ?int $programId): array
    {
        if (!$programId || !ProgramRepository::get($programId, $schoolId)) {
            return [];
        }
        $stmt = db()->prepare("SELECT DISTINCT spe.student_id FROM student_program_enrollments spe
            INNER JOIN users u ON u.id = spe.student_id
            WHERE spe.program_id = ? AND spe.status = 'active' AND u.school_id = ? AND u.status = 'active'");
        $stmt->execute([$programId, $schoolId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<int> */
    private static function studentsInProgramLevel(int $schoolId, ?int $levelId): array
    {
        if (!$levelId) {
            return [];
        }
        $level = db()->prepare('SELECT pl.id FROM program_levels pl
            INNER JOIN programs p ON p.id = pl.program_id
            WHERE pl.id = ? AND p.school_id = ?');
        $level->execute([$levelId, $schoolId]);
        if (!$level->fetch()) {
            return [];
        }
        $stmt = db()->prepare('SELECT DISTINCT cgs.student_id FROM class_group_students cgs
            INNER JOIN class_groups g ON g.id = cgs.class_group_id
            INNER JOIN users u ON u.id = cgs.student_id
            WHERE g.school_id = ? AND g.program_level_id = ? AND u.status = \'active\'');
        $stmt->execute([$schoolId, $levelId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<int> */
    private static function studentsInClass(int $schoolId, ?int $classId): array
    {
        if (!$classId || !self::validClass($schoolId, $classId)) {
            return [];
        }
        $stmt = db()->prepare('SELECT DISTINCT cgs.student_id FROM class_group_students cgs
            INNER JOIN classes c ON c.class_group_id = cgs.class_group_id
            INNER JOIN users u ON u.id = cgs.student_id
            WHERE c.id = ? AND c.school_id = ? AND u.status = \'active\'');
        $stmt->execute([$classId, $schoolId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<int> */
    private static function teachersInClass(int $schoolId, ?int $classId): array
    {
        if (!$classId || !self::validClass($schoolId, $classId)) {
            return [];
        }
        $stmt = db()->prepare('SELECT DISTINCT ct.teacher_id FROM class_teachers ct
            INNER JOIN classes c ON c.id = ct.class_id
            INNER JOIN users u ON u.id = ct.teacher_id
            WHERE c.id = ? AND c.school_id = ? AND u.status = \'active\'');
        $stmt->execute([$classId, $schoolId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function validClassGroup(int $schoolId, int $groupId): bool
    {
        return (bool) ClassGroupRepository::get($groupId, $schoolId);
    }

    private static function validClass(int $schoolId, int $classId): bool
    {
        $stmt = db()->prepare('SELECT id FROM classes WHERE id = ? AND school_id = ?');
        $stmt->execute([$classId, $schoolId]);
        return (bool) $stmt->fetchColumn();
    }
}
