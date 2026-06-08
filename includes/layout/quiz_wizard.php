<?php
/**
 * Quiz builder wizard (embedded in course page).
 * Expects $quizWizardCtx from loadQuizWizardContext().
 */
$quizId = (int) $quizWizardCtx['quizId'];
$classId = (int) $quizWizardCtx['classId'];
$quiz = $quizWizardCtx['quiz'];
$step = $quizWizardCtx['step'];
$errors = $quizWizardCtx['errors'];
$editQuestion = $quizWizardCtx['editQuestion'];
$questions = $quizWizardCtx['questions'];
$questionCount = (int) $quizWizardCtx['questionCount'];
$totalPoints = $quizWizardCtx['totalPoints'];
$sections = $quizWizardCtx['sections'];
$courseUrl = teacherCourseUrl($classId);

$mcqChoices = ['', ''];
$mcqCorrectIndex = 0;
$matchingPairs = [['left' => '', 'right' => ''], ['left' => '', 'right' => '']];
$fillBlankSegments = [['type' => 'text', 'value' => '']];
$fillBlankScoringMode = 'partial';
$tfCorrect = 'true';

if ($editQuestion) {
    $editType = normalizeQuestionType($editQuestion['type']);
    if ($editType === 'multiple_choice' && !empty($editQuestion['options'])) {
        $mcqChoices = [];
        $mcqCorrectIndex = 0;
        foreach ($editQuestion['options'] as $i => $opt) {
            $mcqChoices[] = $opt['option_text'];
            if (!empty($opt['is_correct'])) {
                $mcqCorrectIndex = $i;
            }
        }
        if (count($mcqChoices) < 2) {
            $mcqChoices = array_pad($mcqChoices, 2, '');
        }
    } elseif ($editType === 'true_false' && !empty($editQuestion['options'])) {
        foreach ($editQuestion['options'] as $opt) {
            if ($opt['option_text'] === 'True' && !empty($opt['is_correct'])) {
                $tfCorrect = 'true';
            } elseif ($opt['option_text'] === 'False' && !empty($opt['is_correct'])) {
                $tfCorrect = 'false';
            }
        }
    } elseif ($editType === 'matching') {
        $m = $editQuestion['settings']['matching'] ?? ['left' => [], 'right' => []];
        $matchingPairs = [];
        foreach ($m['left'] ?? [] as $i => $left) {
            $matchingPairs[] = ['left' => $left, 'right' => $m['right'][$i] ?? ''];
        }
        if (count($matchingPairs) < 2) {
            $matchingPairs = array_pad($matchingPairs, 2, ['left' => '', 'right' => '']);
        }
    } elseif ($editType === 'fill_blank') {
        $fillBlankSegments = quizFillBlankBuilderSegments(
            (string) $editQuestion['question_text'],
            $editQuestion['settings']['blanks'] ?? []
        );
        $fillBlankScoringMode = ($editQuestion['settings']['scoring_mode'] ?? 'partial') === 'all_or_nothing'
            ? 'all_or_nothing'
            : 'partial';
    }
}
?>
<div class="quiz-wizard">
    <div class="quiz-wizard-top">
        <div class="quiz-wizard-heading">
            <a href="<?= e($courseUrl) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to course content</a>
            <div>
                <h2 class="quiz-wizard-title"><?= e($quiz['title']) ?></h2>
                <p class="text-muted quiz-wizard-subtitle"><?= $questionCount ?> question<?= $questionCount !== 1 ? 's' : '' ?> · <?= e($totalPoints) ?> pts<?php if (empty($quiz['is_published'])): ?> · <span class="quiz-wizard-draft">Draft</span><?php endif; ?></p>
            </div>
        </div>
        <a href="<?= url('teacher/quiz-attempts.php?quiz_id=' . $quizId . '&class_id=' . $classId) ?>" class="btn btn-secondary btn-sm">View attempts</a>
    </div>

    <nav class="quiz-wizard-steps" aria-label="Quiz setup progress">
        <div class="quiz-wizard-step is-complete">
            <span class="quiz-wizard-step-num"><i class="fa-solid fa-check"></i></span>
            <span class="quiz-wizard-step-label">Create quiz</span>
        </div>
        <span class="quiz-wizard-step-line" aria-hidden="true"></span>
        <a href="<?= e(quizEditUrl($quizId, $classId, 'questions')) ?>" class="quiz-wizard-step<?= $step === 'questions' ? ' is-active' : ($questionCount > 0 ? ' is-complete' : '') ?>">
            <span class="quiz-wizard-step-num"><?= $questionCount > 0 && $step !== 'questions' ? '<i class="fa-solid fa-check"></i>' : '2' ?></span>
            <span class="quiz-wizard-step-label">Add questions</span>
        </a>
        <span class="quiz-wizard-step-line" aria-hidden="true"></span>
        <a href="<?= e(quizEditUrl($quizId, $classId, 'settings')) ?>" class="quiz-wizard-step<?= $step === 'settings' ? ' is-active' : '' ?>">
            <span class="quiz-wizard-step-num">3</span>
            <span class="quiz-wizard-step-label">Configure &amp; publish</span>
        </a>
    </nav>

    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

    <?php if ($step === 'questions'): ?>
    <div class="quiz-wizard-panel">
        <div class="quiz-wizard-panel-head">
            <div>
                <h3><?= $editQuestion ? 'Edit question' : 'Add question' ?></h3>
                <p class="text-muted">Build your quiz one question at a time. When you are done, continue to configure the timer, schedule, and publishing options.</p>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data" id="questionForm" class="quiz-question-form">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="save_question">
            <input type="hidden" name="quiz_id" value="<?= $quizId ?>">
            <?php if ($editQuestion): ?><input type="hidden" name="question_id" value="<?= (int) $editQuestion['id'] ?>"><?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" id="questionType" class="form-control">
                        <?php foreach (QUIZ_QUESTION_TYPE_LABELS as $val => $label): ?>
                        <option value="<?= e($val) ?>"<?= ($editQuestion['type'] ?? 'multiple_choice') === $val ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Points</label><input type="number" step="0.01" name="points" class="form-control" value="<?= e($editQuestion['points'] ?? '1') ?>"></div>
            </div>

            <div class="form-group" id="questionTextGroup">
                <label for="questionText">Question</label>
                <textarea name="question_text" id="questionText" class="form-control" rows="3" required><?= e($editQuestion['question_text'] ?? '') ?></textarea>
                <small class="text-muted" id="questionTextHint">Enter the question prompt students will see.</small>
            </div>

            <div id="mcqFields" class="quiz-type-fields" data-choices="<?= e(json_encode($mcqChoices, JSON_UNESCAPED_UNICODE)) ?>" data-correct="<?= (int) $mcqCorrectIndex ?>">
                <div class="quiz-builder-head">
                    <label>Answer choices</label>
                    <button type="button" class="btn btn-secondary btn-sm" id="addMcqChoice"><i class="fa-solid fa-plus"></i> Add choice</button>
                </div>
                <p class="text-muted quiz-builder-help">Select the radio button next to the correct answer. Minimum 2 choices, up to 10.</p>
                <div id="mcqChoicesList" class="quiz-mcq-list"></div>
            </div>

            <div id="tfFields" class="quiz-type-fields" style="display:none">
                <div class="form-group">
                    <label>Correct answer</label>
                    <select name="tf_correct" class="form-control">
                        <option value="true"<?= $tfCorrect === 'true' ? ' selected' : '' ?>>True is correct</option>
                        <option value="false"<?= $tfCorrect === 'false' ? ' selected' : '' ?>>False is correct</option>
                    </select>
                </div>
            </div>

            <div id="essayFields" class="quiz-type-fields" style="display:none">
                <div class="form-group"><label>Rubric (optional)</label><textarea name="essay_rubric" class="form-control" rows="2"><?= e($editQuestion['settings']['rubric'] ?? '') ?></textarea></div>
            </div>

            <div id="fillBlankFields" class="quiz-type-fields" style="display:none">
                <div class="quiz-builder-head">
                    <label>Sentence with blanks</label>
                    <div class="quiz-fib-toolbar">
                        <button type="button" class="btn btn-primary btn-sm" id="fibInsertBlank"><i class="fa-solid fa-square"></i> Insert blank here</button>
                    </div>
                </div>
                <p class="text-muted quiz-builder-help">Type your sentence in the line below. Click where a blank should go, then press <strong>Insert blank here</strong> — the answer box appears inline in the sentence. You can keep typing after each blank.</p>
                <div id="fillBlankBuilder" class="quiz-fib-builder" data-segments="<?= e(json_encode($fillBlankSegments, JSON_UNESCAPED_UNICODE)) ?>" tabindex="0" aria-label="Sentence builder"></div>
                <div class="form-group quiz-fib-scoring-mode">
                    <label for="fillBlankScoringMode">How should this question be scored?</label>
                    <select name="fill_blank_scoring_mode" id="fillBlankScoringMode" class="form-control">
                        <option value="partial"<?= $fillBlankScoringMode === 'partial' ? ' selected' : '' ?>>Partial credit — question points are split across blanks</option>
                        <option value="all_or_nothing"<?= $fillBlankScoringMode === 'all_or_nothing' ? ' selected' : '' ?>>All or nothing — full points only if every blank is correct</option>
                    </select>
                    <small class="text-muted">The <strong>Points</strong> field above is the total for this question.</small>
                </div>
            </div>

            <div id="matchingFields" class="quiz-type-fields" style="display:none">
                <div class="quiz-builder-head">
                    <label>Matching pairs</label>
                    <button type="button" class="btn btn-secondary btn-sm" id="addMatchingRow"><i class="fa-solid fa-plus"></i> Add pair</button>
                </div>
                <p class="text-muted quiz-builder-help">Each row is one pair. Students will match prompts on the left to answers on the right.</p>
                <div class="quiz-matching-table-wrap">
                    <table class="quiz-matching-table">
                        <thead>
                            <tr>
                                <th>Prompt (left)</th>
                                <th>Answer (right)</th>
                                <th aria-label="Actions"></th>
                            </tr>
                        </thead>
                        <tbody id="matchingRowsList" data-pairs="<?= e(json_encode($matchingPairs, JSON_UNESCAPED_UNICODE)) ?>"></tbody>
                    </table>
                </div>
            </div>

            <div id="fileResponseFields" class="quiz-type-fields" style="display:none">
                <div class="form-group"><label>Template file (optional)</label><input type="file" name="teacher_attachment" class="form-control"></div>
            </div>

            <div class="quiz-wizard-form-actions">
                <button type="submit" class="btn btn-primary"><?= $editQuestion ? 'Update question' : 'Add question' ?></button>
                <?php if ($editQuestion): ?><a href="<?= e(quizEditUrl($quizId, $classId, 'questions')) ?>" class="btn btn-secondary">Cancel edit</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="quiz-wizard-panel quiz-wizard-panel--list">
        <h3>Questions (<?= $questionCount ?>) · <?= e($totalPoints) ?> pts</h3>
        <?php if ($questionCount === 0): ?>
            <div class="quiz-wizard-empty">
                <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
                <p>No questions yet. Add your first question above.</p>
            </div>
        <?php else: ?>
            <?php foreach ($questions as $i => $q): ?>
            <div class="question-block quiz-edit-question">
                <div class="panel-header">
                    <strong>Q<?= $i + 1 ?>. <?= e($q['question_text']) ?></strong>
                    <span class="badge badge-submitted"><?= e(questionTypeLabel($q['type'])) ?> · <?= e($q['points']) ?> pts</span>
                </div>
                <?php if (!empty($q['options'])): ?>
                <ul class="quiz-edit-options"><?php foreach ($q['options'] as $opt): ?><li><?= e($opt['option_text']) ?><?= $opt['is_correct'] ? ' ✓' : '' ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <div class="actions">
                    <a href="<?= e(quizEditUrl($quizId, $classId, 'questions', (int) $q['id'])) ?>" class="btn btn-sm btn-secondary">Edit</a>
                    <form method="post" class="inline-form"><?= csrfField() ?><input type="hidden" name="form_action" value="reorder_question"><input type="hidden" name="quiz_id" value="<?= $quizId ?>"><input type="hidden" name="question_id" value="<?= $q['id'] ?>"><input type="hidden" name="direction" value="up"><button class="btn btn-sm btn-secondary">↑</button></form>
                    <form method="post" class="inline-form"><?= csrfField() ?><input type="hidden" name="form_action" value="reorder_question"><input type="hidden" name="quiz_id" value="<?= $quizId ?>"><input type="hidden" name="question_id" value="<?= $q['id'] ?>"><input type="hidden" name="direction" value="down"><button class="btn btn-sm btn-secondary">↓</button></form>
                    <form method="post" onsubmit="return confirm('Delete?')"><?= csrfField() ?><input type="hidden" name="form_action" value="delete_question"><input type="hidden" name="quiz_id" value="<?= $quizId ?>"><input type="hidden" name="question_id" value="<?= $q['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="quiz-wizard-footer">
        <a href="<?= e($courseUrl) ?>" class="btn btn-secondary">Save for later</a>
        <a href="<?= e(quizEditUrl($quizId, $classId, 'settings')) ?>" class="btn btn-primary">
            Continue to settings <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>

    <?php else: ?>
    <div class="quiz-wizard-panel quiz-settings-panel">
        <div class="quiz-wizard-panel-head">
            <div>
                <h3>Quiz configuration</h3>
                <p class="text-muted">Set the schedule, time limit, attempts, and visibility. Publish when you are ready for students to take the quiz.</p>
            </div>
        </div>

        <?php if ($questionCount === 0): ?>
            <div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation"></i> This quiz has no questions yet. <a href="<?= e(quizEditUrl($quizId, $classId, 'questions')) ?>">Add questions</a> before publishing.</div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="quiz-settings-form">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="save_settings">
            <input type="hidden" name="quiz_id" value="<?= $quizId ?>">

            <fieldset class="quiz-settings-group">
                <legend>Basic details</legend>
                <div class="form-group"><label>Quiz title</label><input name="title" class="form-control" value="<?= e($quiz['title']) ?>" required></div>
                <?php if (!empty($sections)): ?>
                <div class="form-group">
                    <label>Lesson section</label>
                    <select name="section_id" class="form-control">
                        <?= courseSectionOptions($sections, (int) ($quiz['section_id'] ?? 0) ?: null) ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="form-group"><label>Instructions for students</label><textarea name="instructions" class="form-control" rows="3" placeholder="Optional directions shown before students start the quiz"><?= e($quiz['instructions'] ?? '') ?></textarea></div>
                <div class="form-group"><label>Cover image</label><input type="file" name="cover_image" class="form-control" accept="image/*"><?php if (!empty($quiz['cover_image'])): ?><img src="<?= e(quizCoverServeUrl($quiz['cover_image'])) ?>" alt="" class="quiz-cover-preview"><?php endif; ?></div>
            </fieldset>

            <fieldset class="quiz-settings-group">
                <legend>Schedule</legend>
                <div class="form-row">
                    <div class="form-group"><label>Opens at</label><input type="datetime-local" name="opens_at" class="form-control" value="<?= !empty($quiz['opens_at']) ? e(date('Y-m-d\TH:i', strtotime($quiz['opens_at']))) : '' ?>"><small class="text-muted">When students can start</small></div>
                    <div class="form-group"><label>Closes at</label><input type="datetime-local" name="closes_at" class="form-control" value="<?= !empty($quiz['closes_at']) ? e(date('Y-m-d\TH:i', strtotime($quiz['closes_at']))) : '' ?>"><small class="text-muted">When access ends</small></div>
                    <div class="form-group"><label>Due date</label><input type="datetime-local" name="due_date" class="form-control" value="<?= !empty($quiz['due_date']) ? e(date('Y-m-d\TH:i', strtotime($quiz['due_date']))) : '' ?>"><small class="text-muted">Shown on course timeline</small></div>
                </div>
            </fieldset>

            <fieldset class="quiz-settings-group">
                <legend>Attempts &amp; timer</legend>
                <div class="form-row">
                    <div class="form-group"><label>Time limit (minutes)</label><input type="number" name="time_limit_minutes" class="form-control" value="<?= e($quiz['time_limit_minutes'] ?? '') ?>" placeholder="Leave blank for no limit"></div>
                    <div class="form-group"><label>Max attempts</label><input type="number" name="max_attempts" class="form-control" value="<?= e($quiz['max_attempts']) ?>" min="1"></div>
                </div>
            </fieldset>

            <fieldset class="quiz-settings-group">
                <legend>Student experience</legend>
                <div class="form-check"><input type="checkbox" name="randomize_questions_order" id="rand" <?= !empty($quiz['randomize_questions_order']) ? 'checked' : '' ?>><label for="rand">Randomize question order per attempt</label></div>
                <div class="form-check"><input type="checkbox" name="show_score_to_students" id="showscore" <?= !empty($quiz['show_score_to_students']) ? 'checked' : '' ?>><label for="showscore">Show score to students after submission</label></div>
                <div class="form-check quiz-settings-publish"><input type="checkbox" name="is_published" id="pub" <?= !empty($quiz['is_published']) ? 'checked' : '' ?>><label for="pub"><strong>Publish quiz</strong> — students can see and take this quiz when schedule allows</label></div>
            </fieldset>

            <div class="quiz-wizard-footer quiz-wizard-footer--form">
                <a href="<?= e(quizEditUrl($quizId, $classId, 'questions')) ?>" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to questions</a>
                <div class="quiz-wizard-footer-actions">
                    <button type="submit" class="btn btn-secondary">Save settings</button>
                    <button type="submit" name="finish" value="1" class="btn btn-primary"<?= $questionCount === 0 ? ' disabled title="Add at least one question first"' : '' ?>><i class="fa-solid fa-check"></i> Save &amp; return to course</button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php if ($step === 'questions'): ?>
<script src="<?= url('assets/js/quiz-question-builder.js') ?>"></script>
<?php endif; ?>
