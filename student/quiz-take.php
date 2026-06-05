<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('student');
requireSchoolActive();

$user = currentUser();
$quizId = (int) ($_GET['quiz_id'] ?? 0);

$stmt = db()->prepare('SELECT q.* FROM quizzes q
    INNER JOIN class_students cs ON cs.class_id = q.class_id AND cs.student_id = ?
    WHERE q.id = ?');
$stmt->execute([$user['id'], $quizId]);
$quiz = $stmt->fetch();

if (!$quiz) {
    flash('error', 'Quiz not found.');
    redirect('student/quizzes.php');
}

$can = canStudentTakeQuiz($quiz, $user['id']);

$attempt = db()->prepare("SELECT * FROM quiz_attempts WHERE quiz_id=? AND student_id=? AND status='in_progress' ORDER BY id DESC LIMIT 1");
$attempt->execute([$quizId, $user['id']]);
$attempt = $attempt->fetch();

if (!$attempt && !$can['ok']) {
    flash('error', $can['reason'] ?? 'Cannot take this quiz.');
    redirect('student/quizzes.php');
}

if (!$attempt) {
    db()->prepare('INSERT INTO quiz_attempts (quiz_id, student_id) VALUES (?, ?)')->execute([$quizId, $user['id']]);
    $attemptId = (int) db()->lastInsertId();

    $questions = db()->prepare('SELECT id FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order, id');
    $questions->execute([$quizId]);
    foreach ($questions->fetchAll() as $q) {
        db()->prepare('INSERT INTO quiz_attempt_answers (attempt_id, question_id) VALUES (?, ?)')->execute([$attemptId, $q['id']]);
    }

    $attempt = db()->prepare('SELECT * FROM quiz_attempts WHERE id = ?');
    $attempt->execute([$attemptId]);
    $attempt = $attempt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['form_action'] ?? '';

    if ($action === 'submit') {
        $answers = db()->prepare('SELECT qaa.*, qq.type FROM quiz_attempt_answers qaa
            INNER JOIN quiz_questions qq ON qq.id = qaa.question_id
            WHERE qaa.attempt_id = ?');
        $answers->execute([$attempt['id']]);
        $answerRows = $answers->fetchAll();

        foreach ($answerRows as $ans) {
            $qid = $ans['question_id'];
            if ($ans['type'] === 'short_answer') {
                $text = trim($_POST['answer_' . $qid] ?? '');
                db()->prepare('UPDATE quiz_attempt_answers SET answer_text=? WHERE attempt_id=? AND question_id=?')
                    ->execute([$text, $attempt['id'], $qid]);
            } else {
                $optId = (int) ($_POST['answer_' . $qid] ?? 0);
                db()->prepare('UPDATE quiz_attempt_answers SET selected_option_id=? WHERE attempt_id=? AND question_id=?')
                    ->execute([$optId ?: null, $attempt['id'], $qid]);
            }
        }

        db()->prepare("UPDATE quiz_attempts SET submitted_at=NOW(), status='submitted' WHERE id=?")->execute([$attempt['id']]);
        gradeQuizAttempt((int) $attempt['id']);
        flash('success', 'Quiz submitted!');
        redirect('student/quiz-results.php?attempt_id=' . $attempt['id']);
    }
}

$questions = QuizRepository::questionsWithOptions($quizId);

foreach ($questions as &$q) {
    $existing = db()->prepare('SELECT selected_option_id, answer_text FROM quiz_attempt_answers WHERE attempt_id = ? AND question_id = ?');
    $existing->execute([$attempt['id'], $q['id']]);
    $saved = $existing->fetch();
    $q['selected_option_id'] = $saved['selected_option_id'] ?? null;
    $q['answer_text'] = $saved['answer_text'] ?? '';
}
unset($q);

$timeLimitSec = $quiz['time_limit_minutes'] ? $quiz['time_limit_minutes'] * 60 : null;
$elapsed = time() - strtotime($attempt['started_at']);

$pageTitle = $quiz['title'];
$pageHeading = $quiz['title'];
$activeMenu = 'quizzes';
$menuItems = studentMenu();

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<?php if ($quiz['instructions']): ?>
<div class="panel"><p><?= nl2br(e($quiz['instructions'])) ?></p></div>
<?php endif; ?>

<?php if ($timeLimitSec): ?>
<div class="alert alert-warning" id="timer">Time remaining: <span id="timeLeft"></span></div>
<?php endif; ?>

<form method="post" id="quizForm">
    <?= csrfField() ?>
    <input type="hidden" name="form_action" value="submit">

    <?php foreach ($questions as $i => $q): ?>
    <div class="question-block">
        <strong>Q<?= $i + 1 ?>. <?= e($q['question_text']) ?></strong>
        <span class="badge badge-submitted"><?= e($q['points']) ?> pts</span>

        <?php if ($q['type'] === 'mcq' || $q['type'] === 'true_false'): ?>
            <?php foreach ($q['options'] as $opt): ?>
            <div class="form-check">
                <input type="radio" name="answer_<?= $q['id'] ?>" id="opt<?= $opt['id'] ?>" value="<?= $opt['id'] ?>" <?= (int)$q['selected_option_id'] === (int)$opt['id'] ? 'checked' : '' ?>>
                <label for="opt<?= $opt['id'] ?>"><?= e($opt['option_text']) ?></label>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="form-group mt-1">
                <textarea name="answer_<?= $q['id'] ?>" class="form-control"><?= e($q['answer_text'] ?? '') ?></textarea>
            </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if (empty($questions)): ?>
        <p class="text-muted">This quiz has no questions yet.</p>
    <?php else: ?>
        <button type="submit" class="btn btn-primary" onclick="return confirm('Submit quiz? You cannot change answers after submitting.')">Submit Quiz</button>
    <?php endif; ?>
</form>

<?php if ($timeLimitSec): ?>
<script>
(function() {
    var remaining = <?= max(0, $timeLimitSec - $elapsed) ?>;
    var el = document.getElementById('timeLeft');
    var form = document.getElementById('quizForm');
    function tick() {
        if (remaining <= 0) {
            el.textContent = '0:00';
            form.submit();
            return;
        }
        var m = Math.floor(remaining / 60);
        var s = remaining % 60;
        el.textContent = m + ':' + (s < 10 ? '0' : '') + s;
        remaining--;
        setTimeout(tick, 1000);
    }
    tick();
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
