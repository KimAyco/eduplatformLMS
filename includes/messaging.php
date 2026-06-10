<?php

function canUseMessaging(?array $user = null): bool
{
    $user = $user ?? currentUser();
    if (!$user) {
        return false;
    }
    if ($user['role'] === 'super_admin') {
        return false;
    }
    return !empty($user['school_id']) && ($user['school_status'] ?? '') === 'active';
}

function messagingParticipantSummary(array $user): array
{
    $role = $user['role'] ?? '';
    $photoUrl = userProfileImageUrl($user);
    return [
        'id'            => (int) $user['id'],
        'name'          => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
        'first_name'    => $user['first_name'] ?? '',
        'last_name'     => $user['last_name'] ?? '',
        'email'         => $user['email'] ?? '',
        'role'          => $role,
        'role_label'    => ROLES[$role] ?? ucfirst($role),
        'initials'      => userInitials($user),
        'profile_image' => $photoUrl,
    ];
}

function verifyConversationAccess(int $conversationId, int $userId, int $schoolId): bool
{
    $stmt = db()->prepare('
        SELECT 1
        FROM conversations c
        INNER JOIN conversation_participants cp ON cp.conversation_id = c.id AND cp.user_id = ?
        WHERE c.id = ? AND c.school_id = ?
    ');
    $stmt->execute([$userId, $conversationId, $schoolId]);
    return (bool) $stmt->fetchColumn();
}

function messagingJsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function messagingFormatInbox(array $rows, int $currentUserId): array
{
    return array_map(static function (array $row) use ($currentUserId): array {
        $other = messagingParticipantSummary($row['other_user']);
        $last = $row['last_message'] ?? null;
        if ($last) {
            $last['is_mine'] = (int) ($last['sender_id'] ?? 0) === $currentUserId;
        }
        return [
            'conversation_id' => $row['conversation_id'],
            'updated_at'      => $row['updated_at'],
            'unread_count'    => $row['unread_count'],
            'other_user'      => $other,
            'last_message'    => $last,
        ];
    }, $rows);
}
