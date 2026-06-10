<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('school_admin');
requireSchoolActive();

$sid = schoolId();
$action = $_GET['action'] ?? '';
$editId = (int) ($_GET['id'] ?? 0);
$errors = [];

$programs = ProgramRepository::forSchool($sid);
$programLevels = [];
foreach ($programs as $program) {
    foreach (ProgramRepository::levelsForProgram((int) $program['id'], $sid) as $level) {
        $programLevels[] = [
            'program_id' => (int) $program['id'],
            'program_name' => $program['name'],
            'level_id' => (int) $level['id'],
            'level_name' => $level['name'],
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['form_action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $year = trim($_POST['academic_year'] ?? '');
    $groupId = (int) ($_POST['group_id'] ?? 0);
    $programLevelId = (int) ($_POST['program_level_id'] ?? 0);
    $programId = (int) ($_POST['program_id'] ?? 0);
    $applyCurriculum = !empty($_POST['apply_curriculum']);

    if ($programLevelId > 0) {
        $level = ProgramRepository::getLevel($programLevelId, $sid);
        if (!$level) {
            $errors[] = 'Invalid program level selected.';
        } else {
            $programId = (int) $level['program_id'];
        }
    } else {
        $programId = 0;
        $programLevelId = 0;
    }

    if ($name === '') {
        $errors[] = 'Group name is required.';
    }

    if ($postAction === 'add' && empty($errors)) {
        $newId = ClassGroupRepository::create(
            $sid,
            $name,
            $description ?: null,
            $year ?: null,
            $programId ?: null,
            $programLevelId ?: null
        );
        if ($applyCurriculum && $programLevelId > 0) {
            $added = ProgramRepository::applyCurriculumForLevel($newId, $programLevelId, $sid);
            flash('success', 'Class group created with ' . $added . ' curriculum subject(s).');
        } else {
            flash('success', 'Class group created.');
        }
        redirect('school/class-group.php?id=' . $newId);
    } elseif ($postAction === 'edit' && $groupId && empty($errors)) {
        ClassGroupRepository::update(
            $groupId,
            $sid,
            $name,
            $description ?: null,
            $year ?: null,
            $programId ?: null,
            $programLevelId ?: null
        );
        if ($applyCurriculum && $programLevelId > 0) {
            $added = ProgramRepository::applyCurriculumForLevel($groupId, $programLevelId, $sid);
            flash('success', 'Class group updated. Added ' . $added . ' curriculum subject(s).');
        } else {
            flash('success', 'Class group updated.');
        }
        redirect('school/class-group.php?id=' . $groupId);
    } elseif ($postAction === 'delete' && $groupId) {
        $stmt = db()->prepare('DELETE FROM class_groups WHERE id=? AND school_id=?');
        $stmt->execute([$groupId, $sid]);
        flash('success', 'Class group deleted.');
        redirect('school/class-groups.php');
    }
}

$editGroup = null;
if ($action === 'edit' && $editId) {
    $editGroup = ClassGroupRepository::get($editId, $sid);
}

$groups = ClassGroupRepository::withCounts($sid);

$pageTitle = 'Class Groups';
$pageHeading = 'Class Groups';
$pageSubtitle = 'Each group contains subjects from your catalog with assigned teachers.';
$pageActionUrl = ($action === 'add' || $editGroup) ? null : 'school/class-groups.php?action=add';
$pageActionLabel = ($action === 'add' || $editGroup) ? null : 'Add Class Group';
$pageActionIcon = 'fa-plus';
$activeMenu = 'class_groups';
$menuItems = schoolAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'school/dashboard.php'],
    ['label' => 'Class Groups', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<?php if ($action === 'add' || $editGroup): ?>
<div class="admin-form-card">
<div class="panel">
    <h2><?= $editGroup ? 'Edit Class Group' : 'Add Class Group' ?></h2>
    <p class="text-muted mb-1">Link a program level to auto-add curriculum subjects, then assign teachers on the group page.</p>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="<?= $editGroup ? 'edit' : 'add' ?>">
        <?php if ($editGroup): ?><input type="hidden" name="group_id" value="<?= $editGroup['id'] ?>"><?php endif; ?>
        <div class="form-row">
            <div class="form-group"><label>Group Name</label><input name="name" class="form-control" value="<?= e($editGroup['name'] ?? '') ?>" required placeholder="e.g. BSIT 1A"></div>
            <div class="form-group"><label>Academic Year</label><input name="academic_year" class="form-control" value="<?= e($editGroup['academic_year'] ?? '') ?>" placeholder="e.g. 2025-2026"></div>
        </div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control"><?= e($editGroup['description'] ?? '') ?></textarea></div>
        <?php if (!empty($programs)): ?>
        <div class="form-row">
            <div class="form-group">
                <label>Program</label>
                <select name="program_id" id="groupProgramSelect" class="form-control">
                    <option value="">— None —</option>
                    <?php foreach ($programs as $program): ?>
                    <option value="<?= (int) $program['id'] ?>" <?= (int) ($editGroup['program_id'] ?? 0) === (int) $program['id'] ? 'selected' : '' ?>><?= e($program['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Program level</label>
                <select name="program_level_id" id="groupLevelSelect" class="form-control">
                    <option value="">— None —</option>
                    <?php foreach ($programLevels as $row): ?>
                    <option value="<?= (int) $row['level_id'] ?>" data-program-id="<?= (int) $row['program_id'] ?>" <?= (int) ($editGroup['program_level_id'] ?? 0) === (int) $row['level_id'] ? 'selected' : '' ?>><?= e($row['program_name'] . ' · ' . $row['level_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <label class="program-checkbox-label">
            <input type="checkbox" name="apply_curriculum" value="1" checked>
            Add all subjects for the selected level from the program curriculum
        </label>
        <?php endif; ?>
        <div class="actions">
            <button type="submit" class="btn btn-primary"><?= $editGroup ? 'Update' : 'Create' ?> Group</button>
            <?php if ($editGroup): ?>
                <a href="<?= url('school/class-group.php?id=' . $editGroup['id']) ?>" class="btn btn-secondary">Cancel</a>
            <?php else: ?>
                <a href="<?= url('school/class-groups.php') ?>" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>
</div>
<script>
(function () {
    var programSelect = document.getElementById('groupProgramSelect');
    var levelSelect = document.getElementById('groupLevelSelect');
    if (!programSelect || !levelSelect) return;

    function filterLevels() {
        var programId = programSelect.value;
        Array.prototype.forEach.call(levelSelect.options, function (opt, index) {
            if (index === 0) {
                opt.hidden = false;
                return;
            }
            var match = !programId || opt.getAttribute('data-program-id') === programId;
            opt.hidden = !match;
            if (!match && opt.selected) {
                opt.selected = false;
                levelSelect.selectedIndex = 0;
            }
        });
    }

    programSelect.addEventListener('change', filterLevels);
    filterLevels();
})();
</script>
<?php elseif (empty($groups)): ?>
<?php
$cta = SubjectRepository::count($sid) === 0
    ? ['school/subjects.php?action=add', 'Add subjects first']
    : ['school/class-groups.php?action=add', 'Create class group'];
echo adminEmptyState('fa-layer-group', 'No class groups yet', 'Create a class group after setting up your subject catalog and teachers.', $cta[0], $cta[1]);
?>
<?php else: ?>
<div class="admin-table-card">
<div class="table-wrap">
    <table class="admin-data-table admin-data-table--roster">
        <thead>
            <tr>
                <th class="col-name">Group</th>
                <th class="col-program">Program</th>
                <th class="col-num" title="Subjects">Subj</th>
                <th class="col-num" title="Students">Std</th>
                <th class="col-status">Status</th>
                <th class="col-actions"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($groups as $g): ?>
            <tr>
                <td class="col-name">
                    <a href="<?= url('school/class-group.php?id=' . $g['id']) ?>" class="table-group-link">
                        <?= tableGroupCell($g['name'], $g['academic_year'] ?: null) ?>
                    </a>
                </td>
                <td class="col-program text-muted" title="<?= !empty($g['program_name']) ? e($g['program_name'] . (!empty($g['level_name']) ? ' · ' . $g['level_name'] : '')) : '' ?>">
                    <?php if (!empty($g['program_name'])): ?>
                        <?= e($g['program_name']) ?><?= !empty($g['level_name']) ? ' · ' . e($g['level_name']) : '' ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td class="col-num"><?= (int) $g['class_count'] ?></td>
                <td class="col-num"><?= (int) $g['student_count'] ?></td>
                <td class="col-status">
                    <?php if ((int) $g['unassigned_count'] > 0): ?>
                        <span class="badge badge-pending"><?= (int) $g['unassigned_count'] ?> open</span>
                    <?php else: ?>
                        <span class="badge badge-active">Ready</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <div class="table-row-actions">
                        <a href="<?= url('school/class-group.php?id=' . $g['id']) ?>" class="table-action-btn table-action-btn--primary" title="Manage" aria-label="Manage <?= e($g['name']) ?>"><i class="fa-solid fa-folder-open"></i></a>
                        <a href="<?= url('school/class-groups.php?action=edit&id=' . $g['id']) ?>" class="table-action-btn" title="Edit" aria-label="Edit <?= e($g['name']) ?>"><i class="fa-solid fa-pen"></i></a>
                        <form method="post" data-confirm="Delete this group and all its subject assignments?">
                            <?= csrfField() ?>
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="group_id" value="<?= (int) $g['id'] ?>">
                            <button type="submit" class="table-action-btn table-action-btn--danger" title="Delete" aria-label="Delete <?= e($g['name']) ?>"><i class="fa-solid fa-trash"></i></button>
                        </form>
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
