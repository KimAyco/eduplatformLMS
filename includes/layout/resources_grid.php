<?php

function renderResourcesStats(array $resources): void
{
    $total = count($resources);
    $shared = count(array_filter($resources, static fn ($r) => !empty($r['library_resource_id'])));
    $decks = count(array_filter($resources, static fn ($r) => ($r['resource_type'] ?? '') === 'deck'));
    $docs = $total - $decks;
    ?>
    <div class="resources-stats stats-grid">
        <div class="stat-card stat-card--compact">
            <div class="value"><?= $total ?></div>
            <div class="label">Total resources</div>
        </div>
        <div class="stat-card stat-card--compact">
            <div class="value"><?= $shared ?></div>
            <div class="label">In library</div>
        </div>
        <div class="stat-card stat-card--compact">
            <div class="value"><?= $decks ?></div>
            <div class="label">Slide decks</div>
        </div>
        <div class="stat-card stat-card--compact">
            <div class="value"><?= $docs ?></div>
            <div class="label">Documents</div>
        </div>
    </div>
    <?php
}

function renderResourcesToolbar(string $baseUrl, array $filters, array $subjects = []): void
{
    ?>
    <div class="resources-toolbar resources-toolbar--enhanced">
        <div class="resources-toolbar-create">
            <form method="post" action="<?= url($baseUrl) ?>" class="resources-create-form">
                <?= csrfField() ?>
                <input type="hidden" name="form_action" value="create_deck">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-display"></i> New slide deck</button>
            </form>
            <form method="post" action="<?= url($baseUrl) ?>" class="resources-create-form">
                <?= csrfField() ?>
                <input type="hidden" name="form_action" value="create_doc">
                <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-file-lines"></i> New document</button>
            </form>
        </div>
        <form method="get" action="<?= url($baseUrl) ?>" class="resources-filters">
            <label class="library-filter">
                <span class="sr-only">Search</span>
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" name="q" class="form-control" placeholder="Search resources…" value="<?= e($filters['search'] ?? '') ?>">
            </label>
            <label class="library-filter">
                <span>Type</span>
                <select name="type" class="form-control">
                    <option value="">All types</option>
                    <option value="deck"<?= ($filters['resource_type'] ?? '') === 'deck' ? ' selected' : '' ?>>Slide decks</option>
                    <option value="doc"<?= ($filters['resource_type'] ?? '') === 'doc' ? ' selected' : '' ?>>Documents</option>
                </select>
            </label>
            <label class="library-filter">
                <span>Status</span>
                <select name="status" class="form-control">
                    <option value="">Active</option>
                    <option value="archived"<?= ($filters['status'] ?? '') === 'archived' ? ' selected' : '' ?>>Archived</option>
                    <option value="all"<?= ($filters['status'] ?? '') === 'all' ? ' selected' : '' ?>>All</option>
                </select>
            </label>
            <label class="library-filter">
                <span>Library</span>
                <select name="shared" class="form-control">
                    <option value="">Any</option>
                    <option value="yes"<?= ($filters['shared'] ?? '') === 'yes' ? ' selected' : '' ?>>Shared to library</option>
                    <option value="no"<?= ($filters['shared'] ?? '') === 'no' ? ' selected' : '' ?>>Not shared</option>
                </select>
            </label>
            <?php if ($subjects !== []): ?>
            <label class="library-filter">
                <span>Subject</span>
                <select name="subject_id" class="form-control">
                    <option value="">All subjects</option>
                    <?php foreach ($subjects as $subject): ?>
                    <option value="<?= (int) $subject['id'] ?>"<?= (int) ($filters['subject_id'] ?? 0) === (int) $subject['id'] ? ' selected' : '' ?>><?= e($subject['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
            <label class="library-filter">
                <span>Sort</span>
                <select name="sort" class="form-control">
                    <option value="updated"<?= ($filters['sort'] ?? '') === 'updated' ? ' selected' : '' ?>>Recently updated</option>
                    <option value="title"<?= ($filters['sort'] ?? '') === 'title' ? ' selected' : '' ?>>Title A–Z</option>
                    <option value="type"<?= ($filters['sort'] ?? '') === 'type' ? ' selected' : '' ?>>Type</option>
                </select>
            </label>
            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        </form>
        <div class="resources-view-toggle" role="group" aria-label="View mode">
            <button type="button" class="btn btn-sm btn-secondary is-active" data-resources-view="grid" title="Grid view"><i class="fa-solid fa-grip"></i></button>
            <button type="button" class="btn btn-sm btn-secondary" data-resources-view="list" title="List view"><i class="fa-solid fa-list"></i></button>
        </div>
    </div>
    <div class="resources-bulk-bar" id="resourcesBulkBar" hidden>
        <span data-bulk-count>0</span> selected
        <form method="post" action="<?= url($baseUrl) ?>" id="resourcesBulkForm">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="bulk_archive">
            <div id="resourcesBulkIds"></div>
            <button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('Archive selected resources?');">Archive selected</button>
        </form>
    </div>
    <?php
}

function renderResourcesGrid(array $resources, string $role, array $teacherClasses = []): void
{
    if ($resources === []) {
        echo adminEmptyState('fa-folder-open', 'No resources yet', 'Create a slide deck or document to get started.');
        return;
    }
    ?>
    <div class="resources-grid" id="resourcesGrid" data-resources-container>
        <?php foreach ($resources as $resource):
            $isDeck = $resource['resource_type'] === 'deck';
            $thumbUrl = !empty($resource['thumbnail_path']) ? downloadUrl($resource['thumbnail_path'], 'resource_asset') : null;
            $libStatus = $resource['library_status'] ?? null;
        ?>
        <article class="resources-card" data-resource-title="<?= e(mb_strtolower($resource['title'])) ?>" data-resource-type-filter="<?= e($resource['resource_type']) ?>">
            <label class="resources-card-select">
                <input type="checkbox" class="resources-bulk-check" value="<?= (int) $resource['id'] ?>">
                <span class="sr-only">Select <?= e($resource['title']) ?></span>
            </label>
            <a href="<?= e(contentResourceEditorUrl((int) $resource['id'], $resource['resource_type'])) ?>" class="resources-card-preview<?= $thumbUrl ? ' has-thumb' : ' needs-preview' ?>" data-resource-id="<?= (int) $resource['id'] ?>" data-resource-type="<?= e($resource['resource_type']) ?>" aria-label="Open <?= e($resource['title']) ?>">
                <?php if ($thumbUrl): ?>
                    <img src="<?= e($thumbUrl) ?>" alt="">
                <?php else: ?>
                    <div class="resources-card-preview-mount" aria-hidden="true"></div>
                    <span class="resources-card-preview-fallback"><i class="fa-solid <?= $isDeck ? 'fa-display' : 'fa-file-lines' ?>"></i></span>
                <?php endif; ?>
            </a>
            <div class="resources-card-body">
                <div class="resources-card-tags">
                    <span class="library-tag"><?= e(contentResourceTypeLabel($resource['resource_type'])) ?></span>
                    <?php if ($resource['status'] === 'archived'): ?>
                        <span class="library-tag library-tag--status library-tag--rejected">Archived</span>
                    <?php endif; ?>
                    <?php if ($libStatus): ?>
                        <span class="library-tag library-tag--status library-tag--<?= e($libStatus) ?>"><?= e(libraryStatusLabel($libStatus)) ?></span>
                    <?php endif; ?>
                </div>
                <h3><a href="<?= e(contentResourceEditorUrl((int) $resource['id'], $resource['resource_type'])) ?>"><?= e($resource['title']) ?></a></h3>
                <?php if (!empty($resource['description'])): ?>
                    <p><?= e(mb_strimwidth($resource['description'], 0, 100, '…')) ?></p>
                <?php endif; ?>
                <div class="library-card-meta">
                    <?php if (!empty($resource['subject_name'])): ?>
                        <span><i class="fa-solid fa-book"></i> <?= e($resource['subject_name']) ?></span>
                    <?php endif; ?>
                    <span><i class="fa-solid fa-user"></i> <?= e(trim(($resource['creator_first'] ?? '') . ' ' . ($resource['creator_last'] ?? ''))) ?></span>
                    <span><i class="fa-regular fa-clock"></i> <?= formatDate($resource['updated_at'], 'M j, Y') ?></span>
                </div>
            </div>
            <div class="resources-card-actions">
                <a href="<?= e(contentResourceEditorUrl((int) $resource['id'], $resource['resource_type'])) ?>" class="btn btn-sm btn-primary">Edit</a>
                <?php if ($role === 'teacher' && !empty($teacherClasses)): ?>
                    <button type="button" class="btn btn-sm btn-secondary" data-resource-attach="<?= (int) $resource['id'] ?>" data-resource-title="<?= e($resource['title']) ?>">Add to class</button>
                <?php endif; ?>
                <?php if (!$libStatus || $libStatus === 'rejected'): ?>
                    <button type="button" class="btn btn-sm btn-secondary" data-resource-share="<?= (int) $resource['id'] ?>" data-resource-title="<?= e($resource['title']) ?>">Share to library</button>
                <?php endif; ?>
                <form method="post" action="<?= e(contentResourceListUrl($role)) ?>" class="resources-inline-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="form_action" value="duplicate">
                    <input type="hidden" name="resource_id" value="<?= (int) $resource['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-secondary" title="Duplicate"><i class="fa-solid fa-copy"></i></button>
                </form>
                <?php if ($resource['status'] === 'archived'): ?>
                    <form method="post" action="<?= e(contentResourceListUrl($role)) ?>" class="resources-inline-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="form_action" value="restore">
                        <input type="hidden" name="resource_id" value="<?= (int) $resource['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-secondary" title="Restore"><i class="fa-solid fa-rotate-left"></i></button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e(contentResourceListUrl($role)) ?>" class="resources-inline-form" onsubmit="return confirm('Archive this resource?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="form_action" value="archive">
                        <input type="hidden" name="resource_id" value="<?= (int) $resource['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-secondary" title="Archive"><i class="fa-solid fa-box-archive"></i></button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php renderResourcesPreviewData($resources); ?>
    <?php
}

/**
 * @param array<int, array<string, mixed>> $resources
 */
function renderResourcesPreviewData(array $resources): void
{
    $payload = [];
    foreach ($resources as $resource) {
        if (!empty($resource['thumbnail_path'])) {
            continue;
        }
        $type = $resource['resource_type'] ?? 'deck';
        $entry = [
            'id' => (int) $resource['id'],
            'type' => $type,
        ];
        if ($type === 'deck') {
            $decoded = json_decode((string) ($resource['content'] ?? ''), true);
            $entry['deck'] = is_array($decoded) ? $decoded : json_decode(defaultDeckContent(), true);
        } else {
            $entry['html'] = sanitizeHtml((string) ($resource['content'] ?? ''));
        }
        $payload[] = $entry;
    }
    if ($payload === []) {
        return;
    }
    ?>
    <script type="application/json" id="resourcesPreviewData"><?= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <?php
}

function renderResourcesPreviewAssets(): void
{
    ?>
    <script src="https://cdn.jsdelivr.net/npm/konva@9.3.6/konva.min.js"></script>
    <?php
}

function renderResourceAttachModal(array $teacherClasses, string $postUrl): void
{
    if ($teacherClasses === []) {
        return;
    }
    ?>
    <dialog class="library-attach-dialog" id="resourceAttachDialog">
        <form method="post" action="<?= url($postUrl) ?>" class="library-attach-form">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="attach_to_class">
            <input type="hidden" name="resource_id" id="resourceAttachId" value="">
            <header class="library-attach-header">
                <h2>Add to class</h2>
                <p id="resourceAttachTitle" class="text-muted"></p>
                <button type="button" class="library-attach-close" data-close-resource-attach aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            </header>
            <div class="form-group">
                <label>Class</label>
                <select name="class_id" id="resourceAttachClass" class="form-control" required>
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
                <select name="section_id" id="resourceAttachSection" class="form-control">
                    <option value="">Unassigned</option>
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary">Add to class</button>
                <button type="button" class="btn btn-secondary" data-close-resource-attach>Cancel</button>
            </div>
        </form>
    </dialog>
    <?php
}

function renderResourceShareModal(array $subjects, string $postUrl): void
{
    ?>
    <dialog class="library-attach-dialog" id="resourceShareDialog">
        <form method="post" action="<?= url($postUrl) ?>" class="library-attach-form">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="share_to_library">
            <input type="hidden" name="resource_id" id="resourceShareId" value="">
            <header class="library-attach-header">
                <h2>Share to Virtual Library</h2>
                <p id="resourceShareTitle" class="text-muted"></p>
                <p class="text-muted text-sm">Your resource will be submitted for admin approval before it appears publicly.</p>
                <button type="button" class="library-attach-close" data-close-resource-share aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            </header>
            <div class="form-group">
                <label>Description (optional)</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Brief summary for the library listing"></textarea>
            </div>
            <div class="form-group">
                <label>Resource kind</label>
                <select name="resource_kind" class="form-control">
                    <?php foreach (LibraryResourceRepository::RESOURCE_KINDS as $kind): ?>
                        <option value="<?= e($kind) ?>"<?= $kind === 'lesson' ? ' selected' : '' ?>><?= e(resourceKindLabel($kind)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Subject (optional)</label>
                <select name="subject_id" class="form-control">
                    <option value="">None</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= (int) $subject['id'] ?>"><?= e($subject['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Audience</label>
                <select name="audience" class="form-control">
                    <option value="all">Teachers &amp; students</option>
                    <option value="teachers">Teachers only</option>
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary">Submit for approval</button>
                <button type="button" class="btn btn-secondary" data-close-resource-share>Cancel</button>
            </div>
        </form>
    </dialog>
    <?php
}

function renderCourseResourcePickerModal(array $resources, int $classId, array $sections): void
{
    if ($resources === []) {
        return;
    }
    ?>
    <dialog class="library-attach-dialog" id="courseResourcePickerDialog">
        <form method="post" class="library-attach-form">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="attach_from_resources">
            <input type="hidden" name="class_id" value="<?= (int) $classId ?>">
            <header class="library-attach-header">
                <h2>Add from Resources</h2>
                <p class="text-muted">Choose a slide deck or document from your Resources hub.</p>
                <button type="button" class="library-attach-close" data-close-course-resource aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            </header>
            <div class="form-group">
                <label>Resource</label>
                <select name="resource_id" class="form-control" required>
                    <option value="">Choose a resource…</option>
                    <?php foreach ($resources as $resource): ?>
                        <option value="<?= (int) $resource['id'] ?>">
                            <?= e($resource['title']) ?> (<?= e(contentResourceTypeLabel($resource['resource_type'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!empty($sections)): ?>
            <div class="form-group">
                <label>Lesson section</label>
                <select name="section_id" class="form-control">
                    <?= courseSectionOptions($sections, null) ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="actions">
                <button type="submit" class="btn btn-primary">Add to course</button>
                <a href="<?= url('teacher/resources.php') ?>" class="btn btn-secondary">Manage resources</a>
                <button type="button" class="btn btn-secondary" data-close-course-resource>Cancel</button>
            </div>
        </form>
    </dialog>
    <?php
}

function resourcesPageScripts(): void
{
    ?>
    <script>
    (function () {
        function bindAttachDialog(dialogId, btnAttr, idInput, titleEl, closeAttr) {
            var dialog = document.getElementById(dialogId);
            if (!dialog) return;
            var idInputEl = document.getElementById(idInput);
            var titleElNode = document.getElementById(titleEl);
            var classSelect = dialog.querySelector('select[name="class_id"]');
            var sectionSelect = dialog.querySelector('select[name="section_id"]');

            function syncSections() {
                if (!classSelect || !sectionSelect) return;
                var opt = classSelect.options[classSelect.selectedIndex];
                sectionSelect.innerHTML = '<option value="">Unassigned</option>';
                if (!opt || !opt.dataset.sections) return;
                try {
                    JSON.parse(opt.dataset.sections).forEach(function (section) {
                        var o = document.createElement('option');
                        o.value = section.id;
                        o.textContent = section.title;
                        sectionSelect.appendChild(o);
                    });
                } catch (e) {}
            }

            if (classSelect) classSelect.addEventListener('change', syncSections);

            document.querySelectorAll('[' + btnAttr + ']').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (idInputEl) idInputEl.value = btn.getAttribute(btnAttr);
                    if (titleElNode) titleElNode.textContent = btn.getAttribute('data-resource-title') || '';
                    if (classSelect) classSelect.selectedIndex = 0;
                    syncSections();
                    dialog.showModal();
                });
            });

            dialog.querySelectorAll('[' + closeAttr + ']').forEach(function (btn) {
                btn.addEventListener('click', function () { dialog.close(); });
            });
        }

        bindAttachDialog('resourceAttachDialog', 'data-resource-attach', 'resourceAttachId', 'resourceAttachTitle', 'data-close-resource-attach');

        var shareDialog = document.getElementById('resourceShareDialog');
        if (shareDialog) {
            var shareId = document.getElementById('resourceShareId');
            var shareTitle = document.getElementById('resourceShareTitle');
            document.querySelectorAll('[data-resource-share]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (shareId) shareId.value = btn.getAttribute('data-resource-share');
                    if (shareTitle) shareTitle.textContent = btn.getAttribute('data-resource-title') || '';
                    shareDialog.showModal();
                });
            });
            shareDialog.querySelectorAll('[data-close-resource-share]').forEach(function (btn) {
                btn.addEventListener('click', function () { shareDialog.close(); });
            });
        }
    })();
    </script>
    <?php
}
