<?php
$user = currentUser();
$initials = userInitials($user);
$firstName = e($user['first_name'] ?? 'there');
$subtitle = $welcomeSubtitle ?? 'Here\'s an overview of your activity.';
?>
<div class="dashboard-welcome">
    <div class="dashboard-welcome-avatar" aria-hidden="true"><?= e($initials) ?></div>
    <div>
        <h2 class="dashboard-welcome-title">Welcome back, <strong><?= $firstName ?></strong></h2>
        <p class="dashboard-welcome-sub"><?= e($subtitle) ?></p>
    </div>
</div>
