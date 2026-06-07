<?php

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/security.php';

initSecurity();

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout/course_card.php';
require_once __DIR__ . '/layout/course_content.php';
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
require_once __DIR__ . '/quiz.php';
require_once __DIR__ . '/superadmin_school.php';

require_once __DIR__ . '/repositories/ClassGroupRepository.php';
require_once __DIR__ . '/repositories/SubjectRepository.php';
require_once __DIR__ . '/repositories/ClassRepository.php';
require_once __DIR__ . '/repositories/CourseSectionRepository.php';
require_once __DIR__ . '/repositories/QuizRepository.php';
require_once __DIR__ . '/repositories/DashboardRepository.php';
require_once __DIR__ . '/repositories/UserRepository.php';
