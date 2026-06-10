<?php

/**
 * Resolve best graded quiz attempt score for gradebook sync.
 *
 * @return array{score: float, max_score: float, percent: float, attempt_id: int}|null
 */
function resolveQuizGradeForStudent(int $quizId, int $studentId): ?array
{
    $quiz = db()->prepare('SELECT id, max_attempts FROM quizzes WHERE id = ?');
    $quiz->execute([$quizId]);
    $quiz = $quiz->fetch();
    if (!$quiz) {
        return null;
    }

    $stmt = db()->prepare("SELECT id, score, max_score FROM quiz_attempts
        WHERE quiz_id = ? AND student_id = ? AND status = 'graded' AND score IS NOT NULL
        ORDER BY score DESC, submitted_at DESC");
    $stmt->execute([$quizId, $studentId]);
    $attempts = $stmt->fetchAll();

    if ($attempts === []) {
        return null;
    }

    $maxAttempts = max(1, (int) ($quiz['max_attempts'] ?? 1));
    $pick = $maxAttempts <= 1 ? $attempts[0] : $attempts[0];

    $score = (float) $pick['score'];
    $maxScore = (float) ($pick['max_score'] ?? 0);
    if ($maxScore <= 0) {
        $maxScore = (float) getQuizTotalPoints($quizId);
    }
    if ($maxScore <= 0) {
        return null;
    }

    return [
        'score' => $score,
        'max_score' => $maxScore,
        'percent' => round(($score / $maxScore) * 100, 2),
        'attempt_id' => (int) $pick['id'],
    ];
}

function syncQuizAttemptToGradebook(int $attemptId): void
{
    $stmt = db()->prepare('SELECT qa.*, q.class_id, q.quiz_mode, q.counts_toward_gradebook FROM quiz_attempts qa
        INNER JOIN quizzes q ON q.id = qa.quiz_id
        WHERE qa.id = ?');
    $stmt->execute([$attemptId]);
    $attempt = $stmt->fetch();
    if (!$attempt || $attempt['status'] !== 'graded' || $attempt['score'] === null) {
        return;
    }

    if (($attempt['quiz_mode'] ?? 'exam') === 'practice' || (int) ($attempt['counts_toward_gradebook'] ?? 1) === 0) {
        return;
    }

    $link = GradebookRepository::linkByQuizId((int) $attempt['quiz_id']);
    if (!$link) {
        return;
    }

    if (GradebookRepository::isManualOverride(
        (int) $attempt['class_id'],
        (int) $attempt['student_id'],
        (int) $link['component_id']
    )) {
        return;
    }

    $resolved = resolveQuizGradeForStudent((int) $attempt['quiz_id'], (int) $attempt['student_id']);
    if (!$resolved) {
        return;
    }

    GradebookRepository::upsertStudentGrade(
        (int) $attempt['class_id'],
        (int) $attempt['student_id'],
        (int) $link['component_id'],
        $resolved['score'],
        $resolved['max_score'],
        $resolved['percent'],
        false,
        $resolved['attempt_id']
    );
}

function syncAssignmentSubmissionToGradebook(int $submissionId): void
{
    $stmt = db()->prepare('SELECT s.*, a.class_id, a.max_points
        FROM assignment_submissions s
        INNER JOIN assignments a ON a.id = s.assignment_id
        WHERE s.id = ?');
    $stmt->execute([$submissionId]);
    $submission = $stmt->fetch();
    if (!$submission || $submission['grade'] === null || $submission['status'] !== 'graded') {
        return;
    }

    $link = GradebookRepository::linkByAssignmentId((int) $submission['assignment_id']);
    if (!$link) {
        return;
    }

    if (GradebookRepository::isManualOverride(
        (int) $submission['class_id'],
        (int) $submission['student_id'],
        (int) $link['component_id']
    )) {
        return;
    }

    $maxScore = (float) $submission['max_points'];
    if ($maxScore <= 0) {
        return;
    }

    $score = (float) $submission['grade'];
    $percent = round(($score / $maxScore) * 100, 2);

    GradebookRepository::upsertStudentGrade(
        (int) $submission['class_id'],
        (int) $submission['student_id'],
        (int) $link['component_id'],
        $score,
        $maxScore,
        $percent,
        false,
        null,
        (int) $submission['id']
    );
}

function recalculateClassGradebook(int $classId): void
{
    $class = db()->prepare('SELECT c.*, cg.id AS class_group_id FROM classes c
        INNER JOIN class_groups cg ON cg.id = c.class_group_id
        WHERE c.id = ?');
    $class->execute([$classId]);
    $class = $class->fetch();
    if (!$class) {
        return;
    }

    $components = GradebookRepository::componentsForSubject((int) $class['subject_id'], (int) $class['school_id']);
    $links = GradebookRepository::linksForClass($classId);
    $students = ClassGroupRepository::enrolledStudents((int) $class['class_group_id']);

    foreach ($students as $student) {
        $studentId = (int) $student['id'];
        foreach ($components as $component) {
            $componentId = (int) $component['id'];
            $link = $links[$componentId] ?? null;
            if (!$link) {
                continue;
            }

            if (GradebookRepository::isManualOverride($classId, $studentId, $componentId)) {
                continue;
            }

            if (in_array($component['category'], ['quiz', 'exam'], true) && !empty($link['quiz_id'])) {
                $resolved = resolveQuizGradeForStudent((int) $link['quiz_id'], $studentId);
                if ($resolved) {
                    GradebookRepository::upsertStudentGrade(
                        $classId,
                        $studentId,
                        $componentId,
                        $resolved['score'],
                        $resolved['max_score'],
                        $resolved['percent'],
                        false,
                        $resolved['attempt_id']
                    );
                }
            } elseif ($component['category'] === 'assignment' && !empty($link['assignment_id'])) {
                $sub = db()->prepare("SELECT id, grade, status FROM assignment_submissions
                    WHERE assignment_id = ? AND student_id = ? AND status = 'graded' AND grade IS NOT NULL
                    ORDER BY submitted_at DESC LIMIT 1");
                $sub->execute([(int) $link['assignment_id'], $studentId]);
                $submission = $sub->fetch();
                if ($submission) {
                    syncAssignmentSubmissionToGradebook((int) $submission['id']);
                }
            }
        }
    }
}

/**
 * @param list<array{category: string, label: string, weight_percent: string|float}> $postedRows
 * @return array{ok: bool, error?: string, rows: list<array{category: string, label: string, weight_percent: float}>}
 */
function parseSubjectGradingSchemeInput(array $postedRows): array
{
    $rows = [];
    $total = 0.0;

    foreach ($postedRows as $row) {
        $category = trim((string) ($row['category'] ?? ''));
        $label = trim((string) ($row['label'] ?? ''));
        $weight = (float) ($row['weight_percent'] ?? 0);

        if ($label === '' && $weight <= 0) {
            continue;
        }

        if ($label === '') {
            return ['ok' => false, 'error' => 'Each grading component needs a label.', 'rows' => []];
        }

        if (!array_key_exists($category, GradebookRepository::CATEGORIES)) {
            return ['ok' => false, 'error' => 'Invalid grading category.', 'rows' => []];
        }

        if ($weight < 0) {
            return ['ok' => false, 'error' => 'Weights cannot be negative.', 'rows' => []];
        }

        $rows[] = [
            'category' => $category,
            'label' => $label,
            'weight_percent' => round($weight, 2),
        ];
        $total += $weight;
    }

    if ($rows !== [] && abs($total - 100) > 0.01) {
        return ['ok' => false, 'error' => 'Grading weights must total exactly 100%. Current total: ' . round($total, 2) . '%.', 'rows' => []];
    }

    return ['ok' => true, 'rows' => $rows];
}

function resyncGradebookCell(int $classId, int $studentId, int $componentId): void
{
    $class = db()->prepare('SELECT subject_id, school_id FROM classes WHERE id = ?');
    $class->execute([$classId]);
    $class = $class->fetch();
    if (!$class) {
        return;
    }

    $components = GradebookRepository::componentsForSubject((int) $class['subject_id'], (int) $class['school_id']);
    $component = null;
    foreach ($components as $row) {
        if ((int) $row['id'] === $componentId) {
            $component = $row;
            break;
        }
    }
    if (!$component || GradebookRepository::isManualCategory($component['category'])) {
        return;
    }

    $links = GradebookRepository::linksForClass($classId);
    $link = $links[$componentId] ?? null;
    if (!$link) {
        return;
    }

    if (in_array($component['category'], ['quiz', 'exam'], true) && !empty($link['quiz_id'])) {
        $resolved = resolveQuizGradeForStudent((int) $link['quiz_id'], $studentId);
        if ($resolved) {
            GradebookRepository::upsertStudentGrade(
                $classId,
                $studentId,
                $componentId,
                $resolved['score'],
                $resolved['max_score'],
                $resolved['percent'],
                false,
                $resolved['attempt_id']
            );
        }
        return;
    }

    if ($component['category'] === 'assignment' && !empty($link['assignment_id'])) {
        $sub = db()->prepare("SELECT id FROM assignment_submissions
            WHERE assignment_id = ? AND student_id = ? AND status = 'graded' AND grade IS NOT NULL
            ORDER BY submitted_at DESC LIMIT 1");
        $sub->execute([(int) $link['assignment_id'], $studentId]);
        $submission = $sub->fetch();
        if ($submission) {
            syncAssignmentSubmissionToGradebook((int) $submission['id']);
        }
    }
}
