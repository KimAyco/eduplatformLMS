<?php

function userCanManageProfile(array $targetUser, ?array $actor = null): bool
{
    $actor ??= currentUser();
    if (!$actor) {
        return false;
    }

    if ((int) $actor['id'] === (int) $targetUser['id']) {
        return true;
    }

    if (($actor['role'] ?? '') === 'super_admin') {
        return true;
    }

    if (($actor['role'] ?? '') === 'school_admin'
        && (int) ($actor['school_id'] ?? 0) === (int) ($targetUser['school_id'] ?? 0)
        && in_array($targetUser['role'] ?? '', ['student', 'teacher'], true)) {
        return true;
    }

    return false;
}

function handleUserProfilePhotoPost(array $user, string $redirectUrl): array
{
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return $errors;
    }

    $action = $_POST['action'] ?? '';
    if (!in_array($action, ['upload_profile_photo', 'remove_profile_photo'], true)) {
        return $errors;
    }

    verifyCsrf();

    if (!userCanManageProfile($user)) {
        flash('error', 'You are not allowed to update this profile photo.');
        redirect($redirectUrl);
    }

    $action = $_POST['action'] ?? '';
    $userId = (int) $user['id'];

    if ($action === 'remove_profile_photo') {
        removeUserProfileImage($user);
        flash('success', 'Profile photo removed.');
        redirect($redirectUrl);
    }

    if ($action === 'upload_profile_photo') {
        try {
            $newPath = uploadUserProfile($_FILES['profile_photo'] ?? [], $userId, $user['school_id'] ?? null);
            if ($newPath === null) {
                $errors[] = 'Please choose an image file to upload.';
            } else {
                if (!empty($user['profile_image'])) {
                    deleteUpload($user['profile_image']);
                }
                db()->prepare('UPDATE users SET profile_image = ? WHERE id = ?')->execute([$newPath, $userId]);
                flash('success', 'Profile photo updated.');
                redirect($redirectUrl);
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    return $errors;
}

function renderUserProfilePhotoPanel(array $user, string $heading = 'Profile photo', string $description = '', bool $compact = false, bool $collapsible = false, bool $startOpen = false): void
{
    if ($description === '') {
        $description = 'Use a clear, square photo. It appears on your dashboard, navbar, and anywhere your name is shown.';
    }

    $openByDefault = $collapsible && (
        $startOpen
        || (!empty($_POST['action']) && in_array($_POST['action'], ['upload_profile_photo', 'remove_profile_photo'], true))
    );

    $renderBody = static function () use ($user, $heading, $description, $compact, $collapsible): void {
        if (!$collapsible): ?>
        <div class="profile-photo-card__head">
            <h3><i class="fa-solid fa-camera"></i> <?= e($heading) ?></h3>
            <p class="text-muted"><?= e($description) ?></p>
        </div>
        <?php endif;

        if (!$compact): ?>
        <div class="profile-photo-card__preview" data-preview-avatar>
            <?= userAvatarHtml($user, 'profile-photo-card__avatar') ?>
        </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="profile-photo-form" data-upload-preview>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="upload_profile_photo">
            <label class="profile-photo-upload">
                <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif" required data-preview-input data-preview-type="avatar" data-preview-scope="profile">
                <span class="profile-photo-upload__box">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <strong>Choose a photo</strong>
                    <span>JPG, PNG, WebP, or GIF · max 2 MB</span>
                </span>
            </label>
            <p class="profile-photo-note" data-preview-note hidden><i class="fa-solid fa-eye"></i> Preview ready — save to upload.</p>
            <div class="profile-photo-form__actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save photo</button>
                <?php if (!empty($user['profile_image'])): ?>
                <button type="submit" form="profileRemovePhotoForm" class="btn btn-outline btn-sm" onclick="return confirm('Remove this profile photo?');">
                    <i class="fa-solid fa-trash"></i> Remove
                </button>
                <?php endif; ?>
                <?php if ($collapsible): ?>
                <button type="button" class="btn btn-outline btn-sm" data-close-profile-photo>Cancel</button>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!empty($user['profile_image'])): ?>
        <form method="post" id="profileRemovePhotoForm" class="sr-only">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="remove_profile_photo">
        </form>
        <?php endif;
    };

    if ($collapsible): ?>
    <div id="profilePhotoDrawer" class="profile-photo-drawer profile-photo-card panel"<?= $openByDefault ? '' : ' hidden' ?>>
        <div class="profile-photo-drawer__head">
            <h3><i class="fa-solid fa-camera"></i> <?= e($heading) ?></h3>
            <button type="button" class="profile-photo-drawer__close" data-close-profile-photo aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <p class="profile-photo-drawer__desc text-muted"><?= e($description) ?></p>
        <div class="profile-photo-drawer__body">
            <?php $renderBody(); ?>
        </div>
    </div>
    <?php else: ?>
    <div class="profile-photo-card panel">
        <?php $renderBody(); ?>
    </div>
    <?php endif;
}

function menuItemsForRole(string $role): array
{
    return match ($role) {
        'super_admin' => superAdminMenu(),
        'school_admin' => schoolAdminMenu(),
        'teacher' => teacherMenu(),
        'student' => studentMenu(),
        default => [],
    };
}

function activeMenuForRole(string $role): string
{
    return match ($role) {
        'super_admin' => 'profile',
        'school_admin' => 'profile',
        'teacher' => 'profile',
        'student' => 'profile',
        default => '',
    };
}

function dashboardHomeForRole(string $role): string
{
    return match ($role) {
        'super_admin' => 'superadmin/dashboard.php',
        'school_admin' => 'school/dashboard.php',
        'teacher' => 'teacher/dashboard.php',
        'student' => 'student/dashboard.php',
        default => 'index.php',
    };
}
