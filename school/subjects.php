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
<div class="admin-form-card">
<div class="panel">
    <h2><?= $editSubject ? 'Edit Subject' : 'Add Subject' ?></h2>
    <p class="text-muted mb-1">Add all subjects your school offers (e.g. ENG101, NSTP1). Configure the grading scheme so teachers can sync quizzes and assignments to each student's grade card.</p>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" id="subjectForm">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="<?= $editSubject ? 'edit' : 'add' ?>">
        <?php if ($editSubject): ?><input type="hidden" name="subject_id" value="<?= $editSubject['id'] ?>"><?php endif; ?>
        <div class="form-row">
            <div class="form-group"><label>Subject Name</label><input name="name" class="form-control" value="<?= e($editSubject['name'] ?? '') ?>" required placeholder="e.g. ENG101"></div>
        </div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control"><?= e($editSubject['description'] ?? '') ?></textarea></div>

        <fieldset class="grading-scheme-fieldset">
            <legend>Grading scheme</legend>
            <p class="text-muted grading-scheme-help">Build how the final grade is calculated. Each row is one component teachers can score. Weights must total <strong>100%</strong>.</p>

            <div class="grading-scheme-preview">
                <div class="gb-weight-bar gb-weight-bar--admin">
                    <div id="gradingWeightBar" class="gb-weight-bar__track" aria-hidden="true"></div>
                </div>
                <p class="grading-weight-total-line">Total: <strong id="gradingWeightTotal">0.00</strong>% <span class="grading-weight-hint">(must equal 100%)</span></p>
            </div>

            <div class="grading-scheme-toolbar">
                <span class="grading-scheme-toolbar__label">Quick add:</span>
                <button type="button" class="gb-chip-btn gb-chip-btn--quiz" data-add-grading-row data-category="quiz"><i class="fa-solid fa-circle-question"></i> Quiz</button>
                <button type="button" class="gb-chip-btn gb-chip-btn--exam" data-add-grading-row data-category="exam"><i class="fa-solid fa-file-pen"></i> Exam</button>
                <button type="button" class="gb-chip-btn gb-chip-btn--assignment" data-add-grading-row data-category="assignment"><i class="fa-solid fa-pen-to-square"></i> Assignment</button>
                <button type="button" class="gb-chip-btn gb-chip-btn--participation" data-add-grading-row data-category="participation"><i class="fa-solid fa-hand"></i> Participation</button>
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
<div class="admin-table-card">
<div class="table-wrap">
    <table>
        <thead><tr><th>Subject</th><th>Description</th><th>Grading scheme</th><th>Used in groups</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($subjects as $s):
            $stats = $schemeStats[(int) $s['id']] ?? ['count' => 0, 'weight' => 0];
        ?>
            <tr>
                <td><?= tableSubjectCell($s['name']) ?></td>
                <td class="text-muted"><?= e($s['description'] ?: '—') ?></td>
                <td>
                    <?php if ($stats['count'] > 0): ?>
                        <span class="badge badge-submitted"><?= (int) $stats['count'] ?> components</span>
                        <span class="text-muted"><?= e($stats['weight']) ?>%</span>
                    <?php else: ?>
                        <span class="text-muted">Not configured</span>
                    <?php endif; ?>
                </td>
                <td><?= (int) $s['usage_count'] ?></td>
                <td class="actions">
                    <a href="<?= url('school/subjects.php?action=edit&id=' . $s['id']) ?>" class="btn btn-sm btn-secondary">Edit</a>
                    <?php if ((int) $s['usage_count'] === 0): ?>
                    <form method="post" style="display:inline" data-confirm="Delete this subject?"><?= csrfField() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="subject_id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
