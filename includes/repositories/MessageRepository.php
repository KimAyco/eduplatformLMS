<?php

class MessageRepository
{
    private static function hiddenForUserSql(string $messageAlias = 'm'): string
    {
        return 'NOT EXISTS (
            SELECT 1 FROM message_user_hidden muh
            WHERE muh.message_id = ' . $messageAlias . '.id AND muh.user_id = ?
        )';
    }

    private static function messageSelectSql(): string
    {
        return "
            SELECT m.id, m.conversation_id, m.sender_id, m.reply_to_message_id, m.body, m.created_at, m.edited_at, m.deleted_at,
                   u.first_name, u.last_name, u.role, u.profile_image,
                   rm.id AS reply_id, rm.body AS reply_body, rm.deleted_at AS reply_deleted_at, rm.sender_id AS reply_sender_id,
                   ru.first_name AS reply_first_name, ru.last_name AS reply_last_name
            FROM messages m
            INNER JOIN users u ON u.id = m.sender_id
            LEFT JOIN messages rm ON rm.id = m.reply_to_message_id
            LEFT JOIN users ru ON ru.id = rm.sender_id
        ";
    }

    public static function inboxForUser(int $userId, int $schoolId): array
    {
        $stmt = db()->prepare("
            SELECT
                c.id AS conversation_id,
                c.updated_at,
                cp.last_read_message_id,
                other.id AS other_user_id,
                other.first_name AS other_first_name,
                other.last_name AS other_last_name,
                other.email AS other_email,
                other.role AS other_role,
                other.profile_image AS other_profile_image,
                lm.id AS last_message_id,
                lm.body AS last_message_body,
                lm.sender_id AS last_message_sender_id,
                lm.created_at AS last_message_at,
                lm.deleted_at AS last_message_deleted_at,
                (
                    SELECT COUNT(*)
                    FROM messages um
                    WHERE um.conversation_id = c.id
                      AND um.sender_id != ?
                      AND um.deleted_at IS NULL
                      AND NOT EXISTS (
                          SELECT 1 FROM message_user_hidden muh
                          WHERE muh.message_id = um.id AND muh.user_id = ?
                      )
                      AND um.id > COALESCE(cp.last_read_message_id, 0)
                ) AS unread_count
            FROM conversations c
            INNER JOIN conversation_participants cp
                ON cp.conversation_id = c.id AND cp.user_id = ?
            INNER JOIN conversation_participants cp_other
                ON cp_other.conversation_id = c.id AND cp_other.user_id != ?
            INNER JOIN users other
                ON other.id = cp_other.user_id
                AND other.school_id = c.school_id
                AND other.status = 'active'
            LEFT JOIN messages lm ON lm.id = (
                SELECT m2.id FROM messages m2
                WHERE m2.conversation_id = c.id
                  AND NOT EXISTS (
                      SELECT 1 FROM message_user_hidden muh
                      WHERE muh.message_id = m2.id AND muh.user_id = ?
                  )
                ORDER BY m2.id DESC
                LIMIT 1
            )
            WHERE c.school_id = ?
            ORDER BY c.updated_at DESC
        ");
        $stmt->execute([$userId, $userId, $userId, $userId, $userId, $schoolId]);

        return array_map(static function (array $row) use ($userId): array {
            return [
                'conversation_id' => (int) $row['conversation_id'],
                'updated_at'      => $row['updated_at'],
                'unread_count'    => (int) $row['unread_count'],
                'other_user'      => [
                    'id'            => (int) $row['other_user_id'],
                    'first_name'    => $row['other_first_name'],
                    'last_name'     => $row['other_last_name'],
                    'email'         => $row['other_email'],
                    'role'          => $row['other_role'],
                    'profile_image' => $row['other_profile_image'],
                ],
                'last_message' => $row['last_message_id'] ? self::formatLastMessagePreview(
                    (int) $row['last_message_id'],
                    $row['last_message_body'],
                    (int) $row['last_message_sender_id'],
                    $row['last_message_at'],
                    $row['last_message_deleted_at'] ?? null,
                    $userId
                ) : null,
            ];
        }, $stmt->fetchAll());
    }

    public static function messagesForConversation(int $conversationId, int $userId, int $sinceId = 0): array
    {
        if (!self::isParticipant($conversationId, $userId)) {
            return [];
        }

        $sql = self::messageSelectSql() . "
            WHERE m.conversation_id = ?
              AND " . self::hiddenForUserSql('m') . "
        ";
        $params = [$conversationId, $userId];

        if ($sinceId > 0) {
            $sql .= ' AND m.id > ?';
            $params[] = $sinceId;
        }

        $sql .= ' ORDER BY m.id ASC LIMIT 200';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return array_map(static function (array $row) use ($userId): array {
            return self::formatMessageRow($row, $userId);
        }, $stmt->fetchAll());
    }

    public static function updatedMessagesSince(int $conversationId, int $userId, string $syncSince): array
    {
        if (!self::isParticipant($conversationId, $userId) || $syncSince === '') {
            return [];
        }

        $stmt = db()->prepare(self::messageSelectSql() . "
            WHERE m.conversation_id = ?
              AND (m.edited_at >= ? OR m.deleted_at >= ?)
            ORDER BY m.id ASC
            LIMIT 100
        ");
        $stmt->execute([$conversationId, $syncSince, $syncSince]);

        return array_map(static function (array $row) use ($userId): array {
            return self::formatMessageRow($row, $userId);
        }, $stmt->fetchAll());
    }

    public static function editMessage(int $messageId, int $userId, string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            throw new InvalidArgumentException('Message cannot be empty.');
        }
        if (mb_strlen($body) > 2000) {
            throw new InvalidArgumentException('Message is too long (max 2000 characters).');
        }

        $message = self::messageForSender($messageId, $userId);
        if ($message['deleted_at']) {
            throw new InvalidArgumentException('Cannot edit an unsent message.');
        }

        $current = db()->prepare('SELECT body FROM messages WHERE id = ? AND deleted_at IS NULL');
        $current->execute([$messageId]);
        $previousBody = (string) ($current->fetchColumn() ?: '');
        if ($previousBody === $body) {
            return self::getMessageById($messageId, $userId);
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO message_edits (message_id, body) VALUES (?, ?)')
                ->execute([$messageId, $previousBody]);
            $pdo->prepare('
                UPDATE messages
                SET body = ?, edited_at = NOW()
                WHERE id = ? AND sender_id = ? AND deleted_at IS NULL
            ')->execute([$body, $messageId, $userId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return self::getMessageById($messageId, $userId);
    }

    public static function editHistoryForMessage(int $messageId, int $userId): array
    {
        $message = self::getMessageById($messageId, $userId);
        if (!$message['is_edited'] || $message['is_deleted']) {
            return [
                'message_id' => $messageId,
                'is_mine'    => $message['is_mine'],
                'current'    => [
                    'body' => $message['body'],
                    'at'   => $message['edited_at'] ?? $message['created_at'],
                ],
                'previous'   => [],
            ];
        }

        $stmt = db()->prepare('
            SELECT body, saved_at
            FROM message_edits
            WHERE message_id = ?
            ORDER BY saved_at DESC
        ');
        $stmt->execute([$messageId]);

        $previous = array_map(static function (array $row): array {
            return [
                'body' => (string) $row['body'],
                'at'   => $row['saved_at'],
            ];
        }, $stmt->fetchAll());

        return [
            'message_id' => $messageId,
            'is_mine'    => $message['is_mine'],
            'current'    => [
                'body' => $message['body'],
                'at'   => $message['edited_at'] ?? $message['created_at'],
            ],
            'previous'   => $previous,
        ];
    }

    public static function deleteForMe(int $messageId, int $userId): void
    {
        self::messageForParticipant($messageId, $userId);

        db()->prepare('
            INSERT INTO message_user_hidden (message_id, user_id) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE hidden_at = NOW()
        ')->execute([$messageId, $userId]);
    }

    public static function unsendMessage(int $messageId, int $userId): array
    {
        $message = self::messageForSender($messageId, $userId);
        if ($message['deleted_at']) {
            return self::getMessageById($messageId, $userId);
        }

        db()->prepare("
            UPDATE messages
            SET deleted_at = NOW(), body = ''
            WHERE id = ? AND sender_id = ? AND deleted_at IS NULL
        ")->execute([$messageId, $userId]);

        return self::getMessageById($messageId, $userId);
    }

    private static function messageForSender(int $messageId, int $userId): array
    {
        $stmt = db()->prepare('
            SELECT m.id, m.conversation_id, m.sender_id, m.deleted_at
            FROM messages m
            INNER JOIN conversation_participants cp
                ON cp.conversation_id = m.conversation_id AND cp.user_id = ?
            WHERE m.id = ?
        ');
        $stmt->execute([$userId, $messageId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Message not found.');
        }
        if ((int) $row['sender_id'] !== $userId) {
            throw new RuntimeException('You can only change your own messages.');
        }

        return $row;
    }

    private static function messageForParticipant(int $messageId, int $userId): array
    {
        $stmt = db()->prepare('
            SELECT m.id, m.conversation_id, m.sender_id, m.deleted_at
            FROM messages m
            INNER JOIN conversation_participants cp
                ON cp.conversation_id = m.conversation_id AND cp.user_id = ?
            WHERE m.id = ?
        ');
        $stmt->execute([$userId, $messageId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Message not found.');
        }

        return $row;
    }

    private static function getMessageById(int $messageId, int $userId): array
    {
        $stmt = db()->prepare(self::messageSelectSql() . "
            INNER JOIN conversation_participants cp
                ON cp.conversation_id = m.conversation_id AND cp.user_id = ?
            WHERE m.id = ?
        ");
        $stmt->execute([$userId, $messageId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Message not found.');
        }

        return self::formatMessageRow($row, $userId);
    }

    private static function formatMessageRow(array $row, int $userId): array
    {
        $isDeleted = !empty($row['deleted_at']);

        return [
            'id'         => (int) $row['id'],
            'sender_id'  => (int) $row['sender_id'],
            'body'       => $isDeleted ? '' : (string) $row['body'],
            'created_at' => $row['created_at'],
            'edited_at'  => $row['edited_at'] ?? null,
            'deleted_at' => $row['deleted_at'] ?? null,
            'is_mine'    => (int) $row['sender_id'] === $userId,
            'is_deleted' => $isDeleted,
            'is_edited'  => !$isDeleted && !empty($row['edited_at']),
            'reply_to'   => self::formatReplyPreview($row, $userId),
            'sender'     => [
                'id'            => (int) $row['sender_id'],
                'first_name'    => $row['first_name'],
                'last_name'     => $row['last_name'],
                'role'          => $row['role'],
                'profile_image' => $row['profile_image'],
            ],
        ];
    }

    private static function formatReplyPreview(array $row, int $userId): ?array
    {
        if (empty($row['reply_id'])) {
            return null;
        }

        $isDeleted = !empty($row['reply_deleted_at']);
        $senderId = (int) ($row['reply_sender_id'] ?? 0);

        return [
            'id'          => (int) $row['reply_id'],
            'body'        => $isDeleted ? '' : (string) ($row['reply_body'] ?? ''),
            'is_deleted'  => $isDeleted,
            'is_mine'     => $senderId === $userId,
            'sender_name' => trim(($row['reply_first_name'] ?? '') . ' ' . ($row['reply_last_name'] ?? '')),
        ];
    }

    private static function formatLastMessagePreview(
        int $id,
        ?string $body,
        int $senderId,
        ?string $createdAt,
        ?string $deletedAt,
        int $userId
    ): array {
        $isDeleted = !empty($deletedAt);

        return [
            'id'         => $id,
            'body'       => $isDeleted ? '' : (string) $body,
            'sender_id'  => $senderId,
            'created_at' => $createdAt,
            'is_mine'    => $senderId === $userId,
            'is_deleted' => $isDeleted,
        ];
    }

    public static function findOrCreateDirectConversation(int $schoolId, int $userIdA, int $userIdB): int
    {
        if ($userIdA === $userIdB) {
            throw new InvalidArgumentException('Cannot message yourself.');
        }

        $stmt = db()->prepare("
            SELECT c.id
            FROM conversations c
            INNER JOIN conversation_participants cp1
                ON cp1.conversation_id = c.id AND cp1.user_id = ?
            INNER JOIN conversation_participants cp2
                ON cp2.conversation_id = c.id AND cp2.user_id = ?
            WHERE c.school_id = ?
              AND (SELECT COUNT(*) FROM conversation_participants WHERE conversation_id = c.id) = 2
            LIMIT 1
        ");
        $stmt->execute([$userIdA, $userIdB, $schoolId]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            return (int) $existing;
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO conversations (school_id) VALUES (?)')->execute([$schoolId]);
            $conversationId = (int) $pdo->lastInsertId();

            $insertParticipant = $pdo->prepare(
                'INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)'
            );
            $insertParticipant->execute([$conversationId, $userIdA]);
            $insertParticipant->execute([$conversationId, $userIdB]);

            $pdo->commit();
            return $conversationId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function sendMessage(int $conversationId, int $senderId, string $body, int $replyToMessageId = 0): array
    {
        $body = trim($body);
        if ($body === '') {
            throw new InvalidArgumentException('Message cannot be empty.');
        }
        if (mb_strlen($body) > 2000) {
            throw new InvalidArgumentException('Message is too long (max 2000 characters).');
        }

        if (!self::isParticipant($conversationId, $senderId)) {
            throw new RuntimeException('Access denied.');
        }

        $stmt = db()->prepare('
            SELECT c.school_id, u.school_id AS sender_school_id
            FROM conversations c
            INNER JOIN conversation_participants cp ON cp.conversation_id = c.id AND cp.user_id = ?
            INNER JOIN users u ON u.id = ?
            WHERE c.id = ?
        ');
        $stmt->execute([$senderId, $senderId, $conversationId]);
        $row = $stmt->fetch();
        if (!$row || (int) $row['school_id'] !== (int) $row['sender_school_id']) {
            throw new RuntimeException('Access denied.');
        }

        if ($replyToMessageId > 0) {
            self::validateReplyTarget($conversationId, $senderId, $replyToMessageId);
        } else {
            $replyToMessageId = 0;
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('
                INSERT INTO messages (conversation_id, sender_id, reply_to_message_id, body)
                VALUES (?, ?, ?, ?)
            ')->execute([
                $conversationId,
                $senderId,
                $replyToMessageId > 0 ? $replyToMessageId : null,
                $body,
            ]);

            $messageId = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE conversations SET updated_at = NOW() WHERE id = ?')
                ->execute([$conversationId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return self::getMessageById($messageId, $senderId);
    }

    private static function validateReplyTarget(int $conversationId, int $userId, int $replyToMessageId): void
    {
        $stmt = db()->prepare('
            SELECT m.id
            FROM messages m
            INNER JOIN conversation_participants cp
                ON cp.conversation_id = m.conversation_id AND cp.user_id = ?
            WHERE m.id = ? AND m.conversation_id = ?
              AND ' . self::hiddenForUserSql('m') . '
        ');
        $stmt->execute([$userId, $replyToMessageId, $conversationId, $userId]);
        if (!$stmt->fetchColumn()) {
            throw new InvalidArgumentException('Cannot reply to that message.');
        }
    }

    public static function markRead(int $conversationId, int $userId, int $messageId): void
    {
        if (!self::isParticipant($conversationId, $userId)) {
            throw new RuntimeException('Access denied.');
        }

        $stmt = db()->prepare('
            SELECT MAX(id) FROM messages WHERE conversation_id = ? AND id <= ?
        ');
        $stmt->execute([$conversationId, $messageId]);
        $maxId = (int) ($stmt->fetchColumn() ?: 0);
        if ($maxId <= 0) {
            return;
        }

        db()->prepare('
            UPDATE conversation_participants
            SET last_read_message_id = GREATEST(COALESCE(last_read_message_id, 0), ?)
            WHERE conversation_id = ? AND user_id = ?
        ')->execute([$maxId, $conversationId, $userId]);
    }

    public static function unreadTotal(int $userId): int
    {
        $stmt = db()->prepare("
            SELECT COALESCE(SUM(sub.unread), 0)
            FROM (
                SELECT (
                    SELECT COUNT(*)
                    FROM messages um
                    WHERE um.conversation_id = cp.conversation_id
                      AND um.sender_id != cp.user_id
                      AND um.deleted_at IS NULL
                      AND NOT EXISTS (
                          SELECT 1 FROM message_user_hidden muh
                          WHERE muh.message_id = um.id AND muh.user_id = cp.user_id
                      )
                      AND um.id > COALESCE(cp.last_read_message_id, 0)
                ) AS unread
                FROM conversation_participants cp
                WHERE cp.user_id = ?
            ) sub
        ");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function searchSchoolUsers(int $schoolId, string $query, int $excludeUserId, int $limit = 20): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $like = '%' . $query . '%';
        $stmt = db()->prepare("
            SELECT id, first_name, last_name, email, role, profile_image
            FROM users
            WHERE school_id = ?
              AND status = 'active'
              AND id != ?
              AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?
                   OR CONCAT(first_name, ' ', last_name) LIKE ?)
            ORDER BY last_name, first_name
            LIMIT ?
        ");
        $stmt->bindValue(1, $schoolId, PDO::PARAM_INT);
        $stmt->bindValue(2, $excludeUserId, PDO::PARAM_INT);
        $stmt->bindValue(3, $like);
        $stmt->bindValue(4, $like);
        $stmt->bindValue(5, $like);
        $stmt->bindValue(6, $like);
        $stmt->bindValue(7, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function (array $row): array {
            return [
                'id'            => (int) $row['id'],
                'first_name'    => $row['first_name'],
                'last_name'     => $row['last_name'],
                'email'         => $row['email'],
                'role'          => $row['role'],
                'profile_image' => $row['profile_image'],
            ];
        }, $stmt->fetchAll());
    }

    public static function conversationForUser(int $conversationId, int $userId, int $schoolId): ?array
    {
        $stmt = db()->prepare("
            SELECT
                c.id AS conversation_id,
                other.id AS other_user_id,
                other.first_name AS other_first_name,
                other.last_name AS other_last_name,
                other.email AS other_email,
                other.role AS other_role,
                other.profile_image AS other_profile_image
            FROM conversations c
            INNER JOIN conversation_participants cp
                ON cp.conversation_id = c.id AND cp.user_id = ?
            INNER JOIN conversation_participants cp_other
                ON cp_other.conversation_id = c.id AND cp_other.user_id != ?
            INNER JOIN users other ON other.id = cp_other.user_id
            WHERE c.id = ? AND c.school_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId, $userId, $conversationId, $schoolId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return [
            'conversation_id' => (int) $row['conversation_id'],
            'other_user'      => [
                'id'            => (int) $row['other_user_id'],
                'first_name'    => $row['other_first_name'],
                'last_name'     => $row['other_last_name'],
                'email'         => $row['other_email'],
                'role'          => $row['other_role'],
                'profile_image' => $row['other_profile_image'],
            ],
        ];
    }

    public static function isParticipant(int $conversationId, int $userId): bool
    {
        $stmt = db()->prepare('
            SELECT 1 FROM conversation_participants WHERE conversation_id = ? AND user_id = ?
        ');
        $stmt->execute([$conversationId, $userId]);
        return (bool) $stmt->fetchColumn();
    }

    public static function validateSchoolRecipient(int $schoolId, int $recipientId): ?array
    {
        $stmt = db()->prepare("
            SELECT id, first_name, last_name, email, role, profile_image, school_id, status
            FROM users WHERE id = ?
        ");
        $stmt->execute([$recipientId]);
        $user = $stmt->fetch();
        if (!$user || $user['status'] !== 'active' || (int) $user['school_id'] !== $schoolId) {
            return null;
        }
        return $user;
    }
}
