<?php

require_once __DIR__ . '/../includes/env.php';

$config = require __DIR__ . '/../config/database.php';
$files = array_slice($argv, 1);
if ($files === []) {
    $files = [
        '015_materials_module.sql',
        '016_quiz_settings.sql',
        '017_quiz_question_types.sql',
        '018_quiz_answer_payload.sql',
        '019_gradebook.sql',
        '020_messaging.sql',
        '021_message_edit_unsend.sql',
        '022_message_edit_history.sql',
        '023_message_replies.sql',
        '024_programs.sql',
        '025_library.sql',
        '026_content_resources.sql',
        '027_ai_platform.sql',
        '028_lesson_context.sql',
        '029_practice_quizzes.sql',
        '030_practice_course_scope.sql',
        '031_school_practice_setting.sql',
        '032_announcements.sql',
        '033_ai_analytics_index.sql',
    ];
}

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['host'], $config['dbname'], $config['charset']),
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

foreach ($files as $file) {
    $path = __DIR__ . '/../sql/migrations/' . basename($file);
    if (!is_file($path)) {
        fwrite(STDERR, "Missing migration: {$file}\n");
        exit(1);
    }

    echo "Running {$file}...\n";
    $sql = file_get_contents($path);
    $statements = preg_split('/;\s*\R/', $sql) ?: [];

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        $stmt = preg_replace('/^(--[^\n]*\n?)+/', '', $stmt) ?? $stmt;
        $stmt = trim($stmt);
        if ($stmt === '' || str_starts_with($stmt, '--')) {
            continue;
        }

        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Duplicate column')
                || str_contains($msg, 'already exists')
                || str_contains($msg, 'Duplicate key name')) {
                echo '  Skipped: ' . substr($msg, 0, 100) . "\n";
                continue;
            }
            throw $e;
        }
    }

    echo "  Done.\n";
}

echo "All migrations processed.\n";
