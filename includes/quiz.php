<?php

function gradeQuizAttempt(int $attemptId): void
{
    $stmt = db()->prepare('SELECT * FROM quiz_attempts WHERE id = ?');
    $stmt->execute([$attemptId]);
    $attempt = $stmt->fetch();
    if (!$attempt) return;

    $answers = db()->prepare('SELECT qa.*, qq.type, qq.points, qq.correct_answer
        FROM quiz_attempt_answers qa
        INNER JOIN quiz_questions qq ON qq.id = qa.question_id
        WHERE qa.attempt_id = ?');
    $answers->execute([$attemptId]);
    $answers = $answers->fetchAll();

    $totalScore = 0;
    $allGraded = true;

    foreach ($answers as $ans) {
        $earned = 0;
        $isCorrect = null;

        if ($ans['type'] === 'mcq' || $ans['type'] === 'true_false') {
            if ($ans['selected_option_id']) {
                $opt = db()->prepare('SELECT is_correct FROM quiz_options WHERE id = ?');
                $opt->execute([$ans['selected_option_id']]);
                $optRow = $opt->fetch();
                $isCorrect = $optRow && $optRow['is_correct'] ? 1 : 0;
                $earned = $isCorrect ? (float) $ans['points'] : 0;
            } else {
                $isCorrect = 0;
                $earned = 0;
            }
            db()->prepare('UPDATE quiz_attempt_answers SET is_correct=?, points_earned=? WHERE id=?')
                ->execute([$isCorrect, $earned, $ans['id']]);
            $totalScore += $earned;
        } elseif ($ans['type'] === 'short_answer') {
            if ($ans['points_earned'] !== null) {
                $totalScore += (float) $ans['points_earned'];
            } else {
                $allGraded = false;
            }
        }
    }

    $status = $allGraded ? 'graded' : 'submitted';
    db()->prepare('UPDATE quiz_attempts SET score=?, status=? WHERE id=?')
        ->execute([$totalScore, $status, $attemptId]);
}

function getQuizTotalPoints(int $quizId): float
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(points), 0) FROM quiz_questions WHERE quiz_id = ?');
    $stmt->execute([$quizId]);
    return (float) $stmt->fetchColumn();
}

function studentQuizAttempts(int $quizId, int $studentId): int
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id=? AND student_id=? AND status != 'in_progress'");
    $stmt->execute([$quizId, $studentId]);
    return (int) $stmt->fetchColumn();
}

function canStudentTakeQuiz(array $quiz, int $studentId): array
{
    if ($quiz['due_date'] && strtotime($quiz['due_date']) < time()) {
        return ['ok' => false, 'reason' => 'This quiz is past its due date.'];
    }

    $inProgress = db()->prepare("SELECT id FROM quiz_attempts WHERE quiz_id=? AND student_id=? AND status='in_progress'");
    $inProgress->execute([$quiz['id'], $studentId]);
    if ($inProgress->fetch()) {
        return ['ok' => true, 'resume' => true];
    }

    $attempts = studentQuizAttempts($quiz['id'], $studentId);
    if ($attempts >= (int) $quiz['max_attempts']) {
        return ['ok' => false, 'reason' => 'You have used all allowed attempts.'];
    }

    return ['ok' => true, 'resume' => false];
}
