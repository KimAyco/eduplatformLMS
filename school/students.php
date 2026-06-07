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
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($firstName === '' || $lastName === '') $errors[] = 'First and last name are required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

    if ($postAction === 'add') {
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if (empty($errors)) {
            $check = db()->prepare('SELECT id FROM users WHERE school_id = ? AND email = ?');
            $check->execute([$sid, $email]);
            if ($check->fetch()) {
                $errors[] = 'A student with this email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = db()->prepare('INSERT INTO users (school_id, email, password_hash, role, first_name, last_name) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$sid, $email, $hash, 'student', $firstName, $lastName]);
                flash('success', 'Student added successfully.');
                redirect('school/students.php');
            }
        }
    } elseif ($postAction === 'edit' && $userId) {
        $check = db()->prepare('SELECT id FROM users WHERE id = ? AND school_id = ? AND role = ?');
        $check->execute([$userId, $sid, 'student']);
        if (!$check->fetch()) {
            $errors[] = 'Student not found.';
        } else {
            $dup = db()->prepare('SELECT id FROM users WHERE school_id = ? AND email = ? AND id != ?');
            $dup->execute([$sid, $email, $userId]);
            if ($dup->fetch()) {
                $errors[] = 'Another user with this email already exists.';
            } elseif (empty($errors)) {
                if ($password !== '') {
                    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
                    else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = db()->prepare('UPDATE users SET first_name=?, last_name=?, email=?, password_hash=? WHERE id=? AND school_id=?');
                        $stmt->execute([$firstName, $lastName, $email, $hash, $userId, $sid]);
                    }
                } else {
                    $stmt = db()->prepare('UPDATE users SET first_name=?, last_name=?, email=? WHERE id=? AND school_id=?');
                    $stmt->execute([$firstName, $lastName, $email, $userId, $sid]);
                }
                if (empty($errors)) {
                    flash('success', 'Student updated.');
                    redirect('school/students.php');
                }
            }
        }
    } elseif ($postAction === 'toggle' && $userId) {
        $stmt = db()->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id=? AND school_id=? AND role='student'");
        $stmt->execute([$userId, $sid]);
        flash('success', 'Student status updated.');
        redirect('school/students.php');
    } elseif ($postAction === 'delete' && $userId) {
        $target = UserRepository::getByRole($userId, $sid, 'student');
        if ($target && !empty($target['profile_image'])) {
            deleteUpload($target['profile_image']);
        }
        $stmt = db()->prepare('DELETE FROM users WHERE id=? AND school_id=? AND role=?');
        $stmt->execute([$userId, $sid, 'student']);
        flash('success', 'Student removed.');
        redirect('school/students.php');
    }
}

$editUser = null;
if ($action === 'edit' && $editId) {
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND school_id = ? AND role = ?');
    $stmt->execute([$editId, $sid, 'student']);
    $editUser = $stmt->fetch();
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$list = UserRepository::paginatedByRole($sid, 'student', $page);
$students = $list['items'];
$pager = paginate($list['total'], $list['page'], $list['per_page']);

$pageTitle = 'Students';
$pageHeading = 'Students';
$pageSubtitle = 'Manage student accounts and enroll them in class groups.';
$pageActionUrl = ($action === 'add' || $editUser) ? null : 'school/students.php?action=add';
$pageActionLabel = ($action === 'add' || $editUser) ? null : 'Add Student';
$pageActionIcon = 'fa-user-plus';
$activeMenu = 'students';
$menuItems = schoolAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'school/dashboard.php'],
    ['label' => 'Students', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<?php if ($action === 'add' || $editUser): ?>
<div class="admin-form-card">
<div class="panel">
    <h2><?= $editUser ? 'Edit Student' : 'Add Student' ?></h2>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
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
        <div class="actions">
            <button type="submit" class="btn btn-primary"><?= $editUser ? 'Update' : 'Add' ?> Student</button>
            <a href="<?= url('school/students.php') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
</div>
<?php elseif (empty($students)): ?>
<?= adminEmptyState('fa-user-graduate', 'No students yet', 'Add students, then enroll them in a class group.', 'school/students.php?action=add', 'Add student') ?>
<?php else: ?>
<div class="admin-table-card">
<div class="table-wrap">
    <table>
        <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($students as $s): ?>
            <tr class="table-row-link" data-href="<?= e(url('school/student.php?id=' . $s['id'])) ?>" tabindex="0">
                <td><?= tableUserCell($s['first_name'], $s['last_name'], $s) ?></td>
                <td><?= e($s['email']) ?></td>
                <td><span class="badge badge-<?= $s['status'] === 'active' ? 'active' : 'suspended' ?>"><?= e(ucfirst($s['status'])) ?></span></td>
                <td class="actions">
                    <a href="<?= url('school/students.php?action=edit&id=' . $s['id']) ?>" class="btn btn-sm btn-secondary">Edit</a>
                    <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="form_action" value="toggle"><input type="hidden" name="user_id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-secondary"><?= $s['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button></form>
                    <form method="post" style="display:inline" data-confirm="Delete this student?"><?= csrfField() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="user_id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>
<?= renderPagination($pager, 'school/students.php') ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
