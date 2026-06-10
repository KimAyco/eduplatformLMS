<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('school_admin');
requireSchoolActive();

$sid = schoolId();
$programId = (int) ($_GET['id'] ?? 0);
$editMode = isset($_GET['mode']) && $_GET['mode'] === 'edit';
$modeQuery = $editMode ? '&mode=edit' : '';
$program = ProgramRepository::curriculumTree($programId, $sid);

if (!$program) {
    flash('error', 'Program not found.');
    redirect('school/programs.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['form_action'] ?? '';

    if ($action === 'add_level') {
        $name = trim($_POST['level_name'] ?? '');
        if ($name === '') {
            flash('error', 'Level name is required.');
        } elseif (ProgramRepository::addLevel($programId, $sid, $name)) {
            flash('success', 'Level added.');
        } else {
            flash('error', 'Could not add level.');
        }
        redirect('school/program.php?id=' . $programId . '&mode=edit');
    }

    if ($action === 'edit_level') {
        $levelId = (int) ($_POST['level_id'] ?? 0);
        $name = trim($_POST['level_name'] ?? '');
        if ($name === '' || !ProgramRepository::updateLevel($levelId, $sid, $name)) {
            flash('error', 'Could not update level.');
        } else {
            flash('success', 'Level updated.');
        }
        redirect('school/program.php?id=' . $programId . '&mode=edit#level-' . $levelId);
    }

    if ($action === 'delete_level') {
        $levelId = (int) ($_POST['level_id'] ?? 0);
        $result = ProgramRepository::deleteLevel($levelId, $sid);
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Level deleted.' : ($result['error'] ?? 'Could not delete level.'));
        redirect('school/program.php?id=' . $programId . '&mode=edit');
    }

    if ($action === 'add_term') {
        $levelId = (int) ($_POST['level_id'] ?? 0);
        $name = trim($_POST['term_name'] ?? '');
        if ($name === '') {
            flash('error', 'Term name is required.');
        } elseif (ProgramRepository::addTerm($levelId, $sid, $name)) {
            flash('success', 'Term added.');
        } else {
            flash('error', 'Could not add term.');
        }
        redirect('school/program.php?id=' . $programId . '&mode=edit#level-' . $levelId);
    }

    if ($action === 'edit_term') {
        $termId = (int) ($_POST['term_id'] ?? 0);
        $name = trim($_POST['term_name'] ?? '');
        $term = ProgramRepository::getTerm($termId, $sid);
        if ($name === '' || !ProgramRepository::updateTerm($termId, $sid, $name)) {
            flash('error', 'Could not update term.');
        } else {
            flash('success', 'Term updated.');
        }
        $levelId = (int) ($term['program_level_id'] ?? 0);
        redirect('school/program.php?id=' . $programId . '&mode=edit' . ($levelId ? '#level-' . $levelId : '') . '#term-' . $termId);
    }

    if ($action === 'delete_term') {
        $termId = (int) ($_POST['term_id'] ?? 0);
        $term = ProgramRepository::getTerm($termId, $sid);
        if (ProgramRepository::deleteTerm($termId, $sid)) {
            flash('success', 'Term deleted.');
        } else {
            flash('error', 'Could not delete term.');
        }
        $levelId = (int) ($term['program_level_id'] ?? 0);
        redirect('school/program.php?id=' . $programId . '&mode=edit' . ($levelId ? '#level-' . $levelId : ''));
    }

    if ($action === 'save_term_subjects') {
        $termId = (int) ($_POST['term_id'] ?? 0);
        $subjectIds = array_map('intval', (array) ($_POST['subject_ids'] ?? []));
        $term = ProgramRepository::getTerm($termId, $sid);
        if (ProgramRepository::syncTermSubjects($termId, $sid, $subjectIds)) {
            flash('success', 'Term subjects saved.');
        } else {
            flash('error', 'Could not save term subjects.');
        }
        $levelId = (int) ($term['program_level_id'] ?? 0);
        redirect('school/program.php?id=' . $programId . '&mode=edit' . ($levelId ? '#level-' . $levelId : '') . '#term-' . $termId);
    }
}

$program = ProgramRepository::curriculumTree($programId, $sid);
$allSubjects = SubjectRepository::forSchool($sid);

$termCount = 0;
$uniqueSubjectIds = [];
foreach ($program['levels'] as $level) {
    $termCount += count($level['terms']);
    foreach ($level['terms'] as $term) {
        foreach ($term['subjects'] as $subject) {
            $uniqueSubjectIds[(int) $subject['id']] = true;
        }
    }
}

$pageTitle = $program['name'] . ' — Curriculum';
$pageHeading = $program['name'];
$pageSubtitle = $editMode
    ? 'Edit levels, terms, and subjects for this program.'
    : 'Curriculum overview';
$pageActionUrl = $editMode ? 'school/programs.php?action=edit&id=' . $programId : null;
$pageActionLabel = $editMode ? 'Edit program info' : null;
$pageActionIcon = $editMode ? 'fa-pen' : null;
$activeMenu = 'programs';
$menuItems = schoolAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'school/dashboard.php'],
    ['label' => 'Programs', 'url' => 'school/programs.php'],
    ['label' => $program['name'], 'url' => ''],
];
$pageScripts = ['assets/js/program-curriculum.js'];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<?php if ($editMode && empty($allSubjects)): ?>
<div class="alert alert-warning program-curriculum-alert">
    <span>Add subjects to your <a href="<?= url('school/subjects.php?action=add') ?>">subject catalog</a> before assigning them to terms.</span>
    <a href="<?= url('school/subjects.php?action=add') ?>" class="btn btn-sm btn-primary">Add subjects</a>
</div>
<?php endif; ?>

<div class="program-curriculum-app<?= $editMode ? ' is-edit-mode' : ' is-preview-mode' ?>" data-program-curriculum>
    <div class="program-curriculum-toolbar">
        <div class="program-toolbar-meta">
            <?php if (!empty($program['code'])): ?>
            <span class="program-code-badge"><?= e($program['code']) ?></span>
            <?php endif; ?>
            <?php if (!$editMode && !empty($program['description'])): ?>
            <p class="program-toolbar-desc"><?= e($program['description']) ?></p>
            <?php elseif ($editMode): ?>
            <p class="program-toolbar-desc program-toolbar-desc--muted">Link a class group to a level to offer these subjects to students.</p>
            <?php endif; ?>
            <p class="program-toolbar-stats">
                <span><strong><?= count($program['levels']) ?></strong> levels</span>
                <span class="program-toolbar-stats-sep" aria-hidden="true">·</span>
                <span><strong><?= $termCount ?></strong> terms</span>
                <span class="program-toolbar-stats-sep" aria-hidden="true">·</span>
                <span><strong><?= count($uniqueSubjectIds) ?></strong> subjects</span>
            </p>
        </div>
        <div class="program-toolbar-actions">
            <div class="program-mode-toggle" role="group" aria-label="Curriculum view mode">
                <a href="<?= url('school/program.php?id=' . $programId) ?>"
                    class="program-mode-btn<?= !$editMode ? ' is-active' : '' ?>"
                    <?= !$editMode ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid fa-eye" aria-hidden="true"></i> Preview
                </a>
                <a href="<?= url('school/program.php?id=' . $programId . '&mode=edit') ?>"
                    class="program-mode-btn<?= $editMode ? ' is-active' : '' ?>"
                    <?= $editMode ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit
                </a>
            </div>
            <?php if (!$editMode && !empty($program['levels'])): ?>
            <a href="<?= url('school/program.php?id=' . $programId . '&mode=edit') ?>" class="btn btn-primary btn-sm program-toolbar-edit-btn">
                <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit curriculum
            </a>
            <?php elseif ($editMode): ?>
            <a href="<?= url('school/program.php?id=' . $programId) ?>" class="btn btn-secondary btn-sm program-toolbar-edit-btn">
                <i class="fa-solid fa-eye" aria-hidden="true"></i> Preview
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($program['levels'])): ?>
    <div class="admin-form-card program-curriculum-empty">
        <div class="panel">
            <?php if ($editMode): ?>
            <?= adminEmptyState('fa-layer-group', 'Start with a level', 'Levels are stages like Year 1, Grade 7, or Kindergarten. Each level can have its own terms (semesters, quarters, etc.).', null, null) ?>
            <form method="post" class="program-add-form program-add-form--centered">
                <?= csrfField() ?>
                <input type="hidden" name="form_action" value="add_level">
                <div class="form-group">
                    <label for="firstLevelName">First level name</label>
                    <input type="text" id="firstLevelName" name="level_name" class="form-control" placeholder="e.g. Year 1" required>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add level</button>
            </form>
            <?php else: ?>
            <?= adminEmptyState('fa-layer-group', 'No curriculum yet', 'This program has no levels defined.', 'school/program.php?id=' . $programId . '&mode=edit', 'Build curriculum') ?>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="program-curriculum-layout">
        <aside class="program-curriculum-sidebar">
            <div class="program-curriculum-sidebar-head">
                <h3>Levels</h3>
                <p>Stages in this program</p>
            </div>
            <nav class="program-level-nav" role="tablist" aria-label="Program levels">
                <?php foreach ($program['levels'] as $level):
                    $levelTermCount = count($level['terms']);
                    $levelSubjectCount = 0;
                    foreach ($level['terms'] as $t) {
                        $levelSubjectCount += count($t['subjects']);
                    }
                    $levelKey = 'level-' . (int) $level['id'];
                ?>
                <button type="button"
                    class="program-level-nav-item"
                    role="tab"
                    data-level-tab="<?= e($levelKey) ?>"
                    aria-selected="false">
                    <span class="program-level-nav-label"><?= e($level['name']) ?></span>
                    <span class="program-level-nav-meta">
                        <?= $levelTermCount ?> term<?= $levelTermCount !== 1 ? 's' : '' ?>
                        · <?= $levelSubjectCount ?> subject<?= $levelSubjectCount !== 1 ? 's' : '' ?>
                    </span>
                </button>
                <?php endforeach; ?>
            </nav>
            <div data-curriculum-edit>
            <button type="button" class="program-add-trigger" data-add-toggle="addLevelForm" aria-expanded="false">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Add level
            </button>
            <form method="post" id="addLevelForm" class="program-add-form" hidden>
                <?= csrfField() ?>
                <input type="hidden" name="form_action" value="add_level">
                <div class="form-group">
                    <label>Level name</label>
                    <input type="text" name="level_name" class="form-control" placeholder="e.g. Year 2" required>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Add</button>
            </form>
            </div>
        </aside>

        <div class="program-curriculum-main">
            <?php foreach ($program['levels'] as $levelIndex => $level):
                $levelKey = 'level-' . (int) $level['id'];
            ?>
            <section class="program-level-panel"
                id="<?= e($levelKey) ?>"
                data-level-panel="<?= e($levelKey) ?>"
                role="tabpanel"
                <?= $levelIndex > 0 ? 'hidden' : '' ?>>
                <header class="program-level-panel-head" data-curriculum-edit>
                    <div class="program-level-edit-bar">
                        <h2 class="program-level-title"><?= e($level['name']) ?></h2>
                        <div class="program-level-edit-actions">
                            <div class="program-rename-wrap" data-rename-wrap>
                                <button type="button" class="btn btn-secondary btn-sm" data-rename-toggle>
                                    <i class="fa-solid fa-pen"></i> Rename
                                </button>
                                <form method="post" class="program-rename-form program-rename-form--inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="form_action" value="edit_level">
                                    <input type="hidden" name="level_id" value="<?= (int) $level['id'] ?>">
                                    <input type="text" name="level_name" class="form-control form-control-sm" value="<?= e($level['name']) ?>" required>
                                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                    <button type="button" class="btn btn-secondary btn-sm" data-rename-cancel>Cancel</button>
                                </form>
                            </div>
                            <form method="post" data-confirm="Delete “<?= e($level['name']) ?>” and all its terms? This cannot be undone.">
                                <?= csrfField() ?>
                                <input type="hidden" name="form_action" value="delete_level">
                                <input type="hidden" name="level_id" value="<?= (int) $level['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i> Delete level</button>
                            </form>
                        </div>
                    </div>
                </header>

                <div class="program-level-toolbar" data-curriculum-edit>
                    <button type="button" class="program-add-trigger program-add-trigger--inline" data-add-toggle="addTerm-<?= (int) $level['id'] ?>" aria-expanded="false">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Add term
                    </button>
                    <form method="post" id="addTerm-<?= (int) $level['id'] ?>" class="program-add-form program-add-form--inline" hidden>
                        <?= csrfField() ?>
                        <input type="hidden" name="form_action" value="add_term">
                        <input type="hidden" name="level_id" value="<?= (int) $level['id'] ?>">
                        <input type="text" name="term_name" class="form-control" placeholder="e.g. 1st Semester, Quarter 1" required>
                        <button type="submit" class="btn btn-primary btn-sm">Add term</button>
                    </form>
                </div>

                <?php if (empty($level['terms'])): ?>
                <div class="program-term-empty">
                    <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                    <p><?= $editMode ? 'No terms yet. Add a term to start assigning subjects for ' . e($level['name']) . '.' : 'No terms defined for ' . e($level['name']) . '.' ?></p>
                </div>
                <?php else: ?>
                <div class="program-terms-list">
                    <?php foreach ($level['terms'] as $termIndex => $term):
                        $subjectCount = count($term['subjects']);
                        $termOpen = $termIndex === 0;
                    ?>
                    <article class="program-term-card<?= $termOpen ? ' is-open' : '' ?>"
                        id="term-<?= (int) $term['id'] ?>"
                        data-program-term="<?= (int) $term['id'] ?>">
                        <div class="program-term-card-head">
                            <button type="button" class="program-term-toggle" data-term-toggle aria-expanded="<?= $termOpen ? 'true' : 'false' ?>">
                                <span class="program-term-order"><?= $termIndex + 1 ?></span>
                                <span class="program-term-heading">
                                    <strong><?= e($term['name']) ?></strong>
                                    <small><?= $subjectCount ?> subject<?= $subjectCount !== 1 ? 's' : '' ?></small>
                                </span>
                                <i class="fa-solid fa-chevron-down program-term-chevron" aria-hidden="true"></i>
                            </button>
                            <div class="program-term-actions" data-curriculum-edit>
                                <div class="program-rename-wrap program-rename-wrap--compact" data-rename-wrap>
                                    <button type="button" class="btn btn-icon btn-sm" data-rename-toggle title="Rename term" aria-label="Rename term">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="post" class="program-rename-form program-rename-form--dropdown">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="form_action" value="edit_term">
                                        <input type="hidden" name="term_id" value="<?= (int) $term['id'] ?>">
                                        <input type="text" name="term_name" class="form-control form-control-sm" value="<?= e($term['name']) ?>" required>
                                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                        <button type="button" class="btn btn-secondary btn-sm" data-rename-cancel>Cancel</button>
                                    </form>
                                </div>
                                <form method="post" data-confirm="Delete “<?= e($term['name']) ?>”?">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="form_action" value="delete_term">
                                    <input type="hidden" name="term_id" value="<?= (int) $term['id'] ?>">
                                    <button type="submit" class="btn btn-icon btn-sm" title="Delete term" aria-label="Delete term">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="program-term-preview-body" data-curriculum-preview>
                            <?php if (empty($term['subjects'])): ?>
                            <p class="program-term-preview-empty">No subjects assigned.</p>
                            <?php else: ?>
                            <table class="program-subject-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Code</th>
                                        <th scope="col">Subject</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($term['subjects'] as $subject): ?>
                                    <tr>
                                        <td class="program-subject-table-code"><?= e($subject['name']) ?></td>
                                        <td><?= e($subject['description'] ?: '—') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>

                        <div class="program-term-body" data-curriculum-edit>
                            <?php if (empty($allSubjects)): ?>
                            <p class="text-muted">Add subjects to your catalog first.</p>
                            <?php else: ?>
                            <form method="post" class="program-subject-form" data-subject-picker>
                                <?= csrfField() ?>
                                <input type="hidden" name="form_action" value="save_term_subjects">
                                <input type="hidden" name="term_id" value="<?= (int) $term['id'] ?>">

                                <div class="program-subject-toolbar">
                                    <div class="program-subject-search-wrap">
                                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                        <input type="search" class="form-control" data-subject-search placeholder="Search by code or title…" autocomplete="off">
                                    </div>
                                    <div class="program-subject-filter" role="group" aria-label="Filter subjects">
                                        <button type="button" class="program-subject-filter-btn is-active" data-subject-filter="all">All</button>
                                        <button type="button" class="program-subject-filter-btn" data-subject-filter="selected">Selected</button>
                                        <button type="button" class="program-subject-filter-btn" data-subject-filter="unselected">Available</button>
                                    </div>
                                </div>

                                <div class="program-subject-summary">
                                    <span class="program-subject-summary-count">
                                        <strong data-subject-count><?= $subjectCount ?></strong> of <?= count($allSubjects) ?> selected
                                    </span>
                                    <div class="program-subject-toolbar-actions">
                                        <button type="button" class="btn btn-secondary btn-sm" data-subject-select-visible>Select visible</button>
                                        <button type="button" class="btn btn-secondary btn-sm" data-subject-clear>Clear all</button>
                                    </div>
                                </div>

                                <div class="program-subject-catalog" data-subject-grid>
                                    <div class="program-subject-catalog-head" aria-hidden="true">
                                        <span class="program-subject-catalog-col program-subject-catalog-col--check"></span>
                                        <span class="program-subject-catalog-col program-subject-catalog-col--code">Code</span>
                                        <span class="program-subject-catalog-col program-subject-catalog-col--title">Subject</span>
                                    </div>
                                    <?php
                                    $selected = array_map(static fn ($s) => (int) $s['id'], $term['subjects']);
                                    foreach ($allSubjects as $subject):
                                        $isSelected = in_array((int) $subject['id'], $selected, true);
                                    ?>
                                    <label class="program-subject-row<?= $isSelected ? ' is-selected' : '' ?>"
                                        data-subject-row="<?= e(strtolower($subject['name'] . ' ' . ($subject['description'] ?? ''))) ?>">
                                        <input type="checkbox" class="program-subject-row-check" name="subject_ids[]" value="<?= (int) $subject['id'] ?>"
                                            data-label="<?= e($subject['name']) ?>"
                                            <?= $isSelected ? 'checked' : '' ?>>
                                        <span class="program-subject-row-code"><?= e($subject['name']) ?></span>
                                        <span class="program-subject-row-title"><?= e($subject['description'] ?: '—') ?></span>
                                        <span class="program-subject-row-icon" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>

                                <footer class="program-subject-footer">
                                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save subjects for <?= e($term['name']) ?></button>
                                </footer>
                            </form>
                            <?php endif; ?>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
