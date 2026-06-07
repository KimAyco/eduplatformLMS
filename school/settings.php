<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('school_admin');
requireSchoolActive();

$schoolId = schoolId();
$stmt = db()->prepare('SELECT id, name, cover_image, logo_image FROM schools WHERE id = ?');
$stmt->execute([$schoolId]);
$school = $stmt->fetch();

if (!$school) {
    flash('error', 'School not found.');
    redirect('school/dashboard.php');
}

$errors = [];
$coverPreviewUrl = schoolCoverImageUrl($school);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? 'upload_cover';

    if ($action === 'remove_cover') {
        if (!empty($school['cover_image'])) {
            deleteUpload($school['cover_image']);
            db()->prepare('UPDATE schools SET cover_image = NULL WHERE id = ?')->execute([$schoolId]);
        }
        flash('success', 'Cover photo removed. A default image will be shown on the public school card.');
        redirect('school/settings.php');
    }

    if ($action === 'remove_logo') {
        if (!empty($school['logo_image'])) {
            deleteUpload($school['logo_image']);
            db()->prepare('UPDATE schools SET logo_image = NULL WHERE id = ?')->execute([$schoolId]);
        }
        flash('success', 'Logo removed. Your school initial will be shown on the public school card.');
        redirect('school/settings.php');
    }

    if ($action === 'upload_cover') {
        try {
            $newPath = uploadSchoolCover($_FILES['cover'] ?? [], $schoolId);
            if ($newPath === null) {
                $errors[] = 'Please choose an image file to upload.';
            } else {
                if (!empty($school['cover_image'])) {
                    deleteUpload($school['cover_image']);
                }
                db()->prepare('UPDATE schools SET cover_image = ? WHERE id = ?')->execute([$newPath, $schoolId]);
                flash('success', 'Cover photo updated.');
                redirect('school/settings.php');
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if ($action === 'upload_logo') {
        try {
            $newPath = uploadSchoolLogo($_FILES['logo'] ?? [], $schoolId);
            if ($newPath === null) {
                $errors[] = 'Please choose an image file to upload.';
            } else {
                if (!empty($school['logo_image'])) {
                    deleteUpload($school['logo_image']);
                }
                db()->prepare('UPDATE schools SET logo_image = ? WHERE id = ?')->execute([$newPath, $schoolId]);
                flash('success', 'School logo updated.');
                redirect('school/settings.php');
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = 'Settings';
$pageHeading = 'Settings';
$pageSubtitle = 'Customize how your school appears on the public landing page.';
$activeMenu = 'settings';
$menuItems = schoolAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'school/dashboard.php'],
    ['label' => 'Settings'],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="panel">
    <h2><i class="fa-solid fa-image"></i> Public card cover photo</h2>
    <p class="text-muted mb-1">This image appears on your school card on the EduPlatform home page and on your public school page.</p>

    <div class="school-settings-preview">
        <div class="school-settings-preview-card">
            <div class="school-card-cover" data-preview-cover style="background-image: url('<?= e($coverPreviewUrl) ?>')"></div>
            <div class="school-settings-preview-body">
                <div class="school-settings-preview-header">
                    <div data-preview-avatar><?= schoolAvatarHtml($school, 'school-avatar') ?></div>
                    <div>
                        <strong><?= e($school['name']) ?></strong>
                        <span class="text-muted">Preview of landing card</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" class="school-settings-form" data-upload-preview>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="upload_cover">
        <div class="form-group">
            <label for="cover">Upload new cover photo</label>
            <input type="file" id="cover" name="cover" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif" required data-preview-input data-preview-type="cover">
            <small>JPG, PNG, WebP, or GIF. Max 5 MB. Recommended size: 800×400 px or wider.</small>
        </div>
        <p class="image-upload-preview-note" data-preview-note hidden>Preview updated. Click Save cover photo to upload.</p>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload"></i> Save cover photo</button>
    </form>

    <?php if (!empty($school['cover_image'])): ?>
    <form method="post" class="school-settings-remove" onsubmit="return confirm('Remove your custom cover photo and use a default image?');">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="remove_cover">
        <button type="submit" class="btn btn-outline btn-sm"><i class="fa-solid fa-trash"></i> Remove custom cover</button>
    </form>
    <?php endif; ?>
</div>

<div class="panel">
    <h2><i class="fa-solid fa-circle-user"></i> School logo / profile photo</h2>
    <p class="text-muted mb-1">This circular avatar appears on your school card on the home page and on your public school page. If no logo is set, the first letter of your school name is shown.</p>

    <div class="school-settings-preview school-settings-preview--logo" data-preview-avatar>
        <?= schoolAvatarHtml($school, 'school-avatar') ?>
        <span class="text-muted">Preview of landing card avatar</span>
    </div>

    <form method="post" enctype="multipart/form-data" class="school-settings-form" data-upload-preview>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="upload_logo">
        <div class="form-group">
            <label for="logo">Upload new logo</label>
            <input type="file" id="logo" name="logo" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif" required data-preview-input data-preview-type="avatar" data-preview-scope="school-brand">
            <small>JPG, PNG, WebP, or GIF. Max 2 MB. Recommended size: 256×256 px square.</small>
        </div>
        <p class="image-upload-preview-note" data-preview-note hidden>Preview updated. Click Save logo to upload.</p>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload"></i> Save logo</button>
    </form>

    <?php if (!empty($school['logo_image'])): ?>
    <form method="post" class="school-settings-remove" onsubmit="return confirm('Remove your custom logo and use the school initial instead?');">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="remove_logo">
        <button type="submit" class="btn btn-outline btn-sm"><i class="fa-solid fa-trash"></i> Remove custom logo</button>
    </form>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
