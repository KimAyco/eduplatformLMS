<?php

function isHttpsRequest(): bool
{
    if (APP_ENV === 'production') {
        return true;
    }

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    return isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
}

function resolveSessionCookieDomain(): string
{
    $cookieDomain = SESSION_COOKIE_DOMAIN;
    if ($cookieDomain !== '') {
        return $cookieDomain;
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '' || $host === 'localhost' || str_starts_with($host, '127.0.0.1')) {
        return '';
    }

    if (preg_match('/(?:^|\.)((?:[^.]+\.)?[^.]+\.[^.]+)$/', $host, $matches)) {
        return '.' . $matches[1];
    }

    return '';
}

function initSecurity(): void
{
    if (!APP_DEBUG) {
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        $logDir = APP_ROOT . '/storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        ini_set('error_log', $logDir . '/app.log');
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);

    $cookieDomain = resolveSessionCookieDomain();
    $isSecure = isHttpsRequest();

    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => $cookieDomain,
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        session_set_cookie_params(
            $cookieParams['lifetime'],
            $cookieParams['path'],
            $cookieParams['domain'],
            $cookieParams['secure'],
            $cookieParams['httponly']
        );
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    sendSecurityHeaders();
}

function sendSecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

function clientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function checkLoginRateLimit(string $email): bool
{
    try {
        $since = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_MINUTES * 60);
        $stmt = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND attempted_at > ?');
        $stmt->execute([strtolower($email), $since]);
        return (int) $stmt->fetchColumn() < LOGIN_MAX_ATTEMPTS;
    } catch (PDOException) {
        return true;
    }
}

function recordLoginAttempt(string $email, bool $success = false): void
{
    try {
        if ($success) {
            db()->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([strtolower($email)]);
            return;
        }
        db()->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)')
            ->execute([strtolower($email), clientIp()]);
    } catch (PDOException) {
        // Table may not exist yet before migration
    }
}

function loginLockedMessage(): string
{
    return 'Too many login attempts. Please try again in ' . LOGIN_LOCKOUT_MINUTES . ' minutes.';
}

function appLog(string $message): void
{
    $logDir = APP_ROOT . '/storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    error_log('[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, 3, $logDir . '/app.log');
}
