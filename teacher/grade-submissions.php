<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('teacher');
requireSchoolActive();

$user = currentUser();
$assignmentId = (int) ($_GET['assignment_id'] ?? 0);

$assignmentJoin = 'FROM assignments a
    INNER JOIN classes c ON c.id = a.class_id
    INNER JOIN subjects s ON s.id = c.subject_id
    INNER JOIN class_groups g ON g.id = c.class_group_id';

if ($assignmentId <= 0) {
    $stmt = db()->prepare("SELECT a.*, s.name AS name, g.name AS group_name,
        (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) AS submission_count,
        (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND status = 'submitted' AND grade IS NULL) AS pending_count
        $assignmentJoin
        WHERE a.teacher_id = ?
        ORDER BY a.due_date DESC, a.created_at DESC");
    $stmt->execute([$user['id']]);
    $assignments = $stmt->fetchAll();

    $pageTitle = 'Grade Submissions';
    $pageHeading = 'Grade submissions';
    $pageSubtitle = 'Select an assignment to review and grade student work.';
    $activeMenu = 'grading';
    $menuItems = teacherMenu();
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => 'teacher/dashboard.php'],
        ['label' => 'Grade Submissions', 'url' => 'teacher/grade-submissions.php'],
    ];

    require __DIR__ . '/../includes/layout/dashboard_header.php';
    ?>

    <?php if (empty($assignments)): ?>
    <div class="empty-state">
        <i class="fa-solid fa-inbox"></i>
        <h3>No assignments yet</h3>
        <p>Create an assignment in one of your classes to start collecting submissions.</p>
        <a href="<?= url('teacher/classes.php') ?>" class="btn btn-primary btn-sm">View classes</a>
    </div>
    <?php else: ?>
    <div class="admin-table-card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Assignment</th>
                        <th>Class</th>
                        <th>Due</th>
                        <th>Submissions</th>
                        <th>Pending</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $a): ?>
                    <tr>
                        <td><strong><?= e($a['title']) ?></strong></td>
                        <td><?= e(classDisplayName($a)) ?></td>
                        <td><?= formatDate($a['due_date'], 'M j, Y') ?></td>
                        <td><?= (int) $a['submission_count'] ?></td>
                        <td>
                            <?php if ((int) $a['pending_count'] > 0): ?>
                                <span class="badge badge-warning"><?= (int) $a['pending_count'] ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <a href="<?= url('teacher/grade-submissions.php?assignment_id=' . $a['id']) ?>" class="btn btn-sm btn-primary">Grade</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php require __DIR__ . '/../includes/layout/dashboard_footer.php';
    exit;
}

$stmt = db()->prepare("SELECT a.*, s.name AS name, g.name AS group_name $assignmentJoin
    WHERE a.id = ? AND a.teacher_id = ?");
$stmt->execute([$assignmentId, $user['id']]);
$assignment = $stmt->fetch();

if (!$assignment) {
    flash('error', 'Assignment not found.');
    redirect('teacher/grade-submissions.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $submissionId = (int) ($_POST['submission_id'] ?? 0);
    $grade = $_POST['grade'] !== '' ? (float) $_POST['grade'] : null;
    $feedback = trim($_POST['feedback'] ?? '');

    $sub = db()->prepare('SELECT s.* FROM assignment_submissions s
        INNER JOIN assignments a ON a.id = s.assignment_id
        WHERE s.id = ? AND a.id = ? AND a.teacher_id = ?');
    $sub->execute([$submissionId, $assignmentId, $user['id']]);
    if ($sub->fetch()) {
        $status = $grade !== null ? 'graded' : 'submitted';
        db()->prepare('UPDATE assignment_submissions SET grade=?, feedback=?, status=? WHERE id=?')
            ->execute([$grade, $feedback ?: null, $status, $submissionId]);
        flash('success', 'Submission graded.');
    }
    redirect('teacher/grade-submissions.php?assignment_id=' . $assignmentId);
}

$submissions = db()->prepare('SELECT s.*, u.first_name, u.last_name, u.email
    FROM assignment_submissions s
    INNER JOIN users u ON u.id = s.student_id
    WHERE s.assignment_id = ?
    ORDER BY s.submitted_at DESC');
$submissions->execute([$assignmentId]);
$submissions = $submissions->fetchAll();

$pageTitle = 'Grade Submissions';
$pageHeading = 'Grade: ' . $assignment['title'];
$activeMenu = 'grading';
$menuItems = teacherMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'teacher/dashboard.php'],
    ['label' => 'Grade Submissions', 'url' => 'teacher/grade-submissions.php'],
    ['label' => $assignment['title'], 'url' => 'teacher/grade-submissions.php?assignment_id=' . $assignmentId],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="actions mb-1">
    <a href="<?= url('teacher/grade-submissions.php') ?>" class="btn btn-secondary btn-sm">Back to assignments</a>
</div>

<div class="panel">
    <p><strong>Class:</strong> <?= e(classDisplayName($assignment)) ?> · <strong>Max Points:</strong> <?= e($assignment['max_points']) ?> · <strong>Due:</strong> <?= formatDate($assignment['due_date']) ?></p>
</div>

<?php if (empty($submissions)): ?>
    <p class="text-muted">No submissions yet.</p>
<?php else: foreach ($submissions as $s): ?>
<div class="panel">
    <div class="panel-header">
        <h2><?= e($s['first_name'] . ' ' . $s['last_name']) ?></h2>
        <span class="badge badge-<?= e($s['status']) ?>"><?= e(ucfirst($s['status'])) ?></span>
    </div>
    <p class="text-muted">Submitted: <?= formatDate($s['submitted_at']) ?></p>
    <?php if ($s['content']): ?><div class="mb-1"><?= nl2br(e($s['content'])) ?></div><?php endif; ?>
    <?php if ($s['file_path']): ?><p><a href="<?= e(uploadUrl($s['file_path'], 'submission')) ?>" target="_blank">Download attachment</a></p><?php endif; ?>

    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="submission_id" value="<?= $s['id'] ?>">
        <div class="form-row">
            <div class="form-group">
                <label>Grade (max <?= e($assignment['max_points']) ?>)</label>
                <input type="number" step="0.01" name="grade" class="form-control" value="<?= e($s['grade'] ?? '') ?>" max="<?= e($assignment['max_points']) ?>">
            </div>
            <div class="form-group">
                <label>Feedback</label>
                <textarea name="feedback" class="form-control"><?= e($s['feedback'] ?? '') ?></textarea>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Save Grade</button>
    </form>
</div>
<?php endforeach; endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
