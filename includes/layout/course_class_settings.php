<?php
/** Course class settings body — expects $class, $coverPreviewUrl, optional $classId for hidden fields */
$classId = (int) ($classId ?? ($class['id'] ?? 0));
?>
<section class="course-settings-section" id="courseAppearance">
    <h4 class="course-settings-section-title"><i class="fa-solid fa-palette"></i> Course appearance</h4>
    <p class="text-muted course-settings-section-desc">Customize the cover photo shown on course cards for this class.</p>

    <div class="course-appearance-preview">
        <div class="course-appearance-preview-card lms-course-card course-card<?= classHasCustomCover($class) ? ' course-card-has-cover' : '' ?>">
            <div class="course-card-header" data-preview-cover style="background-image: url('<?= e($coverPreviewUrl) ?>')">
                <div class="course-card-header-overlay" aria-hidden="true"></div>
                <div class="course-card-header-content">
                    <span class="course-card-badge"><?= e($class['name']) ?></span>
                    <h3><?= e($class['name']) ?></h3>
                    <?php if (!empty($class['group_name'])): ?>
                        <span class="course-card-chip"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> <?= e($class['group_name']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <span class="text-muted course-appearance-preview-note">Preview of course card header</span>
    </div>

    <form method="post" enctype="multipart/form-data" class="course-appearance-form" data-upload-preview>
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="upload_class_cover">
        <?php if ($classId): ?><input type="hidden" name="class_id" value="<?= $classId ?>"><?php endif; ?>
        <div class="form-group">
            <label for="class_cover">Upload cover photo</label>
            <input type="file" id="class_cover" name="cover" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif" required data-preview-input data-preview-type="cover">
            <small>JPG, PNG, WebP, or GIF. Max 5 MB. Recommended size: 800×400 px or wider.</small>
        </div>
        <div class="course-cover-file-preview" data-preview-upload hidden>
            <span class="course-cover-file-preview-label">Selected image</span>
            <div class="course-cover-file-preview-frame">
                <img src="" alt="Cover preview" data-preview-image>
            </div>
        </div>
        <p class="image-upload-preview-note" data-preview-note hidden>Preview updated. Click Save cover to upload.</p>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-upload"></i> Save cover</button>
    </form>

    <?php if (!empty($class['cover_image'])): ?>
    <form method="post" class="course-appearance-remove" onsubmit="return confirm('Remove this course cover and use the default image?');">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="remove_class_cover">
        <?php if ($classId): ?><input type="hidden" name="class_id" value="<?= $classId ?>"><?php endif; ?>
        <button type="submit" class="btn btn-outline btn-sm"><i class="fa-solid fa-trash"></i> Remove custom cover</button>
    </form>
    <?php endif; ?>
</section>
