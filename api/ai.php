<?php

require_once __DIR__ . '/../includes/bootstrap.php';

requireLogin();
requireSchoolActive();

$user = currentUser();
$userId = (int) $user['id'];
$schoolId = (int) ($user['school_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    match ($action) {
        'job_status' => handleAiJobStatus(),
        'enqueue' => handleAiEnqueue($userId, $schoolId),
        'practice_start' => handlePracticeStart($userId),
        'proficiency' => handleProficiency($userId),
        'resource_summary' => handleResourceSummary($userId, $schoolId),
        'extract_document' => handleExtractDocument($userId),
        default => aiJsonResponse(['ok' => false, 'error' => 'Unknown action.'], 400),
    };
} catch (InvalidArgumentException $e) {
    aiJsonResponse(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (RuntimeException $e) {
    aiJsonResponse(['ok' => false, 'error' => practiceUserErrorMessage($e)], 403);
} catch (PDOException $e) {
    aiJsonResponse(['ok' => false, 'error' => practiceUserErrorMessage($e)], 500);
}

function handleAiJobStatus(): never
{
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        throw new InvalidArgumentException('Job ID required.');
    }

    aiProcessPendingJobs(1);
    $job = AiQueueRepository::findById($id);
    if (!$job) {
        throw new RuntimeException('Job not found.');
    }

    aiJsonResponse(['ok' => true, 'job' => aiFormatJobForClient($job)]);
}

function handleAiEnqueue(int $userId, int $schoolId): never
{
    verifyCsrfHeader();
    $role = currentUser()['role'] ?? '';
    if (!in_array($role, ['teacher', 'school_admin'], true)) {
        throw new RuntimeException('Not allowed.');
    }

    $body = json_decode((string) file_get_contents('php://input'), true) ?? $_POST;
    $type = (string) ($body['job_type'] ?? '');
    $payload = $body['payload'] ?? [];
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Invalid payload.');
    }

    if ($type === 'generate_exam_quiz') {
        $payload['teacher_id'] = $userId;
    }
    $preview = (string) ($body['prompt_preview'] ?? $type);

    $jobId = aiEnqueueJob($type, $payload, $userId, $schoolId, $preview);
    aiProcessPendingJobs(1);

    aiJsonResponse(['ok' => true, 'job_id' => $jobId, 'job' => aiFormatJobForClient(AiQueueRepository::findById($jobId))]);
}

function handlePracticeStart(int $userId): never
{
    if ((currentUser()['role'] ?? '') !== 'student') {
        throw new RuntimeException('Students only.');
    }

    requireSchoolPracticeQuizzes();

    $body = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $body = [];
    }
    $input = array_merge($_GET, $_POST, $body);
    $classId = (int) ($input['class_id'] ?? 0);
    $sectionId = (int) ($input['section_id'] ?? 0);
    $scope = (string) ($input['scope'] ?? 'lesson');
    $parsed = parsePracticeScope($scope, $sectionId);
    $config = parsePracticeSessionConfig($input);

    aiProcessPendingJobs(1);
    $result = PracticeQuizService::ensurePracticeQuizForStudent(
        $classId,
        $parsed['section_id'],
        $userId,
        (bool) $parsed['is_course_wide'],
        $config
    );

    if (isset($result['pending_job_id'])) {
        aiJsonResponse([
            'ok' => true,
            'status' => 'pending',
            'job_id' => (int) $result['pending_job_id'],
        ]);
    }

    aiJsonResponse([
        'ok' => true,
        'status' => 'ready',
        'quiz_id' => (int) $result['id'],
        'url' => url('student/quiz-take.php?quiz_id=' . (int) $result['id']),
    ]);
}

function handleProficiency(int $userId): never
{
    requireSchoolPracticeQuizzes();
    $classId = (int) ($_GET['class_id'] ?? 0);
    requireClassAccess($classId, 'student');
    $rows = PracticeQuizService::proficiencyForStudent($userId, $classId);
    aiJsonResponse(['ok' => true, 'proficiency' => $rows]);
}

function handleExtractDocument(int $userId): never
{
    verifyCsrfHeader();
    $role = currentUser()['role'] ?? '';
    if (!in_array($role, ['teacher', 'school_admin'], true)) {
        throw new RuntimeException('Not allowed.');
    }

    if (empty($_FILES['document']['name'])) {
        throw new InvalidArgumentException('Document file required.');
    }

    $meta = uploadFileWithMeta($_FILES['document'], schoolId() . '/ai_uploads');
    $text = textExtractFromFilePath($meta['path'], $meta['original_name']);
    deleteUpload($meta['path']);

    if (trim($text) === '') {
        throw new InvalidArgumentException('Could not extract text from this document. Try a TXT or DOCX file.');
    }

    aiJsonResponse(['ok' => true, 'text' => textTruncateForContext($text, 20000)]);
}

function handleResourceSummary(int $userId, int $schoolId): never
{
    verifyCsrfHeader();
    $role = currentUser()['role'] ?? '';
    if (!in_array($role, ['teacher', 'school_admin'], true)) {
        throw new RuntimeException('Not allowed.');
    }

    $body = json_decode((string) file_get_contents('php://input'), true) ?? $_POST;
    $text = trim((string) ($body['text'] ?? ''));
    if ($text === '') {
        throw new InvalidArgumentException('Text required.');
    }

    $jobId = aiEnqueueJob('resource_summary', ['text' => $text], $userId, $schoolId, 'Resource summary');
    aiProcessPendingJobs(1);
    $job = AiQueueRepository::findById($jobId);

    aiJsonResponse([
        'ok' => true,
        'job_id' => $jobId,
        'job' => aiFormatJobForClient($job),
        'summary' => $job['result']['summary'] ?? null,
    ]);
}
