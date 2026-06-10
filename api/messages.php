<?php

require_once __DIR__ . '/../includes/bootstrap.php';

requireLogin();
requireSchoolActive();

$user = currentUser();
if (!canUseMessaging($user)) {
    messagingJsonResponse(['ok' => false, 'error' => 'Messaging is not available.'], 403);
}

$userId = (int) $user['id'];
$schoolId = (int) $user['school_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    match ($action) {
        'inbox' => handleInbox($userId, $schoolId),
        'thread' => handleThread($userId, $schoolId),
        'send' => handleSend($userId, $schoolId),
        'start' => handleStart($userId, $schoolId),
        'read' => handleRead($userId, $schoolId),
        'edit' => handleEdit($userId, $schoolId),
        'unsend' => handleUnsend($userId, $schoolId),
        'edit_history' => handleEditHistory($userId, $schoolId),
        'delete_for_me' => handleDeleteForMe($userId, $schoolId),
        'search_users' => handleSearchUsers($userId, $schoolId),
        'unread_count' => handleUnreadCount($userId),
        default => messagingJsonResponse(['ok' => false, 'error' => 'Unknown action.'], 400),
    };
} catch (InvalidArgumentException $e) {
    messagingJsonResponse(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (RuntimeException $e) {
    messagingJsonResponse(['ok' => false, 'error' => $e->getMessage()], 403);
}

function handleInbox(int $userId, int $schoolId): never
{
    $rows = MessageRepository::inboxForUser($userId, $schoolId);
    messagingJsonResponse([
        'ok'    => true,
        'inbox' => messagingFormatInbox($rows, $userId),
        'me'    => messagingParticipantSummary(currentUser()),
    ]);
}

function handleThread(int $userId, int $schoolId): never
{
    $conversationId = (int) ($_GET['conversation_id'] ?? 0);
    $sinceId = (int) ($_GET['since_id'] ?? 0);
    $syncSince = trim((string) ($_GET['sync_since'] ?? ''));

    if ($conversationId <= 0 || !verifyConversationAccess($conversationId, $userId, $schoolId)) {
        messagingJsonResponse(['ok' => false, 'error' => 'Conversation not found.'], 404);
    }

    $conversation = MessageRepository::conversationForUser($conversationId, $userId, $schoolId);
    $messages = MessageRepository::messagesForConversation($conversationId, $userId, $sinceId);
    $updates = $sinceId > 0 && $syncSince !== ''
        ? MessageRepository::updatedMessagesSince($conversationId, $userId, $syncSince)
        : [];

    foreach ($messages as &$msg) {
        $msg['sender'] = messagingParticipantSummary($msg['sender']);
    }
    unset($msg);

    foreach ($updates as &$msg) {
        $msg['sender'] = messagingParticipantSummary($msg['sender']);
    }
    unset($msg);

    messagingJsonResponse([
        'ok'           => true,
        'conversation' => [
            'conversation_id' => $conversationId,
            'other_user'      => messagingParticipantSummary($conversation['other_user']),
        ],
        'messages'     => $messages,
        'updates'      => $updates,
    ]);
}

function handleSend(int $userId, int $schoolId): never
{
    verifyCsrfHeader();

    $conversationId = (int) ($_POST['conversation_id'] ?? 0);
    $body = (string) ($_POST['body'] ?? '');

    if ($conversationId <= 0 || !verifyConversationAccess($conversationId, $userId, $schoolId)) {
        messagingJsonResponse(['ok' => false, 'error' => 'Conversation not found.'], 404);
    }

    $message = MessageRepository::sendMessage(
        $conversationId,
        $userId,
        $body,
        (int) ($_POST['reply_to_message_id'] ?? 0)
    );
    if (!empty($message['sender'])) {
        $message['sender'] = messagingParticipantSummary($message['sender']);
    }

    messagingJsonResponse(['ok' => true, 'message' => $message]);
}

function handleStart(int $userId, int $schoolId): never
{
    verifyCsrfHeader();

    $recipientId = (int) ($_POST['recipient_id'] ?? 0);
    if ($recipientId <= 0 || $recipientId === $userId) {
        messagingJsonResponse(['ok' => false, 'error' => 'Invalid recipient.'], 422);
    }

    $recipient = MessageRepository::validateSchoolRecipient($schoolId, $recipientId);
    if (!$recipient) {
        messagingJsonResponse(['ok' => false, 'error' => 'Recipient not found.'], 404);
    }

    $conversationId = MessageRepository::findOrCreateDirectConversation($schoolId, $userId, $recipientId);
    $conversation = MessageRepository::conversationForUser($conversationId, $userId, $schoolId);

    messagingJsonResponse([
        'ok'           => true,
        'conversation' => [
            'conversation_id' => $conversationId,
            'other_user'      => messagingParticipantSummary($conversation['other_user']),
        ],
    ]);
}

function handleRead(int $userId, int $schoolId): never
{
    verifyCsrfHeader();

    $conversationId = (int) ($_POST['conversation_id'] ?? 0);
    $messageId = (int) ($_POST['message_id'] ?? 0);

    if ($conversationId <= 0 || $messageId <= 0 || !verifyConversationAccess($conversationId, $userId, $schoolId)) {
        messagingJsonResponse(['ok' => false, 'error' => 'Invalid request.'], 422);
    }

    MessageRepository::markRead($conversationId, $userId, $messageId);

    messagingJsonResponse([
        'ok'           => true,
        'unread_count' => MessageRepository::unreadTotal($userId),
    ]);
}

function handleEdit(int $userId, int $schoolId): never
{
    verifyCsrfHeader();

    $messageId = (int) ($_POST['message_id'] ?? 0);
    $body = (string) ($_POST['body'] ?? '');

    if ($messageId <= 0) {
        messagingJsonResponse(['ok' => false, 'error' => 'Invalid message.'], 422);
    }

    $message = MessageRepository::editMessage($messageId, $userId, $body);
    $message['sender'] = messagingParticipantSummary($message['sender']);

    messagingJsonResponse(['ok' => true, 'message' => $message]);
}

function handleUnsend(int $userId, int $schoolId): never
{
    verifyCsrfHeader();

    $messageId = (int) ($_POST['message_id'] ?? 0);

    if ($messageId <= 0) {
        messagingJsonResponse(['ok' => false, 'error' => 'Invalid message.'], 422);
    }

    $message = MessageRepository::unsendMessage($messageId, $userId);
    $message['sender'] = messagingParticipantSummary($message['sender']);

    messagingJsonResponse(['ok' => true, 'message' => $message]);
}

function handleEditHistory(int $userId, int $schoolId): never
{
    $messageId = (int) ($_GET['message_id'] ?? 0);

    if ($messageId <= 0) {
        messagingJsonResponse(['ok' => false, 'error' => 'Invalid message.'], 422);
    }

    $history = MessageRepository::editHistoryForMessage($messageId, $userId);

    messagingJsonResponse(['ok' => true, 'history' => $history]);
}

function handleDeleteForMe(int $userId, int $schoolId): never
{
    verifyCsrfHeader();

    $messageId = (int) ($_POST['message_id'] ?? 0);

    if ($messageId <= 0) {
        messagingJsonResponse(['ok' => false, 'error' => 'Invalid message.'], 422);
    }

    MessageRepository::deleteForMe($messageId, $userId);

    messagingJsonResponse(['ok' => true]);
}

function handleSearchUsers(int $userId, int $schoolId): never
{
    $query = (string) ($_GET['q'] ?? '');
    $users = MessageRepository::searchSchoolUsers($schoolId, $query, $userId);
    $results = array_map('messagingParticipantSummary', $users);

    messagingJsonResponse(['ok' => true, 'users' => $results]);
}

function handleUnreadCount(int $userId): never
{
    messagingJsonResponse([
        'ok'           => true,
        'unread_count' => MessageRepository::unreadTotal($userId),
    ]);
}
