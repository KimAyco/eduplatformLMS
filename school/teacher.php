<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('school_admin');
requireSchoolActive();

$sid = schoolId();
$teacherId = (int) ($_GET['id'] ?? 0);
$teacher = UserRepository::getByRole($teacherId, $sid, 'teacher');

if (!$teacher) {
    flash('error', 'Teacher not found.');
    redirect('school/teachers.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['form_action'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($userId !== $teacherId) {
        flash('error', 'Invalid request.');
        redirect('school/teacher.php?id=' . $teacherId);
    }

    if ($postAction === 'toggle') {
        db()->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id=? AND school_id=? AND role='teacher'")
            ->execute([$userId, $sid]);
        flash('success', 'Teacher status updated.');
        redirect('school/teacher.php?id=' . $teacherId);
    }

    if ($postAction === 'delete') {
        if (!empty($teacher['profile_image'])) {
            deleteUpload($teacher['profile_image']);
        }
        db()->prepare('DELETE FROM users WHERE id=? AND school_id=? AND role=?')
            ->execute([$userId, $sid, 'teacher']);
        flash('success', 'Teacher removed.');
        redirect('school/teachers.php');
    }
}

$errors = handleUserProfilePhotoPost($teacher, 'school/teacher.php?id=' . $teacherId);
$teacher = UserRepository::getByRole($teacherId, $sid, 'teacher') ?? $teacher;

$subjects = SubjectRepository::forTeacher($teacherId, $sid);
$classes = ClassRepository::forTeacher($teacherId, $sid);
$fullName = trim($teacher['first_name'] . ' ' . $teacher['last_name']);

$pageTitle = $fullName;
$pageHeading = $fullName;
$pageSubtitle = 'Teacher profile';
$activeMenu = 'teachers';
$menuItems = schoolAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'school/dashboard.php'],
    ['label' => 'Teachers', 'url' => 'school/teachers.php'],
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
            <?= userAvatarHtml($teacher, 'user-profile-avatar user-profile-avatar--teacher') ?>
            <div>
                <div class="user-profile-title-row">
                    <h2><?= e($fullName) ?></h2>
                    <span class="badge badge-<?= $teacher['status'] === 'active' ? 'active' : 'suspended' ?>"><?= e(ucfirst($teacher['status'])) ?></span>
                </div>
                <p class="text-muted user-profile-role"><i class="fa-solid fa-chalkboard-user"></i> Teacher</p>
            </div>
        </div>
        <div class="actions">
            <a href="<?= url('school/teachers.php?action=edit&id=' . $teacherId) ?>" class="btn btn-secondary btn-sm">Edit</a>
            <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="form_action" value="toggle"><input type="hidden" name="user_id" value="<?= $teacherId ?>"><button class="btn btn-secondary btn-sm"><?= $teacher['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button></form>
            <form method="post" style="display:inline" data-confirm="Delete this teacher?"><?= csrfField() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="user_id" value="<?= $teacherId ?>"><button class="btn btn-danger btn-sm">Delete</button></form>
            <a href="<?= url('school/teachers.php') ?>" class="btn btn-secondary btn-sm">Back to list</a>
        </div>
    </div>
</div>

<div class="user-profile-grid">
    <?php renderUserProfilePhotoPanel($teacher, 'Teacher profile photo', 'This photo appears on the teacher profile and anywhere this teacher is listed in your school.') ?>

    <div class="panel user-profile-panel">
        <h3>Account information</h3>
        <dl class="user-profile-details">
            <div>
                <dt>Email</dt>
                <dd><a href="mailto:<?= e($teacher['email']) ?>"><?= e($teacher['email']) ?></a></dd>
            </div>
            <div>
                <dt>First name</dt>
                <dd><?= e($teacher['first_name']) ?></dd>
            </div>
            <div>
                <dt>Last name</dt>
                <dd><?= e($teacher['last_name']) ?></dd>
            </div>
            <div>
                <dt>Status</dt>
                <dd><?= e(ucfirst($teacher['status'])) ?></dd>
            </div>
            <div>
                <dt>Member since</dt>
                <dd><?= formatDate($teacher['created_at'], 'M j, Y') ?></dd>
            </div>
            <div>
                <dt>Last updated</dt>
                <dd><?= formatDate($teacher['updated_at'], 'M j, Y g:i A') ?></dd>
            </div>
        </dl>
    </div>

    <div class="panel user-profile-panel">
        <h3>Teachable subjects</h3>
        <?php if (empty($subjects)): ?>
            <p class="text-muted mb-0">No subjects assigned. Edit this teacher to select subjects from the catalog.</p>
        <?php else: ?>
            <div class="subject-tags">
                <?php foreach ($subjects as $subject): ?>
                    <span class="subject-tag"><?= e($subject['name']) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="panel user-profile-panel">
    <h3>Assigned classes</h3>
    <?php if (empty($classes)): ?>
        <p class="text-muted mb-0">Not assigned to any class yet. Assign this teacher from a <a href="<?= url('school/class-groups.php') ?>">class group</a>.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Subject</th><th>Class group</th><th>Academic year</th></tr></thead>
                <tbody>
                <?php foreach ($classes as $class): ?>
                    <tr>
                        <td><?= e($class['name']) ?></td>
                        <td><a href="<?= url('school/class-group.php?id=' . (int) $class['class_group_id'] . '&tab=subjects') ?>"><?= e($class['group_name']) ?></a></td>
                        <td><?= e($class['group_academic_year'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
