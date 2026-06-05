<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('teacher');
requireSchoolActive();

$user = currentUser();
$quizId = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT q.*, c.name AS class_name FROM quizzes q
    INNER JOIN classes c ON c.id = q.class_id WHERE q.id = ? AND q.teacher_id = ?');
$stmt->execute([$quizId, $user['id']]);
$quiz = $stmt->fetch();

if (!$quiz) {
    flash('error', 'Quiz not found.');
    $returnClassId = (int) ($_GET['class_id'] ?? 0);
    redirect($returnClassId ? 'teacher/course.php?id=' . $returnClassId : 'teacher/dashboard.php');
}

$returnClassId = (int) ($_GET['class_id'] ?? $quiz['class_id']);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['form_action'] ?? '';

    if ($action === 'add_question') {
        $text = trim($_POST['question_text'] ?? '');
        $type = $_POST['type'] ?? 'mcq';
        $points = (float) ($_POST['points'] ?? 1);
        $correctAnswer = trim($_POST['correct_answer'] ?? '');

        if ($text === '') {
            $errors[] = 'Question text is required.';
        } else {
            $order = db()->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM quiz_questions WHERE quiz_id=?');
            $order->execute([$quizId]);
            $sortOrder = (int) $order->fetchColumn();

            db()->prepare('INSERT INTO quiz_questions (quiz_id, question_text, type, points, sort_order, correct_answer) VALUES (?,?,?,?,?,?)')
                ->execute([$quizId, $text, $type, $points, $sortOrder, $type === 'short_answer' ? $correctAnswer : null]);
            $questionId = (int) db()->lastInsertId();

            if ($type === 'mcq') {
                $options = $_POST['options'] ?? [];
                $correctIdx = (int) ($_POST['correct_option'] ?? 0);
                foreach ($options as $i => $optText) {
                    $optText = trim($optText);
                    if ($optText === '') continue;
                    db()->prepare('INSERT INTO quiz_options (question_id, option_text, is_correct) VALUES (?,?,?)')
                        ->execute([$questionId, $optText, $i === $correctIdx ? 1 : 0]);
                }
            } elseif ($type === 'true_false') {
                $correct = $_POST['tf_correct'] ?? 'true';
                db()->prepare('INSERT INTO quiz_options (question_id, option_text, is_correct) VALUES (?,?,?)')->execute([$questionId, 'True', $correct === 'true' ? 1 : 0]);
                db()->prepare('INSERT INTO quiz_options (question_id, option_text, is_correct) VALUES (?,?,?)')->execute([$questionId, 'False', $correct === 'false' ? 1 : 0]);
            }
            flash('success', 'Question added.');
            redirect('teacher/quiz-edit.php?id=' . $quizId);
        }
    } elseif ($action === 'delete_question') {
        $qid = (int) ($_POST['question_id'] ?? 0);
        db()->prepare('DELETE FROM quiz_questions WHERE id=? AND quiz_id=?')->execute([$qid, $quizId]);
        flash('success', 'Question deleted.');
        redirect('teacher/quiz-edit.php?id=' . $quizId);
    }
}

$questions = QuizRepository::questionsWithOptions($quizId);

$pageTitle = 'Edit Quiz Questions';
$pageHeading = $quiz['title'] . ' — Questions';
$activeMenu = 'dashboard';
$menuItems = teacherMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'teacher/dashboard.php'],
    ['label' => $quiz['class_name'], 'url' => 'teacher/course.php?id=' . $returnClassId],
    ['label' => $quiz['title'], 'url' => 'teacher/quiz-edit.php?id=' . $quizId . '&class_id=' . $returnClassId],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="actions mb-1">
    <a href="<?= teacherCourseUrl($returnClassId) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to course</a>
    <a href="<?= teacherCourseUrl($returnClassId, 'action=edit_quiz&item_id=' . $quizId) ?>" class="btn btn-secondary btn-sm">Quiz settings</a>
</div>

<div class="panel">
    <p><strong>Class:</strong> <?= e($quiz['class_name']) ?> · <strong>Total Points:</strong> <?= getQuizTotalPoints($quizId) ?> · <strong>Time Limit:</strong> <?= $quiz['time_limit_minutes'] ? e($quiz['time_limit_minutes']) . ' min' : 'None' ?></p>
</div>

<div class="panel">
    <h2>Add Question</h2>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" id="questionForm">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="add_question">
        <div class="form-group"><label>Question</label><textarea name="question_text" class="form-control" required></textarea></div>
        <div class="form-row">
            <div class="form-group">
                <label>Type</label>
                <select name="type" id="questionType" class="form-control" onchange="toggleQuestionFields()">
                    <option value="mcq">Multiple Choice</option>
                    <option value="true_false">True / False</option>
                    <option value="short_answer">Short Answer</option>
                </select>
            </div>
            <div class="form-group"><label>Points</label><input type="number" step="0.01" name="points" class="form-control" value="1"></div>
        </div>

        <div id="mcqFields">
            <label>Options (select correct answer)</label>
            <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="option-row">
                <input type="radio" name="correct_option" value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?>>
                <input type="text" name="options[]" class="form-control" placeholder="Option <?= $i + 1 ?>">
            </div>
            <?php endfor; ?>
        </div>

        <div id="tfFields" style="display:none">
            <div class="form-group">
                <label>Correct Answer</label>
                <select name="tf_correct" class="form-control">
                    <option value="true">True</option>
                    <option value="false">False</option>
                </select>
            </div>
        </div>

        <div id="saFields" style="display:none">
            <div class="form-group">
                <label>Expected Answer (for reference; graded manually)</label>
                <input type="text" name="correct_answer" class="form-control">
            </div>
        </div>

        <button type="submit" class="btn btn-primary mt-1">Add Question</button>
    </form>
</div>

<h2 style="margin-bottom:1rem;">Questions (<?= count($questions) ?>)</h2>

<?php if (empty($questions)): ?>
    <p class="text-muted">No questions yet.</p>
<?php else: foreach ($questions as $i => $q): ?>
<div class="question-block">
    <div class="panel-header">
        <strong>Q<?= $i + 1 ?>. <?= e($q['question_text']) ?></strong>
        <span class="badge badge-submitted"><?= e(str_replace('_', ' ', $q['type'])) ?> · <?= e($q['points']) ?> pts</span>
    </div>
    <?php if (!empty($q['options'])): ?>
        <ul style="margin:.5rem 0;padding-left:1.25rem;">
            <?php foreach ($q['options'] as $opt): ?>
                <li><?= e($opt['option_text']) ?><?= $opt['is_correct'] ? ' ✓' : '' ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($q['type'] === 'short_answer' && $q['correct_answer']): ?>
        <p class="text-muted">Expected: <?= e($q['correct_answer']) ?></p>
    <?php endif; ?>
    <form method="post" onsubmit="return confirm('Delete question?')">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="delete_question">
        <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
        <button class="btn btn-sm btn-danger">Delete</button>
    </form>
</div>
<?php endforeach; endif; ?>

<script>
function toggleQuestionFields() {
    var type = document.getElementById('questionType').value;
    document.getElementById('mcqFields').style.display = type === 'mcq' ? 'block' : 'none';
    document.getElementById('tfFields').style.display = type === 'true_false' ? 'block' : 'none';
    document.getElementById('saFields').style.display = type === 'short_answer' ? 'block' : 'none';
}
</script>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
