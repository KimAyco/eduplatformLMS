<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout/profile_page.php';
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
$dashboardUrl = dashboardHomeForRole($role);
$teacherSubjects = [];
$teacherClasses = [];
if ($role === 'teacher') {
    $teacherSubjects = SubjectRepository::forTeacher($userId, schoolId());
    $teacherClasses = getTeacherClasses($userId);
}

$pageTitle = 'My profile';
$pageHeading = 'My profile';
$pageSubtitle = 'Manage your account photo and view your details.';
$hidePageHeader = true;
$activeMenu = 'profile';
$menuItems = menuItemsForRole($role);
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => $dashboardUrl],
    ['label' => 'My profile', 'url' => ''],
];

require __DIR__ . '/includes/layout/dashboard_header.php';
?>

<div class="profile-page">
    <?php foreach ($errors as $err): ?>
        <div class="alert alert-error alert-icon"><i class="fa-solid fa-circle-exclamation"></i><span><?= e($err) ?></span></div>
    <?php endforeach; ?>

    <?php renderProfileHero($user, $actor, $role, $fullName, $dashboardUrl); ?>

    <?php renderUserProfilePhotoPanel($user, 'Change photo', '', true, true, $errors !== []); ?>

    <section class="profile-layout__main panel">
        <div class="profile-section-head">
            <h2><i class="fa-solid fa-address-card"></i> Account details</h2>
            <p class="text-muted">Your profile information across EduPlatform.</p>
        </div>
        <?php renderProfileDetailsGrid($user, $actor, $role, $fullName); ?>
        <?php if ($role === 'teacher'): ?>
            <?php renderProfileTeacherSubjects($teacherSubjects, $teacherClasses); ?>
        <?php endif; ?>
    </section>
</div>
<script>
(function () {
    var drawer = document.getElementById('profilePhotoDrawer');
    if (!drawer) return;

    function openDrawer() {
        drawer.hidden = false;
        drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        var input = drawer.querySelector('#profile_photo');
        if (input) input.focus();
    }

    function closeDrawer() {
        drawer.hidden = true;
    }

    document.querySelectorAll('[data-open-profile-photo]').forEach(function (btn) {
        btn.addEventListener('click', openDrawer);
    });
    document.querySelectorAll('[data-close-profile-photo]').forEach(function (btn) {
        btn.addEventListener('click', closeDrawer);
    });
})();
</script>

<?php require __DIR__ . '/includes/layout/dashboard_footer.php'; ?>
