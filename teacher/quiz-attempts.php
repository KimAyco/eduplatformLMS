<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('teacher');
requireSchoolActive();

$user = currentUser();
$quizId = (int) ($_GET['quiz_id'] ?? 0);
$classId = (int) ($_GET['class_id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM quizzes WHERE id = ? AND teacher_id = ?');
$stmt->execute([$quizId, $user['id']]);
$quiz = $stmt->fetch();

if (!$quiz) {
    flash('error', 'Quiz not found.');
    redirect('teacher/quizzes.php');
}

if ($classId <= 0 && !empty($quiz['class_id'])) {
    $classId = (int) $quiz['class_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $answerId = (int) ($_POST['answer_id'] ?? 0);
    $points = (float) ($_POST['points_earned'] ?? 0);
    $isCorrect = isset($_POST['is_correct']) ? 1 : 0;

    $ans = db()->prepare('SELECT qaa.* FROM quiz_attempt_answers qaa
        INNER JOIN quiz_attempts qa ON qa.id = qaa.attempt_id
        INNER JOIN quiz_questions qq ON qq.id = qaa.question_id
        WHERE qaa.id = ? AND qa.quiz_id = ? AND qq.type = ?');
    $ans->execute([$answerId, $quizId, 'short_answer']);
    if ($ans->fetch()) {
        db()->prepare('UPDATE quiz_attempt_answers SET points_earned=?, is_correct=? WHERE id=?')
            ->execute([$points, $isCorrect, $answerId]);
        $attemptId = db()->prepare('SELECT attempt_id FROM quiz_attempt_answers WHERE id=?');
        $attemptId->execute([$answerId]);
        gradeQuizAttempt((int) $attemptId->fetchColumn());
        flash('success', 'Answer graded.');
    }
    redirect('teacher/quiz-attempts.php?quiz_id=' . $quizId . ($classId ? '&class_id=' . $classId : ''));
}

$attemptsQuery = 'SELECT qa.*, u.first_name, u.last_name, u.email
    FROM quiz_attempts qa
    INNER JOIN users u ON u.id = qa.student_id
    WHERE qa.quiz_id = ? AND qa.status != ?
    ORDER BY qa.submitted_at DESC';
$attempts = db()->prepare($attemptsQuery);
$attempts->execute([$quizId, 'in_progress']);
$attempts = $attempts->fetchAll();
$maxScore = getQuizTotalPoints($quizId);
$backUrl = $classId > 0 ? teacherCourseUrl($classId) : url('teacher/quizzes.php');
$reviewBase = 'teacher/quiz-attempt-view.php?attempt_id=';
$reviewClass = $classId > 0 ? '&class_id=' . $classId : '';

$pageTitle = 'Quiz Attempts';
$pageHeading = 'Attempts: ' . $quiz['title'];
$activeMenu = $classId > 0 ? 'classes' : 'quizzes';
$menuItems = teacherMenu();

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="actions mb-1">
    <a href="<?= e($backUrl) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> <?= $classId > 0 ? 'Back to course' : 'Back to quizzes' ?></a>
</div>

<div class="panel quiz-attempts-summary">
    <p class="mb-0">
        <strong><?= count($attempts) ?></strong> completed attempt<?= count($attempts) !== 1 ? 's' : '' ?>
        · Quiz total: <strong><?= e($maxScore) ?> pts</strong>
    </p>
</div>

<div class="table-wrap">
    <table>
        <thead><tr><th>Student</th><th>Score</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
        <tbody>
        <?php if (empty($attempts)): ?>
            <tr><td colspan="5" class="text-muted">No completed attempts yet.</td></tr>
        <?php else: foreach ($attempts as $a): ?>
            <tr>
                <td>
                    <strong><?= e($a['first_name'] . ' ' . $a['last_name']) ?></strong>
                    <div class="text-muted" style="font-size:0.8125rem"><?= e($a['email']) ?></div>
                </td>
                <td><?= $a['score'] !== null ? e($a['score']) . ' / ' . e($a['max_score'] ?? $maxScore) : '—' ?></td>
                <td><span class="badge badge-<?= $a['status'] === 'graded' ? 'graded' : 'submitted' ?>"><?= e(ucfirst($a['status'])) ?></span></td>
                <td><?= formatDate($a['submitted_at']) ?></td>
                <td><a href="<?= url($reviewBase . $a['id'] . $reviewClass) ?>" class="btn btn-sm btn-primary">Review</a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
