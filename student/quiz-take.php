<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('student');
requireSchoolActive();

$user = currentUser();
$quizId = (int) ($_GET['quiz_id'] ?? 0);

$stmt = db()->prepare('SELECT q.* FROM quizzes q
    INNER JOIN classes c ON c.id = q.class_id
    INNER JOIN class_group_students cgs ON cgs.class_group_id = c.class_group_id AND cgs.student_id = ?
    WHERE q.id = ?');
$stmt->execute([$user['id'], $quizId]);
$quiz = $stmt->fetch();

if (!$quiz) {
    flash('error', 'Quiz not found.');
    redirect('student/classes.php');
}

$can = canStudentTakeQuiz($quiz, $user['id']);

$attempt = db()->prepare("SELECT * FROM quiz_attempts WHERE quiz_id=? AND student_id=? AND status='in_progress' ORDER BY id DESC LIMIT 1");
$attempt->execute([$quizId, $user['id']]);
$attempt = $attempt->fetch();

if (!$attempt && !$can['ok']) {
    flash('error', $can['reason'] ?? 'Cannot take this quiz.');
    redirect('student/course.php?id=' . (int) $quiz['class_id']);
}

if (!$attempt) {
    $attemptId = startQuizAttempt($quizId, (int) $user['id'], !empty($quiz['randomize_questions_order']));
    $attempt = db()->prepare('SELECT * FROM quiz_attempts WHERE id = ?');
    $attempt->execute([$attemptId]);
    $attempt = $attempt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (($_POST['form_action'] ?? '') === 'submit') {
        $answers = db()->prepare('SELECT qaa.id, qaa.question_id, qq.type FROM quiz_attempt_answers qaa
            INNER JOIN quiz_questions qq ON qq.id = qaa.question_id
            WHERE qaa.attempt_id = ?');
        $answers->execute([$attempt['id']]);

        foreach ($answers->fetchAll() as $ans) {
            $qid = (int) $ans['question_id'];
            $type = normalizeQuestionType($ans['type']);

            if ($type === 'multiple_choice' || $type === 'true_false') {
                $optId = (int) ($_POST['answer_' . $qid] ?? 0);
                db()->prepare('UPDATE quiz_attempt_answers SET selected_option_id=?, answer_text=NULL, response_payload=NULL WHERE attempt_id=? AND question_id=?')
                    ->execute([$optId ?: null, $attempt['id'], $qid]);
            } elseif ($type === 'essay') {
                $text = trim($_POST['answer_' . $qid] ?? '');
                db()->prepare('UPDATE quiz_attempt_answers SET answer_text=?, selected_option_id=NULL WHERE attempt_id=? AND question_id=?')
                    ->execute([$text, $attempt['id'], $qid]);
            } elseif ($type === 'fill_blank') {
                $qRow = db()->prepare('SELECT settings FROM quiz_questions WHERE id=?');
                $qRow->execute([$qid]);
                $settings = decodeQuestionSettings($qRow->fetchColumn() ?: null);
                $n = count($settings['blanks'] ?? []);
                $blanks = [];
                for ($i = 0; $i < $n; $i++) {
                    $blanks[$i] = trim($_POST['blank_' . $qid . '_' . $i] ?? '');
                }
                db()->prepare('UPDATE quiz_attempt_answers SET response_payload=?, answer_text=NULL, selected_option_id=NULL WHERE attempt_id=? AND question_id=?')
                    ->execute([json_encode(['blanks' => $blanks]), $attempt['id'], $qid]);
            } elseif ($type === 'matching') {
                $qRow = db()->prepare('SELECT settings FROM quiz_questions WHERE id=?');
                $qRow->execute([$qid]);
                $settings = decodeQuestionSettings($qRow->fetchColumn() ?: null);
                $n = count($settings['matching']['left'] ?? []);
                $map = [];
                for ($i = 0; $i < $n; $i++) {
                    $map[$i] = $_POST['matching_' . $qid . '_' . $i] ?? '';
                }
                db()->prepare('UPDATE quiz_attempt_answers SET response_payload=?, answer_text=NULL, selected_option_id=NULL WHERE attempt_id=? AND question_id=?')
                    ->execute([json_encode(['matching' => $map]), $attempt['id'], $qid]);
            } elseif ($type === 'file_response') {
                $fileKey = 'file_answer_' . $qid;
                if (!empty($_FILES[$fileKey]['name'])) {
                    try {
                        $path = uploadFile($_FILES[$fileKey], schoolId() . '/quiz_submissions');
                        db()->prepare('UPDATE quiz_attempt_answers SET student_attachment_path=?, answer_text=? WHERE attempt_id=? AND question_id=?')
                            ->execute([$path, $_FILES[$fileKey]['name'] ?? 'upload', $attempt['id'], $qid]);
                    } catch (RuntimeException $e) {
                        flash('error', $e->getMessage());
                        redirect('student/quiz-take.php?quiz_id=' . $quizId);
                    }
                }
            }
        }

        db()->prepare("UPDATE quiz_attempts SET submitted_at=NOW(), status='submitted' WHERE id=?")->execute([$attempt['id']]);
        gradeQuizAttempt((int) $attempt['id']);
        flash('success', 'Quiz submitted!');
        redirect('student/quiz-results.php?attempt_id=' . $attempt['id']);
    }
}

$questions = QuizRepository::questionsForAttemptView((int) $attempt['id']);

$timeLimitSec = $quiz['time_limit_minutes'] ? $quiz['time_limit_minutes'] * 60 : null;
$elapsed = time() - strtotime($attempt['started_at']);
$coverUrl = quizCoverServeUrl($quiz['cover_image'] ?? null);

$pageTitle = $quiz['title'];
$pageHeading = $quiz['title'];
$activeMenu = 'classes';
$menuItems = studentMenu();

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<?php if ($coverUrl): ?>
<div class="quiz-take-cover" style="background-image:url('<?= e($coverUrl) ?>')"></div>
<?php endif; ?>

<?php if ($quiz['instructions']): ?>
<div class="panel"><p><?= nl2br(e($quiz['instructions'])) ?></p></div>
<?php endif; ?>

<?php if ($timeLimitSec): ?>
<div class="alert alert-warning" id="timer">Time remaining: <span id="timeLeft"></span></div>
<?php endif; ?>

<form method="post" id="quizForm" enctype="multipart/form-data">
    <?= csrfField() ?>
    <input type="hidden" name="form_action" value="submit">

    <?php foreach ($questions as $i => $q): ?>
        <?php renderQuizTakeQuestion($q, $i); ?>
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
        if (remaining <= 0) { el.textContent = '0:00'; form.submit(); return; }
        var m = Math.floor(remaining / 60), s = remaining % 60;
        el.textContent = m + ':' + (s < 10 ? '0' : '') + s;
        remaining--;
        setTimeout(tick, 1000);
    }
    tick();
})();
</script>
<?php endif; ?>

<script src="<?= url('assets/js/quiz-matching-take.js') ?>"></script>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
