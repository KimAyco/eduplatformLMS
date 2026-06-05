<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('student');
requireSchoolActive();

$user = currentUser();
$classes = getStudentClasses();
$assignmentId = (int) ($_GET['id'] ?? 0);
$errors = [];

if ($assignmentId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $stmt = db()->prepare('SELECT a.* FROM assignments a
        INNER JOIN class_students cs ON cs.class_id = a.class_id AND cs.student_id = ?
        WHERE a.id = ?');
    $stmt->execute([$user['id'], $assignmentId]);
    $assignment = $stmt->fetch();

    if (!$assignment) {
        flash('error', 'Assignment not found.');
        redirect('student/assignments.php');
    }

    $isLate = $assignment['due_date'] && strtotime($assignment['due_date']) < time();
    if ($isLate && !$assignment['allow_late']) {
        $errors[] = 'This assignment is past due and late submissions are not allowed.';
    } else {
        $content = trim($_POST['content'] ?? '');
        try {
            $filePath = null;
            if (!empty($_FILES['file']['name'])) {
                $filePath = uploadFile($_FILES['file'], schoolId() . '/submissions');
            }

            $existing = db()->prepare('SELECT id, file_path FROM assignment_submissions WHERE assignment_id=? AND student_id=?');
            $existing->execute([$assignmentId, $user['id']]);
            $sub = $existing->fetch();

            if ($sub) {
                if ($filePath && $sub['file_path']) deleteUpload($sub['file_path']);
                db()->prepare('UPDATE assignment_submissions SET content=?, file_path=COALESCE(?, file_path), submitted_at=NOW(), status=? WHERE id=?')
                    ->execute([$content ?: null, $filePath, 'submitted', $sub['id']]);
            } else {
                db()->prepare('INSERT INTO assignment_submissions (assignment_id, student_id, content, file_path) VALUES (?,?,?,?)')
                    ->execute([$assignmentId, $user['id'], $content ?: null, $filePath]);
            }
            flash('success', 'Assignment submitted successfully.');
            redirect('student/assignments.php');
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

if ($assignmentId) {
    $stmt = db()->prepare('SELECT a.*, c.name AS class_name FROM assignments a
        INNER JOIN class_students cs ON cs.class_id = a.class_id AND cs.student_id = ?
        INNER JOIN classes c ON c.id = a.class_id
        WHERE a.id = ?');
    $stmt->execute([$user['id'], $assignmentId]);
    $viewAssignment = $stmt->fetch();

    if (!$viewAssignment) {
        flash('error', 'Assignment not found.');
        redirect('student/assignments.php');
    }

    $sub = db()->prepare('SELECT * FROM assignment_submissions WHERE assignment_id=? AND student_id=?');
    $sub->execute([$assignmentId, $user['id']]);
    $submission = $sub->fetch();

    $pageTitle = $viewAssignment['title'];
    $pageHeading = $viewAssignment['title'];
    $activeMenu = 'assignments';
    $menuItems = studentMenu();
    require __DIR__ . '/../includes/layout/dashboard_header.php';
    ?>

    <div class="actions mb-1"><a href="<?= url('student/assignments.php') ?>" class="btn btn-secondary btn-sm">Back</a></div>

    <div class="panel">
        <p><strong>Class:</strong> <?= e($viewAssignment['class_name']) ?> · <strong>Due:</strong> <?= formatDate($viewAssignment['due_date']) ?> · <strong>Points:</strong> <?= e($viewAssignment['max_points']) ?></p>
        <?php if ($viewAssignment['instructions']): ?><div class="mt-1"><?= nl2br(e($viewAssignment['instructions'])) ?></div><?php endif; ?>
    </div>

    <?php if ($submission && $submission['status'] === 'graded'): ?>
    <div class="panel">
        <h2>Your Grade</h2>
        <p><strong><?= e($submission['grade']) ?></strong> / <?= e($viewAssignment['max_points']) ?></p>
        <?php if ($submission['feedback']): ?><p><?= nl2br(e($submission['feedback'])) ?></p><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

    <div class="panel">
        <h2><?= $submission ? 'Update Submission' : 'Submit Assignment' ?></h2>
        <form method="post" enctype="multipart/form-data">
            <?= csrfField() ?>
            <div class="form-group"><label>Your Response</label><textarea name="content" class="form-control"><?= e($submission['content'] ?? '') ?></textarea></div>
            <div class="form-group">
                <label>Attachment</label>
                <input type="file" name="file" class="form-control">
                <?php if ($submission && $submission['file_path']): ?>
                    <small>Current: <a href="<?= e(uploadUrl($submission['file_path'], 'submission')) ?>" target="_blank">Download</a></small>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary">Submit</button>
        </form>
    </div>

    <?php require __DIR__ . '/../includes/layout/dashboard_footer.php';
    exit;
}

$stmt = db()->prepare('SELECT a.*, c.name AS class_name, s.id AS submission_id, s.status AS submission_status, s.grade
    FROM assignments a
    INNER JOIN class_students cs ON cs.class_id = a.class_id AND cs.student_id = ?
    INNER JOIN classes c ON c.id = a.class_id
    LEFT JOIN assignment_submissions s ON s.assignment_id = a.id AND s.student_id = ?
    ORDER BY a.due_date ASC');
$stmt->execute([$user['id'], $user['id']]);
$assignments = $stmt->fetchAll();

$pageTitle = 'Assignments';
$pageHeading = 'Assignments';
$activeMenu = 'assignments';
$menuItems = studentMenu();

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="table-wrap">
    <table>
        <thead><tr><th>Title</th><th>Class</th><th>Due</th><th>Status</th><th>Grade</th><th></th></tr></thead>
        <tbody>
        <?php if (empty($assignments)): ?>
            <tr><td colspan="6" class="text-muted">No assignments.</td></tr>
        <?php else: foreach ($assignments as $a): ?>
            <tr>
                <td><?= e($a['title']) ?></td>
                <td><?= e($a['class_name']) ?></td>
                <td><?= formatDate($a['due_date']) ?></td>
                <td>
                    <?php if (!$a['submission_id']): ?>
                        <span class="badge badge-pending">Not submitted</span>
                    <?php else: ?>
                        <span class="badge badge-<?= e($a['submission_status']) ?>"><?= e(ucfirst($a['submission_status'])) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= $a['grade'] !== null ? e($a['grade']) . '/' . e($a['max_points']) : '—' ?></td>
                <td><a href="<?= url('student/assignments.php?id='.$a['id']) ?>" class="btn btn-sm btn-primary"><?= $a['submission_id'] ? 'View' : 'Submit' ?></a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
