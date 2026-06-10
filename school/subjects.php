<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('school_admin');
requireSchoolActive();

$sid = schoolId();
$action = $_GET['action'] ?? '';
$editId = (int) ($_GET['id'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['form_action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $subjectId = (int) ($_POST['subject_id'] ?? 0);

    if ($name === '') {
        $errors[] = 'Subject name is required.';
    }

    $schemeInput = [];
    $categories = $_POST['grading_category'] ?? [];
    $labels = $_POST['grading_label'] ?? [];
    $weights = $_POST['grading_weight'] ?? [];
    if (is_array($categories)) {
        foreach ($categories as $i => $category) {
            $schemeInput[] = [
                'category' => $category,
                'label' => $labels[$i] ?? '',
                'weight_percent' => $weights[$i] ?? 0,
            ];
        }
    }
    $schemeParsed = parseSubjectGradingSchemeInput($schemeInput);
    if (!$schemeParsed['ok']) {
        $errors[] = $schemeParsed['error'];
    }

    if ($postAction === 'add' && empty($errors)) {
        $dup = db()->prepare('SELECT id FROM subjects WHERE school_id = ? AND name = ?');
        $dup->execute([$sid, $name]);
        if ($dup->fetch()) {
            $errors[] = 'A subject with this name already exists.';
        } else {
            $stmt = db()->prepare('INSERT INTO subjects (school_id, name, description) VALUES (?, ?, ?)');
            $stmt->execute([$sid, $name, $description ?: null]);
            $newId = (int) db()->lastInsertId();
            if ($schemeParsed['rows'] !== []) {
                GradebookRepository::saveSubjectScheme($newId, $sid, $schemeParsed['rows']);
            }
            flash('success', 'Subject added to catalog.');
            redirect('school/subjects.php');
        }
    } elseif ($postAction === 'edit' && $subjectId && empty($errors)) {
        $dup = db()->prepare('SELECT id FROM subjects WHERE school_id = ? AND name = ? AND id != ?');
        $dup->execute([$sid, $name, $subjectId]);
        if ($dup->fetch()) {
            $errors[] = 'A subject with this name already exists.';
        } else {
            $stmt = db()->prepare('UPDATE subjects SET name=?, description=? WHERE id=? AND school_id=?');
            $stmt->execute([$name, $description ?: null, $subjectId, $sid]);
            GradebookRepository::saveSubjectScheme($subjectId, $sid, $schemeParsed['rows']);
            flash('success', 'Subject updated.');
            redirect('school/subjects.php');
        }
    } elseif ($postAction === 'delete' && $subjectId) {
        $usage = db()->prepare('SELECT COUNT(*) FROM classes WHERE subject_id = ?');
        $usage->execute([$subjectId]);
        if ((int) $usage->fetchColumn() > 0) {
            flash('error', 'Cannot delete a subject that is used in a class group. Remove it from groups first.');
        } else {
            db()->prepare('DELETE FROM subjects WHERE id=? AND school_id=?')->execute([$subjectId, $sid]);
            flash('success', 'Subject deleted.');
        }
        redirect('school/subjects.php');
    }
}

$editSubject = null;
$schemeComponents = [];
if ($action === 'edit' && $editId) {
    $editSubject = SubjectRepository::get($editId, $sid);
    if ($editSubject) {
        $schemeComponents = GradebookRepository::componentsForSubject($editId, $sid);
    }
}

$subjects = SubjectRepository::withUsageCounts($sid);
$schemeStats = [];
foreach ($subjects as $s) {
    $schemeStats[(int) $s['id']] = [
        'count' => count(GradebookRepository::componentsForSubject((int) $s['id'], $sid)),
        'weight' => GradebookRepository::totalWeightForSubject((int) $s['id'], $sid),
    ];
}

$pageTitle = 'Subjects';
$pageHeading = 'Subjects';
$pageSubtitle = 'Create subjects first, then add teachers and assign them to class groups.';
$pageActionUrl = ($action === 'add' || $editSubject) ? null : 'school/subjects.php?action=add';
$pageActionLabel = ($action === 'add' || $editSubject) ? null : 'Add Subject';
$pageActionIcon = 'fa-plus';
$activeMenu = 'subjects';
$menuItems = schoolAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'school/dashboard.php'],
    ['label' => 'Subjects', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<?php if ($action === 'add' || $editSubject): ?>
<div class="admin-form-card admin-form-card--subject">
<div class="panel">
    <h2><?= $editSubject ? 'Edit Subject' : 'Add Subject' ?></h2>
    <p class="text-muted mb-1">Subject code and title, then define grading components (weights must total 100%).</p>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" id="subjectForm" class="subject-form">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="<?= $editSubject ? 'edit' : 'add' ?>">
        <?php if ($editSubject): ?><input type="hidden" name="subject_id" value="<?= $editSubject['id'] ?>"><?php endif; ?>
        <div class="form-row subject-form__basics">
            <div class="form-group"><label>Subject code</label><input name="name" class="form-control" value="<?= e($editSubject['name'] ?? '') ?>" required placeholder="e.g. ENG101"></div>
            <div class="form-group"><label>Title</label><input name="description" class="form-control" value="<?= e($editSubject['description'] ?? '') ?>" placeholder="e.g. Communication Skills 1"></div>
        </div>

        <fieldset class="grading-scheme-fieldset grading-scheme-fieldset--featured">
            <legend class="grading-scheme-legend">
                <span class="grading-scheme-legend__icon" aria-hidden="true"><i class="fa-solid fa-chart-pie"></i></span>
                Grading scheme
            </legend>

            <div class="grading-scheme-fieldset__banner">
                <div class="grading-scheme-fieldset__banner-text">
                    <strong>How this subject is graded</strong>
                    <p>Add components (quizzes, exams, assignments) and set weights. Teachers sync scores from courses — weights must total <strong>100%</strong>.</p>
                </div>
                <div class="grading-scheme-total-badge" id="gradingWeightBadge" aria-live="polite">
                    <span class="grading-scheme-total-badge__label">Weight total</span>
                    <span class="grading-scheme-total-badge__value"><strong id="gradingWeightTotal">0.00</strong>%</span>
                </div>
            </div>

            <div class="grading-scheme-preview grading-scheme-preview--compact">
                <div class="gb-weight-bar gb-weight-bar--admin">
                    <div id="gradingWeightBar" class="gb-weight-bar__track" aria-hidden="true"></div>
                </div>
            </div>

            <div class="grading-scheme-toolbar">
                <span class="grading-scheme-toolbar__label"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add component:</span>
                <button type="button" class="gb-chip-btn gb-chip-btn--quiz" data-add-grading-row data-category="quiz"><i class="fa-solid fa-circle-question"></i> Quiz</button>
                <button type="button" class="gb-chip-btn gb-chip-btn--exam" data-add-grading-row data-category="exam"><i class="fa-solid fa-file-pen"></i> Exam</button>
                <button type="button" class="gb-chip-btn gb-chip-btn--assignment" data-add-grading-row data-category="assignment"><i class="fa-solid fa-pen-to-square"></i> Assignment</button>
                <button type="button" class="gb-chip-btn gb-chip-btn--participation" data-add-grading-row data-category="participation"><i class="fa-solid fa-hand"></i> Part.</button>
                <button type="button" class="gb-chip-btn gb-chip-btn--project" data-add-grading-row data-category="project"><i class="fa-solid fa-folder-open"></i> Project</button>
                <button type="button" class="gb-chip-btn gb-chip-btn--other" data-add-grading-row data-category="other"><i class="fa-solid fa-tag"></i> Other</button>
            </div>

            <div class="grading-scheme-table-head" aria-hidden="true">
                <span>Type</span><span>Component name</span><span>Weight</span><span></span>
            </div>
            <div id="gradingSchemeRows" class="grading-scheme-rows"
                data-categories="<?= e(json_encode(GradebookRepository::CATEGORIES, JSON_UNESCAPED_UNICODE)) ?>"
                data-initial="<?= e(json_encode(array_map(static fn ($c) => [
                    'category' => $c['category'],
                    'label' => $c['label'],
                    'weight_percent' => $c['weight_percent'],
                ], $schemeComponents), JSON_UNESCAPED_UNICODE)) ?>"></div>
        </fieldset>

        <div class="actions">
            <button type="submit" class="btn btn-primary"><?= $editSubject ? 'Update' : 'Add' ?> Subject</button>
            <a href="<?= url('school/subjects.php') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
</div>
<script src="<?= url('assets/js/subject-grading-scheme.js') ?>"></script>
<?php endif; ?>

<?php if (empty($subjects) && $action !== 'add' && !$editSubject): ?>
<?= adminEmptyState('fa-book', 'No subjects yet', 'Add all subjects your school offers before setting up class groups.', 'school/subjects.php?action=add', 'Add your first subject') ?>
<?php elseif ($action !== 'add' && !$editSubject): ?>
<div class="admin-table-card admin-table-card--subjects">
    <div class="admin-table-card-head">
        <div class="admin-table-card-head-text">
            <h2>Subject catalog</h2>
            <p><?= count($subjects) ?> subject<?= count($subjects) !== 1 ? 's' : '' ?> in your school</p>
        </div>
        <label class="admin-table-search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" class="form-control" placeholder="Search subjects…" data-subjects-search autocomplete="off">
        </label>
    </div>
    <div class="table-wrap">
        <table class="admin-data-table">
            <thead>
                <tr>
                    <th class="col-subject">Code</th>
                    <th class="col-desc">Title</th>
                    <th class="col-scheme">Grading</th>
                    <th class="col-num">Groups</th>
                    <th class="col-actions"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($subjects as $s):
                $stats = $schemeStats[(int) $s['id']] ?? ['count' => 0, 'weight' => 0];
                $searchText = strtolower($s['name'] . ' ' . ($s['description'] ?? ''));
            ?>
                <tr data-subjects-row="<?= e($searchText) ?>">
                    <td class="col-subject">
                        <a href="<?= url('school/subjects.php?action=edit&id=' . $s['id']) ?>" class="subject-code-link">
                            <code class="subject-code-chip"><?= e($s['name']) ?></code>
                        </a>
                    </td>
                    <td class="col-desc">
                        <span class="subject-title<?= empty($s['description']) ? ' subject-title--empty' : '' ?>"><?= e($s['description'] ?: '—') ?></span>
                    </td>
                    <td class="col-scheme">
                        <?php if ($stats['count'] > 0): ?>
                        <span class="subject-status subject-status--ok" title="<?= e($stats['weight']) ?>% total weight">
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                            <?= (int) $stats['count'] ?> parts · <?= e($stats['weight']) ?>%
                        </span>
                        <?php else: ?>
                        <span class="subject-status subject-status--muted">
                            <i class="fa-regular fa-circle" aria-hidden="true"></i>
                            Not set up
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="col-num">
                        <?php if ((int) $s['usage_count'] > 0): ?>
                        <span class="subject-usage-badge is-active"><?= (int) $s['usage_count'] ?></span>
                        <?php else: ?>
                        <span class="subject-usage-badge">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <div class="table-row-actions">
                            <a href="<?= url('school/subjects.php?action=edit&id=' . $s['id']) ?>" class="table-action-btn" title="Edit subject" aria-label="Edit <?= e($s['name']) ?>">
                                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                            </a>
                            <?php if ((int) $s['usage_count'] === 0): ?>
                            <form method="post" data-confirm="Delete this subject?">
                                <?= csrfField() ?>
                                <input type="hidden" name="form_action" value="delete">
                                <input type="hidden" name="subject_id" value="<?= (int) $s['id'] ?>">
                                <button type="submit" class="table-action-btn table-action-btn--danger" title="Delete subject" aria-label="Delete <?= e($s['name']) ?>">
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
(function () {
    var input = document.querySelector('[data-subjects-search]');
    if (!input) return;
    input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        document.querySelectorAll('[data-subjects-row]').forEach(function (row) {
            var text = row.getAttribute('data-subjects-row') || '';
            row.hidden = q !== '' && text.indexOf(q) === -1;
        });
    });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
