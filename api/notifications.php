<?php

require_once __DIR__ . '/../includes/bootstrap.php';

requireLogin();
requireSchoolActive();

$user = currentUser();
$userId = (int) $user['id'];
$schoolId = (int) ($user['school_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    match ($action) {
        'list' => handleNotificationList($userId),
        'unread_count' => handleNotificationUnreadCount($userId),
        'read' => handleNotificationRead($userId),
        'read_all' => handleNotificationReadAll($userId),
        'estimate' => handleNotificationEstimate($schoolId),
        'search_users' => handleNotificationSearchUsers($schoolId),
        default => notificationsJsonResponse(['ok' => false, 'error' => 'Unknown action.'], 400),
    };
} catch (InvalidArgumentException $e) {
    notificationsJsonResponse(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (RuntimeException $e) {
    notificationsJsonResponse(['ok' => false, 'error' => $e->getMessage()], 403);
}

function handleNotificationList(int $userId): never
{
    $limit = max(1, min(50, (int) ($_GET['limit'] ?? 15)));
    $rows = AnnouncementRepository::notificationsForUser($userId, $limit);
    $items = array_map('formatNotificationForClient', $rows);
    notificationsJsonResponse(['ok' => true, 'notifications' => $items]);
}

function handleNotificationUnreadCount(int $userId): never
{
    notificationsJsonResponse([
        'ok' => true,
        'unread_count' => AnnouncementRepository::unreadCount($userId),
    ]);
}

function handleNotificationRead(int $userId): never
{
    verifyCsrfHeader();
    $body = json_decode((string) file_get_contents('php://input'), true) ?? $_POST;
    $id = (int) ($body['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        throw new InvalidArgumentException('Notification ID required.');
    }
    $row = AnnouncementRepository::getNotificationForUser($id, $userId);
    if (!$row) {
        throw new RuntimeException('Notification not found.');
    }
    AnnouncementRepository::markRead($id, $userId);
    notificationsJsonResponse(['ok' => true]);
}

function handleNotificationReadAll(int $userId): never
{
    verifyCsrfHeader();
    AnnouncementRepository::markAllRead($userId);
    notificationsJsonResponse(['ok' => true]);
}

function handleNotificationEstimate(int $schoolId): never
{
    if ((currentUser()['role'] ?? '') !== 'school_admin') {
        throw new RuntimeException('School administrators only.');
    }
    $body = json_decode((string) file_get_contents('php://input'), true) ?? $_POST;
    $targets = $body['targets'] ?? [];
    if (!is_array($targets)) {
        $targets = [];
    }
    $count = AnnouncementRepository::estimateRecipients($schoolId, $targets);
    notificationsJsonResponse(['ok' => true, 'recipient_count' => $count]);
}

function handleNotificationSearchUsers(int $schoolId): never
{
    if ((currentUser()['role'] ?? '') !== 'school_admin') {
        throw new RuntimeException('School administrators only.');
    }
    $query = (string) ($_GET['q'] ?? '');
    $users = AnnouncementRepository::searchSchoolUsers($schoolId, $query);
    $results = array_map(static function (array $u): array {
        return [
            'id' => (int) $u['id'],
            'name' => trim($u['first_name'] . ' ' . $u['last_name']),
            'email' => $u['email'],
            'role' => $u['role'],
            'role_label' => ROLES[$u['role']] ?? $u['role'],
        ];
    }, $users);
    notificationsJsonResponse(['ok' => true, 'users' => $results]);
}
