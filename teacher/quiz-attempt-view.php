<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('teacher');
requireSchoolActive();

$user = currentUser();
$attemptId = (int) ($_GET['attempt_id'] ?? 0);

$stmt = db()->prepare('SELECT qa.*, q.title AS quiz_title, q.id AS quiz_id, u.first_name, u.last_name
    FROM quiz_attempts qa
    INNER JOIN quizzes q ON q.id = qa.quiz_id
    INNER JOIN users u ON u.id = qa.student_id
    WHERE qa.id = ? AND q.teacher_id = ?');
$stmt->execute([$attemptId, $user['id']]);
$attempt = $stmt->fetch();

if (!$attempt) {
    flash('error', 'Attempt not found.');
    redirect('teacher/quizzes.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $answerId = (int) ($_POST['answer_id'] ?? 0);
    $points = (float) ($_POST['points_earned'] ?? 0);
    $isCorrect = isset($_POST['is_correct']) ? 1 : 0;

    $check = db()->prepare('SELECT qaa.id FROM quiz_attempt_answers qaa
        INNER JOIN quiz_questions qq ON qq.id = qaa.question_id
        WHERE qaa.id = ? AND qaa.attempt_id = ? AND qq.type = ?');
    $check->execute([$answerId, $attemptId, 'short_answer']);
    if ($check->fetch()) {
        db()->prepare('UPDATE quiz_attempt_answers SET points_earned=?, is_correct=? WHERE id=?')
            ->execute([$points, $isCorrect, $answerId]);
        gradeQuizAttempt($attemptId);
        flash('success', 'Answer graded.');
    }
    redirect('teacher/quiz-attempt-view.php?attempt_id=' . $attemptId);
}

$answers = QuizRepository::attemptAnswersWithDetails($attemptId);

$pageTitle = 'Review Attempt';
$pageHeading = $attempt['quiz_title'] . ' — ' . $attempt['first_name'] . ' ' . $attempt['last_name'];
$activeMenu = 'quizzes';
$menuItems = teacherMenu();

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="actions mb-1">
    <a href="<?= url('teacher/quiz-attempts.php?quiz_id='.$attempt['quiz_id']) ?>" class="btn btn-secondary btn-sm">Back</a>
</div>

<div class="panel">
    <p><strong>Score:</strong> <?= $attempt['score'] !== null ? e($attempt['score']) . ' / ' . getQuizTotalPoints($attempt['quiz_id']) : 'Pending' ?>
    · <strong>Status:</strong> <?= e(ucfirst($attempt['status'])) ?></p>
</div>

<?php foreach ($answers as $i => $a): ?>
<div class="question-block">
    <strong>Q<?= $i + 1 ?>. <?= e($a['question_text']) ?></strong>
    <span class="badge badge-submitted"><?= e(str_replace('_', ' ', $a['type'])) ?> · <?= e($a['points']) ?> pts</span>

    <?php if ($a['type'] === 'short_answer'): ?>
        <p class="mt-1"><strong>Answer:</strong> <?= e($a['answer_text'] ?: '—') ?></p>
        <?php if ($a['correct_answer']): ?><p class="text-muted">Expected: <?= e($a['correct_answer']) ?></p><?php endif; ?>
        <form method="post" class="mt-1">
            <?= csrfField() ?>
            <input type="hidden" name="answer_id" value="<?= $a['id'] ?>">
            <div class="form-row">
                <div class="form-group"><label>Points Earned</label><input type="number" step="0.01" name="points_earned" class="form-control" value="<?= e($a['points_earned'] ?? '') ?>" max="<?= e($a['points']) ?>"></div>
                <div class="form-check" style="align-self:end;padding-bottom:.75rem;"><input type="checkbox" name="is_correct" id="c<?= $a['id'] ?>" <?= $a['is_correct'] ? 'checked' : '' ?>><label for="c<?= $a['id'] ?>">Mark correct</label></div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Grade</button>
        </form>
    <?php else: ?>
        <p class="mt-1">Answer: <?= e($a['selected_text'] ?? '—') ?></p>
        <p><?= $a['is_correct'] ? '<span class="badge badge-graded">Correct (+'.e($a['points_earned']).')</span>' : '<span class="badge badge-rejected">Incorrect</span>' ?></p>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
