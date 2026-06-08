<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout/quiz_attempt_review.php';
requireRole('teacher');
requireSchoolActive();

$user = currentUser();
$attemptId = (int) ($_GET['attempt_id'] ?? 0);
$classId = (int) ($_GET['class_id'] ?? 0);

$stmt = db()->prepare('SELECT qa.*, q.title AS quiz_title, q.id AS quiz_id, q.class_id, u.first_name, u.last_name, u.email
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

if ($classId <= 0 && !empty($attempt['class_id'])) {
    $classId = (int) $attempt['class_id'];
}
$attemptsUrl = 'teacher/quiz-attempts.php?quiz_id=' . (int) $attempt['quiz_id'] . ($classId > 0 ? '&class_id=' . $classId : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $answerId = (int) ($_POST['answer_id'] ?? 0);
    $points = (float) ($_POST['points_earned'] ?? 0);
    $feedback = trim($_POST['teacher_feedback'] ?? '');

    $check = db()->prepare('SELECT qaa.id, qq.type, qq.points FROM quiz_attempt_answers qaa
        INNER JOIN quiz_questions qq ON qq.id = qaa.question_id
        WHERE qaa.id = ? AND qaa.attempt_id = ?');
    $check->execute([$answerId, $attemptId]);
    $ans = $check->fetch();
    if ($ans && isManualGradeQuestionType($ans['type'])) {
        $isCorrect = $points >= (float) $ans['points'] ? 1 : ($points > 0 ? null : 0);
        db()->prepare('UPDATE quiz_attempt_answers SET points_earned=?, is_correct=?, teacher_feedback=? WHERE id=?')
            ->execute([$points, $isCorrect, $feedback ?: null, $answerId]);
        db()->prepare('UPDATE quiz_attempts SET graded_by=? WHERE id=?')->execute([$user['id'], $attemptId]);
        gradeQuizAttempt($attemptId);
        flash('success', 'Answer graded.');
    }
    redirect('teacher/quiz-attempt-view.php?attempt_id=' . $attemptId . ($classId > 0 ? '&class_id=' . $classId : ''));
}

$answers = QuizRepository::attemptAnswersWithDetails($attemptId);
$maxScore = (float) ($attempt['max_score'] ?? getQuizTotalPoints((int) $attempt['quiz_id']));
$score = $attempt['score'] !== null ? (float) $attempt['score'] : null;
$scorePct = ($score !== null && $maxScore > 0) ? min(100, round(($score / $maxScore) * 100)) : 0;
$studentName = trim($attempt['first_name'] . ' ' . $attempt['last_name']);
$initials = strtoupper(substr($attempt['first_name'], 0, 1) . substr($attempt['last_name'], 0, 1));

$pageTitle = 'Review Attempt';
$pageHeading = $attempt['quiz_title'] . ' — ' . $studentName;
$activeMenu = 'classes';
$menuItems = teacherMenu();

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="quiz-attempt-review">
    <header class="quiz-attempt-hero">
        <div class="quiz-attempt-hero__top">
            <a href="<?= url($attemptsUrl) ?>" class="btn btn-secondary btn-sm quiz-attempt-hero__back">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to attempts
            </a>
            <span class="badge badge-<?= $attempt['status'] === 'graded' ? 'graded' : 'submitted' ?> quiz-attempt-hero__status">
                <?= e(ucfirst($attempt['status'])) ?>
            </span>
        </div>

        <div class="quiz-attempt-hero__body">
            <div class="quiz-attempt-hero__student">
                <div class="quiz-attempt-hero__avatar" aria-hidden="true"><?= e($initials) ?></div>
                <div>
                    <p class="quiz-attempt-hero__quiz"><?= e($attempt['quiz_title']) ?></p>
                    <h2 class="quiz-attempt-hero__name"><?= e($studentName) ?></h2>
                    <p class="quiz-attempt-hero__meta text-muted">
                        <?= e($attempt['email']) ?>
                        <?php if (!empty($attempt['submitted_at'])): ?>
                            · Submitted <?= formatDate($attempt['submitted_at']) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="quiz-attempt-hero__score" aria-label="Total score">
                <div class="quiz-attempt-score-ring" style="--score-pct: <?= (int) $scorePct ?>">
                    <span class="quiz-attempt-score-ring__value">
                        <?= $score !== null ? e(rtrim(rtrim(number_format($score, 2, '.', ''), '0'), '.')) : '—' ?>
                    </span>
                    <span class="quiz-attempt-score-ring__max">/ <?= e(rtrim(rtrim(number_format($maxScore, 2, '.', ''), '0'), '.')) ?></span>
                </div>
                <span class="quiz-attempt-hero__score-label">Total score</span>
            </div>
        </div>
    </header>

    <section class="quiz-review-questions" aria-label="Question responses">
        <div class="quiz-review-questions__toolbar">
            <span class="quiz-review-questions__count"><?= count($answers) ?> question<?= count($answers) !== 1 ? 's' : '' ?></span>
            <div class="quiz-review-questions__actions">
                <button type="button" class="btn btn-sm btn-secondary" data-quiz-review-toggle="expand">Expand all</button>
                <button type="button" class="btn btn-sm btn-secondary" data-quiz-review-toggle="collapse">Collapse all</button>
            </div>
        </div>

        <?php foreach ($answers as $i => $a):
            $qPoints = (float) $a['points'];
            $earned = $a['points_earned'] !== null ? (float) $a['points_earned'] : null;
            $scoreClass = quizReviewScoreClass($earned, $qPoints);
            $type = normalizeQuestionType((string) $a['type']);
            $needsGrading = isManualGradeQuestionType($type) && $earned === null;
            $openByDefault = $needsGrading;
        ?>
        <details class="quiz-review-question"<?= $openByDefault ? ' open' : '' ?>>
            <summary class="quiz-review-question__summary">
                <span class="quiz-review-question__chevron" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
                <span class="quiz-review-question__num">Q<?= $i + 1 ?></span>
                <span class="quiz-review-question__title"><?= e($a['question_text']) ?></span>
                <span class="quiz-review-question__type">
                    <i class="fa-solid <?= e(quizReviewTypeIcon($type)) ?>" aria-hidden="true"></i>
                    <?= e(questionTypeLabel($type)) ?>
                </span>
                <span class="quiz-review-score <?= e($scoreClass) ?>">
                    <?php if ($earned !== null): ?>
                        <span class="quiz-review-score__earned"><?= e(rtrim(rtrim(number_format($earned, 2, '.', ''), '0'), '.')) ?></span>
                        <span class="quiz-review-score__sep">/</span>
                        <span class="quiz-review-score__max"><?= e(rtrim(rtrim(number_format($qPoints, 2, '.', ''), '0'), '.')) ?></span>
                        <span class="quiz-review-score__unit">pts</span>
                    <?php else: ?>
                        <span class="quiz-review-score__pending">Pending</span>
                    <?php endif; ?>
                </span>
            </summary>

            <div class="quiz-review-question__body">
                <?php if (isManualGradeQuestionType($type)): ?>
                    <?php renderQuizAttemptAnswerReview($a, $attemptId); ?>
                    <form method="post" class="quiz-review-grade-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="answer_id" value="<?= (int) $a['id'] ?>">
                        <div class="quiz-review-grade-form__fields">
                            <div class="form-group">
                                <label>Points earned</label>
                                <input type="number" step="0.01" name="points_earned" class="form-control" value="<?= e($a['points_earned'] ?? '') ?>" max="<?= e($qPoints) ?>" min="0">
                            </div>
                            <div class="form-group quiz-review-grade-form__feedback">
                                <label>Feedback for student</label>
                                <textarea name="teacher_feedback" class="form-control" rows="2" placeholder="Optional"><?= e($a['teacher_feedback'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" aria-hidden="true"></i> Save grade</button>
                    </form>
                <?php else: ?>
                    <?php renderQuizAttemptAnswerReview($a, $attemptId); ?>
                <?php endif; ?>
            </div>
        </details>
        <?php endforeach; ?>
    </section>
</div>

<script>
(function () {
    var root = document.querySelector('.quiz-review-questions');
    if (!root) return;
    root.querySelectorAll('[data-quiz-review-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var open = btn.getAttribute('data-quiz-review-toggle') === 'expand';
            root.querySelectorAll('.quiz-review-question').forEach(function (el) {
                el.open = open;
            });
        });
    });
})();
</script>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
