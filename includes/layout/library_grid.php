<?php

function renderLibraryFilters(string $baseUrl, array $subjects, array $filters, bool $showStatus = false): void
{
    $tab = $filters['tab'] ?? 'published';
    ?>
    <form method="get" action="<?= url($baseUrl) ?>" class="library-filters">
        <?php if ($showStatus): ?><input type="hidden" name="tab" value="<?= e($tab) ?>"><?php endif; ?>
        <label class="library-filter">
            <span class="sr-only">Search</span>
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" name="q" class="form-control" placeholder="Search resources…" value="<?= e($filters['search'] ?? '') ?>">
        </label>
        <label class="library-filter">
            <span>Kind</span>
            <select name="kind" class="form-control">
                <option value="">All kinds</option>
                <?php foreach (LibraryResourceRepository::RESOURCE_KINDS as $kind): ?>
                    <option value="<?= e($kind) ?>"<?= ($filters['resource_kind'] ?? '') === $kind ? ' selected' : '' ?>><?= e(resourceKindLabel($kind)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="library-filter">
            <span>Subject</span>
            <select name="subject_id" class="form-control">
                <option value="">All subjects</option>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= (int) $subject['id'] ?>"<?= (int) ($filters['subject_id'] ?? 0) === (int) $subject['id'] ? ' selected' : '' ?>><?= e($subject['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="library-filter">
            <span>Type</span>
            <select name="type" class="form-control">
                <option value="">All types</option>
                <option value="file"<?= ($filters['type'] ?? '') === 'file' ? ' selected' : '' ?>>File</option>
                <option value="link"<?= ($filters['type'] ?? '') === 'link' ? ' selected' : '' ?>>Link</option>
                <option value="doc"<?= ($filters['type'] ?? '') === 'doc' ? ' selected' : '' ?>>Document</option>
                <option value="deck"<?= ($filters['type'] ?? '') === 'deck' ? ' selected' : '' ?>>Slide deck</option>
            </select>
        </label>
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        <?php if (!empty($filters['search']) || !empty($filters['resource_kind']) || !empty($filters['subject_id']) || !empty($filters['type'])): ?>
            <a href="<?= url($baseUrl . ($showStatus ? '?tab=' . urlencode($tab) : '')) ?>" class="btn btn-secondary btn-sm">Clear</a>
        <?php endif; ?>
    </form>
    <?php
}

function renderLibraryGrid(array $resources, string $mode = 'browse'): void
{
    if ($resources === []) {
        echo adminEmptyState('fa-book-bookmark', 'No resources found', 'Try adjusting your filters or add a new resource.');
        return;
    }
    ?>
    <div class="library-grid">
        <?php foreach ($resources as $resource):
            $icon = libraryResourceIcon($resource['resource_kind'], $resource['type']);
            $status = $resource['status'] ?? 'published';
        ?>
        <article class="library-card">
            <div class="library-card-icon" aria-hidden="true"><i class="fa-solid <?= e($icon) ?>"></i></div>
            <div class="library-card-body">
                <div class="library-card-tags">
                    <span class="library-tag"><?= e(resourceKindLabel($resource['resource_kind'])) ?></span>
                    <span class="library-tag library-tag--muted"><?= e(materialTypeLabel($resource['type'])) ?></span>
                    <?php if ($mode === 'admin' && $status !== 'published'): ?>
                        <span class="library-tag library-tag--status library-tag--<?= e($status) ?>"><?= e(libraryStatusLabel($status)) ?></span>
                    <?php endif; ?>
                    <?php if (($resource['audience'] ?? 'all') === 'teachers'): ?>
                        <span class="library-tag library-tag--audience">Teachers only</span>
                    <?php endif; ?>
                </div>
                <h3><a href="<?= e(libraryViewUrl((int) $resource['id'])) ?>"><?= e($resource['title']) ?></a></h3>
                <?php if (!empty($resource['description'])): ?>
                    <p><?= e(mb_strimwidth($resource['description'], 0, 120, '…')) ?></p>
                <?php endif; ?>
                <div class="library-card-meta">
                    <?php if (!empty($resource['subject_name'])): ?>
                        <span><i class="fa-solid fa-book"></i> <?= e($resource['subject_name']) ?></span>
                    <?php endif; ?>
                    <span><i class="fa-solid fa-user"></i> <?= e(trim(($resource['creator_first'] ?? '') . ' ' . ($resource['creator_last'] ?? ''))) ?></span>
                    <span><i class="fa-regular fa-calendar"></i> <?= formatDate($resource['created_at'], 'M j, Y') ?></span>
                </div>
            </div>
            <div class="library-card-actions">
                <a href="<?= e(libraryViewUrl((int) $resource['id'])) ?>" class="btn btn-sm btn-secondary">View</a>
                <?php if ($mode === 'admin'): ?>
                    <a href="<?= url('school/library.php?action=edit&id=' . (int) $resource['id']) ?>" class="btn btn-sm btn-secondary">Edit</a>
                <?php elseif ($mode === 'teacher'): ?>
                    <button type="button" class="btn btn-sm btn-primary" data-library-attach="<?= (int) $resource['id'] ?>" data-library-title="<?= e($resource['title']) ?>">Add to class</button>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php
}

function renderLibraryAttachModal(array $teacherClasses): void
{
    if ($teacherClasses === []) {
        return;
    }
    ?>
    <dialog class="library-attach-dialog" id="libraryAttachDialog">
        <form method="post" class="library-attach-form">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="attach_to_class">
            <input type="hidden" name="library_id" id="libraryAttachId" value="">
            <header class="library-attach-header">
                <h2>Add to class</h2>
                <p id="libraryAttachTitle" class="text-muted"></p>
                <button type="button" class="library-attach-close" data-close-attach aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            </header>
            <div class="form-group">
                <label>Class</label>
                <select name="class_id" id="libraryAttachClass" class="form-control" required>
                    <option value="">Choose a class…</option>
                    <?php foreach ($teacherClasses as $class): ?>
                        <option value="<?= (int) $class['id'] ?>" data-sections="<?= e(json_encode(array_map(static fn ($s) => ['id' => (int) $s['id'], 'title' => $s['title']], CourseSectionRepository::forClass((int) $class['id'])), JSON_UNESCAPED_UNICODE)) ?>">
                            <?= e(classDisplayName($class)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Lesson section</label>
                <select name="section_id" id="libraryAttachSection" class="form-control">
                    <option value="">Unassigned</option>
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary">Add to class</button>
                <button type="button" class="btn btn-secondary" data-close-attach>Cancel</button>
            </div>
        </form>
    </dialog>
    <?php
}
