<?php

define('APP_NAME', env('APP_NAME', 'EduPlatform'));
define('APP_ENV', env('APP_ENV', 'local'));
define('APP_DEBUG', (bool) env('APP_DEBUG', false));
define('APP_ROOT', dirname(__DIR__));
define('BASE_URL', env('BASE_URL', ''));

define('UPLOAD_DIR', APP_ROOT . '/uploads');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);

define('ALLOWED_UPLOAD_EXTENSIONS', [
    'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx',
    'txt', 'jpg', 'jpeg', 'png', 'gif', 'zip',
]);

define('ROLES', [
    'super_admin'  => 'Super Admin',
    'school_admin' => 'School Admin',
    'teacher'      => 'Teacher',
    'student'      => 'Student',
]);

define('SCHOOL_STATUSES', [
    'pending'   => 'Pending',
    'active'    => 'Active',
    'rejected'  => 'Rejected',
    'suspended' => 'Suspended',
]);

define('SESSION_LIFETIME', (int) env('SESSION_LIFETIME', 7200));
define('LOGIN_MAX_ATTEMPTS', (int) env('LOGIN_MAX_ATTEMPTS', 5));
define('LOGIN_LOCKOUT_MINUTES', (int) env('LOGIN_LOCKOUT_MINUTES', 15));
