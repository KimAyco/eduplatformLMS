<?php

function profileRoleTone(string $role): string
{
    return match ($role) {
        'teacher' => 'teacher',
        'student' => 'student',
        'school_admin' => 'admin',
        'super_admin' => 'super',
        default => 'default',
    };
}

/**
 * @param array<string, mixed> $user
 * @param array<string, mixed> $actor
 */
function renderProfileHero(array $user, array $actor, string $role, string $fullName, string $dashboardUrl): void
{
    $tone = profileRoleTone($role);
    $schoolName = $actor['school_name'] ?? null;
    ?>
    <section class="profile-hero profile-hero--<?= e($tone) ?>">
        <div class="profile-hero__cover" aria-hidden="true"></div>
        <div class="profile-hero__content">
            <div class="profile-hero__main">
                <div class="profile-hero__avatar-ring" data-preview-avatar>
                    <?= userAvatarHtml($user, 'profile-hero__avatar') ?>
                </div>
                <div class="profile-hero__text">
                    <div class="profile-hero__badges">
                        <span class="profile-role-badge profile-role-badge--<?= e($tone) ?>">
                            <i class="fa-solid fa-id-badge"></i> <?= e(ROLES[$role] ?? ucfirst($role)) ?>
                        </span>
                        <span class="profile-status-badge profile-status-badge--<?= e($user['status'] === 'active' ? 'active' : 'inactive') ?>">
                            <?= e(ucfirst($user['status'])) ?>
                        </span>
                    </div>
                    <h1 class="profile-hero__name"><?= e($fullName) ?></h1>
                    <a class="profile-hero__email" href="mailto:<?= e($user['email']) ?>"><?= e($user['email']) ?></a>
                    <p class="profile-hero__meta">
                        <span><i class="fa-solid fa-calendar-days"></i> Member since <?= formatDate($user['created_at'], 'M j, Y') ?></span>
                        <?php if ($schoolName): ?>
                        <span><i class="fa-solid fa-school"></i> <?= e($schoolName) ?></span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="profile-hero__actions">
                <button type="button" class="btn btn-secondary btn-sm" data-open-profile-photo>
                    <i class="fa-solid fa-camera"></i> Change photo
                </button>
                <a href="<?= url($dashboardUrl) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-gauge"></i> Dashboard</a>
                <a href="<?= url('logout.php') ?>" class="btn btn-outline btn-sm" data-confirm-logout="Are you sure you want to log out?"><i class="fa-solid fa-right-from-bracket"></i> Log out</a>
            </div>
        </div>
    </section>
    <?php
}

/**
 * @param array<string, mixed> $user
 * @param array<string, mixed> $actor
 */
function renderProfileDetailsGrid(array $user, array $actor, string $role, string $fullName): void
{
    $schoolName = $actor['school_name'] ?? null;
    $items = [
        ['icon' => 'fa-envelope', 'label' => 'Email', 'value' => $user['email'], 'href' => 'mailto:' . $user['email']],
    ];
    if ($schoolName) {
        $items[] = ['icon' => 'fa-school', 'label' => 'School', 'value' => $schoolName];
    }
    $items[] = ['icon' => 'fa-user', 'label' => 'Last name', 'value' => $user['last_name']];
    $items[] = ['icon' => 'fa-id-badge', 'label' => 'Role', 'value' => ROLES[$role] ?? ucfirst($role)];
    $items[] = ['icon' => 'fa-user', 'label' => 'First name', 'value' => $user['first_name']];
    $items[] = ['icon' => 'fa-calendar-check', 'label' => 'Member since', 'value' => formatDate($user['created_at'], 'F j, Y')];
    ?>
    <div class="profile-details-grid">
        <?php foreach ($items as $item): ?>
        <article class="profile-detail-card">
            <span class="profile-detail-card__icon"><i class="fa-solid <?= e($item['icon']) ?>"></i></span>
            <div class="profile-detail-card__body">
                <span class="profile-detail-card__label"><?= e($item['label']) ?></span>
                <?php if (!empty($item['href'])): ?>
                    <a class="profile-detail-card__value" href="<?= e($item['href']) ?>"><?= e($item['value']) ?></a>
                <?php else: ?>
                    <span class="profile-detail-card__value"><?= e($item['value']) ?></span>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * @param list<array<string, mixed>> $subjects
 * @param list<array<string, mixed>> $classes
 */
function renderProfileTeacherSubjects(array $subjects, array $classes): void
{
    $classesBySubject = [];
    foreach ($classes as $class) {
        $classesBySubject[(int) $class['subject_id']][] = $class;
    }
    ?>
    <div class="profile-teaching-section">
        <div class="profile-section-head profile-section-head--compact">
            <div>
                <h3><i class="fa-solid fa-book-open"></i> Teachable subjects</h3>
                <p class="text-muted">Subjects you are approved to teach at your school.</p>
            </div>
            <?php if ($subjects !== []): ?>
            <span class="profile-teaching-count"><?= count($subjects) ?> subject<?= count($subjects) !== 1 ? 's' : '' ?></span>
            <?php endif; ?>
        </div>

        <?php if ($subjects === []): ?>
            <div class="profile-teaching-empty">
                <i class="fa-solid fa-book"></i>
                <p>No teachable subjects assigned yet. Contact your school administrator to add subjects to your profile.</p>
            </div>
        <?php else: ?>
            <div class="profile-subject-list">
                <?php foreach ($subjects as $subject):
                    $subjectId = (int) $subject['id'];
                    $subjectClasses = $classesBySubject[$subjectId] ?? [];
                    $description = trim((string) ($subject['description'] ?? ''));
                ?>
                <article class="profile-subject-card">
                    <div class="profile-subject-card__head">
                        <span class="profile-subject-card__icon"><i class="fa-solid fa-book"></i></span>
                        <div class="profile-subject-card__title">
                            <strong><?= e($subject['name']) ?></strong>
                            <?php if ($description !== ''): ?>
                                <span><?= e($description) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($subjectClasses !== []): ?>
                    <div class="profile-subject-card__classes">
                        <?php foreach ($subjectClasses as $class): ?>
                        <a href="<?= e(teacherCourseUrl((int) $class['id'])) ?>" class="profile-subject-chip">
                            <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                            <span><?= e($class['group_name'] ?: 'Class group') ?></span>
                            <?php if (!empty($class['group_academic_year'])): ?>
                                <span class="profile-subject-chip__year"><?= e($class['group_academic_year']) ?></span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="profile-subject-card__pending">Not assigned to a class section yet.</p>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
