<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('school_admin');
requireSchoolActive();

$sid = schoolId();
$groupId = (int) ($_GET['id'] ?? 0);
$tab = $_GET['tab'] ?? 'subjects';
$group = ClassGroupRepository::get($groupId, $sid);

if (!$group) {
    flash('error', 'Class group not found.');
    redirect('school/class-groups.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['form_action'] ?? '';

    if ($action === 'add_subject') {
        $subjectId = (int) ($_POST['subject_id'] ?? 0);
        if (ClassGroupRepository::addSubject($groupId, $subjectId, $sid)) {
            flash('success', 'Subject added to group.');
        } else {
            flash('error', 'Could not add subject. It may already be in this group.');
        }
        redirect('school/class-group.php?id=' . $groupId . '&tab=subjects');
    }

    if ($action === 'remove_subject') {
        $classId = (int) ($_POST['class_id'] ?? 0);
        if (ClassGroupRepository::removeOffering($classId, $groupId, $sid)) {
            flash('success', 'Subject removed from group.');
        } else {
            flash('error', 'Could not remove subject.');
        }
        redirect('school/class-group.php?id=' . $groupId . '&tab=subjects');
    }

    if ($action === 'assign_teacher') {
        $classId = (int) ($_POST['class_id'] ?? 0);
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        if ($teacherId <= 0) {
            ClassRepository::removeTeacher($classId, $sid);
            flash('success', 'Teacher unassigned.');
        } elseif (ClassRepository::assignTeacher($classId, $teacherId, $sid)) {
            flash('success', 'Teacher assigned.');
        } else {
            flash('error', 'Could not assign teacher. They must be able to teach this subject.');
        }
        redirect('school/class-group.php?id=' . $groupId . '&tab=subjects');
    }

    if ($action === 'enroll_student') {
        $studentId = (int) ($_POST['student_id'] ?? 0);
        if (ClassGroupRepository::enrollStudent($groupId, $studentId, $sid)) {
            flash('success', 'Student enrolled.');
        } else {
            flash('error', 'Could not enroll student.');
        }
        redirect('school/class-group.php?id=' . $groupId . '&tab=students');
    }

    if ($action === 'remove_student') {
        $studentId = (int) ($_POST['student_id'] ?? 0);
        ClassGroupRepository::removeStudent($groupId, $studentId, $sid);
        flash('success', 'Student removed from group.');
        redirect('school/class-group.php?id=' . $groupId . '&tab=students');
    }

    if ($action === 'remove_class_cover') {
        $classId = (int) ($_POST['class_id'] ?? 0);
        $classRow = ClassRepository::getWithGroup($classId, $sid);
        if ($classRow && !empty($classRow['cover_image'])) {
            deleteUpload($classRow['cover_image']);
            db()->prepare('UPDATE classes SET cover_image = NULL WHERE id = ? AND school_id = ?')->execute([$classId, $sid]);
            clearClassCoverCaches($classId, $sid);
            flash('success', 'Course cover removed.');
        }
        redirect('school/class-group.php?id=' . $groupId . '&tab=subjects');
    }

    if ($action === 'upload_class_cover') {
        $classId = (int) ($_POST['class_id'] ?? 0);
        $classRow = ClassRepository::getWithGroup($classId, $sid);
        if (!$classRow) {
            flash('error', 'Class offering not found.');
            redirect('school/class-group.php?id=' . $groupId . '&tab=subjects');
        }
        try {
            $newPath = uploadClassCover($_FILES['cover'] ?? [], $sid, $classId);
            if ($newPath === null) {
                flash('error', 'Please choose an image file to upload.');
            } else {
                if (!empty($classRow['cover_image'])) {
                    deleteUpload($classRow['cover_image']);
                }
                db()->prepare('UPDATE classes SET cover_image = ? WHERE id = ? AND school_id = ?')->execute([$newPath, $classId, $sid]);
                clearClassCoverCaches($classId, $sid);
                flash('success', 'Course cover updated.');
            }
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirect('school/class-group.php?id=' . $groupId . '&tab=subjects');
    }
}

$offerings = ClassGroupRepository::offerings($groupId, $sid);
$availableSubjects = SubjectRepository::notInGroup($sid, $groupId);
$enrolledStudents = ClassGroupRepository::enrolledStudents($groupId);
$availableStudents = ClassGroupRepository::availableStudents($groupId, $sid);

$teachersBySubject = [];
foreach ($offerings as $offering) {
    $teachersBySubject[$offering['subject_id']] = SubjectRepository::teachersForSubject((int) $offering['subject_id'], $sid);
}

$pageTitle = $group['name'];
$pageHeading = $group['name'];
$activeMenu = 'class_groups';
$menuItems = schoolAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'school/dashboard.php'],
    ['label' => 'Class Groups', 'url' => 'school/class-groups.php'],
    ['label' => $group['name'], 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="panel group-detail-header">
    <div class="panel-header">
        <div>
            <h2><i class="fa-solid fa-layer-group"></i> <?= e($group['name']) ?></h2>
            <?php if ($group['academic_year']): ?>
                <p class="text-muted">Academic year: <?= e($group['academic_year']) ?></p>
            <?php endif; ?>
            <?php if ($group['description']): ?>
                <p class="text-muted"><?= e($group['description']) ?></p>
            <?php endif; ?>
        </div>
        <div class="actions">
            <a href="<?= url('school/class-groups.php?action=edit&id=' . $groupId) ?>" class="btn btn-secondary btn-sm">Edit group</a>
            <a href="<?= url('school/class-groups.php') ?>" class="btn btn-secondary btn-sm">Back to list</a>
        </div>
    </div>
</div>

<div class="admin-tabs">
    <a href="<?= url('school/class-group.php?id=' . $groupId . '&tab=subjects') ?>" class="admin-tab <?= $tab === 'subjects' ? 'active' : '' ?>">
        <i class="fa-solid fa-book"></i> Subjects &amp; teachers
    </a>
    <a href="<?= url('school/class-group.php?id=' . $groupId . '&tab=students') ?>" class="admin-tab <?= $tab === 'students' ? 'active' : '' ?>">
        <i class="fa-solid fa-user-graduate"></i> Students (<?= count($enrolledStudents) ?>)
    </a>
</div>

<?php if ($tab === 'subjects'): ?>
<div class="panel">
    <h3>Subjects in this group</h3>
    <p class="text-muted mb-1">Add subjects from your catalog, then assign one teacher per subject.</p>

    <?php if (SubjectRepository::count($sid) === 0): ?>
        <div class="alert alert-error">No subjects in catalog. <a href="<?= url('school/subjects.php?action=add') ?>">Add subjects first</a>.</div>
    <?php elseif (!empty($availableSubjects)): ?>
        <form method="post" class="inline-assign-form filter-bar">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="add_subject">
            <select name="subject_id" class="form-control" required>
                <option value="">Add subject from catalog…</option>
                <?php foreach ($availableSubjects as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Add to group</button>
        </form>
    <?php endif; ?>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Subject</th><th>Assigned teacher</th><th>Card cover</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($offerings)): ?>
                <tr><td colspan="4" class="text-muted">No subjects in this group yet. Add subjects from your catalog above.</td></tr>
            <?php else: foreach ($offerings as $o):
                $offeringCoverUrl = classCoverImageUrl($o);
            ?>
                <tr>
                    <td><strong><?= e($o['name']) ?></strong></td>
                    <td>
                        <form method="post" class="inline-assign-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="form_action" value="assign_teacher">
                            <input type="hidden" name="class_id" value="<?= $o['id'] ?>">
                            <select name="teacher_id" class="form-control" onchange="this.form.submit()">
                                <option value="">— Unassigned —</option>
                                <?php foreach ($teachersBySubject[$o['subject_id']] ?? [] as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= (int) ($o['teacher_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>>
                                        <?= e($t['first_name'] . ' ' . $t['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <?php if (empty($teachersBySubject[$o['subject_id']] ?? [])): ?>
                            <small class="text-muted">No teachers can teach this subject. <a href="<?= url('school/teachers.php?action=add') ?>">Add or edit teachers</a>.</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <details class="class-cover-details">
                            <summary class="class-cover-summary">
                                <span class="class-cover-thumb" style="background-image: url('<?= e($offeringCoverUrl) ?>')" aria-hidden="true"></span>
                                <span class="class-cover-summary-label"><?= classHasCustomCover($o) ? 'Change cover' : 'Add cover' ?></span>
                            </summary>
                            <div class="class-cover-upload-panel panel-nested">
                                <div class="class-cover-preview lms-course-card course-card<?= classHasCustomCover($o) ? ' course-card-has-cover' : '' ?>">
                                    <div class="course-card-header" data-preview-cover style="background-image: url('<?= e($offeringCoverUrl) ?>')">
                                        <div class="course-card-header-overlay" aria-hidden="true"></div>
                                        <div class="course-card-header-content">
                                            <span class="course-card-badge"><?= e($o['name']) ?></span>
                                            <h3><?= e($o['name']) ?></h3>
                                        </div>
                                    </div>
                                </div>
                                <form method="post" enctype="multipart/form-data" data-upload-preview>
                                    <?= csrfField() ?>
                                    <input type="hidden" name="form_action" value="upload_class_cover">
                                    <input type="hidden" name="class_id" value="<?= (int) $o['id'] ?>">
                                    <div class="form-group">
                                        <input type="file" name="cover" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif" required data-preview-input data-preview-type="cover">
                                    </div>
                                    <p class="image-upload-preview-note" data-preview-note hidden>Preview updated. Save to upload.</p>
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-upload"></i> Save cover</button>
                                </form>
                                <?php if (!empty($o['cover_image'])): ?>
                                <form method="post" class="class-cover-remove" onsubmit="return confirm('Remove this course cover?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="form_action" value="remove_class_cover">
                                    <input type="hidden" name="class_id" value="<?= (int) $o['id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm"><i class="fa-solid fa-trash"></i> Remove</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </details>
                    </td>
                    <td class="actions">
                        <form method="post" style="display:inline" data-confirm="Remove this subject from the group?">
                            <?= csrfField() ?>
                            <input type="hidden" name="form_action" value="remove_subject">
                            <input type="hidden" name="class_id" value="<?= $o['id'] ?>">
                            <button class="btn btn-sm btn-danger">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>

<div class="panel">
    <h3>Enrolled students</h3>
    <p class="text-muted mb-1">Students in this group automatically have access to all subjects listed in the Subjects tab.</p>

    <?php if (!empty($availableStudents)): ?>
        <form method="post" class="inline-assign-form filter-bar">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="enroll_student">
            <select name="student_id" class="form-control" required>
                <option value="">Enroll student…</option>
                <?php foreach ($availableStudents as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= e($s['first_name'] . ' ' . $s['last_name']) ?> (<?= e($s['email']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Enroll</button>
        </form>
    <?php elseif (empty($enrolledStudents)): ?>
        <div class="alert alert-error">No active students available. <a href="<?= url('school/students.php?action=add') ?>">Add students first</a>.</div>
    <?php endif; ?>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Email</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($enrolledStudents)): ?>
                <tr><td colspan="3" class="text-muted">No students enrolled in this group.</td></tr>
            <?php else: foreach ($enrolledStudents as $s): ?>
                <tr>
                    <td><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                    <td><?= e($s['email']) ?></td>
                    <td>
                        <form method="post" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="form_action" value="remove_student">
                            <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                            <button class="btn btn-sm btn-danger">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
