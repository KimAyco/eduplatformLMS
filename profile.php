<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

$actor = currentUser();
$role = $actor['role'] ?? '';
$userId = (int) $actor['id'];

$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    logoutUser();
    redirect('login.php');
}

$errors = handleUserProfilePhotoPost($user, 'profile.php');

$stmt->execute([$userId]);
$user = $stmt->fetch();

$fullName = trim($user['first_name'] . ' ' . $user['last_name']);

$pageTitle = 'My profile';
$pageHeading = 'My profile';
$pageSubtitle = 'Manage your account photo and view your details.';
$activeMenu = 'profile';
$menuItems = menuItemsForRole($role);
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => dashboardHomeForRole($role)],
    ['label' => 'My profile', 'url' => ''],
];

require __DIR__ . '/includes/layout/dashboard_header.php';
?>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-error alert-icon"><i class="fa-solid fa-circle-exclamation"></i><span><?= e($err) ?></span></div>
<?php endforeach; ?>

<div class="user-profile-layout">
    <div class="panel user-profile-header">
        <div class="panel-header">
            <div class="user-profile-intro">
                <?= userAvatarHtml($user, 'user-profile-avatar') ?>
                <div>
                    <div class="user-profile-title-row">
                        <h2><?= e($fullName) ?></h2>
                        <span class="badge badge-<?= $user['status'] === 'active' ? 'active' : 'suspended' ?>"><?= e(ucfirst($user['status'])) ?></span>
                    </div>
                    <p class="text-muted user-profile-role">
                        <i class="fa-solid fa-id-badge"></i> <?= e(ROLES[$role] ?? ucfirst($role)) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="user-profile-grid">
        <?php renderUserProfilePhotoPanel($user) ?>

        <div class="panel user-profile-panel">
            <h3>Account information</h3>
            <dl class="user-profile-details">
                <div>
                    <dt>Email</dt>
                    <dd><a href="mailto:<?= e($user['email']) ?>"><?= e($user['email']) ?></a></dd>
                </div>
                <div>
                    <dt>First name</dt>
                    <dd><?= e($user['first_name']) ?></dd>
                </div>
                <div>
                    <dt>Last name</dt>
                    <dd><?= e($user['last_name']) ?></dd>
                </div>
                <?php if (!empty($user['school_id']) && ($actor['school_name'] ?? null)): ?>
                <div>
                    <dt>School</dt>
                    <dd><?= e($actor['school_name']) ?></dd>
                </div>
                <?php endif; ?>
                <div>
                    <dt>Member since</dt>
                    <dd><?= formatDate($user['created_at'], 'M j, Y') ?></dd>
                </div>
            </dl>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout/dashboard_footer.php'; ?>
