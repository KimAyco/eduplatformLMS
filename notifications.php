<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();
requireSchoolActive();

$user = currentUser();
$userId = (int) $user['id'];
$id = (int) ($_GET['id'] ?? 0);

if ($id > 0) {
    $notification = AnnouncementRepository::getNotificationForUser($id, $userId);
    if (!$notification) {
        flash('error', 'Notification not found.');
        redirect(match ($user['role'] ?? '') {
            'school_admin' => 'school/dashboard.php',
            'teacher' => 'teacher/dashboard.php',
            'student' => 'student/dashboard.php',
            default => 'index.php',
        });
    }
    AnnouncementRepository::markRead($id, $userId);
    $notification['read_at'] = date('Y-m-d H:i:s');

    $pageTitle = $notification['title'];
    $pageHeading = $notification['title'];
    $pageSubtitle = $notification['published_at']
        ? date('M j, Y g:i A', strtotime($notification['published_at']))
        : '';
} else {
    $notifications = AnnouncementRepository::notificationsForUser($userId, 50);
    $pageTitle = 'Notifications';
    $pageHeading = 'Notifications';
    $pageSubtitle = 'School announcements and updates.';
}

$pageScripts = $id > 0 ? [] : ['assets/js/notifications-page.js'];

$role = $user['role'];
$menuItems = match ($role) {
    'school_admin' => schoolAdminMenu(),
    'teacher' => teacherMenu(),
    'student' => studentMenu(),
    default => [],
};

require __DIR__ . '/includes/layout/dashboard_header.php';
?>

<?php if ($id > 0): ?>
<article class="panel notification-detail notification-detail--<?= e($notification['priority']) ?>">
    <?php if ($notification['priority'] !== 'normal'): ?>
    <span class="announcement-priority announcement-priority--<?= e($notification['priority']) ?>">
        <?= e(announcementPriorityLabel($notification['priority'])) ?>
    </span>
    <?php endif; ?>
    <div class="notification-detail__body"><?= nl2br(e($notification['body'])) ?></div>
    <?php if (!empty($notification['link_url'])): ?>
    <p class="notification-detail__link">
        <a href="<?= e($notification['link_url']) ?>" class="btn btn-primary btn-sm" target="_blank" rel="noopener">
            <?= e($notification['link_label'] ?: 'Open link') ?> <i class="fa-solid fa-arrow-up-right-from-square"></i>
        </a>
    </p>
    <?php endif; ?>
    <p class="text-muted"><a href="<?= url('notifications.php') ?>"><i class="fa-solid fa-arrow-left"></i> All notifications</a></p>
</article>
<?php else: ?>
<div class="notifications-page-toolbar">
    <?php if ($notifications !== []): ?>
    <form method="post" action="<?= url('api/notifications.php?action=read_all') ?>" id="markAllReadForm">
        <button type="button" class="btn btn-secondary btn-sm" id="markAllReadBtn"><i class="fa-solid fa-check-double"></i> Mark all read</button>
    </form>
    <?php endif; ?>
</div>

<?php if ($notifications === []): ?>
<div class="panel"><p class="text-muted mb-0">No notifications yet.</p></div>
<?php else: ?>
<div class="notifications-list">
    <?php foreach ($notifications as $row):
        $item = formatNotificationForClient($row);
    ?>
    <a href="<?= e($item['url']) ?>" class="notification-item panel<?= $item['is_read'] ? '' : ' notification-item--unread' ?>">
        <div class="notification-item__head">
            <strong><?= e($item['title']) ?></strong>
            <?php if ($item['priority'] !== 'normal'): ?>
            <span class="announcement-priority announcement-priority--<?= e($item['priority']) ?>"><?= e($item['priority_label']) ?></span>
            <?php endif; ?>
        </div>
        <p class="notification-item__preview"><?= e($item['preview']) ?></p>
        <time class="notification-item__time text-muted"><?= e(date('M j, Y g:i A', strtotime($item['created_at']))) ?></time>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout/dashboard_footer.php'; ?>
