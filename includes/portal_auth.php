<?php

function portalAuthSecret(): string
{
    $secret = trim((string) env('SUBDOMAIN_LOGIN_SECRET', ''));
    if ($secret !== '') {
        return $secret;
    }

    $dbPass = (string) env('DB_PASS', '');
    $dbName = (string) env('DB_NAME', '');
    if ($dbPass === '' || $dbName === '') {
        return '';
    }

    return hash('sha256', APP_NAME . '|portal-auth|' . $dbName . '|' . $dbPass);
}

function createPortalAuthSignature(string $schoolCode, int $timestamp): string
{
    $secret = portalAuthSecret();
    if ($secret === '') {
        throw new RuntimeException('Portal auth secret is not configured.');
    }

    $payload = normalizeSchoolCode($schoolCode) . '|' . $timestamp;

    return hash_hmac('sha256', $payload, $secret);
}

function verifyPortalAuthSignature(string $schoolCode, int $timestamp, string $signature, int $maxAgeSeconds = 300): bool
{
    $secret = portalAuthSecret();
    if ($secret === '' || $signature === '' || $timestamp <= 0) {
        return false;
    }

    if (abs(time() - $timestamp) > $maxAgeSeconds) {
        return false;
    }

    $payload = normalizeSchoolCode($schoolCode) . '|' . $timestamp;
    $expected = hash_hmac('sha256', $payload, $secret);

    return hash_equals($expected, $signature);
}

function portalAuthFields(string $schoolCode): array
{
    $timestamp = time();

    return [
        'portal_ts'  => $timestamp,
        'portal_sig' => createPortalAuthSignature($schoolCode, $timestamp),
    ];
}

function portalAuthAllowedHost(string $host): bool
{
    $host = strtolower(trim($host));
    if ($host === '' || $host === 'localhost' || $host === '127.0.0.1') {
        return false;
    }

    $mainHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($mainHost === '') {
        return false;
    }

    if ($host === $mainHost) {
        return true;
    }

    return str_ends_with($host, '.' . $mainHost);
}

function verifyPortalAuthRequest(): bool
{
    $origin = parse_url($_SERVER['HTTP_ORIGIN'] ?? '', PHP_URL_HOST);
    $referer = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);

    if (is_string($origin) && $origin !== '' && portalAuthAllowedHost($origin)) {
        return true;
    }

    if (is_string($referer) && $referer !== '' && portalAuthAllowedHost($referer)) {
        return true;
    }

    return false;
}
