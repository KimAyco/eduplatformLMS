<?php

/**
 * @return list<array{left: string, chosen: string, expected: string, correct: bool}>
 */
function quizMatchingReviewPairs(array $answer, int $attemptId): array
{
    $payload = $answer['response_payload'] ?? [];
    if (!is_array($payload)) {
        $decoded = is_string($payload) ? json_decode($payload, true) : null;
        $payload = is_array($decoded) ? $decoded : [];
    }
    $settings = is_array($answer['settings'] ?? null) ? $answer['settings'] : [];
    $m = $settings['matching'] ?? [];
    $left = $m['left'] ?? [];
    $right = $m['right'] ?? [];
    $correctMap = $m['correct_map'] ?? range(0, max(0, count($left) - 1));
    $map = $payload['matching'] ?? [];

    if ($left === []) {
        return [];
    }

    $questionId = (int) ($answer['question_id'] ?? 0);
    $shuffled = ($attemptId > 0 && $questionId > 0)
        ? quizMatchingRightShuffle($attemptId, $questionId, $right)
        : $right;

    $pairs = [];
    foreach ($left as $li => $leftText) {
        $expectedIdx = (int) ($correctMap[$li] ?? $li);
        $expected = (string) ($right[$expectedIdx] ?? '');
        $ri = isset($map[$li]) ? (int) $map[$li] : -1;
        $chosen = ($ri >= 0 && isset($shuffled[$ri])) ? (string) $shuffled[$ri] : '';
        $pairs[] = [
            'left' => (string) $leftText,
            'chosen' => $chosen !== '' ? $chosen : '—',
            'expected' => $expected !== '' ? $expected : '—',
            'correct' => $chosen !== '' && strcasecmp($chosen, $expected) === 0,
        ];
    }

    return $pairs;
}

function quizReviewScoreClass(?float $earned, float $max): string
{
    if ($earned === null) {
        return 'quiz-review-score--pending';
    }
    if ($earned <= 0) {
        return 'quiz-review-score--zero';
    }
    if ($earned >= $max) {
        return 'quiz-review-score--full';
    }

    return 'quiz-review-score--partial';
}

function quizReviewTypeIcon(string $type): string
{
    return match (normalizeQuestionType($type)) {
        'multiple_choice' => 'fa-list-check',
        'true_false' => 'fa-toggle-on',
        'essay' => 'fa-align-left',
        'fill_blank' => 'fa-i-cursor',
        'matching' => 'fa-link',
        'file_response' => 'fa-file-arrow-up',
        default => 'fa-circle-question',
    };
}

/** @param array<string, mixed> $answer */
function renderQuizAttemptAnswerReview(array $answer, int $attemptId): void
{
    $type = normalizeQuestionType((string) ($answer['type'] ?? ''));

    if ($type === 'essay') {
        $text = trim((string) ($answer['answer_text'] ?? ''));
        ?>
        <div class="quiz-review-answer quiz-review-answer--essay">
            <?= $text !== '' ? nl2br(e($text)) : '<span class="text-muted">No answer provided.</span>' ?>
        </div>
        <?php
        return;
    }

    if ($type === 'file_response') {
        ?>
        <div class="quiz-review-answer quiz-review-answer--file">
            <?php if (!empty($answer['student_attachment_path'])): ?>
                <a href="<?= e(quizAttachmentUrl($answer['student_attachment_path'])) ?>" class="quiz-review-file-link" download>
                    <i class="fa-solid fa-download" aria-hidden="true"></i>
                    <?= e($answer['answer_text'] ?: 'Download submitted file') ?>
                </a>
            <?php else: ?>
                <span class="text-muted">No file uploaded.</span>
            <?php endif; ?>
        </div>
        <?php
        return;
    }

    if ($type === 'matching') {
        $pairs = quizMatchingReviewPairs($answer, $attemptId);
        if ($pairs === []) {
            echo '<p class="text-muted quiz-review-empty">No matches submitted.</p>';
            return;
        }
        ?>
        <div class="quiz-review-matching">
            <?php foreach ($pairs as $pair): ?>
            <div class="quiz-review-matching-pair quiz-review-matching-pair--<?= $pair['correct'] ? 'correct' : 'incorrect' ?>">
                <span class="quiz-review-matching-status" aria-hidden="true">
                    <i class="fa-solid fa-<?= $pair['correct'] ? 'check' : 'xmark' ?>"></i>
                </span>
                <span class="quiz-review-matching-left"><?= e($pair['left']) ?></span>
                <span class="quiz-review-matching-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                <span class="quiz-review-matching-chosen"><?= e($pair['chosen']) ?></span>
                <?php if (!$pair['correct']): ?>
                <span class="quiz-review-matching-expected">Correct: <?= e($pair['expected']) ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return;
    }

    if ($type === 'fill_blank') {
        $payload = $answer['response_payload'] ?? [];
        if (!is_array($payload)) {
            $decoded = is_string($payload) ? json_decode($payload, true) : null;
            $payload = is_array($decoded) ? $decoded : [];
        }
        $settings = is_array($answer['settings'] ?? null) ? $answer['settings'] : [];
        $defs = $settings['blanks'] ?? [];
        $blanks = $payload['blanks'] ?? [];

        if ($defs === []) {
            ?>
            <div class="quiz-review-answer"><?= nl2br(e(formatQuizAttemptAnswerSummary($answer, $attemptId))) ?></div>
            <?php
            return;
        }
        ?>
        <div class="quiz-review-blanks">
            <?php foreach ($defs as $i => $def):
                $given = trim((string) ($blanks[$i] ?? ''));
                $acceptable = $def['answers'] ?? [];
                $caseInsensitive = (bool) ($def['case_insensitive'] ?? true);
                $matched = false;
                foreach ((array) $acceptable as $acc) {
                    $acc = trim((string) $acc);
                    if ($acc === '') continue;
                    if ($caseInsensitive ? strcasecmp($given, $acc) === 0 : $given === $acc) {
                        $matched = true;
                        break;
                    }
                }
            ?>
            <div class="quiz-review-blank quiz-review-blank--<?= $matched ? 'correct' : 'incorrect' ?>">
                <span class="quiz-review-blank-label">Blank <?= (int) $i + 1 ?></span>
                <span class="quiz-review-blank-given"><?= $given !== '' ? e($given) : '—' ?></span>
                <?php if (!$matched && $acceptable !== []): ?>
                <span class="quiz-review-blank-expected">Accepted: <?= e(implode(' · ', array_map('strval', $acceptable))) ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return;
    }

    if ($type === 'multiple_choice' || $type === 'true_false') {
        $selected = trim((string) ($answer['selected_text'] ?? ''));
        $isCorrect = isset($answer['is_correct']) ? (int) $answer['is_correct'] : null;
        $state = $isCorrect === 1 ? 'correct' : ($isCorrect === 0 ? 'incorrect' : 'neutral');
        ?>
        <div class="quiz-review-choice quiz-review-choice--<?= e($state) ?>">
            <?php if ($selected !== ''): ?>
                <span class="quiz-review-choice-icon" aria-hidden="true">
                    <i class="fa-solid fa-<?= $state === 'correct' ? 'check' : ($state === 'incorrect' ? 'xmark' : 'circle') ?>"></i>
                </span>
                <span><?= e($selected) ?></span>
            <?php else: ?>
                <span class="text-muted">No option selected.</span>
            <?php endif; ?>
        </div>
        <?php
        return;
    }

    ?>
    <div class="quiz-review-answer"><?= nl2br(e(formatQuizAttemptAnswerSummary($answer, $attemptId))) ?></div>
    <?php
}
