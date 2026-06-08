<?php
/**
 * Renders a single quiz question for students (take view).
 */
function renderQuizTakeQuestion(array $q, int $index): void
{
    $type = normalizeQuestionType($q['type']);
    $settings = $q['settings_decoded'] ?? $q['settings'] ?? [];
    $payload = decodeAnswerPayload($q['response_payload'] ?? null);
    ?>
    <div class="question-block quiz-question-block quiz-question-block--<?= e($type) ?>" data-qid="<?= (int) $q['id'] ?>" data-qtype="<?= e($type) ?>">
        <div class="quiz-question-head">
            <strong>Q<?= $index + 1 ?>. <?= e($q['question_text']) ?></strong>
            <span class="badge badge-submitted"><?= e($q['points']) ?> pts</span>
        </div>

        <?php if ($type === 'multiple_choice' || $type === 'true_false'): ?>
            <?php foreach ($q['options'] as $opt): ?>
            <div class="form-check">
                <input type="radio" name="answer_<?= $q['id'] ?>" id="opt<?= $opt['id'] ?>" value="<?= $opt['id'] ?>" <?= (int)($q['selected_option_id'] ?? 0) === (int)$opt['id'] ? 'checked' : '' ?>>
                <label for="opt<?= $opt['id'] ?>"><?= e($opt['option_text']) ?></label>
            </div>
            <?php endforeach; ?>

        <?php elseif ($type === 'essay'): ?>
            <div class="form-group mt-1">
                <textarea name="answer_<?= $q['id'] ?>" class="form-control" rows="5" placeholder="Your answer"><?= e($q['answer_text'] ?? '') ?></textarea>
            </div>

        <?php elseif ($type === 'fill_blank'): ?>
            <div class="quiz-fill-blank">
                <?php
                $parts = preg_split('/(___)/', $q['question_text'], -1, PREG_SPLIT_DELIM_CAPTURE);
                $blankIdx = 0;
                foreach ($parts as $part):
                    if ($part === '___'):
                        $val = $payload['blanks'][$blankIdx] ?? '';
                ?>
                    <input type="text" name="blank_<?= $q['id'] ?>_<?= $blankIdx ?>" class="form-control quiz-blank-input" value="<?= e($val) ?>" aria-label="Blank <?= $blankIdx + 1 ?>">
                <?php
                        $blankIdx++;
                    else:
                        echo '<span class="quiz-fill-text">' . e($part) . '</span>';
                    endif;
                endforeach;
                ?>
            </div>

        <?php elseif ($type === 'matching'): ?>
            <?php
            $m = $settings['matching'] ?? ['left' => [], 'right' => [], 'correct_map' => []];
            $left = $m['left'] ?? [];
            $right = $m['right'] ?? [];
            $shuffled = $right;
            if (!empty($q['matching_shuffle'])) {
                $shuffled = $q['matching_shuffle'];
            }
            $saved = $payload['matching'] ?? [];
            ?>
            <div class="quiz-matching-connect" data-qid="<?= (int) $q['id'] ?>">
                <p class="text-muted quiz-matching-help">Click a prompt on the left, then click its match on the right. Click the same prompt again to remove a link, or click a connected answer on the right to unlink it.</p>
                <div class="quiz-matching-board">
                    <svg class="quiz-matching-lines" aria-hidden="true"></svg>
                    <div class="quiz-matching-col quiz-matching-col--left" aria-label="Prompts">
                        <?php foreach ($left as $li => $leftText): ?>
                        <button type="button" class="quiz-matching-item quiz-matching-item--left" data-side="left" data-index="<?= (int) $li ?>">
                            <?= e($leftText) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="quiz-matching-col quiz-matching-col--right" aria-label="Answers">
                        <?php foreach ($shuffled as $ri => $rightText): ?>
                        <button type="button" class="quiz-matching-item quiz-matching-item--right" data-side="right" data-index="<?= (int) $ri ?>">
                            <?= e($rightText) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="quiz-matching-inputs" hidden aria-hidden="true">
                    <?php foreach ($left as $li => $leftText): ?>
                    <input type="hidden" name="matching_<?= $q['id'] ?>_<?= (int) $li ?>" value="<?= e((string) ($saved[$li] ?? '')) ?>">
                    <?php endforeach; ?>
                </div>
            </div>

        <?php elseif ($type === 'file_response'): ?>
            <?php if (!empty($q['teacher_attachment_path'])): ?>
            <p><a href="<?= e(quizAttachmentUrl($q['teacher_attachment_path'])) ?>" class="btn btn-sm btn-secondary" download><i class="fa-solid fa-download"></i> Download template</a></p>
            <?php endif; ?>
            <div class="form-group">
                <label>Upload your file</label>
                <input type="file" name="file_answer_<?= $q['id'] ?>" class="form-control">
                <?php if (!empty($q['student_attachment_path'])): ?>
                    <small class="text-muted">Current upload on file. Choose a new file to replace.</small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
