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
    $code = trim($_POST['code'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $programId = (int) ($_POST['program_id'] ?? 0);

    if ($name === '') {
        $errors[] = 'Program name is required.';
    }

    if ($postAction === 'add' && empty($errors)) {
        $dup = db()->prepare('SELECT id FROM programs WHERE school_id = ? AND name = ?');
        $dup->execute([$sid, $name]);
        if ($dup->fetch()) {
            $errors[] = 'A program with this name already exists.';
        } else {
            $newId = ProgramRepository::create($sid, $name, $code ?: null, $description ?: null);
            flash('success', 'Program created.');
            redirect('school/program.php?id=' . $newId);
        }
    } elseif ($postAction === 'edit' && $programId && empty($errors)) {
        $dup = db()->prepare('SELECT id FROM programs WHERE school_id = ? AND name = ? AND id != ?');
        $dup->execute([$sid, $name, $programId]);
        if ($dup->fetch()) {
            $errors[] = 'A program with this name already exists.';
        } elseif (ProgramRepository::update($programId, $sid, $name, $code ?: null, $description ?: null)) {
            flash('success', 'Program updated.');
            redirect('school/programs.php');
        } else {
            $errors[] = 'Program not found.';
        }
    } elseif ($postAction === 'delete' && $programId) {
        $result = ProgramRepository::delete($programId, $sid);
        if ($result['ok']) {
            flash('success', 'Program deleted.');
        } else {
            flash('error', $result['error']);
        }
        redirect('school/programs.php');
    }
}

$editProgram = null;
if ($action === 'edit' && $editId) {
    $editProgram = ProgramRepository::get($editId, $sid);
}

$programs = ProgramRepository::withCounts($sid);

$pageTitle = 'Programs';
$pageHeading = 'Programs';
$pageSubtitle = 'Build curriculum templates with levels, terms, and required subjects.';
$pageActionUrl = ($action === 'add' || $editProgram) ? null : 'school/programs.php?action=add';
$pageActionLabel = ($action === 'add' || $editProgram) ? null : 'Add Program';
$pageActionIcon = 'fa-plus';
$activeMenu = 'programs';
$menuItems = schoolAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'school/dashboard.php'],
    ['label' => 'Programs', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<?php if ($action === 'add' || $editProgram): ?>
<div class="admin-form-card">
<div class="panel">
    <h2><?= $editProgram ? 'Edit Program' : 'Add Program' ?></h2>
    <p class="text-muted mb-1">Create a program template such as BSIT, Junior High, or 6-Year Elementary. After saving, open the program to add levels, terms, and subjects.</p>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="<?= $editProgram ? 'edit' : 'add' ?>">
        <?php if ($editProgram): ?><input type="hidden" name="program_id" value="<?= (int) $editProgram['id'] ?>"><?php endif; ?>
        <div class="form-row">
            <div class="form-group"><label>Program Name</label><input name="name" class="form-control" value="<?= e($editProgram['name'] ?? '') ?>" required placeholder="e.g. BSIT"></div>
            <div class="form-group"><label>Code</label><input name="code" class="form-control" value="<?= e($editProgram['code'] ?? '') ?>" placeholder="e.g. BSIT"></div>
        </div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3"><?= e($editProgram['description'] ?? '') ?></textarea></div>
        <div class="actions">
            <button type="submit" class="btn btn-primary"><?= $editProgram ? 'Update' : 'Create' ?> Program</button>
            <a href="<?= url('school/programs.php') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
</div>
<?php endif; ?>

<?php if (empty($programs) && $action !== 'add' && !$editProgram): ?>
<?= adminEmptyState('fa-sitemap', 'No programs yet', 'Create a program template, then define levels, terms, and subjects.', 'school/programs.php?action=add', 'Add your first program') ?>
<?php elseif ($action !== 'add' && !$editProgram): ?>
<div class="admin-table-card">
<div class="table-wrap">
    <table class="admin-data-table admin-data-table--roster">
        <thead>
            <tr>
                <th class="col-name">Program</th>
                <th class="col-code">Code</th>
                <th class="col-num" title="Levels">Lv</th>
                <th class="col-num" title="Enrolled students">Std</th>
                <th class="col-num" title="Class groups">Grp</th>
                <th class="col-actions"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($programs as $p): ?>
            <tr>
                <td class="col-name">
                    <div class="cell-stack">
                        <a href="<?= url('school/program.php?id=' . $p['id']) ?>" class="cell-stack__title" title="<?= e($p['name']) ?>"><?= e($p['name']) ?></a>
                        <?php if ($p['description']): ?>
                        <span class="cell-stack__sub text-muted" title="<?= e($p['description']) ?>"><?= e($p['description']) ?></span>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="col-code">
                    <?php if ($p['code']): ?>
                    <code class="subject-code-chip"><?= e($p['code']) ?></code>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="col-num"><?= (int) $p['level_count'] ?></td>
                <td class="col-num"><?= (int) $p['enrolled_count'] ?></td>
                <td class="col-num"><?= (int) $p['group_count'] ?></td>
                <td class="actions">
                    <div class="table-row-actions">
                        <a href="<?= url('school/program.php?id=' . $p['id']) ?>" class="table-action-btn" title="Curriculum" aria-label="Curriculum for <?= e($p['name']) ?>"><i class="fa-solid fa-sitemap"></i></a>
                        <a href="<?= url('school/programs.php?action=edit&id=' . $p['id']) ?>" class="table-action-btn" title="Edit" aria-label="Edit <?= e($p['name']) ?>"><i class="fa-solid fa-pen"></i></a>
                        <?php if ((int) $p['group_count'] === 0 && (int) $p['enrolled_count'] === 0): ?>
                        <form method="post" data-confirm="Delete this program and all its curriculum?">
                            <?= csrfField() ?>
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="program_id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="table-action-btn table-action-btn--danger" title="Delete" aria-label="Delete <?= e($p['name']) ?>"><i class="fa-solid fa-trash"></i></button>
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
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
