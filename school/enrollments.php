<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('school_admin');
requireSchoolActive();

$sid = schoolId();
$classId = (int) ($_GET['class_id'] ?? $_POST['class_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $classId = (int) ($_POST['class_id'] ?? 0);
    $action = $_POST['form_action'] ?? '';

    $check = db()->prepare('SELECT id FROM classes WHERE id = ? AND school_id = ?');
    $check->execute([$classId, $sid]);
    if (!$check->fetch()) {
        flash('error', 'Invalid class.');
        redirect('school/enrollments.php');
    }

    if ($action === 'assign_teacher') {
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        $tCheck = db()->prepare('SELECT id FROM users WHERE id=? AND school_id=? AND role=? AND status=?');
        $tCheck->execute([$teacherId, $sid, 'teacher', 'active']);
        if ($tCheck->fetch()) {
            $ins = db()->prepare('INSERT IGNORE INTO class_teachers (class_id, teacher_id) VALUES (?, ?)');
            $ins->execute([$classId, $teacherId]);
            flash('success', 'Teacher assigned to class.');
        }
    } elseif ($action === 'remove_teacher') {
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        db()->prepare('DELETE FROM class_teachers WHERE class_id=? AND teacher_id=?')->execute([$classId, $teacherId]);
        flash('success', 'Teacher removed from class.');
    } elseif ($action === 'assign_student') {
        $studentId = (int) ($_POST['student_id'] ?? 0);
        $sCheck = db()->prepare('SELECT id FROM users WHERE id=? AND school_id=? AND role=? AND status=?');
        $sCheck->execute([$studentId, $sid, 'student', 'active']);
        if ($sCheck->fetch()) {
            $ins = db()->prepare('INSERT IGNORE INTO class_students (class_id, student_id) VALUES (?, ?)');
            $ins->execute([$classId, $studentId]);
            flash('success', 'Student enrolled in class.');
        }
    } elseif ($action === 'remove_student') {
        $studentId = (int) ($_POST['student_id'] ?? 0);
        db()->prepare('DELETE FROM class_students WHERE class_id=? AND student_id=?')->execute([$classId, $studentId]);
        flash('success', 'Student removed from class.');
    }
    redirect('school/enrollments.php?class_id=' . $classId);
}

$classes = db()->prepare('SELECT * FROM classes WHERE school_id = ? ORDER BY name, section');
$classes->execute([$sid]);
$classes = $classes->fetchAll();

$selectedClass = null;
$assignedTeachers = [];
$assignedStudents = [];
$availableTeachers = [];
$availableStudents = [];

if ($classId) {
    $stmt = db()->prepare('SELECT * FROM classes WHERE id = ? AND school_id = ?');
    $stmt->execute([$classId, $sid]);
    $selectedClass = $stmt->fetch();

    if ($selectedClass) {
        $assignedTeachers = db()->prepare('SELECT u.* FROM users u INNER JOIN class_teachers ct ON ct.teacher_id = u.id WHERE ct.class_id = ?');
        $assignedTeachers->execute([$classId]);
        $assignedTeachers = $assignedTeachers->fetchAll();

        $assignedStudents = db()->prepare('SELECT u.* FROM users u INNER JOIN class_students cs ON cs.student_id = u.id WHERE cs.class_id = ? ORDER BY u.last_name');
        $assignedStudents->execute([$classId]);
        $assignedStudents = $assignedStudents->fetchAll();

        $assignedTeacherIds = array_column($assignedTeachers, 'id');
        $assignedStudentIds = array_column($assignedStudents, 'id');

        $allTeachers = db()->prepare("SELECT * FROM users WHERE school_id=? AND role='teacher' AND status='active' ORDER BY last_name");
        $allTeachers->execute([$sid]);
        $availableTeachers = array_filter($allTeachers->fetchAll(), fn($t) => !in_array($t['id'], $assignedTeacherIds));

        $allStudents = db()->prepare("SELECT * FROM users WHERE school_id=? AND role='student' AND status='active' ORDER BY last_name");
        $allStudents->execute([$sid]);
        $availableStudents = array_filter($allStudents->fetchAll(), fn($s) => !in_array($s['id'], $assignedStudentIds));
    }
}

$pageTitle = 'Enrollments';
$pageHeading = 'Class Enrollments';
$activeMenu = 'enrollments';
$menuItems = schoolAdminMenu();

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="panel">
    <form method="get" class="filter-bar">
        <label>Select Class:</label>
        <select name="class_id" class="form-control" onchange="this.form.submit()" required>
            <option value="">— Choose a class —</option>
            <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $classId === (int)$c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name'] . ($c['section'] ? ' - ' . $c['section'] : '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if ($selectedClass): ?>
<div class="panel">
    <h2><?= e($selectedClass['name']) ?> — Teachers</h2>
    <?php if (!empty($availableTeachers)): ?>
    <form method="post" class="filter-bar">
        <?= csrfField() ?>
        <input type="hidden" name="class_id" value="<?= $classId ?>">
        <input type="hidden" name="form_action" value="assign_teacher">
        <select name="teacher_id" class="form-control" required>
            <option value="">Add teacher...</option>
            <?php foreach ($availableTeachers as $t): ?>
                <option value="<?= $t['id'] ?>"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Assign</button>
    </form>
    <?php endif; ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Email</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($assignedTeachers)): ?>
                <tr><td colspan="3" class="text-muted">No teachers assigned.</td></tr>
            <?php else: foreach ($assignedTeachers as $t): ?>
                <tr>
                    <td><?= e($t['first_name'] . ' ' . $t['last_name']) ?></td>
                    <td><?= e($t['email']) ?></td>
                    <td>
                        <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="class_id" value="<?= $classId ?>"><input type="hidden" name="form_action" value="remove_teacher"><input type="hidden" name="teacher_id" value="<?= $t['id'] ?>"><button class="btn btn-sm btn-danger">Remove</button></form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <h2><?= e($selectedClass['name']) ?> — Students</h2>
    <?php if (!empty($availableStudents)): ?>
    <form method="post" class="filter-bar">
        <?= csrfField() ?>
        <input type="hidden" name="class_id" value="<?= $classId ?>">
        <input type="hidden" name="form_action" value="assign_student">
        <select name="student_id" class="form-control" required>
            <option value="">Add student...</option>
            <?php foreach ($availableStudents as $s): ?>
                <option value="<?= $s['id'] ?>"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Enroll</button>
    </form>
    <?php endif; ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Email</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($assignedStudents)): ?>
                <tr><td colspan="3" class="text-muted">No students enrolled.</td></tr>
            <?php else: foreach ($assignedStudents as $s): ?>
                <tr>
                    <td><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                    <td><?= e($s['email']) ?></td>
                    <td>
                        <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="class_id" value="<?= $classId ?>"><input type="hidden" name="form_action" value="remove_student"><input type="hidden" name="student_id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-danger">Remove</button></form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif ($classId): ?>
    <div class="alert alert-error">Class not found.</div>
<?php else: ?>
    <p class="text-muted">Select a class to manage teacher and student enrollments.</p>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
