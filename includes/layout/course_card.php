<?php

function renderCourseCard(array $class, string $href, ?string $bodyHtml = null): void
{
    $coverUrl = classCoverImageUrl($class);
    $hasCover = classHasCustomCover($class);
    $subjectName = (string) ($class['name'] ?? 'Course');
    $groupName = trim((string) ($class['group_name'] ?? ''));
    $academicYear = trim((string) ($class['group_academic_year'] ?? ''));

    if ($bodyHtml === null) {
        if (!empty($class['description'])) {
            $bodyHtml = '<p class="text-muted course-card-desc">' . e(mb_strimwidth((string) $class['description'], 0, 100, '…')) . '</p>';
        } else {
            $bodyHtml = '<p class="text-muted course-card-desc">' . e($academicYear ?: 'Open course') . '</p>';
        }
    }
    ?>
    <a href="<?= e($href) ?>" class="course-card course-card-clickable lms-course-card<?= $hasCover ? ' course-card-has-cover' : '' ?>">
        <div class="course-card-header" style="background-image: url('<?= e($coverUrl) ?>')">
            <div class="course-card-header-overlay" aria-hidden="true"></div>
            <div class="course-card-header-content">
                <span class="course-card-badge"><?= e($subjectName) ?></span>
                <h3><?= e($subjectName) ?></h3>
                <?php if ($groupName !== ''): ?>
                    <span class="course-card-chip"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> <?= e($groupName) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="course-card-body">
            <?= $bodyHtml ?>
        </div>
        <div class="course-card-footer">
            <span class="course-open-link">Open course <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
        </div>
    </a>
    <?php
}
