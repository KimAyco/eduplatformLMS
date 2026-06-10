<?php

function schoolHasPracticeSettingColumn(): bool
{
    static $has = null;
    if ($has !== null) {
        return $has;
    }
    try {
        $has = (bool) db()->query("SHOW COLUMNS FROM schools LIKE 'practice_quizzes_enabled'")->fetch();
    } catch (PDOException) {
        $has = false;
    }
    return $has;
}

function schoolPracticeQuizzesEnabled(?int $schoolId = null): bool
{
    if (!aiIsEnabled() || !aiPracticeTablesReady()) {
        return false;
    }

    $schoolId = $schoolId ?? schoolId();
    if (!$schoolId) {
        return false;
    }

    if (!schoolHasPracticeSettingColumn()) {
        return true;
    }

    $stmt = db()->prepare('SELECT practice_quizzes_enabled FROM schools WHERE id = ?');
    $stmt->execute([$schoolId]);
    return (bool) $stmt->fetchColumn();
}

function requireSchoolPracticeQuizzes(): void
{
    if (!schoolPracticeQuizzesEnabled()) {
        throw new RuntimeException('Practice quizzes are not enabled for your school.');
    }
}

/** @return list<string> */
function practiceAllowedQuestionTypes(): array
{
    return array_values(array_filter(QUIZ_QUESTION_TYPES, static fn ($t) => $t !== 'file_response'));
}

/**
 * @param array<string, mixed> $input
 * @return array{item_count: int, question_types: list<string>, difficulty: string}
 */
function parsePracticeSessionConfig(array $input): array
{
    $types = $input['question_types'] ?? ['multiple_choice', 'true_false'];
    if (!is_array($types)) {
        $types = ['multiple_choice', 'true_false'];
    }
    $allowed = practiceAllowedQuestionTypes();
    $types = array_values(array_intersect($types, $allowed));
    if ($types === []) {
        $types = ['multiple_choice', 'true_false'];
    }

    return [
        'item_count' => max(3, min(30, (int) ($input['item_count'] ?? 10))),
        'question_types' => $types,
        'difficulty' => in_array($input['difficulty'] ?? 'mixed', ['easy', 'medium', 'hard', 'mixed'], true)
            ? (string) ($input['difficulty'] ?? 'mixed')
            : 'mixed',
    ];
}
