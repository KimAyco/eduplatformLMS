<?php

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/security.php';

initSecurity();

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/repositories/MaterialRepository.php';
require_once __DIR__ . '/layout/course_card.php';
require_once __DIR__ . '/layout/course_content.php';
require_once __DIR__ . '/layout/quiz_take_render.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/cache.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $config = require __DIR__ . '/../config/database.php';
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['dbname'],
            $config['charset']
        );
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
        ]);
    }
    return $pdo;
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/user_profile.php';
require_once __DIR__ . '/menus.php';
require_once __DIR__ . '/quiz_scoring.php';
require_once __DIR__ . '/quiz_question.php';
require_once __DIR__ . '/quiz.php';
require_once __DIR__ . '/quiz_wizard.php';
require_once __DIR__ . '/superadmin_school.php';

require_once __DIR__ . '/repositories/ClassGroupRepository.php';
require_once __DIR__ . '/repositories/SubjectRepository.php';
require_once __DIR__ . '/repositories/ProgramRepository.php';
require_once __DIR__ . '/repositories/ClassRepository.php';
require_once __DIR__ . '/repositories/CourseSectionRepository.php';
require_once __DIR__ . '/repositories/QuizRepository.php';
require_once __DIR__ . '/repositories/DashboardRepository.php';
require_once __DIR__ . '/repositories/UserRepository.php';
require_once __DIR__ . '/repositories/GradebookRepository.php';
require_once __DIR__ . '/repositories/ClassProgressRepository.php';
require_once __DIR__ . '/repositories/MessageRepository.php';
require_once __DIR__ . '/repositories/LibraryResourceRepository.php';
require_once __DIR__ . '/gradebook.php';
require_once __DIR__ . '/messaging.php';
require_once __DIR__ . '/announcements.php';
require_once __DIR__ . '/repositories/AnnouncementRepository.php';
require_once __DIR__ . '/library.php';
require_once __DIR__ . '/repositories/ContentResourceRepository.php';
require_once __DIR__ . '/content_resources.php';
require_once __DIR__ . '/repositories/PlatformSettingsRepository.php';
require_once __DIR__ . '/repositories/AiQueueRepository.php';
require_once __DIR__ . '/repositories/AiAnalyticsRepository.php';
require_once __DIR__ . '/ai/groq_keys.php';
require_once __DIR__ . '/ai/groq_rate_limiter.php';
require_once __DIR__ . '/ai/groq_client.php';
require_once __DIR__ . '/ai/text_extract.php';
require_once __DIR__ . '/ai/lesson_context.php';
require_once __DIR__ . '/ai/ai_jobs.php';
require_once __DIR__ . '/school_practice.php';
require_once __DIR__ . '/ai/practice_scope.php';
require_once __DIR__ . '/ai/practice.php';
require_once __DIR__ . '/ai/ai_queue.php';
