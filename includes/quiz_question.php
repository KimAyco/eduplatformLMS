<?php

const QUIZ_QUESTION_TYPES = [
    'multiple_choice',
    'essay',
    'true_false',
    'fill_blank',
    'matching',
    'file_response',
];

const QUIZ_QUESTION_TYPE_LABELS = [
    'multiple_choice' => 'Multiple choice',
    'true_false' => 'True / False',
    'essay' => 'Essay',
    'fill_blank' => 'Fill in the blank',
    'matching' => 'Matching',
    'file_response' => 'File upload',
];

function questionTypeLabel(string $type): string
{
    $type = normalizeQuestionType($type);
    return QUIZ_QUESTION_TYPE_LABELS[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

function normalizeQuestionType(string $type): string
{
    $type = strtolower(trim($type));
    return match ($type) {
        'mcq' => 'multiple_choice',
        'short_answer' => 'essay',
        default => $type,
    };
}

function isManualGradeQuestionType(string $type): bool
{
    $type = normalizeQuestionType($type);
    return in_array($type, ['essay', 'file_response'], true);
}

/** @param list<string> $rightItems */
function quizMatchingRightShuffle(int $attemptId, int $questionId, array $rightItems): array
{
    if ($rightItems === []) {
        return [];
    }

    $shuffled = $rightItems;
    mt_srand(crc32($attemptId . '-' . $questionId));
    shuffle($shuffled);
    mt_srand();

    return $shuffled;
}

/**
 * Human-readable student response for teacher attempt review.
 *
 * @param array<string, mixed> $answer
 */
function formatQuizAttemptAnswerSummary(array $answer, int $attemptId = 0): string
{
    $type = normalizeQuestionType((string) ($answer['type'] ?? ''));
    $payload = $answer['response_payload'] ?? [];
    if (!is_array($payload)) {
        $decoded = is_string($payload) ? json_decode($payload, true) : null;
        $payload = is_array($decoded) ? $decoded : [];
    }
    $settings = is_array($answer['settings'] ?? null) ? $answer['settings'] : [];

    if ($type === 'multiple_choice' || $type === 'true_false') {
        $text = trim((string) ($answer['selected_text'] ?? ''));
        return $text !== '' ? $text : '—';
    }

    if ($type === 'fill_blank') {
        $blanks = $payload['blanks'] ?? [];
        if ($blanks === []) {
            return '—';
        }
        $lines = [];
        foreach ($blanks as $i => $val) {
            $val = trim((string) $val);
            $lines[] = 'Blank ' . ((int) $i + 1) . ': ' . ($val !== '' ? $val : '—');
        }
        return implode("\n", $lines);
    }

    if ($type === 'matching') {
        $m = $settings['matching'] ?? [];
        $left = $m['left'] ?? [];
        $right = $m['right'] ?? [];
        $map = $payload['matching'] ?? [];
        if ($left === [] || $map === []) {
            return '—';
        }

        $questionId = (int) ($answer['question_id'] ?? 0);
        $shuffled = ($attemptId > 0 && $questionId > 0)
            ? quizMatchingRightShuffle($attemptId, $questionId, $right)
            : $right;

        $lines = [];
        foreach ($left as $li => $leftText) {
            $ri = isset($map[$li]) ? (int) $map[$li] : -1;
            $chosen = ($ri >= 0 && isset($shuffled[$ri])) ? (string) $shuffled[$ri] : '—';
            $lines[] = $leftText . ' → ' . $chosen;
        }

        return implode("\n", $lines);
    }

    $fallback = trim((string) ($answer['selected_text'] ?? $answer['answer_text'] ?? ''));
    return $fallback !== '' ? $fallback : '—';
}

/** @return list<string> */
function quizQuestionLinesFromTextarea(?string $raw): array
{
    if ($raw === null || $raw === '') {
        return [];
    }

    $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
    $out = [];
    foreach ($lines as $line) {
        $t = trim((string) $line);
        if ($t !== '') {
            $out[] = $t;
        }
    }

    return array_values($out);
}

/**
 * Validate question input and build settings JSON for the given type.
 *
 * @param array<string, mixed> $input
 * @return array{settings: ?array<string, mixed>, error: ?string}
 */
function validateAndBuildQuestionSettings(string $type, array $input): array
{
    $type = normalizeQuestionType($type);
    $questionText = trim((string) ($input['question_text'] ?? ''));

    if (!in_array($type, QUIZ_QUESTION_TYPES, true)) {
        return ['settings' => null, 'error' => 'Invalid question type.'];
    }

    if ($type === 'fill_blank') {
        $blanksDef = [];
        if (isset($input['blank_answers']) && is_array($input['blank_answers'])) {
            foreach ($input['blank_answers'] as $ans) {
                $ans = trim((string) $ans);
                if ($ans === '') {
                    continue;
                }
                $parts = array_values(array_filter(array_map('trim', explode('|', $ans)), static fn ($v) => $v !== ''));
                $blanksDef[] = [
                    'answers' => $parts ?: [$ans],
                    'case_insensitive' => true,
                ];
            }
        } else {
            $blankAnswers = quizQuestionLinesFromTextarea($input['blank_answers_text'] ?? '');
            foreach ($blankAnswers as $ans) {
                $parts = array_values(array_filter(array_map('trim', explode('|', $ans)), static fn ($v) => $v !== ''));
                $blanksDef[] = [
                    'answers' => $parts ?: [trim($ans)],
                    'case_insensitive' => true,
                ];
            }
        }

        $blankCount = substr_count($questionText, '___');
        if ($blankCount < 1) {
            return ['settings' => null, 'error' => 'Add at least one blank to the sentence.'];
        }
        if (count($blanksDef) !== $blankCount) {
            return ['settings' => null, 'error' => 'Provide a correct answer for each blank (' . $blankCount . ' required).'];
        }

        return [
            'settings' => [
                'blanks' => $blanksDef,
                'scoring_mode' => (($input['fill_blank_scoring_mode'] ?? 'partial') === 'all_or_nothing') ? 'all_or_nothing' : 'partial',
            ],
            'error' => null,
        ];
    }

    if ($type === 'matching') {
        $left = [];
        $right = [];
        if (isset($input['matching_left'], $input['matching_right']) && is_array($input['matching_left']) && is_array($input['matching_right'])) {
            foreach ($input['matching_left'] as $i => $leftText) {
                $leftText = trim((string) $leftText);
                $rightText = trim((string) ($input['matching_right'][$i] ?? ''));
                if ($leftText === '' && $rightText === '') {
                    continue;
                }
                if ($leftText === '' || $rightText === '') {
                    return ['settings' => null, 'error' => 'Each matching pair needs both a prompt and an answer.'];
                }
                $left[] = $leftText;
                $right[] = $rightText;
            }
        } else {
            $left = quizQuestionLinesFromTextarea($input['matching_left_text'] ?? '');
            $right = quizQuestionLinesFromTextarea($input['matching_right_text'] ?? '');
        }

        if (count($left) < 2 || count($left) !== count($right)) {
            return ['settings' => null, 'error' => 'Matching requires at least two complete pairs.'];
        }

        $n = count($left);
        return [
            'settings' => [
                'matching' => [
                    'left' => $left,
                    'right' => $right,
                    'correct_map' => range(0, $n - 1),
                ],
            ],
            'error' => null,
        ];
    }

    if ($type === 'multiple_choice') {
        $choices = [];
        if (isset($input['choices']) && is_array($input['choices'])) {
            foreach ($input['choices'] as $choiceData) {
                $text = trim((string) (is_array($choiceData) ? ($choiceData['text'] ?? '') : $choiceData));
                if ($text !== '') {
                    $choices[] = ['text' => $text];
                }
            }
        } elseif (isset($input['options']) && is_array($input['options'])) {
            foreach ($input['options'] as $text) {
                $text = trim((string) $text);
                if ($text !== '') {
                    $choices[] = ['text' => $text];
                }
            }
        }

        if (count($choices) < 2) {
            return ['settings' => null, 'error' => 'Multiple choice questions need at least two choices.'];
        }

        $correctIndex = isset($input['correct_choice_index']) ? (int) $input['correct_choice_index'] : (int) ($input['correct_option'] ?? -1);
        if ($correctIndex < 0 || $correctIndex >= count($choices)) {
            return ['settings' => null, 'error' => 'Select the correct choice.'];
        }

        $input['choices'] = $choices;
        $input['correct_choice_index'] = $correctIndex;
    }

    if ($type === 'true_false') {
        if (!array_key_exists('correct_is_true', $input)) {
            return ['settings' => null, 'error' => 'Select whether True or False is correct.'];
        }
    }

    return ['settings' => null, 'error' => null];
}

/**
 * Persist MCQ or true/false options for a question.
 *
 * @param array<string, mixed> $input
 */
function saveQuestionOptions(int $questionId, string $type, array $input): void
{
    $type = normalizeQuestionType($type);

    db()->prepare('DELETE FROM quiz_options WHERE question_id = ?')->execute([$questionId]);

    if ($type === 'multiple_choice') {
        $choices = $input['choices'] ?? [];
        if (!is_array($choices)) {
            return;
        }
        $correctIndex = isset($input['correct_choice_index']) ? (int) $input['correct_choice_index'] : -1;
        $stmt = db()->prepare('INSERT INTO quiz_options (question_id, option_text, is_correct) VALUES (?, ?, ?)');
        foreach ($choices as $index => $choiceData) {
            $text = trim((string) ($choiceData['text'] ?? $choiceData['option_text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $isCorrect = ($index === $correctIndex) ? 1 : 0;
            $stmt->execute([$questionId, $text, $isCorrect]);
        }
        return;
    }

    if ($type === 'true_false') {
        $truthy = filter_var($input['correct_is_true'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $stmt = db()->prepare('INSERT INTO quiz_options (question_id, option_text, is_correct) VALUES (?, ?, ?)');
        $stmt->execute([$questionId, 'True', $truthy ? 1 : 0]);
        $stmt->execute([$questionId, 'False', $truthy ? 0 : 1]);
    }
}

/**
 * @return list<array{type: string, value?: string, answer?: string}>
 */
function quizFillBlankBuilderSegments(string $questionText, array $blankSettings = []): array
{
    $parts = preg_split('/(___)/', $questionText, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $segments = [];
    $blankIdx = 0;

    foreach ($parts as $part) {
        if ($part === '___') {
            $answers = $blankSettings[$blankIdx]['answers'] ?? [''];
            $segments[] = [
                'type' => 'blank',
                'answer' => implode('|', array_map('strval', $answers)),
            ];
            $blankIdx++;
        } elseif ($part !== '') {
            $segments[] = ['type' => 'text', 'value' => $part];
        }
    }

    if (empty($segments)) {
        $segments[] = ['type' => 'text', 'value' => ''];
    }

    return $segments;
}

/**
 * @param array<string, mixed> $post
 * @return array{ok: bool, errors: list<string>}
 */
function saveQuizQuestion(int $quizId, array $post, ?int $questionId, ?array $existing): array
{
    $type = normalizeQuestionType((string) ($post['type'] ?? 'multiple_choice'));
    $questionText = trim((string) ($post['question_text'] ?? ''));
    $points = max(0.01, (float) ($post['points'] ?? 1));

    if ($questionText === '') {
        return ['ok' => false, 'errors' => ['Question text is required.']];
    }

    $input = $post;
    if ($type === 'multiple_choice') {
        $choices = [];
        if (isset($post['choices']) && is_array($post['choices'])) {
            foreach ($post['choices'] as $choiceData) {
                $text = trim((string) (is_array($choiceData) ? ($choiceData['text'] ?? '') : $choiceData));
                if ($text !== '') {
                    $choices[] = ['text' => $text];
                }
            }
        }
        $input['choices'] = $choices;
        $input['correct_choice_index'] = (int) ($post['correct_choice_index'] ?? $post['correct_option'] ?? 0);
    }

    if ($type === 'true_false') {
        $input['correct_is_true'] = ($post['tf_correct'] ?? 'true') === 'true';
    }

    if ($type === 'fill_blank' && isset($post['blank_answers']) && is_array($post['blank_answers'])) {
        $input['blank_answers'] = array_values(array_map('strval', $post['blank_answers']));
    }

    if ($type === 'matching') {
        $input['matching_left'] = $post['matching_left'] ?? [];
        $input['matching_right'] = $post['matching_right'] ?? [];
    }

    $validation = validateAndBuildQuestionSettings($type, array_merge($input, ['question_text' => $questionText]));
    if ($validation['error']) {
        return ['ok' => false, 'errors' => [$validation['error']]];
    }

    $settings = $validation['settings'];
    if ($type === 'essay') {
        $rubric = trim((string) ($post['essay_rubric'] ?? ''));
        $settings = $rubric !== '' ? ['rubric' => $rubric] : null;
    }

    $settingsJson = $settings !== null ? json_encode($settings, JSON_UNESCAPED_UNICODE) : null;
    $teacherAttachment = $existing['teacher_attachment_path'] ?? null;

    if ($type === 'file_response' && !empty($_FILES['teacher_attachment']['name'])) {
        try {
            $meta = uploadFileWithMeta($_FILES['teacher_attachment'], schoolId() . '/quiz_attachments');
            if (!empty($teacherAttachment)) {
                deleteUpload($teacherAttachment);
            }
            $teacherAttachment = $meta['path'];
        } catch (RuntimeException $e) {
            return ['ok' => false, 'errors' => [$e->getMessage()]];
        }
    }

    if ($questionId) {
        $stmt = db()->prepare('UPDATE quiz_questions SET type=?, question_text=?, points=?, settings=?, teacher_attachment_path=? WHERE id=? AND quiz_id=?');
        $stmt->execute([$type, $questionText, $points, $settingsJson, $teacherAttachment, $questionId, $quizId]);
    } else {
        $stmt = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM quiz_questions WHERE quiz_id = ?');
        $stmt->execute([$quizId]);
        $sortOrder = (int) $stmt->fetchColumn();
        $stmt = db()->prepare('INSERT INTO quiz_questions (quiz_id, type, question_text, points, sort_order, settings, teacher_attachment_path) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$quizId, $type, $questionText, $points, $sortOrder, $settingsJson, $teacherAttachment]);
        $questionId = (int) db()->lastInsertId();
    }

    if (in_array($type, ['multiple_choice', 'true_false'], true)) {
        saveQuestionOptions($questionId, $type, $input);
    } else {
        db()->prepare('DELETE FROM quiz_options WHERE question_id = ?')->execute([$questionId]);
    }

    return ['ok' => true, 'errors' => []];
}

function reorderQuizQuestion(int $quizId, int $questionId, string $direction): void
{
    $stmt = db()->prepare('SELECT id, sort_order FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$quizId]);
    $rows = $stmt->fetchAll();

    $index = null;
    foreach ($rows as $i => $row) {
        if ((int) $row['id'] === $questionId) {
            $index = $i;
            break;
        }
    }

    if ($index === null) {
        return;
    }

    $swapWith = $direction === 'up' ? $index - 1 : $index + 1;
    if ($swapWith < 0 || $swapWith >= count($rows)) {
        return;
    }

    $currentOrder = (int) $rows[$index]['sort_order'];
    $swapOrder = (int) $rows[$swapWith]['sort_order'];
    $currentId = (int) $rows[$index]['id'];
    $swapId = (int) $rows[$swapWith]['id'];

    db()->prepare('UPDATE quiz_questions SET sort_order = ? WHERE id = ?')->execute([$swapOrder, $currentId]);
    db()->prepare('UPDATE quiz_questions SET sort_order = ? WHERE id = ?')->execute([$currentOrder, $swapId]);
}
