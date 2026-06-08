<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('student');
requireSchoolActive();

$user = currentUser();
$attemptId = (int) ($_GET['attempt_id'] ?? 0);
$quizId = (int) ($_GET['quiz_id'] ?? 0);

if ($attemptId) {
    $stmt = db()->prepare('SELECT qa.*, q.title, q.id AS quiz_id FROM quiz_attempts qa
        INNER JOIN quizzes q ON q.id = qa.quiz_id
        INNER JOIN classes c ON c.id = q.class_id
        INNER JOIN class_group_students cgs ON cgs.class_group_id = c.class_group_id AND cgs.student_id = ?
        WHERE qa.id = ? AND qa.student_id = ?');
    $stmt->execute([$user['id'], $attemptId, $user['id']]);
    $attempt = $stmt->fetch();
} elseif ($quizId) {
    $attempts = db()->prepare("SELECT qa.*, q.title, q.id AS quiz_id FROM quiz_attempts qa
        INNER JOIN quizzes q ON q.id = qa.quiz_id
        WHERE qa.quiz_id = ? AND qa.student_id = ? AND qa.status != 'in_progress'
        ORDER BY qa.submitted_at DESC");
    $attempts->execute([$quizId, $user['id']]);
    $allAttempts = $attempts->fetchAll();

    if (count($allAttempts) === 1) {
        $attempt = $allAttempts[0];
        $attemptId = $attempt['id'];
    } else {
        $pageTitle = 'Quiz Results';
        $pageHeading = 'Quiz Results';
        $activeMenu = 'quizzes';
        $menuItems = studentMenu();
        require __DIR__ . '/../includes/layout/dashboard_header.php';
        ?>
        <div class="actions mb-1"><a href="<?= url('student/quizzes.php') ?>" class="btn btn-secondary btn-sm">Back</a></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Attempt</th><th>Score</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($allAttempts as $i => $a): ?>
                    <tr>
                        <td>Attempt <?= count($allAttempts) - $i ?></td>
                        <td><?= $a['score'] !== null ? e($a['score']) . ' / ' . getQuizTotalPoints($a['quiz_id']) : 'Pending' ?></td>
                        <td><span class="badge badge-<?= $a['status'] === 'graded' ? 'graded' : 'submitted' ?>"><?= e(ucfirst($a['status'])) ?></span></td>
                        <td><?= formatDate($a['submitted_at']) ?></td>
                        <td><a href="<?= url('student/quiz-results.php?attempt_id='.$a['id']) ?>" class="btn btn-sm btn-primary">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php require __DIR__ . '/../includes/layout/dashboard_footer.php';
        exit;
    }
} else {
    redirect('student/quizzes.php');
}

if (!$attempt) {
    flash('error', 'Result not found.');
    redirect('student/classes.php');
}

$quizRow = db()->prepare('SELECT show_score_to_students FROM quizzes WHERE id=?');
$quizRow->execute([$attempt['quiz_id']]);
$showScore = (int) ($quizRow->fetchColumn() ?: 1);

$answers = QuizRepository::attemptAnswersWithDetails($attemptId);

$pageTitle = 'Result: ' . $attempt['title'];
$pageHeading = $attempt['title'] . ' — Results';
$activeMenu = 'quizzes';
$menuItems = studentMenu();

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="actions mb-1"><a href="<?= url('student/quizzes.php') ?>" class="btn btn-secondary btn-sm">Back to Quizzes</a></div>

<div class="panel">
    <?php if ($showScore): ?>
    <p><strong>Score:</strong> <?= $attempt['score'] !== null ? e($attempt['score']) . ' / ' . e($attempt['max_score'] ?? getQuizTotalPoints($attempt['quiz_id'])) : 'Pending grading' ?>
    <?php else: ?>
    <p><strong>Score:</strong> <span class="text-muted">Your teacher has hidden scores for this quiz.</span>
    <?php endif; ?>
    · <strong>Status:</strong> <?= e(ucfirst($attempt['status'])) ?>
    · <strong>Submitted:</strong> <?= formatDate($attempt['submitted_at']) ?></p>
</div>

<?php foreach ($answers as $i => $a): ?>
<div class="question-block">
    <strong>Q<?= $i + 1 ?>. <?= e($a['question_text']) ?></strong>
    <?php if ($a['type'] === 'short_answer'): ?>
        <p class="mt-1">Your answer: <?= e($a['answer_text'] ?: '—') ?></p>
        <p><?= $a['points_earned'] !== null ? 'Points: ' . e($a['points_earned']) . '/' . e($a['points']) : 'Awaiting manual grading' ?></p>
    <?php else: ?>
        <p class="mt-1">Your answer: <?= e($a['selected_text'] ?? '—') ?></p>
        <p><?= $a['is_correct'] ? '<span class="badge badge-graded">Correct</span>' : '<span class="badge badge-rejected">Incorrect</span>' ?>
        <?= $a['points_earned'] !== null ? ' (' . e($a['points_earned']) . '/' . e($a['points']) . ' pts)' : '' ?></p>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
