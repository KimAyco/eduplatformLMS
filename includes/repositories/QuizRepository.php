<?php

class QuizRepository
{
    public static function getQuizById(int $quizId, ?int $classId = null): ?array
    {
        $sql = 'SELECT * FROM quizzes WHERE id = ?';
        $params = [$quizId];
        if ($classId !== null) {
            $sql .= ' AND class_id = ?';
            $params[] = $classId;
        }

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function updateQuizSettings(int $quizId, int $classId, array $settings): bool
    {
        $quiz = self::getQuizById($quizId, $classId);
        if (!$quiz) {
            return false;
        }

        $fields = [];
        $params = [];

        $allowed = [
            'title', 'instructions', 'time_limit_minutes', 'due_date', 'opens_at', 'closes_at',
            'is_published', 'randomize_questions_order', 'show_score_to_students', 'cover_image', 'max_attempts', 'section_id',
        ];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $settings)) {
                continue;
            }
            $value = $settings[$key];
            if (in_array($key, ['is_published', 'randomize_questions_order', 'show_score_to_students'], true)) {
                $value = $value ? 1 : 0;
            }
            if ($key === 'section_id') {
                $value = $value ? CourseSectionRepository::resolveSectionId((int) $value, $classId) : null;
            }
            $fields[] = $key . ' = ?';
            $params[] = $value;
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $quizId;
        $params[] = $classId;
        $sql = 'UPDATE quizzes SET ' . implode(', ', $fields) . ' WHERE id = ? AND class_id = ?';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /** @param array<string, mixed> $question */
    public static function decodeQuestionSettings(array &$question): void
    {
        if (!isset($question['settings']) || $question['settings'] === null || $question['settings'] === '') {
            $question['settings'] = [];
            return;
        }

        if (is_string($question['settings'])) {
            $decoded = json_decode($question['settings'], true);
            $question['settings'] = is_array($decoded) ? $decoded : [];
        }
    }

    public static function questionsWithOptions(int $quizId): array
    {
        $questions = db()->prepare('SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order, id');
        $questions->execute([$quizId]);
        $questions = $questions->fetchAll();

        if (empty($questions)) {
            return [];
        }

        foreach ($questions as &$q) {
            self::decodeQuestionSettings($q);
            $q['type'] = normalizeQuestionType((string) $q['type']);
        }
        unset($q);

        $ids = array_column($questions, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $opts = db()->prepare("SELECT * FROM quiz_options WHERE question_id IN ($placeholders) ORDER BY question_id, id");
        $opts->execute($ids);
        $optionsByQuestion = [];
        foreach ($opts->fetchAll() as $opt) {
            $optionsByQuestion[$opt['question_id']][] = $opt;
        }

        foreach ($questions as &$q) {
            $q['options'] = $optionsByQuestion[$q['id']] ?? [];
        }
        unset($q);

        return $questions;
    }

    public static function questionsForAttempt(int $quizId, bool $randomize = false): array
    {
        $questions = self::questionsWithOptions($quizId);

        if ($randomize && count($questions) > 1) {
            shuffle($questions);
        }

        return $questions;
    }

    public static function attemptAnswersWithDetails(int $attemptId): array
    {
        $answers = db()->prepare('SELECT qaa.*, qq.question_text, qq.type, qq.points, qq.correct_answer, qq.sort_order, qq.settings
            FROM quiz_attempt_answers qaa
            INNER JOIN quiz_questions qq ON qq.id = qaa.question_id
            WHERE qaa.attempt_id = ?
            ORDER BY qaa.id');
        $answers->execute([$attemptId]);
        $answers = $answers->fetchAll();

        $optionIds = array_filter(array_column($answers, 'selected_option_id'));
        $optionMap = [];
        if (!empty($optionIds)) {
            $ph = implode(',', array_fill(0, count($optionIds), '?'));
            $stmt = db()->prepare("SELECT id, option_text FROM quiz_options WHERE id IN ($ph)");
            $stmt->execute(array_values($optionIds));
            foreach ($stmt->fetchAll() as $o) {
                $optionMap[$o['id']] = $o['option_text'];
            }
        }

        foreach ($answers as &$a) {
            $a['type'] = normalizeQuestionType((string) $a['type']);
            self::decodeQuestionSettings($a);
            $a['selected_text'] = $optionMap[$a['selected_option_id']] ?? null;
            if (!empty($a['response_payload']) && is_string($a['response_payload'])) {
                $decoded = json_decode($a['response_payload'], true);
                $a['response_payload'] = is_array($decoded) ? $decoded : [];
            }
        }
        unset($a);

        return $answers;
    }

    public static function questionsForAttemptView(int $attemptId): array
    {
        $questions = db()->prepare('SELECT qq.* FROM quiz_attempt_answers qaa
            INNER JOIN quiz_questions qq ON qq.id = qaa.question_id
            WHERE qaa.attempt_id = ?
            ORDER BY qaa.id');
        $questions->execute([$attemptId]);
        $questions = $questions->fetchAll();

        if (empty($questions)) {
            return [];
        }

        foreach ($questions as &$q) {
            self::decodeQuestionSettings($q);
            $q['type'] = normalizeQuestionType((string) $q['type']);
            $q['settings_decoded'] = $q['settings'];
        }
        unset($q);

        $ids = array_column($questions, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $opts = db()->prepare("SELECT * FROM quiz_options WHERE question_id IN ($placeholders) ORDER BY question_id, id");
        $opts->execute($ids);
        $optionsByQuestion = [];
        foreach ($opts->fetchAll() as $opt) {
            $optionsByQuestion[$opt['question_id']][] = $opt;
        }

        foreach ($questions as &$q) {
            $q['options'] = $optionsByQuestion[$q['id']] ?? [];
            $saved = db()->prepare('SELECT selected_option_id, answer_text, response_payload, student_attachment_path FROM quiz_attempt_answers WHERE attempt_id = ? AND question_id = ?');
            $saved->execute([$attemptId, $q['id']]);
            $row = $saved->fetch() ?: [];
            $q['selected_option_id'] = $row['selected_option_id'] ?? null;
            $q['answer_text'] = $row['answer_text'] ?? '';
            $q['response_payload'] = decodeAnswerPayload($row['response_payload'] ?? null);
            $q['student_attachment_path'] = $row['student_attachment_path'] ?? null;
            if ($q['type'] === 'matching') {
                $right = $q['settings']['matching']['right'] ?? [];
                $seed = crc32($attemptId . '-' . $q['id']);
                $shuffled = $right;
                mt_srand($seed);
                shuffle($shuffled);
                mt_srand();
                $q['matching_shuffle'] = $shuffled;
            }
        }
        unset($q);

        return $questions;
    }
}
