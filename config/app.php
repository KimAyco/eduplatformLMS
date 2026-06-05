<?php

define('APP_ROOT', dirname(__DIR__));

function resolveBaseUrl(): string
{
    $docRoot = rtrim(str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $appRoot = rtrim(str_replace('\\', '/', (string) realpath(APP_ROOT)), '/');

    $detected = '';
    if ($docRoot !== '' && $appRoot !== '' && str_starts_with($appRoot, $docRoot)) {
        $detected = rtrim(substr($appRoot, strlen($docRoot)), '/');
    }

    $configured = env('BASE_URL');
    if ($configured === null || $configured === '') {
        return $detected;
    }

    $configured = rtrim(str_replace('\\', '/', (string) $configured), '/');
    if ($docRoot !== '' && is_file($docRoot . $configured . '/assets/css/app.css')) {
        return $configured;
    }

    return $detected;
}

define('APP_NAME', env('APP_NAME', 'EduPlatform'));
define('APP_ENV', env('APP_ENV', 'local'));
define('APP_DEBUG', (bool) env('APP_DEBUG', false));
define('BASE_URL', resolveBaseUrl());

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
define('SESSION_COOKIE_DOMAIN', trim((string) env('SESSION_COOKIE_DOMAIN', '')));
