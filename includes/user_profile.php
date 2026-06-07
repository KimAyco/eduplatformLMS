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

function renderUserProfilePhotoPanel(array $user, string $heading = 'Profile photo', string $description = ''): void
{
    if ($description === '') {
        $description = 'Upload a square photo. It appears on your account, dashboard, and anywhere your name is shown.';
    }
    ?>
    <div class="panel user-profile-photo-panel">
        <h3><i class="fa-solid fa-camera"></i> <?= e($heading) ?></h3>
        <p class="text-muted mb-1"><?= e($description) ?></p>

        <div class="user-profile-photo-preview" data-preview-avatar>
            <?= userAvatarHtml($user, 'user-profile-avatar user-profile-avatar--large') ?>
        </div>

        <form method="post" enctype="multipart/form-data" class="school-settings-form" data-upload-preview>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="upload_profile_photo">
            <div class="form-group">
                <label for="profile_photo">Upload new photo</label>
                <input type="file" id="profile_photo" name="profile_photo" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif" required data-preview-input data-preview-type="avatar">
                <small>JPG, PNG, WebP, or GIF. Max 2 MB. Recommended size: 256×256 px square.</small>
            </div>
            <p class="image-upload-preview-note" data-preview-note hidden>Preview updated. Click Save photo to upload.</p>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload"></i> Save photo</button>
        </form>

        <?php if (!empty($user['profile_image'])): ?>
        <form method="post" class="school-settings-remove" onsubmit="return confirm('Remove this profile photo?');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="remove_profile_photo">
            <button type="submit" class="btn btn-outline btn-sm"><i class="fa-solid fa-trash"></i> Remove photo</button>
        </form>
        <?php endif; ?>
    </div>
    <?php
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
