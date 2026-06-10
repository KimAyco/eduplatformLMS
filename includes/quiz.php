<?php

function decodeAnswerPayload(mixed $payload): array
{
    if ($payload === null || $payload === '') {
        return [];
    }
    if (is_array($payload)) {
        return $payload;
    }
    if (is_string($payload)) {
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}

function decodeQuestionSettings(mixed $raw): array
{
    if ($raw === null || $raw === '') {
        return [];
    }
    if (is_array($raw)) {
        return $raw;
    }
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}

function gradeQuizAttempt(int $attemptId): void
{
    $stmt = db()->prepare('SELECT * FROM quiz_attempts WHERE id = ?');
    $stmt->execute([$attemptId]);
    $attempt = $stmt->fetch();
    if (!$attempt) {
        return;
    }

    $answers = db()->prepare('SELECT qa.*, qq.type, qq.points, qq.settings
        FROM quiz_attempt_answers qa
        INNER JOIN quiz_questions qq ON qq.id = qa.question_id
        WHERE qa.attempt_id = ?');
    $answers->execute([$attemptId]);
    $answers = $answers->fetchAll();

    $totalScore = 0.0;
    $allGraded = true;

    foreach ($answers as $ans) {
        $type = normalizeQuestionType((string) $ans['type']);
        QuizRepository::decodeQuestionSettings($ans);

        $earned = 0.0;
        $isCorrect = null;

        if ($type === 'multiple_choice' || $type === 'true_false') {
            if ($ans['selected_option_id']) {
                $opt = db()->prepare('SELECT is_correct FROM quiz_options WHERE id = ?');
                $opt->execute([$ans['selected_option_id']]);
                $optRow = $opt->fetch();
                $isCorrect = $optRow && $optRow['is_correct'] ? 1 : 0;
                $earned = $isCorrect ? (float) $ans['points'] : 0.0;
            } else {
                $isCorrect = 0;
                $earned = 0.0;
            }
            db()->prepare('UPDATE quiz_attempt_answers SET is_correct=?, points_earned=? WHERE id=?')
                ->execute([$isCorrect, $earned, $ans['id']]);
            $totalScore += $earned;
        } elseif ($type === 'fill_blank') {
            $payload = decodeAnswerPayload($ans['response_payload'] ?? null);
            $blanksIn = $payload['blanks'] ?? [];
            $earned = scoreFillBlankQuestion($ans, is_array($blanksIn) ? $blanksIn : []);
            $isCorrect = $earned >= (float) $ans['points'] ? 1 : ($earned > 0 ? null : 0);
            db()->prepare('UPDATE quiz_attempt_answers SET is_correct=?, points_earned=? WHERE id=?')
                ->execute([$isCorrect, $earned, $ans['id']]);
            $totalScore += $earned;
        } elseif ($type === 'matching') {
            $payload = decodeAnswerPayload($ans['response_payload'] ?? null);
            $mapIn = $payload['matching'] ?? $payload;
            $earned = scoreMatchingQuestion($ans, is_array($mapIn) ? $mapIn : []);
            $isCorrect = $earned >= (float) $ans['points'] ? 1 : ($earned > 0 ? null : 0);
            db()->prepare('UPDATE quiz_attempt_answers SET is_correct=?, points_earned=? WHERE id=?')
                ->execute([$isCorrect, $earned, $ans['id']]);
            $totalScore += $earned;
        } elseif ($type === 'essay' || $type === 'file_response') {
            if ($ans['points_earned'] !== null) {
                $totalScore += (float) $ans['points_earned'];
            } else {
                $allGraded = false;
            }
        }
    }

    $status = $allGraded ? 'graded' : 'submitted';
    db()->prepare('UPDATE quiz_attempts SET score=?, status=? WHERE id=?')
        ->execute([round($totalScore, 2), $status, $attemptId]);

    if ($status === 'graded') {
        $quizStmt = db()->prepare('SELECT * FROM quizzes WHERE id = ?');
        $quizStmt->execute([(int) $attempt['quiz_id']]);
        $quizRow = $quizStmt->fetch();
        $isPractice = isPracticeQuiz($quizRow ?: []);

        if (!$isPractice && (int) ($quizRow['counts_toward_gradebook'] ?? 1) === 1) {
            syncQuizAttemptToGradebook($attemptId);
        }

        if ($isPractice && $attempt['max_score'] > 0) {
            $pct = round(((float) $totalScore / (float) $attempt['max_score']) * 100, 2);
            PracticeQuizService::recordProficiency(
                (int) $attempt['student_id'],
                (int) $quizRow['class_id'],
                isset($quizRow['source_section_id']) ? (int) $quizRow['source_section_id'] : null,
                $pct,
                !empty($quizRow['is_course_wide'])
            );
        }
    }
}

function isPracticeQuiz(?array $quiz): bool
{
    if (!$quiz) {
        return false;
    }
    return ($quiz['quiz_mode'] ?? 'exam') === 'practice';
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
    $practice = isPracticeQuiz($quiz);

    if (!$practice && isset($quiz['is_published']) && !(int) $quiz['is_published']) {
        return ['ok' => false, 'reason' => 'This quiz is not published yet.'];
    }

    $inProgress = db()->prepare("SELECT id FROM quiz_attempts WHERE quiz_id=? AND student_id=? AND status='in_progress'");
    $inProgress->execute([$quiz['id'], $studentId]);
    if ($inProgress->fetch()) {
        return ['ok' => true, 'resume' => true];
    }

    if ($practice) {
        return ['ok' => true, 'resume' => false];
    }

    $now = time();

    if (!empty($quiz['opens_at']) && strtotime($quiz['opens_at']) > $now) {
        return ['ok' => false, 'reason' => 'This quiz is not open yet.'];
    }

    $closesAt = $quiz['closes_at'] ?? $quiz['due_date'] ?? null;
    if ($closesAt && strtotime($closesAt) < $now) {
        return ['ok' => false, 'reason' => 'This quiz is closed.'];
    }

    $attempts = studentQuizAttempts($quiz['id'], $studentId);
    if ($attempts >= (int) $quiz['max_attempts']) {
        return ['ok' => false, 'reason' => 'You have used all allowed attempts.'];
    }

    return ['ok' => true, 'resume' => false];
}

function startQuizAttempt(int $quizId, int $studentId, bool $randomize = false): int
{
    $maxScore = getQuizTotalPoints($quizId);

    db()->prepare('INSERT INTO quiz_attempts (quiz_id, student_id, max_score) VALUES (?, ?, ?)')
        ->execute([$quizId, $studentId, $maxScore]);
    $attemptId = (int) db()->lastInsertId();

    $questions = QuizRepository::questionsForAttempt($quizId, $randomize);
    $stmt = db()->prepare('INSERT INTO quiz_attempt_answers (attempt_id, question_id) VALUES (?, ?)');
    foreach ($questions as $q) {
        $stmt->execute([$attemptId, $q['id']]);
    }

    return $attemptId;
}
