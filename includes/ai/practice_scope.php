<?php

/**
 * @return array{section_id: ?int, is_course_wide: int, scope: string}
 */
function parsePracticeScope(string $scope, int $sectionId = 0): array
{
    return match ($scope) {
        'course' => ['section_id' => null, 'is_course_wide' => 1, 'scope' => 'course'],
        'unassigned' => ['section_id' => null, 'is_course_wide' => 0, 'scope' => 'unassigned'],
        default => [
            'section_id' => $sectionId > 0 ? $sectionId : null,
            'is_course_wide' => 0,
            'scope' => $sectionId > 0 ? 'lesson' : 'unassigned',
        ],
    };
}

function aiPracticeTablesReady(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        db()->query('SELECT 1 FROM lesson_contexts LIMIT 1');
        $ready = true;
    } catch (PDOException) {
        $ready = false;
    }
    return $ready;
}

function practiceUserErrorMessage(Throwable $e): string
{
    if (!aiPracticeTablesReady()) {
        return 'Practice quizzes are not set up yet. Please ask your administrator to run database migrations.';
    }
    if (APP_DEBUG) {
        return $e->getMessage();
    }
    if ($e instanceof RuntimeException || $e instanceof InvalidArgumentException) {
        return $e->getMessage();
    }
    return 'Something went wrong. Please try again in a moment.';
}
