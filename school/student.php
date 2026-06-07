<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('school_admin');
requireSchoolActive();

$sid = schoolId();
$studentId = (int) ($_GET['id'] ?? 0);
$student = UserRepository::getByRole($studentId, $sid, 'student');

if (!$student) {
    flash('error', 'Student not found.');
    redirect('school/students.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['form_action'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($userId !== $studentId) {
        flash('error', 'Invalid request.');
        redirect('school/student.php?id=' . $studentId);
    }

    if ($postAction === 'toggle') {
        db()->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id=? AND school_id=? AND role='student'")
            ->execute([$userId, $sid]);
        flash('success', 'Student status updated.');
        redirect('school/student.php?id=' . $studentId);
    }

    if ($postAction === 'delete') {
        if (!empty($student['profile_image'])) {
            deleteUpload($student['profile_image']);
        }
        db()->prepare('DELETE FROM users WHERE id=? AND school_id=? AND role=?')
            ->execute([$userId, $sid, 'student']);
        flash('success', 'Student removed.');
        redirect('school/students.php');
    }
}

$errors = handleUserProfilePhotoPost($student, 'school/student.php?id=' . $studentId);
$student = UserRepository::getByRole($studentId, $sid, 'student') ?? $student;

$groups = ClassGroupRepository::groupsForStudent($studentId, $sid);
$classes = ClassRepository::forStudent($studentId, $sid);
$fullName = trim($student['first_name'] . ' ' . $student['last_name']);

$pageTitle = $fullName;
$pageHeading = $fullName;
$pageSubtitle = 'Student profile';
$activeMenu = 'students';
$menuItems = schoolAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'school/dashboard.php'],
    ['label' => 'Students', 'url' => 'school/students.php'],
    ['label' => $fullName, 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-error alert-icon"><i class="fa-solid fa-circle-exclamation"></i><span><?= e($err) ?></span></div>
<?php endforeach; ?>

<div class="panel user-profile-header">
    <div class="panel-header">
        <div class="user-profile-intro">
            <?= userAvatarHtml($student, 'user-profile-avatar') ?>
            <div>
                <div class="user-profile-title-row">
                    <h2><?= e($fullName) ?></h2>
                    <span class="badge badge-<?= $student['status'] === 'active' ? 'active' : 'suspended' ?>"><?= e(ucfirst($student['status'])) ?></span>
                </div>
                <p class="text-muted user-profile-role"><i class="fa-solid fa-user-graduate"></i> Student</p>
            </div>
        </div>
        <div class="actions">
            <a href="<?= url('school/students.php?action=edit&id=' . $studentId) ?>" class="btn btn-secondary btn-sm">Edit</a>
            <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="form_action" value="toggle"><input type="hidden" name="user_id" value="<?= $studentId ?>"><button class="btn btn-secondary btn-sm"><?= $student['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button></form>
            <form method="post" style="display:inline" data-confirm="Delete this student?"><?= csrfField() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="user_id" value="<?= $studentId ?>"><button class="btn btn-danger btn-sm">Delete</button></form>
            <a href="<?= url('school/students.php') ?>" class="btn btn-secondary btn-sm">Back to list</a>
        </div>
    </div>
</div>

<div class="user-profile-grid">
    <?php renderUserProfilePhotoPanel($student, 'Student profile photo', 'This photo appears on the student profile and anywhere this student is listed in your school.') ?>

    <div class="panel user-profile-panel">
        <h3>Account information</h3>
        <dl class="user-profile-details">
            <div>
                <dt>Email</dt>
                <dd><a href="mailto:<?= e($student['email']) ?>"><?= e($student['email']) ?></a></dd>
            </div>
            <div>
                <dt>First name</dt>
                <dd><?= e($student['first_name']) ?></dd>
            </div>
            <div>
                <dt>Last name</dt>
                <dd><?= e($student['last_name']) ?></dd>
            </div>
            <div>
                <dt>Status</dt>
                <dd><?= e(ucfirst($student['status'])) ?></dd>
            </div>
            <div>
                <dt>Member since</dt>
                <dd><?= formatDate($student['created_at'], 'M j, Y') ?></dd>
            </div>
            <div>
                <dt>Last updated</dt>
                <dd><?= formatDate($student['updated_at'], 'M j, Y g:i A') ?></dd>
            </div>
        </dl>
    </div>

    <div class="panel user-profile-panel">
        <h3>Class groups</h3>
        <?php if (empty($groups)): ?>
            <p class="text-muted mb-0">Not enrolled in any class group yet. Enroll this student from a <a href="<?= url('school/class-groups.php') ?>">class group</a>.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Group</th><th>Academic year</th><th>Enrolled</th></tr></thead>
                    <tbody>
                    <?php foreach ($groups as $group): ?>
                        <tr>
                            <td><a href="<?= url('school/class-group.php?id=' . (int) $group['id'] . '&tab=students') ?>"><?= e($group['name']) ?></a></td>
                            <td><?= e($group['academic_year'] ?? '—') ?></td>
                            <td><?= formatDate($group['enrolled_at'], 'M j, Y') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="panel user-profile-panel">
    <h3>Subjects &amp; classes</h3>
    <?php if (empty($classes)): ?>
        <p class="text-muted mb-0">No subjects available yet. Subjects appear once the student is in a class group with offerings.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Subject</th><th>Class group</th><th>Academic year</th></tr></thead>
                <tbody>
                <?php foreach ($classes as $class): ?>
                    <tr>
                        <td><?= e($class['name']) ?></td>
                        <td><?= e($class['group_name']) ?></td>
                        <td><?= e($class['group_academic_year'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
