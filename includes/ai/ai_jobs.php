<?php

function aiBuildQuizGenerationPrompt(string $context, array $config): array
{
    $count = max(3, min(30, (int) ($config['item_count'] ?? 10)));
    $difficulty = (string) ($config['difficulty'] ?? 'mixed');
    $types = $config['question_types'] ?? ['multiple_choice', 'true_false', 'fill_blank'];
    if (!is_array($types) || $types === []) {
        $types = ['multiple_choice', 'true_false'];
    }
    $types = array_values(array_intersect($types, ['multiple_choice', 'true_false', 'fill_blank', 'matching', 'essay']));
    $typeList = implode(', ', $types);

    $system = 'You are an expert educator creating quiz questions. Respond with valid JSON only.';
    $user = "Using the lesson context below, generate exactly {$count} quiz questions.\n"
        . "Difficulty: {$difficulty}\n"
        . "Allowed question types: {$typeList}\n\n"
        . "Return JSON in this shape:\n"
        . "{\n"
        . "  \"questions\": [\n"
        . "    {\n"
        . "      \"type\": \"multiple_choice\",\n"
        . "      \"question_text\": \"...\",\n"
        . "      \"points\": 1,\n"
        . "      \"explanation\": \"...\",\n"
        . "      \"options\": [{\"text\": \"...\", \"is_correct\": true}, {\"text\": \"...\", \"is_correct\": false}],\n"
        . "      \"settings\": {}\n"
        . "    }\n"
        . "  ]\n"
        . "}\n\n"
        . "For true_false use two options True and False. For fill_blank include settings.blanks array with accepted answers.\n"
        . "For matching include settings.left_items and settings.right_items arrays and settings.pairs object mapping left to right.\n"
        . "Essay questions should include a sample correct_answer field.\n\n"
        . "LESSON CONTEXT:\n" . textTruncateForContext($context, 20000);

    return [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user],
    ];
}

function aiProcessBuildPracticeBank(array $job): array
{
    $payload = $job['payload'] ?? [];
    $classId = (int) ($payload['class_id'] ?? 0);
    $sectionId = isset($payload['section_id']) && $payload['section_id'] !== null ? (int) $payload['section_id'] : null;
    $courseWide = !empty($payload['is_course_wide']);
    $contextVersion = (string) ($payload['context_version'] ?? '');

    $context = LessonContextService::ensureIndexed($classId, $sectionId, $courseWide);
    if (!$context || empty($context['context_text'])) {
        throw new RuntimeException('No lesson content available to generate practice questions.');
    }

    $sessionConfig = parsePracticeSessionConfig($payload);
    $itemCount = min(30, max(20, $sessionConfig['item_count'] * 2));
    if ($courseWide) {
        $itemCount = max($itemCount, 25);
    }
    $messages = aiBuildQuizGenerationPrompt($context['context_text'], [
        'item_count' => $itemCount,
        'difficulty' => $sessionConfig['difficulty'],
        'question_types' => $sessionConfig['question_types'],
    ]);

    $keyIndex = groqAcquireKeyIndex();
    $json = groqJsonCompletion($messages, $keyIndex);
    $questions = $json['questions'] ?? [];
    if (!is_array($questions) || $questions === []) {
        throw new RuntimeException('AI returned no questions.');
    }

    $quizId = PracticeQuizService::upsertPracticeQuiz($classId, $sectionId, $courseWide, $contextVersion, $questions);
    PracticeQuizService::saveBank($classId, $sectionId, $courseWide, $contextVersion, $questions, $quizId);

    return [
        'quiz_id' => $quizId,
        'class_id' => $classId,
        'section_id' => $sectionId,
        'is_course_wide' => $courseWide ? 1 : 0,
        'item_count' => count($questions),
        'key_index' => $keyIndex,
    ];
}

function aiProcessGenerateExamQuiz(array $job): array
{
    $payload = $job['payload'] ?? [];
    $classId = (int) ($payload['class_id'] ?? 0);
    $teacherId = (int) ($payload['teacher_id'] ?? 0);
    $sectionId = isset($payload['section_id']) && $payload['section_id'] > 0 ? (int) $payload['section_id'] : null;
    $title = trim((string) ($payload['title'] ?? 'AI Generated Quiz'));
    $contextText = (string) ($payload['context_text'] ?? '');

    if ($contextText === '' && !empty($payload['use_lesson_context']) && $classId > 0) {
        $ctx = LessonContextService::ensureIndexed($classId, $sectionId);
        $contextText = (string) ($ctx['context_text'] ?? '');
    } elseif ($contextText === '' && $classId > 0 && $sectionId) {
        $ctx = LessonContextService::ensureIndexed($classId, $sectionId);
        $contextText = (string) ($ctx['context_text'] ?? '');
    }

    if ($contextText === '') {
        throw new RuntimeException('No context available for quiz generation.');
    }

    $config = [
        'item_count' => (int) ($payload['item_count'] ?? 10),
        'difficulty' => (string) ($payload['difficulty'] ?? 'medium'),
        'question_types' => $payload['question_types'] ?? ['multiple_choice', 'true_false'],
    ];

    $messages = aiBuildQuizGenerationPrompt($contextText, $config);
    $keyIndex = groqAcquireKeyIndex();
    $json = groqJsonCompletion($messages, $keyIndex);
    $questions = $json['questions'] ?? [];
    if (!is_array($questions) || $questions === []) {
        throw new RuntimeException('AI returned no questions.');
    }

    $quizId = PracticeQuizService::createExamQuizFromAi($classId, $teacherId, $sectionId, $title, $questions);

    return [
        'quiz_id' => $quizId,
        'class_id' => $classId,
        'item_count' => count($questions),
        'key_index' => $keyIndex,
    ];
}

function aiProcessResourceSummary(array $job): array
{
    $payload = $job['payload'] ?? [];
    $text = (string) ($payload['text'] ?? '');
    if ($text === '') {
        throw new RuntimeException('No text to summarize.');
    }

    $messages = [
        ['role' => 'system', 'content' => 'Summarize educational content concisely in 2-4 sentences.'],
        ['role' => 'user', 'content' => textTruncateForContext($text, 12000)],
    ];
    $keyIndex = groqAcquireKeyIndex();
    $result = groqChatCompletion($messages, $keyIndex, ['max_tokens' => 512]);

    return [
        'summary' => trim($result['content']),
        'key_index' => $keyIndex,
    ];
}

function aiDispatchJob(array $job): array
{
    $type = (string) ($job['job_type'] ?? '');
    return match ($type) {
        'build_practice_bank' => aiProcessBuildPracticeBank($job),
        'generate_exam_quiz' => aiProcessGenerateExamQuiz($job),
        'resource_summary' => aiProcessResourceSummary($job),
        default => throw new RuntimeException('Unknown AI job type: ' . $type),
    };
}
