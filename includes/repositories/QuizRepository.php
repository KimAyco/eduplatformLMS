<?php

class QuizRepository
{
    public static function questionsWithOptions(int $quizId): array
    {
        $questions = db()->prepare('SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order, id');
        $questions->execute([$quizId]);
        $questions = $questions->fetchAll();

        if (empty($questions)) {
            return [];
        }

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

    public static function attemptAnswersWithDetails(int $attemptId): array
    {
        $answers = db()->prepare('SELECT qaa.*, qq.question_text, qq.type, qq.points, qq.correct_answer, qq.sort_order
            FROM quiz_attempt_answers qaa
            INNER JOIN quiz_questions qq ON qq.id = qaa.question_id
            WHERE qaa.attempt_id = ?
            ORDER BY qq.sort_order, qq.id');
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
            $a['selected_text'] = $optionMap[$a['selected_option_id']] ?? null;
        }
        unset($a);

        return $answers;
    }
}
