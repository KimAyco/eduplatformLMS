<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('school_admin');
requireSchoolActive();

$sid = schoolId();
$action = $_GET['action'] ?? '';
$editId = (int) ($_GET['id'] ?? 0);
$errors = [];
$catalogSubjects = SubjectRepository::forSchool($sid);
$selectedSubjectIds = array_map('intval', $_POST['subject_ids'] ?? []);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['form_action'] ?? '';
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);
    $selectedSubjectIds = array_map('intval', $_POST['subject_ids'] ?? []);

    if ($firstName === '' || $lastName === '') {
        $errors[] = 'First and last name are required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if (empty($catalogSubjects) && in_array($postAction, ['add', 'edit'], true)) {
        $errors[] = 'Add subjects to the catalog before assigning teachers.';
    } elseif (in_array($postAction, ['add', 'edit'], true) && empty($selectedSubjectIds)) {
        $errors[] = 'Select at least one subject this teacher can teach.';
    }

    if ($postAction === 'add') {
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if (empty($errors)) {
            $check = db()->prepare('SELECT id FROM users WHERE school_id = ? AND email = ?');
            $check->execute([$sid, $email]);
            if ($check->fetch()) {
                $errors[] = 'A teacher with this email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = db()->prepare('INSERT INTO users (school_id, email, password_hash, role, first_name, last_name) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$sid, $email, $hash, 'teacher', $firstName, $lastName]);
                $newId = (int) db()->lastInsertId();
                SubjectRepository::syncTeacherSubjects($newId, $sid, $selectedSubjectIds);
                flash('success', 'Teacher added successfully.');
                redirect('school/teachers.php');
            }
        }
    } elseif ($postAction === 'edit' && $userId) {
        $check = db()->prepare('SELECT id FROM users WHERE id = ? AND school_id = ? AND role = ?');
        $check->execute([$userId, $sid, 'teacher']);
        if (!$check->fetch()) {
            $errors[] = 'Teacher not found.';
        } else {
            $dup = db()->prepare('SELECT id FROM users WHERE school_id = ? AND email = ? AND id != ?');
            $dup->execute([$sid, $email, $userId]);
            if ($dup->fetch()) {
                $errors[] = 'Another user with this email already exists.';
            } elseif (empty($errors)) {
                if ($password !== '') {
                    if (strlen($password) < 8) {
                        $errors[] = 'Password must be at least 8 characters.';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = db()->prepare('UPDATE users SET first_name=?, last_name=?, email=?, password_hash=? WHERE id=? AND school_id=?');
                        $stmt->execute([$firstName, $lastName, $email, $hash, $userId, $sid]);
                    }
                } else {
                    $stmt = db()->prepare('UPDATE users SET first_name=?, last_name=?, email=? WHERE id=? AND school_id=?');
                    $stmt->execute([$firstName, $lastName, $email, $userId, $sid]);
                }
                if (empty($errors)) {
                    SubjectRepository::syncTeacherSubjects($userId, $sid, $selectedSubjectIds);
                    flash('success', 'Teacher updated.');
                    redirect('school/teachers.php');
                }
            }
        }
    } elseif ($postAction === 'toggle' && $userId) {
        $stmt = db()->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id=? AND school_id=? AND role='teacher'");
        $stmt->execute([$userId, $sid]);
        flash('success', 'Teacher status updated.');
        redirect('school/teachers.php');
    } elseif ($postAction === 'delete' && $userId) {
        $target = UserRepository::getByRole($userId, $sid, 'teacher');
        if ($target && !empty($target['profile_image'])) {
            deleteUpload($target['profile_image']);
        }
        $stmt = db()->prepare('DELETE FROM users WHERE id=? AND school_id=? AND role=?');
        $stmt->execute([$userId, $sid, 'teacher']);
        flash('success', 'Teacher removed.');
        redirect('school/teachers.php');
    }
}

$editUser = null;
$editSubjectIds = [];
if ($action === 'edit' && $editId) {
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND school_id = ? AND role = ?');
    $stmt->execute([$editId, $sid, 'teacher']);
    $editUser = $stmt->fetch();
    if ($editUser) {
        $editSubjectIds = array_column(SubjectRepository::forTeacher($editId, $sid), 'id');
    }
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$list = UserRepository::paginatedByRole($sid, 'teacher', $page);
$teachers = $list['items'];
$pager = paginate($list['total'], $list['page'], $list['per_page']);

$teacherSubjectMap = [];
if (!empty($teachers)) {
    $ids = array_column($teachers, 'id');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT ts.teacher_id, s.name FROM teacher_subjects ts
        INNER JOIN subjects s ON s.id = ts.subject_id
        WHERE ts.teacher_id IN ($ph) AND s.school_id = ?
        ORDER BY s.name");
    $stmt->execute([...$ids, $sid]);
    foreach ($stmt->fetchAll() as $row) {
        $teacherSubjectMap[$row['teacher_id']][] = $row['name'];
    }
}

$pageTitle = 'Teachers';
$pageHeading = 'Teachers';
$pageSubtitle = 'Assign teachable subjects so they can be placed in class groups.';
$pageActionUrl = ($action === 'add' || $editUser) ? null : 'school/teachers.php?action=add';
$pageActionLabel = ($action === 'add' || $editUser) ? null : 'Add Teacher';
$pageActionIcon = 'fa-user-plus';
$activeMenu = 'teachers';
$menuItems = schoolAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'school/dashboard.php'],
    ['label' => 'Teachers', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<?php if ($action === 'add' || $editUser): ?>
<div class="admin-form-card">
<div class="panel">
    <h2><?= $editUser ? 'Edit Teacher' : 'Add Teacher' ?></h2>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <?php if (empty($catalogSubjects)): ?>
        <div class="alert alert-error">No subjects in catalog yet. <a href="<?= url('school/subjects.php?action=add') ?>">Add subjects first</a>.</div>
    <?php endif; ?>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="<?= $editUser ? 'edit' : 'add' ?>">
        <?php if ($editUser): ?><input type="hidden" name="user_id" value="<?= $editUser['id'] ?>"><?php endif; ?>
        <div class="form-row">
            <div class="form-group"><label>First Name</label><input name="first_name" class="form-control" value="<?= e($editUser['first_name'] ?? $_POST['first_name'] ?? '') ?>" required></div>
            <div class="form-group"><label>Last Name</label><input name="last_name" class="form-control" value="<?= e($editUser['last_name'] ?? $_POST['last_name'] ?? '') ?>" required></div>
        </div>
        <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?= e($editUser['email'] ?? $_POST['email'] ?? '') ?>" required></div>
        <div class="form-group">
            <label>Password <?= $editUser ? '(leave blank to keep current)' : '' ?></label>
            <input type="password" name="password" class="form-control" <?= $editUser ? '' : 'required minlength="8"' ?>>
        </div>
        <div class="form-group">
            <label>Teachable subjects</label>
            <?php if (empty($catalogSubjects)): ?>
                <p class="text-muted">Create subjects in the catalog before assigning teachers.</p>
            <?php else: ?>
                <div class="subject-checkbox-grid">
                    <?php
                    $checkedIds = !empty($_POST) ? $selectedSubjectIds : $editSubjectIds;
                    foreach ($catalogSubjects as $subject):
                    ?>
                        <label class="subject-checkbox">
                            <input type="checkbox" name="subject_ids[]" value="<?= (int) $subject['id'] ?>"
                                <?= in_array((int) $subject['id'], $checkedIds, true) ? 'checked' : '' ?>>
                            <span><?= e($subject['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary" <?= empty($catalogSubjects) ? 'disabled' : '' ?>><?= $editUser ? 'Update' : 'Add' ?> Teacher</button>
            <a href="<?= url('school/teachers.php') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
</div>
<?php endif; ?>

<?php if (empty($teachers) && $action !== 'add' && !$editUser): ?>
<?= adminEmptyState('fa-chalkboard-user', 'No teachers yet', 'Add teachers and select which subjects each can teach.', 'school/teachers.php?action=add', 'Add teacher') ?>
<?php elseif ($action !== 'add' && !$editUser): ?>
<div class="admin-table-card">
<div class="table-wrap">
    <table class="admin-data-table admin-data-table--people">
        <thead>
            <tr>
                <th class="col-name">Name</th>
                <th class="col-email">Email</th>
                <th class="col-tags">Subjects</th>
                <th class="col-status">Status</th>
                <th class="col-actions"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($teachers as $t): ?>
            <tr class="table-row-link" data-href="<?= e(url('school/teacher.php?id=' . $t['id'])) ?>" tabindex="0">
                <td class="col-name"><?= tableUserCell($t['first_name'], $t['last_name'], $t) ?></td>
                <td class="col-email" title="<?= e($t['email']) ?>"><?= e($t['email']) ?></td>
                <td class="col-tags">
                    <?php if (!empty($teacherSubjectMap[$t['id']])): ?>
                        <div class="subject-tags">
                            <?php foreach ($teacherSubjectMap[$t['id']] as $tag): ?>
                                <span class="subject-tag"><?= e($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <span class="text-muted">None</span>
                    <?php endif; ?>
                </td>
                <td class="col-status"><span class="badge badge-<?= $t['status'] === 'active' ? 'active' : 'suspended' ?>"><?= e(ucfirst($t['status'])) ?></span></td>
                <td class="actions">
                    <div class="table-row-actions">
                        <a href="<?= url('school/teachers.php?action=edit&id=' . $t['id']) ?>" class="table-action-btn" title="Edit" aria-label="Edit <?= e($t['first_name'] . ' ' . $t['last_name']) ?>"><i class="fa-solid fa-pen"></i></a>
                        <form method="post">
                            <?= csrfField() ?>
                            <input type="hidden" name="form_action" value="toggle">
                            <input type="hidden" name="user_id" value="<?= (int) $t['id'] ?>">
                            <button type="submit" class="table-action-btn" title="<?= $t['status'] === 'active' ? 'Deactivate' : 'Activate' ?>" aria-label="<?= $t['status'] === 'active' ? 'Deactivate' : 'Activate' ?> <?= e($t['first_name']) ?>">
                                <i class="fa-solid <?= $t['status'] === 'active' ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                            </button>
                        </form>
                        <form method="post" data-confirm="Delete this teacher?">
                            <?= csrfField() ?>
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="user_id" value="<?= (int) $t['id'] ?>">
                            <button type="submit" class="table-action-btn table-action-btn--danger" title="Delete" aria-label="Delete <?= e($t['first_name']) ?>"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>
<?= renderPagination($pager, 'school/teachers.php') ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
